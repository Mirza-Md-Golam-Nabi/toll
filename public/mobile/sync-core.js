/*
 * Shared offline-sync core of the collection app, loaded by the page (app.js) and by the
 * service worker (sw.js), so both push unsynced records exactly the same way.
 *
 * - IndexedDB access (the phone's local store; everything is saved here first).
 * - API requests with a timeout, so a dead connection never leaves a sync hanging.
 * - pushPending(): sends unsynced shifts and tickets in batches and marks what the server accepted.
 * - exclusive(): a cross-context lock, so the page and the service worker never push at the same time.
 */
(function (scope) {
    'use strict';

    const API = '/api/mobile';
    const REQUEST_TIMEOUT_MS = 20000;
    const PUSH_BATCH = 200;
    const PENDING = 0;
    const SYNCED = 1;
    const FAILED = 2;

    // ------------------------------------------------------------------ IndexedDB
    let dbPromise = null;

    const openDb = () => {
        dbPromise ??= new Promise((resolve, reject) => {
            const request = indexedDB.open('toll-mobile', 1);

            request.onupgradeneeded = () => {
                const db = request.result;
                db.createObjectStore('kv');
                const sessions = db.createObjectStore('sessions', { keyPath: 'id' });
                sessions.createIndex('sync_state', 'sync_state');
                const tickets = db.createObjectStore('tickets', { keyPath: 'id' });
                tickets.createIndex('sync_state', 'sync_state');
                tickets.createIndex('shift_session_id', 'shift_session_id');
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });

        return dbPromise;
    };

    const tx = async (stores, mode, work) => {
        const db = await openDb();

        return new Promise((resolve, reject) => {
            const transaction = db.transaction(stores, mode);
            let result;
            Promise.resolve(work(transaction)).then((value) => { result = value; }, reject);
            transaction.oncomplete = () => resolve(result);
            transaction.onerror = () => reject(transaction.error);
            transaction.onabort = () => reject(transaction.error);
        });
    };

    const req = (request) => new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    const kvGet = (key) => tx(['kv'], 'readonly', (x) => req(x.objectStore('kv').get(key)));
    const byIndex = (store, index, value, limit) => tx([store], 'readonly', (x) => req(x.objectStore(store).index(index).getAll(value, limit)));

    // ------------------------------------------------------------------ API
    /** status 0 = the server could not be reached (no internet, timeout, captive portal). */
    class ApiError extends Error {
        constructor(message, status) {
            super(message);
            this.status = status;
        }

        get isNetwork() {
            return this.status === 0;
        }

        /** Worth retrying later: unreachable, overloaded or failing server. */
        get isTransient() {
            return this.status === 0 || this.status === 408 || this.status === 429 || this.status >= 500;
        }
    }

    const request = async (method, path, { body, token } = {}) => {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);
        let response;

        try {
            response = await fetch(API + path, {
                method,
                cache: 'no-store',
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...(token ? { Authorization: 'Bearer ' + token } : {}),
                },
                body: body ? JSON.stringify(body) : undefined,
            });
        } catch (error) {
            throw new ApiError('offline', 0);
        } finally {
            clearTimeout(timer);
        }

        const data = await response.json().catch(() => null);

        // A captive portal / proxy answering with HTML means the real server is not reachable.
        if (data === null && response.ok) {
            throw new ApiError('offline', 0);
        }

        if (!response.ok) {
            const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
            throw new ApiError(firstError || data?.message || response.statusText, response.status);
        }

        return data;
    };

    /** Cheap reachability probe: true only when the central server itself answers. */
    const ping = () => request('GET', '/ping');

    // ------------------------------------------------------------------ push
    /** Accepted records become synced, unless they changed again while the request was running. */
    const markSynced = async (sessions, tickets, result) => {
        const accepted = new Set([...result.sessions, ...result.tickets]);
        const rejected = new Map(result.rejected.map((r) => [r.id, r.reason]));
        const sent = new Map([...sessions, ...tickets].map((r) => [r.id, r.version]));

        await tx(['sessions', 'tickets'], 'readwrite', async (x) => {
            for (const store of ['sessions', 'tickets']) {
                const records = store === 'sessions' ? sessions : tickets;

                for (const record of records) {
                    const current = await req(x.objectStore(store).get(record.id));

                    if (!current || current.version !== sent.get(record.id)) {
                        continue;
                    }

                    if (accepted.has(record.id)) {
                        current.sync_state = SYNCED;
                        current.error = null;
                    } else if (rejected.has(record.id)) {
                        current.sync_state = FAILED;
                        current.error = rejected.get(record.id);
                    }

                    x.objectStore(store).put(current);
                }
            }
        });
    };

    /**
     * Sends every unsynced shift and ticket in batches (shifts first, so tickets always find
     * their shift on the server). Returns the number of records sent.
     */
    const pushPending = async (token) => {
        let sent = 0;

        for (;;) {
            const sessions = await byIndex('sessions', 'sync_state', PENDING);
            const tickets = await byIndex('tickets', 'sync_state', PENDING, PUSH_BATCH);

            if (sessions.length === 0 && tickets.length === 0) {
                return sent;
            }

            const result = await request('POST', '/push', {
                token,
                body: {
                    sessions: sessions.map(({ id, shift_id, status, opened_at, opening_cash, closed_at, declared_cash, notes }) => ({ id, shift_id, status, opened_at, opening_cash, closed_at, declared_cash, notes })),
                    tickets: tickets.map(({ id, ticket_no, shift_session_id, vehicle_category_id, amount, exempt_reason, plate_number, print_count, issued_at }) => ({ id, ticket_no, shift_session_id, vehicle_category_id, amount, exempt_reason, plate_number, print_count, issued_at })),
                },
            });

            await markSynced(sessions, tickets, result);
            sent += sessions.length + tickets.length;

            if (tickets.length < PUSH_BATCH) {
                return sent;
            }
        }
    };

    const countPending = async () => (await byIndex('sessions', 'sync_state', PENDING)).length + (await byIndex('tickets', 'sync_state', PENDING)).length;

    /**
     * Runs work while holding the sync lock shared by the page and the service worker.
     * Resolves to {ran: false} when the other side is already syncing.
     */
    const exclusive = async (work) => {
        if (!scope.navigator?.locks) {
            return { ran: true, value: await work() };
        }

        return scope.navigator.locks.request('toll-sync', { ifAvailable: true }, async (lock) => (lock ? { ran: true, value: await work() } : { ran: false }));
    };

    scope.TollSync = Object.freeze({
        API, PUSH_BATCH, PENDING, SYNCED, FAILED,
        BACKGROUND_SYNC_TAG: 'toll-push',
        openDb, tx, req, kvGet, byIndex,
        ApiError, request, ping, pushPending, countPending, exclusive,
    });
})(self);
