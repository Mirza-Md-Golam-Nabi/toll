/*
 * Toll Collection — offline Android app.
 *
 * Everything an operator does (open shift, issue tickets, reprint, close shift) is saved
 * on the phone first (IndexedDB) and works without internet. A background loop pushes
 * unsynced records to the central server (/api/mobile/push) whenever the phone is online
 * and refreshes rates, categories and settings (/api/mobile/bootstrap).
 *
 * Receipts are printed as ESC/POS bytes to a 58mm Bluetooth thermal printer, either through
 * the RawBT app (classic Bluetooth printers) or directly with Web Bluetooth (BLE printers).
 */
(() => {
    'use strict';

    const { PENDING, SYNCED, FAILED, ApiError, tx, req, kvGet, byIndex } = window.TollSync;
    /** Regular sync cadence while the server is reachable. */
    const SYNC_INTERVAL_MS = 30000;
    /** Retry delays after a failed sync grow from 5s up to 5min (exponential backoff). */
    const RETRY_MIN_MS = 5000;
    const RETRY_MAX_MS = 300000;
    /** Wait this long when the service worker is already pushing. */
    const LOCK_RETRY_MS = 4000;
    const KEEP_SYNCED_DAYS = 7;

    // ------------------------------------------------------------------ i18n
    const STRINGS = {
        bn: {
            appName: 'টোল কালেকশন',
            phone: 'মোবাইল নম্বর',
            password: 'পিন',
            signIn: 'লগইন',
            signingIn: 'লগইন হচ্ছে...',
            needInternet: 'প্রথমবার লগইনের জন্য ইন্টারনেট লাগবে।',
            otherUserPending: 'এই ফোনে অন্য অপারেটরের (:phone) সিঙ্ক না হওয়া ডাটা বা চালু শিফট আছে। আগে সেই অপারেটর লগইন করে সিঙ্ক ও শিফট বন্ধ করুন।',
            sessionEnded: 'সেশন শেষ হয়েছে — আবার লগইন করুন। সিঙ্ক না হওয়া ডাটা ফোনেই আছে।',
            online: 'অনলাইন',
            offline: 'অফলাইন',
            syncing: 'সিঙ্ক হচ্ছে',
            pending: ':count টি বাকি',
            openShift: 'শিফট শুরু করুন',
            shift: 'শিফট',
            openingCash: 'শুরুর ক্যাশ (৳)',
            start: 'শুরু করুন',
            plateOptional: 'নম্বর প্লেট (ঐচ্ছিক)',
            clear: 'মুছুন',
            exemptReason: 'ছাড়ের কারণ',
            exemptReasonHint: 'যেমন: অ্যাম্বুলেন্স — রোগী বহন',
            reasonRequired: 'কারণ লিখতে হবে।',
            issue: 'টিকিট দিন',
            cancel: 'বাতিল',
            ok: 'ঠিক আছে',
            ticketIssued: 'টিকিট :no',
            printFailed: 'টিকিট সেভ হয়েছে, কিন্তু প্রিন্ট হয়নি। তালিকা থেকে আবার প্রিন্ট করুন।',
            recent: 'সাম্প্রতিক টিকিট',
            noTickets: 'এখনো কোনো টিকিট নেই।',
            reprint: 'প্রিন্ট',
            collect: 'কালেকশন',
            summary: 'হিসাব',
            settings: 'সেটিংস',
            vehicles: 'গাড়ি',
            revenue: 'আদায়',
            category: 'ক্যাটাগরি',
            count: 'সংখ্যা',
            amount: 'টাকা',
            total: 'মোট',
            expectedCash: 'হাতে থাকার কথা',
            declaredCash: 'হাতে আছে (৳)',
            notes: 'মন্তব্য',
            closeShift: 'শিফট বন্ধ করুন',
            closeConfirm: 'শিফট বন্ধ করবেন? এরপর এই শিফটে আর টিকিট কাটা যাবে না।',
            shiftClosed: 'শিফট বন্ধ হয়েছে। পার্থক্য: :diff',
            shiftOpened: 'শিফট শুরু হয়েছে',
            printer: 'প্রিন্টার',
            printerMode: 'প্রিন্টিং পদ্ধতি',
            modeRawbt: 'RawBT অ্যাপ (Bluetooth — প্রস্তাবিত)',
            modeBle: 'সরাসরি Bluetooth (BLE প্রিন্টার)',
            modeBrowser: 'ব্রাউজার প্রিন্ট',
            modeNone: 'প্রিন্ট ছাড়া',
            rawbtHelp: 'Play Store থেকে "RawBT" অ্যাপ ইনস্টল করে সেখানে প্রিন্টারটি একবার যুক্ত করুন।',
            bleHelp: 'অ্যাপ খোলার পর একবার "প্রিন্টার যুক্ত করুন" চাপতে হবে।',
            connectPrinter: 'প্রিন্টার যুক্ত করুন',
            printerConnected: 'প্রিন্টার যুক্ত: :name',
            printerNotConnected: 'প্রিন্টার যুক্ত নেই',
            bleUnsupported: 'এই ব্রাউজারে Bluetooth সাপোর্ট নেই। Chrome ব্যবহার করুন বা RawBT বেছে নিন।',
            testPrint: 'টেস্ট প্রিন্ট',
            sync: 'সিঙ্ক',
            syncNow: 'এখনই সিঙ্ক করুন',
            lastSync: 'শেষ সিঙ্ক',
            never: 'কখনো না',
            syncDone: 'সিঙ্ক সম্পন্ন',
            syncFailed: 'সিঙ্ক হয়নি: :error',
            failedTickets: 'সার্ভার যেগুলো নেয়নি',
            offlineSaving: 'ইন্টারনেট নেই — সব ডাটা ফোনে সেভ হচ্ছে (:count টি জমা)। সংযোগ ফিরলেই নিজে থেকে সার্ভারে চলে যাবে।',
            language: 'ভাষা',
            account: 'অ্যাকাউন্ট',
            device: 'ডিভাইস',
            logout: 'লগআউট',
            logoutBlocked: 'লগআউটের আগে শিফট বন্ধ করুন এবং সব ডাটা সিঙ্ক করুন (ইন্টারনেট লাগবে)।',
            clockWarning: 'ফোনের ঘড়ি সার্ভারের সাথে মিলছে না (:minutes মিনিট পার্থক্য)। টিকিটের সময় ভুল হবে — ঘড়ি ঠিক করুন।',
            noBooth: 'বুথ',
            operator: 'অপারেটর',
            install: 'অ্যাপ ইনস্টল করুন',
            version: 'ভার্সন',
            exempt: 'ছাড়',
            duplicate: 'ডুপ্লিকেট',
        },
        en: {
            appName: 'Toll Collection',
            phone: 'Mobile number',
            password: 'PIN',
            signIn: 'Sign in',
            signingIn: 'Signing in...',
            needInternet: 'The first sign in needs internet.',
            otherUserPending: 'This phone has unsynced data or an open shift of another operator (:phone). That operator must sign in, sync and close the shift first.',
            sessionEnded: 'Your session has ended — sign in again. Unsynced data is still on the phone.',
            online: 'Online',
            offline: 'Offline',
            syncing: 'Syncing',
            pending: ':count pending',
            openShift: 'Open shift',
            shift: 'Shift',
            openingCash: 'Opening cash (Tk)',
            start: 'Start',
            plateOptional: 'Number plate (optional)',
            clear: 'Clear',
            exemptReason: 'Exemption reason',
            exemptReasonHint: 'e.g. Ambulance — carrying a patient',
            reasonRequired: 'A reason is required.',
            issue: 'Issue ticket',
            cancel: 'Cancel',
            ok: 'OK',
            ticketIssued: 'Ticket :no',
            printFailed: 'Ticket saved, but printing failed. Reprint it from the list.',
            recent: 'Recent tickets',
            noTickets: 'No tickets yet.',
            reprint: 'Print',
            collect: 'Collect',
            summary: 'Summary',
            settings: 'Settings',
            vehicles: 'Vehicles',
            revenue: 'Revenue',
            category: 'Category',
            count: 'Count',
            amount: 'Amount',
            total: 'Total',
            expectedCash: 'Expected cash',
            declaredCash: 'Cash in hand (Tk)',
            notes: 'Notes',
            closeShift: 'Close shift',
            closeConfirm: 'Close the shift? No more tickets can be issued in it.',
            shiftClosed: 'Shift closed. Difference: :diff',
            shiftOpened: 'Shift opened',
            printer: 'Printer',
            printerMode: 'Printing method',
            modeRawbt: 'RawBT app (Bluetooth — recommended)',
            modeBle: 'Direct Bluetooth (BLE printers)',
            modeBrowser: 'Browser print',
            modeNone: 'No printing',
            rawbtHelp: 'Install the "RawBT" app from the Play Store and pair the printer in it once.',
            bleHelp: 'Tap "Connect printer" once after opening the app.',
            connectPrinter: 'Connect printer',
            printerConnected: 'Printer connected: :name',
            printerNotConnected: 'Printer not connected',
            bleUnsupported: 'This browser has no Bluetooth support. Use Chrome or choose RawBT.',
            testPrint: 'Test print',
            sync: 'Sync',
            syncNow: 'Sync now',
            lastSync: 'Last sync',
            never: 'Never',
            syncDone: 'Sync complete',
            syncFailed: 'Sync failed: :error',
            failedTickets: 'Rejected by the server',
            offlineSaving: 'No internet — everything is being saved on this phone (:count waiting). It is sent automatically as soon as the connection is back.',
            language: 'Language',
            account: 'Account',
            device: 'Device',
            logout: 'Sign out',
            logoutBlocked: 'Close the shift and sync everything (needs internet) before signing out.',
            clockWarning: 'The phone clock differs from the server by :minutes minutes. Ticket times will be wrong — fix the clock.',
            noBooth: 'Booth',
            operator: 'Operator',
            install: 'Install app',
            version: 'Version',
            exempt: 'Exempt',
            duplicate: 'DUPLICATE',
        },
    };

    // ------------------------------------------------------------------ state
    const state = {
        lang: 'bn',
        auth: null, // {token, phone, user_id}
        master: null, // bootstrap payload
        session: null, // open shift session on this phone
        recent: [],
        pending: 0,
        failed: [],
        syncing: false,
        connection: navigator.onLine ? 'online' : 'offline', // verified by every sync, not just navigator.onLine
        failures: 0, // consecutive failed syncs, drives the retry backoff
        lastSync: null,
        lastError: null,
        authExpired: false,
        screen: 'collect',
        printer: { mode: 'rawbt' },
        clockSkewMinutes: 0,
        busy: false,
    };

    const t = (key, replace = {}) => {
        let text = (STRINGS[state.lang] || STRINGS.bn)[key] ?? STRINGS.en[key] ?? key;
        Object.entries(replace).forEach(([name, value]) => { text = text.replace(':' + name, value); });

        return text;
    };

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const $ = (selector) => document.querySelector(selector);
    const pad = (n, size = 2) => String(n).padStart(size, '0');
    const money = (value) => (state.master?.settings?.currency || '৳') + Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

    /** Local time as "YYYY-MM-DD HH:mm:ss" — the format the server stores. */
    const stamp = (d = new Date()) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    const ymd = (d = new Date()) => `${String(d.getFullYear()).slice(2)}${pad(d.getMonth() + 1)}${pad(d.getDate())}`;
    const displayTime = (s) => (s ? s.slice(11, 16) : '');

    const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (c) => (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16)));

    // ------------------------------------------------------------------ IndexedDB (core in sync-core.js)
    const kvSet = (key, value) => tx(['kv'], 'readwrite', (x) => req(x.objectStore('kv').put(value, key)));
    const kvDelete = (key) => tx(['kv'], 'readwrite', (x) => req(x.objectStore('kv').delete(key)));
    const put = (store, record) => tx([store], 'readwrite', (x) => req(x.objectStore(store).put(record)));
    const all = (store) => tx([store], 'readonly', (x) => req(x.objectStore(store).getAll()));

    // ------------------------------------------------------------------ API
    /** Same as TollSync.request, with this phone's token and a translated "offline" message. */
    const api = async (method, path, body) => {
        try {
            return await window.TollSync.request(method, path, { body, token: state.auth?.token });
        } catch (error) {
            throw error.isNetwork ? new ApiError(t('offline'), 0) : error;
        }
    };

    // ------------------------------------------------------------------ master data helpers
    const category = (id) => state.master?.categories.find((c) => c.id === id);
    const englishName = (c) => c?.name_en || c?.code || c?.name_bn || '';
    const categoryName = (c) => (state.lang === 'bn' ? c?.name_bn || '' : englishName(c));

    /** The rate in force at the given time (rates are sorted: current first, then upcoming changes). */
    const amountFor = (c, at) => {
        if (c.is_exempt) {
            return '0.00';
        }

        let amount = c.rates[0]?.amount ?? '0.00';
        c.rates.forEach((rate) => {
            if (rate.from && rate.from <= at) {
                amount = rate.amount;
            }
        });

        return amount;
    };

    /** The active shift whose time window contains now (handles overnight shifts). */
    const detectShift = () => {
        const now = stamp().slice(11);

        return state.master.shifts.find((s) => (s.start_time <= s.end_time ? now >= s.start_time && now < s.end_time : now >= s.start_time || now < s.end_time));
    };

    /** Next gap-free ticket number for this phone and day: B01-M2-261112-000123. */
    const nextTicketNo = async (date) => {
        const day = ymd(date);
        const key = 'seq-' + day;
        const serverLast = state.master.ticket_sequence?.date === day ? Number(state.master.ticket_sequence.last) : 0;
        const next = Math.max(Number((await kvGet(key)) || 0), serverLast) + 1;
        await kvSet(key, next);

        return `${state.master.device.ticket_prefix}-${day}-${pad(next, 6)}`;
    };

    // ------------------------------------------------------------------ local data
    const refreshLocal = async () => {
        const sessions = await all('sessions');
        state.session = sessions.find((s) => s.status === 'open' && s.user_id === state.auth?.user_id) || null;

        const pendingTickets = await byIndex('tickets', 'sync_state', PENDING);
        const pendingSessions = await byIndex('sessions', 'sync_state', PENDING);
        state.failed = await byIndex('tickets', 'sync_state', FAILED);
        state.pending = pendingTickets.length + pendingSessions.length;

        state.recent = state.session
            ? (await byIndex('tickets', 'shift_session_id', state.session.id)).sort((a, b) => b.ticket_no.localeCompare(a.ticket_no)).slice(0, 8)
            : [];
    };

    /** Unsynced or failed data / open shift belonging to someone else blocks a different operator. */
    const foreignOwner = async (phone) => {
        const sessions = await all('sessions');
        const pending = state.pending + state.failed.length;
        const open = sessions.some((s) => s.status === 'open');
        const owner = (await kvGet('owner_phone')) || null;

        return owner && owner !== phone && (pending > 0 || open) ? owner : null;
    };

    const pruneOldTickets = async () => {
        const limit = stamp(new Date(Date.now() - KEEP_SYNCED_DAYS * 86400000));
        await tx(['tickets', 'sessions'], 'readwrite', async (x) => {
            const tickets = await req(x.objectStore('tickets').index('sync_state').getAll(SYNCED));
            tickets.filter((ticket) => ticket.issued_at < limit).forEach((ticket) => x.objectStore('tickets').delete(ticket.id));
            const sessions = await req(x.objectStore('sessions').index('sync_state').getAll(SYNCED));
            sessions.filter((s) => s.status === 'closed' && s.closed_at < limit).forEach((s) => x.objectStore('sessions').delete(s.id));
        });
    };

    // ------------------------------------------------------------------ sync
    /*
     * Fully automatic: the loop below keeps running on its own. While the central server is
     * reachable it syncs every SYNC_INTERVAL_MS (and right after every new record); when the
     * internet drops, everything simply keeps being saved on the phone and the loop retries with
     * a growing delay. The moment the connection comes back (online event, app reopened, or the
     * service worker's Background Sync when the app is closed) the waiting records are sent.
     */
    let syncTimer = null;
    let syncRequested = false;

    const applyMaster = async (master) => {
        state.master = master;
        await kvSet('master', master);

        const skew = Math.round((new Date(master.server_time.replace(' ', 'T')).getTime() - Date.now()) / 60000);
        state.clockSkewMinutes = Math.abs(skew) >= 5 ? skew : 0;
    };

    const scheduleSync = (delay = 1500) => {
        clearTimeout(syncTimer);
        syncTimer = setTimeout(() => sync(), delay);
    };

    /** 5s, 10s, 20s ... up to 5min, with a little jitter so phones of one booth don't retry together. */
    const retryDelay = () => Math.min(RETRY_MIN_MS * 2 ** Math.max(state.failures - 1, 0), RETRY_MAX_MS) * (0.8 + Math.random() * 0.4);

    const setConnection = (connection) => {
        if (state.connection !== connection) {
            state.connection = connection;
            renderStatus();
        }
    };

    /** Asks the service worker to push the waiting records as soon as the internet is back, even if the app is closed. */
    const requestBackgroundSync = () => {
        navigator.serviceWorker?.ready
            .then((registration) => registration.sync?.register(window.TollSync.BACKGROUND_SYNC_TAG))
            .catch(() => {});
    };

    const sync = async ({ manual = false } = {}) => {
        clearTimeout(syncTimer);

        if (state.syncing) {
            syncRequested = true;

            return;
        }

        if (!state.auth || state.authExpired) {
            scheduleSync(SYNC_INTERVAL_MS);

            return;
        }

        if (!navigator.onLine) {
            setConnection('offline');

            if (state.pending) {
                requestBackgroundSync();
            }

            if (manual) {
                toast(t('syncFailed', { error: t('offline') }), 'error');
            }

            scheduleSync(retryDelay());

            return;
        }

        state.syncing = true;
        syncRequested = false;
        renderStatus();
        let next = SYNC_INTERVAL_MS;

        try {
            const { ran } = await window.TollSync.exclusive(async () => {
                // Being "connected" to Wi-Fi or mobile data does not mean the server is reachable: probe first.
                if (state.connection !== 'online') {
                    await api('GET', '/ping');
                }

                await window.TollSync.pushPending(state.auth.token);
                await applyMaster((await api('GET', '/bootstrap')).data);
            });

            if (!ran) {
                next = LOCK_RETRY_MS;

                return;
            }

            state.failures = 0;
            state.lastSync = stamp();
            state.lastError = null;
            await kvSet('last_sync', state.lastSync);
            setConnection('online');

            if (manual) {
                toast(t('syncDone'), 'success');
            }
        } catch (error) {
            state.lastError = error.isNetwork ? t('offline') : error.message;

            if (error.status === 401) {
                state.authExpired = true;
                render();

                return;
            }

            state.failures += 1;
            next = retryDelay();

            if (error.isNetwork) {
                setConnection('offline');
                requestBackgroundSync();
            }

            if (manual) {
                toast(t('syncFailed', { error: state.lastError }), 'error');
            }
        } finally {
            state.syncing = false;
            await refreshLocal();
            renderStatus();

            if (state.screen === 'settings' || state.screen === 'summary') {
                render();
            }

            if (!state.authExpired) {
                scheduleSync(syncRequested ? 300 : next);
            }
        }
    };

    /** New data saved on the phone: send it right away, or leave it for the moment the internet returns. */
    const queueSync = (delay = 1500) => {
        if (state.connection === 'offline' || !navigator.onLine) {
            requestBackgroundSync();
            renderStatus();

            return;
        }

        scheduleSync(delay);
    };

    // ------------------------------------------------------------------ operations
    const openShift = async (shiftId, openingCash) => {
        const session = {
            id: uuid(),
            shift_id: Number(shiftId),
            user_id: state.auth.user_id,
            status: 'open',
            opened_at: stamp(),
            opening_cash: Number(openingCash || 0),
            closed_at: null,
            declared_cash: null,
            notes: null,
            sync_state: PENDING,
            version: 1,
        };

        await put('sessions', session);
        await refreshLocal();
        toast(t('shiftOpened'), 'success');
        queueSync();
    };

    const issueTicket = async (c, plate, exemptReason) => {
        const now = new Date();
        const issuedAt = stamp(now);
        const ticket = {
            id: uuid(),
            ticket_no: await nextTicketNo(now),
            shift_session_id: state.session.id,
            vehicle_category_id: c.id,
            amount: amountFor(c, issuedAt),
            is_exempt: !!c.is_exempt,
            exempt_reason: c.is_exempt ? exemptReason : null,
            plate_number: plate ? plate.toUpperCase().replace(/\s+/g, ' ').trim() : null,
            print_count: 1,
            issued_at: issuedAt,
            sync_state: PENDING,
            version: 1,
        };

        await put('tickets', ticket);
        await refreshLocal();

        const printed = await printTicket(ticket);
        toast(printed ? t('ticketIssued', { no: ticket.ticket_no.slice(-6) }) : t('printFailed'), printed ? 'success' : 'warning');
        queueSync();

        return ticket;
    };

    const reprint = async (id) => {
        const ticket = await tx(['tickets'], 'readonly', (x) => req(x.objectStore('tickets').get(id)));
        ticket.print_count += 1;
        ticket.version += 1;
        ticket.sync_state = ticket.sync_state === FAILED ? FAILED : PENDING;
        await put('tickets', ticket);
        await refreshLocal();

        if (!(await printTicket(ticket))) {
            toast(t('printFailed'), 'warning');
        }

        queueSync();
    };

    const shiftTotals = async (session) => {
        const tickets = await byIndex('tickets', 'shift_session_id', session.id);
        const rows = new Map();
        let vehicles = 0;
        let revenue = 0;

        tickets.forEach((ticket) => {
            const row = rows.get(ticket.vehicle_category_id) || { category: category(ticket.vehicle_category_id), count: 0, amount: 0 };
            row.count += 1;
            row.amount += Number(ticket.amount);
            rows.set(ticket.vehicle_category_id, row);
            vehicles += 1;
            revenue += Number(ticket.amount);
        });

        return { rows: [...rows.values()], vehicles, revenue, expected: Number(session.opening_cash) + revenue };
    };

    const closeShift = async (declaredCash, notes) => {
        const session = { ...state.session };
        const totals = await shiftTotals(session);

        session.status = 'closed';
        session.closed_at = stamp();
        session.declared_cash = Number(declaredCash || 0);
        session.notes = notes || null;
        session.sync_state = PENDING;
        session.version += 1;

        await put('sessions', session);
        await refreshLocal();
        await printShiftSummary(session, totals);

        toast(t('shiftClosed', { diff: money(session.declared_cash - totals.expected) }), 'success');
        state.screen = 'collect';
        render();
        queueSync(300);
    };

    // ------------------------------------------------------------------ ESC/POS receipt
    const CHARS_PER_LINE = 32;

    /** Thermal printers only print plain ASCII reliably (no Bangla glyphs). */
    const ascii = (text) => String(text ?? '').normalize('NFKD').replace(/[^\x20-\x7E]/g, '').replace(/\s+/g, ' ').trim();

    class Escpos {
        constructor() {
            this.bytes = [0x1b, 0x40];
            this.lines = [];
        }

        raw(...bytes) {
            this.bytes.push(...bytes);

            return this;
        }

        text(value) {
            this.lines.push(ascii(value));

            for (const ch of ascii(value)) {
                this.bytes.push(ch.charCodeAt(0));
            }

            return this.raw(0x0a);
        }

        align(position) {
            return this.raw(0x1b, 0x61, { left: 0, center: 1, right: 2 }[position]);
        }

        bold(on) {
            return this.raw(0x1b, 0x45, on ? 1 : 0);
        }

        size(width, height) {
            return this.raw(0x1d, 0x21, ((width - 1) << 4) | (height - 1));
        }

        reverse(on) {
            return this.raw(0x1d, 0x42, on ? 1 : 0);
        }

        rule() {
            return this.text('-'.repeat(CHARS_PER_LINE));
        }

        pair(label, value) {
            const left = ascii(label) + ':';
            const right = ascii(value);
            const space = Math.max(1, CHARS_PER_LINE - left.length - right.length);

            return this.text(left + ' '.repeat(space) + right);
        }

        qr(data) {
            const payload = [...ascii(data)].map((ch) => ch.charCodeAt(0));
            const length = payload.length + 3;

            return this
                .raw(0x1d, 0x28, 0x6b, 0x04, 0x00, 0x31, 0x41, 0x32, 0x00)
                .raw(0x1d, 0x28, 0x6b, 0x03, 0x00, 0x31, 0x43, 0x05)
                .raw(0x1d, 0x28, 0x6b, 0x03, 0x00, 0x31, 0x45, 0x31)
                .raw(0x1d, 0x28, 0x6b, length & 0xff, length >> 8, 0x31, 0x50, 0x30, ...payload)
                .raw(0x1d, 0x28, 0x6b, 0x03, 0x00, 0x31, 0x51, 0x30);
        }

        feedAndCut() {
            return this.raw(0x1b, 0x64, 0x03, 0x1d, 0x56, 0x42, 0x00);
        }

        toUint8Array() {
            return new Uint8Array(this.bytes);
        }

        /** The same content as plain text, for the browser print fallback. */
        toHtml() {
            return `<pre>${esc(this.lines.join('\n'))}</pre>`;
        }
    }

    /** Same content and order as the server receipt (App\Services\Receipt\ReceiptData). */
    const receiptFor = (ticket) => {
        const settings = state.master.settings;
        const c = category(ticket.vehicle_category_id);
        const d = new Date(ticket.issued_at.replace(' ', 'T'));
        const hours = d.getHours() % 12 || 12;

        return {
            title: settings.bridge_name_en,
            subtitle: settings.bridge_name_bn,
            header: settings.receipt_header,
            duplicate: ticket.print_count > 1,
            exempt: ticket.is_exempt,
            rows: [
                ['Ticket', ticket.ticket_no],
                ['Date', `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(hours)}:${pad(d.getMinutes())}:${pad(d.getSeconds())} ${d.getHours() < 12 ? 'AM' : 'PM'}`],
                ['Booth', `${state.master.booth.code} - ${ascii(state.master.booth.name) || state.master.device.code}`],
                ['Vehicle', englishName(c)],
                ticket.plate_number ? ['Plate', ticket.plate_number] : null,
                ['Operator', state.master.user.name],
                ticket.is_exempt ? ['Exempt', ticket.exempt_reason || ''] : null,
            ].filter((row) => row && ascii(row[1]) !== ''),
            amount: 'Tk ' + Number(ticket.amount).toFixed(2),
            footer: settings.receipt_footer,
            helpline: settings.helpline || null,
            qr: ticket.ticket_no,
        };
    };

    const escposReceipt = (receipt) => {
        const p = new Escpos().align('center').bold(true).text(receipt.title).bold(false).text(receipt.header);

        if (receipt.duplicate) {
            p.reverse(true).text(' DUPLICATE ').reverse(false);
        }

        p.rule().align('left');
        receipt.rows.forEach(([label, value]) => p.pair(label, value));
        p.rule().align('center').size(1, 2).bold(true).text((receipt.exempt ? 'EXEMPT ' : '') + receipt.amount).bold(false).size(1, 1);
        p.qr(receipt.qr).text(receipt.footer);

        if (receipt.helpline) {
            p.text('Helpline: ' + receipt.helpline);
        }

        return p.feedAndCut().toUint8Array();
    };

    const htmlReceipt = (receipt) => `
        <div class="c b">${esc(receipt.title)}</div>
        <div class="c">${esc(receipt.subtitle)}</div>
        <div class="c">${esc(receipt.header)}</div>
        ${receipt.duplicate ? '<div class="c b">*** DUPLICATE ***</div>' : ''}
        <hr>
        ${receipt.rows.map(([label, value]) => `<div class="r"><span>${esc(label)}:</span><span class="b">${esc(value)}</span></div>`).join('')}
        <hr>
        <div class="c big">${receipt.exempt ? 'EXEMPT ' : ''}${esc(receipt.amount)}</div>
        <div class="c b">${esc(receipt.qr)}</div>
        <div class="c">${esc(receipt.footer)}</div>
        ${receipt.helpline ? `<div class="c">Helpline: ${esc(receipt.helpline)}</div>` : ''}`;

    // ------------------------------------------------------------------ printer transports
    const BLE_SERVICES = [
        '000018f0-0000-1000-8000-00805f9b34fb',
        'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
        '49535343-fe7d-4ae5-8fa9-9fafd205e455',
        '0000ff00-0000-1000-8000-00805f9b34fb',
        '0000ffe0-0000-1000-8000-00805f9b34fb',
        '0000fee7-0000-1000-8000-00805f9b34fb',
    ];

    const ble = { device: null, characteristic: null };

    const findWritable = async (server) => {
        for (const service of await server.getPrimaryServices()) {
            for (const characteristic of await service.getCharacteristics()) {
                if (characteristic.properties.write || characteristic.properties.writeWithoutResponse) {
                    return characteristic;
                }
            }
        }

        throw new Error('No writable characteristic');
    };

    const connectBle = async (device) => {
        ble.device = device;
        ble.characteristic = await findWritable(await device.gatt.connect());
        await kvSet('ble_device_id', device.id);
        renderIfSettings();
    };

    const pairBlePrinter = async () => {
        if (!navigator.bluetooth) {
            throw new Error(t('bleUnsupported'));
        }

        await connectBle(await navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: BLE_SERVICES }));
    };

    /** Reconnects silently to the printer paired earlier (Chrome remembers permitted devices). */
    const restoreBlePrinter = async () => {
        const id = await kvGet('ble_device_id');

        if (!id || !navigator.bluetooth?.getDevices) {
            return;
        }

        const device = (await navigator.bluetooth.getDevices()).find((d) => d.id === id);

        if (device) {
            await connectBle(device).catch(() => {});
        }
    };

    const writeBle = async (bytes) => {
        if (!ble.characteristic) {
            await restoreBlePrinter();
        }

        if (!ble.characteristic) {
            throw new Error(t('printerNotConnected'));
        }

        if (!ble.device.gatt.connected) {
            ble.characteristic = await findWritable(await ble.device.gatt.connect());
        }

        for (let offset = 0; offset < bytes.length; offset += 180) {
            const chunk = bytes.slice(offset, offset + 180);

            if (ble.characteristic.properties.writeWithoutResponse) {
                await ble.characteristic.writeValueWithoutResponse(chunk);
            } else {
                await ble.characteristic.writeValue(chunk);
            }
        }
    };

    /** RawBT (Play Store app) prints to classic Bluetooth printers; works offline. */
    const sendToRawbt = (bytes) => {
        let binary = '';
        bytes.forEach((b) => { binary += String.fromCharCode(b); });
        window.location.href = 'intent:base64,' + btoa(binary) + '#Intent;scheme=rawbt;package=ru.a402d.rawbtprinter;end;';
    };

    const browserPrint = (html) => {
        $('#print-area').innerHTML = html;
        window.print();
    };

    /** Sends a receipt with the chosen method. Returns false (never throws) when printing failed. */
    const send = async (bytes, html) => {
        try {
            switch (state.printer.mode) {
                case 'none':
                    break;
                case 'browser':
                    browserPrint(html);
                    break;
                case 'ble':
                    await writeBle(bytes);
                    break;
                default:
                    sendToRawbt(bytes);
            }

            return true;
        } catch (error) {
            console.error(error);

            return false;
        }
    };

    const print = (receipt) => send(escposReceipt(receipt), htmlReceipt(receipt));

    const printTicket = (ticket) => print(receiptFor(ticket));

    const printShiftSummary = (session, totals) => {
        const p = new Escpos().align('center').bold(true).text(state.master.settings.bridge_name_en).text('SHIFT SUMMARY').bold(false).rule().align('left');
        const shift = state.master.shifts.find((s) => s.id === session.shift_id);

        p.pair('Booth', `${state.master.booth.code} / ${state.master.device.code}`)
            .pair('Operator', state.master.user.name)
            .pair('Shift', shift?.name || '')
            .pair('Opened', session.opened_at.slice(5, 16))
            .pair('Closed', session.closed_at.slice(5, 16))
            .rule();
        totals.rows.forEach((row) => p.pair(`${englishName(row.category) || '?'} x${row.count}`, row.amount.toFixed(2)));
        p.rule()
            .pair('Vehicles', String(totals.vehicles))
            .pair('Revenue', totals.revenue.toFixed(2))
            .pair('Opening cash', Number(session.opening_cash).toFixed(2))
            .pair('Expected cash', totals.expected.toFixed(2))
            .pair('Declared cash', Number(session.declared_cash).toFixed(2))
            .bold(true).pair('Difference', (session.declared_cash - totals.expected).toFixed(2)).bold(false);

        return send(p.feedAndCut().toUint8Array(), p.toHtml());
    };

    // ------------------------------------------------------------------ UI helpers
    let toastTimer = null;

    const toast = (message, type = 'info') => {
        const el = $('#toast');
        el.textContent = message;
        el.className = 'toast toast--' + type;
        el.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { el.hidden = true; }, 3200);
    };

    /** Small promise-based modal. Resolves with the form values, or null when cancelled. */
    const modal = ({ title, body = '', confirm = t('ok'), danger = false }) => new Promise((resolve) => {
        const root = $('#modal-root');
        root.innerHTML = `
            <div class="modal-backdrop">
                <form class="modal" novalidate>
                    <h2>${esc(title)}</h2>
                    ${body}
                    <p class="error" data-error hidden></p>
                    <div class="row">
                        <button type="button" class="btn btn--ghost" data-cancel>${esc(t('cancel'))}</button>
                        <button type="submit" class="btn ${danger ? 'btn--danger' : ''}">${esc(confirm)}</button>
                    </div>
                </form>
            </div>`;

        const form = root.querySelector('form');
        const close = (value) => { root.innerHTML = ''; resolve(value); };

        form.querySelector('[data-cancel]').addEventListener('click', () => close(null));
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const values = Object.fromEntries(new FormData(form).entries());
            const required = [...form.querySelectorAll('[required]')].find((input) => !input.value.trim());

            if (required) {
                const error = form.querySelector('[data-error]');
                error.textContent = required.dataset.message || t('reasonRequired');
                error.hidden = false;

                return;
            }

            close(values);
        });
        form.querySelector('input, textarea')?.focus();
    });

    const renderIfSettings = () => {
        if (state.screen === 'settings') {
            render();
        }
    };

    // ------------------------------------------------------------------ screens
    const statusPill = () => {
        const online = state.connection === 'online';
        const dot = state.syncing ? 'dot--syncing' : online ? 'dot--online' : 'dot--offline';
        const label = state.syncing ? t('syncing') : online ? t('online') : t('offline');
        const pending = state.pending ? ` · ${t('pending', { count: state.pending })}` : '';

        return `<button class="pill" data-action="sync" id="status-pill"><span class="dot ${dot}"></span>${esc(label + pending)}</button>`;
    };

    /** Tells the operator that work continues offline and will be sent automatically. */
    const offlineBanner = () => (state.connection === 'offline'
        ? `<div class="banner banner--warning" id="offline-banner">${esc(t('offlineSaving', { count: state.pending }))}</div>`
        : '<div id="offline-banner" hidden></div>');

    /** Updates only the connection indicators, so typing in a form is never interrupted. */
    const renderStatus = () => {
        [['#status-pill', statusPill], ['#offline-banner', offlineBanner]].forEach(([selector, view]) => {
            const element = $(selector);

            if (element) {
                element.outerHTML = view();
            }
        });
    };

    const topbar = () => `
        <header class="topbar">
            <div class="topbar__title">
                <strong>${esc(state.master.booth.code)} · ${esc(state.master.device.code)} — ${esc(state.master.user.name)}</strong>
                <small>${esc(state.lang === 'bn' ? state.master.settings.bridge_name_bn : state.master.settings.bridge_name_en)}</small>
            </div>
            ${statusPill()}
        </header>`;

    const bottomnav = () => `
        <nav class="bottomnav">
            ${[['collect', '🎫'], ['summary', '📊'], ['settings', '⚙️']].map(([screen, icon]) => `
                <button data-nav="${screen}" class="${state.screen === screen ? 'is-active' : ''}"><span>${icon}</span>${esc(t(screen))}</button>`).join('')}
        </nav>`;

    const banners = () => [
        offlineBanner(),
        state.authExpired ? `<div class="banner banner--danger">${esc(t('sessionEnded'))}</div>` : '',
        state.clockSkewMinutes ? `<div class="banner banner--warning">${esc(t('clockWarning', { minutes: Math.abs(state.clockSkewMinutes) }))}</div>` : '',
        state.failed.length ? `<div class="banner banner--danger">${esc(t('failedTickets'))}: ${state.failed.length}</div>` : '',
    ].join('');

    const loginScreen = (error = '') => `
        <main class="login">
            <div class="login__brand">
                <img src="/mobile/icons/icon-192.png" alt="">
                <h1>${esc(t('appName'))}</h1>
                <p class="muted small">${esc(t('needInternet'))}</p>
            </div>
            ${state.authExpired ? `<div class="banner banner--warning">${esc(t('sessionEnded'))}</div>` : ''}
            <form class="card stack" id="login-form" novalidate>
                <div class="field">
                    <label for="phone">${esc(t('phone'))}</label>
                    <input class="input" id="phone" name="phone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="01XXXXXXXXX" value="${esc(state.auth?.phone || '')}" required>
                </div>
                <div class="field">
                    <label for="password">${esc(t('password'))}</label>
                    <input class="input" id="password" name="password" type="password" inputmode="numeric" pattern="[0-9]{4,5}" minlength="4" maxlength="5" autocomplete="current-password" required>
                </div>
                ${error ? `<p class="error">${esc(error)}</p>` : ''}
                <button class="btn btn--block" type="submit">${esc(t('signIn'))}</button>
            </form>
            <button class="btn btn--ghost btn--small" data-action="toggle-lang">${state.lang === 'bn' ? 'English' : 'বাংলা'}</button>
        </main>`;

    const openShiftScreen = () => {
        const detected = detectShift();

        return `
            ${topbar()}
            <main class="content">
                ${banners()}
                <form class="card stack" id="open-shift-form">
                    <h2>${esc(t('openShift'))}</h2>
                    <div class="field">
                        <label for="shift_id">${esc(t('shift'))}</label>
                        <select class="input" id="shift_id" name="shift_id">
                            ${state.master.shifts.map((s) => `<option value="${s.id}" ${detected?.id === s.id ? 'selected' : ''}>${esc(s.name)} (${s.start_time.slice(0, 5)}–${s.end_time.slice(0, 5)})</option>`).join('')}
                        </select>
                    </div>
                    <div class="field">
                        <label for="opening_cash">${esc(t('openingCash'))}</label>
                        <input class="input input--big" id="opening_cash" name="opening_cash" type="number" inputmode="decimal" min="0" step="1" value="0">
                    </div>
                    <button class="btn btn--block" type="submit">▶ ${esc(t('start'))}</button>
                </form>
            </main>
            ${bottomnav()}`;
    };

    const ticketItem = (ticket) => {
        const c = category(ticket.vehicle_category_id);
        const tag = ticket.sync_state === SYNCED ? 'synced' : ticket.sync_state === FAILED ? 'failed' : 'pending';
        const tagLabel = ticket.sync_state === SYNCED ? '✓' : ticket.sync_state === FAILED ? '✕' : '…';

        return `
            <div class="list__item">
                <span style="font-size:1.4rem">${esc(c?.icon || '🚗')}</span>
                <div class="list__main">
                    <strong>${esc(ticket.ticket_no.slice(-6))} · ${esc(categoryName(c))} ${ticket.plate_number ? '· ' + esc(ticket.plate_number) : ''}</strong>
                    <small>${esc(displayTime(ticket.issued_at))} · ${esc(money(ticket.amount))}${ticket.print_count > 1 ? ' · ×' + ticket.print_count : ''}${ticket.error ? ' · ' + esc(ticket.error) : ''}</small>
                </div>
                <span class="tag tag--${tag}">${tagLabel}</span>
                <button class="btn btn--ghost btn--small" data-reprint="${ticket.id}">🖨 ${esc(t('reprint'))}</button>
            </div>`;
    };

    const collectScreen = () => {
        if (!state.session) {
            return openShiftScreen();
        }

        const now = stamp();

        return `
            ${topbar()}
            <main class="content">
                ${banners()}
                <div class="plate-row">
                    <input class="input" id="plate" placeholder="${esc(t('plateOptional'))}" autocomplete="off" autocapitalize="characters">
                    <button class="btn btn--ghost btn--small" data-action="clear-plate">${esc(t('clear'))}</button>
                </div>
                <div class="grid">
                    ${state.master.categories.map((c) => `
                        <button class="cat" data-category="${c.id}" style="--cat:${esc(c.color || '#0f766e')}">
                            <span class="cat__icon">${esc(c.icon || '🚗')}</span>
                            <span class="cat__name">${esc(categoryName(c))}</span>
                            <span class="cat__amount">${c.is_exempt ? esc(t('exempt')) : esc(money(amountFor(c, now)))}</span>
                        </button>`).join('')}
                </div>
                <section class="card">
                    <h3>${esc(t('recent'))}</h3>
                    <div class="list">${state.recent.length ? state.recent.map(ticketItem).join('') : `<p class="muted small">${esc(t('noTickets'))}</p>`}</div>
                </section>
            </main>
            ${bottomnav()}`;
    };

    const summaryScreen = async () => {
        if (!state.session) {
            return openShiftScreen();
        }

        const totals = await shiftTotals(state.session);
        const shift = state.master.shifts.find((s) => s.id === state.session.shift_id);

        return `
            ${topbar()}
            <main class="content">
                ${banners()}
                <div class="stats">
                    <div class="stat"><span>${esc(t('vehicles'))}</span><strong>${totals.vehicles}</strong></div>
                    <div class="stat"><span>${esc(t('revenue'))}</span><strong>${esc(money(totals.revenue))}</strong></div>
                    <div class="stat"><span>${esc(t('openingCash'))}</span><strong>${esc(money(state.session.opening_cash))}</strong></div>
                    <div class="stat"><span>${esc(t('expectedCash'))}</span><strong>${esc(money(totals.expected))}</strong></div>
                </div>
                <section class="card">
                    <h3>${esc(shift?.name || t('shift'))} · ${esc(displayTime(state.session.opened_at))}</h3>
                    <table class="summary">
                        <thead><tr><th>${esc(t('category'))}</th><th>${esc(t('count'))}</th><th>${esc(t('amount'))}</th></tr></thead>
                        <tbody>${totals.rows.map((row) => `<tr><td>${esc(row.category?.icon || '')} ${esc(categoryName(row.category))}</td><td>${row.count}</td><td>${esc(money(row.amount))}</td></tr>`).join('')}</tbody>
                        <tfoot><tr><td>${esc(t('total'))}</td><td>${totals.vehicles}</td><td>${esc(money(totals.revenue))}</td></tr></tfoot>
                    </table>
                </section>
                <form class="card stack" id="close-shift-form">
                    <h2>${esc(t('closeShift'))}</h2>
                    <div class="field">
                        <label for="declared_cash">${esc(t('declaredCash'))}</label>
                        <input class="input input--big" id="declared_cash" name="declared_cash" type="number" inputmode="decimal" min="0" step="1" value="${totals.expected}" required>
                    </div>
                    <div class="field">
                        <label for="notes">${esc(t('notes'))}</label>
                        <textarea class="input" id="notes" name="notes" rows="2"></textarea>
                    </div>
                    <button class="btn btn--danger btn--block" type="submit">⏹ ${esc(t('closeShift'))}</button>
                </form>
            </main>
            ${bottomnav()}`;
    };

    const settingsScreen = () => {
        const mode = state.printer.mode;
        const bleName = ble.device?.name;

        return `
            ${topbar()}
            <main class="content">
                ${banners()}
                <section class="card stack">
                    <h2>🖨 ${esc(t('printer'))}</h2>
                    <div class="field">
                        <label for="printer_mode">${esc(t('printerMode'))}</label>
                        <select class="input" id="printer_mode">
                            ${[['rawbt', 'modeRawbt'], ['ble', 'modeBle'], ['browser', 'modeBrowser'], ['none', 'modeNone']].map(([value, label]) => `<option value="${value}" ${mode === value ? 'selected' : ''}>${esc(t(label))}</option>`).join('')}
                        </select>
                    </div>
                    ${mode === 'rawbt' ? `<p class="muted small">${esc(t('rawbtHelp'))}</p>` : ''}
                    ${mode === 'ble' ? `
                        <p class="muted small">${esc(t('bleHelp'))}</p>
                        <p class="small"><strong>${esc(bleName ? t('printerConnected', { name: bleName }) : t('printerNotConnected'))}</strong></p>
                        <button class="btn btn--ghost" data-action="pair-ble">🔗 ${esc(t('connectPrinter'))}</button>` : ''}
                    <button class="btn btn--ghost" data-action="test-print">${esc(t('testPrint'))}</button>
                </section>
                <section class="card stack">
                    <h2>🔄 ${esc(t('sync'))}</h2>
                    <p class="small">${esc(t('lastSync'))}: <strong>${esc(state.lastSync || t('never'))}</strong> · ${esc(t('pending', { count: state.pending }))}</p>
                    ${state.lastError ? `<p class="error small">${esc(state.lastError)}</p>` : ''}
                    <button class="btn" data-action="sync">${esc(t('syncNow'))}</button>
                    ${state.failed.length ? `
                        <h3>${esc(t('failedTickets'))}</h3>
                        <div class="list">${state.failed.map(ticketItem).join('')}</div>` : ''}
                </section>
                <section class="card stack">
                    <h2>👤 ${esc(t('account'))}</h2>
                    <p class="small">${esc(t('operator'))}: <strong>${esc(state.master.user.name)}</strong> (${esc(state.master.user.phone)})<br>
                    ${esc(t('noBooth'))}: <strong>${esc(state.master.booth.code)} — ${esc(state.master.booth.name)}</strong><br>
                    ${esc(t('device'))}: <strong>${esc(state.master.device.code)}</strong></p>
                    <div class="row">
                        <button class="btn btn--ghost" data-action="toggle-lang">${esc(t('language'))}: ${state.lang === 'bn' ? 'English' : 'বাংলা'}</button>
                        ${installPrompt ? `<button class="btn btn--ghost" data-action="install">${esc(t('install'))}</button>` : ''}
                    </div>
                    <button class="btn btn--ghost" data-action="logout">${esc(t('logout'))}</button>
                </section>
            </main>
            ${bottomnav()}`;
    };

    const render = async () => {
        const app = $('#app');

        if (!state.auth || !state.master || state.authExpired) {
            app.innerHTML = loginScreen();

            return;
        }

        if (state.screen === 'summary') {
            app.innerHTML = await summaryScreen();
        } else if (state.screen === 'settings') {
            app.innerHTML = settingsScreen();
        } else {
            app.innerHTML = collectScreen();
        }
    };

    // ------------------------------------------------------------------ events
    const signIn = async (form) => {
        const phone = form.phone.value.trim();
        const password = form.password.value;
        const button = form.querySelector('button[type=submit]');
        const owner = await foreignOwner(phone);

        if (owner) {
            $('#app').innerHTML = loginScreen(t('otherUserPending', { phone: owner }));

            return;
        }

        button.disabled = true;
        button.textContent = t('signingIn');

        try {
            let deviceUuid = await kvGet('device_uuid');

            if (!deviceUuid) {
                deviceUuid = uuid();
                await kvSet('device_uuid', deviceUuid);
            }

            const result = await api('POST', '/login', { phone, password, device_uuid: deviceUuid, device_name: navigator.userAgentData?.platform || 'Android' });

            state.auth = { token: result.token, phone: result.data.user.phone, user_id: result.data.user.id };
            state.authExpired = false;
            await kvSet('auth', state.auth);
            await kvSet('owner_phone', state.auth.phone);
            await applyMaster(result.data);
            await refreshLocal();
            state.screen = 'collect';
            render();
            sync();
        } catch (error) {
            $('#app').innerHTML = loginScreen(error.status === 0 ? t('needInternet') : error.message);
        }
    };

    const signOut = async () => {
        if (state.session || state.pending > 0 || state.failed.length > 0 || !navigator.onLine) {
            toast(t('logoutBlocked'), 'error');

            return;
        }

        await api('POST', '/logout').catch(() => {});
        await Promise.all(['auth', 'master', 'owner_phone'].map(kvDelete));
        state.auth = null;
        state.master = null;
        render();
    };

    const onCategory = async (button) => {
        if (state.busy) {
            return;
        }

        const c = category(Number(button.dataset.category));
        let reason = null;

        if (c.is_exempt) {
            const values = await modal({
                title: `${c.icon || ''} ${categoryName(c)}`,
                body: `<div class="field"><label for="reason">${esc(t('exemptReason'))}</label><input class="input" id="reason" name="reason" placeholder="${esc(t('exemptReasonHint'))}" required></div>`,
                confirm: t('issue'),
            });

            if (!values) {
                return;
            }

            reason = values.reason.trim();
        }

        state.busy = true;
        button.classList.add('cat--busy');

        try {
            await issueTicket(c, $('#plate')?.value.trim(), reason);
            render();
        } finally {
            state.busy = false;
        }
    };

    document.addEventListener('submit', async (event) => {
        const form = event.target;

        if (!['login-form', 'open-shift-form', 'close-shift-form'].includes(form.id)) {
            return;
        }

        event.preventDefault();

        if (form.id === 'login-form') {
            await signIn(form);
        } else if (form.id === 'open-shift-form') {
            await openShift(form.shift_id.value, form.opening_cash.value);
            render();
        } else if (form.id === 'close-shift-form') {
            const confirmed = await modal({ title: t('closeShift'), body: `<p>${esc(t('closeConfirm'))}</p>`, confirm: t('closeShift'), danger: true });

            if (confirmed) {
                await closeShift(form.declared_cash.value, form.notes.value.trim());
            }
        }
    });

    document.addEventListener('click', async (event) => {
        const target = event.target.closest('[data-action], [data-nav], [data-category], [data-reprint]');

        if (!target) {
            return;
        }

        if (target.dataset.nav) {
            state.screen = target.dataset.nav;
            render();
        } else if (target.dataset.category) {
            onCategory(target);
        } else if (target.dataset.reprint) {
            await reprint(target.dataset.reprint);
            render();
        } else {
            switch (target.dataset.action) {
                case 'sync':
                    sync({ manual: true });
                    break;
                case 'clear-plate':
                    $('#plate').value = '';
                    break;
                case 'toggle-lang':
                    state.lang = state.lang === 'bn' ? 'en' : 'bn';
                    document.documentElement.lang = state.lang;
                    await kvSet('lang', state.lang);
                    render();
                    break;
                case 'pair-ble':
                    pairBlePrinter().catch((error) => toast(error.message, 'error'));
                    break;
                case 'test-print': {
                    const ok = await print({
                        title: state.master.settings.bridge_name_en,
                        subtitle: state.master.settings.bridge_name_bn,
                        header: 'TEST PRINT',
                        duplicate: false,
                        exempt: false,
                        rows: [['Booth', state.master.booth.code], ['Device', state.master.device.code], ['Date', stamp()]],
                        amount: 'Tk 0.00',
                        footer: state.master.settings.receipt_footer,
                        helpline: null,
                        qr: state.master.device.ticket_prefix,
                    });
                    toast(ok ? t('ok') : t('printFailed'), ok ? 'success' : 'error');
                    break;
                }
                case 'install':
                    installPrompt?.prompt();
                    installPrompt = null;
                    break;
                case 'logout':
                    signOut();
                    break;
                default:
                    break;
            }
        }
    });

    document.addEventListener('change', async (event) => {
        if (event.target.id === 'printer_mode') {
            state.printer.mode = event.target.value;
            await kvSet('printer', state.printer);
            render();
        }
    });

    let installPrompt = null;
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt = event;
        renderIfSettings();
    });

    window.addEventListener('online', () => {
        state.failures = 0;
        sync();
    });
    window.addEventListener('offline', () => {
        setConnection('offline');

        if (state.pending) {
            requestBackgroundSync();
        }
    });
    // The service worker pushed records in the background (Background Sync).
    navigator.serviceWorker?.addEventListener('message', async (event) => {
        if (event.data?.type === 'pushed') {
            await refreshLocal();
            renderStatus();
            renderIfSettings();
        }
    });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            sync();
            keepScreenOn();
        }
    });

    /** Keeps the screen awake while collecting. */
    const keepScreenOn = () => navigator.wakeLock?.request('screen').catch(() => {});

    // ------------------------------------------------------------------ boot
    const boot = async () => {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/mobile/sw.js', { scope: '/mobile/' }).catch(() => {});
        }

        navigator.storage?.persist?.().catch(() => {});

        state.lang = (await kvGet('lang')) || 'bn';
        document.documentElement.lang = state.lang;
        state.auth = (await kvGet('auth')) || null;
        state.master = (await kvGet('master')) || null;
        state.printer = (await kvGet('printer')) || state.printer;
        state.lastSync = (await kvGet('last_sync')) || null;

        await pruneOldTickets().catch(() => {});
        await refreshLocal();
        render();

        if (state.printer.mode === 'ble') {
            restoreBlePrinter().catch(() => {});
        }

        keepScreenOn();
        sync();
    };

    boot();
})();
