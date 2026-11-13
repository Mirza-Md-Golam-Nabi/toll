<?php

namespace App\Services\Mobile;

use App\Enums\AuditEvent;
use App\Enums\PlateSource;
use App\Enums\ShiftStatus;
use App\Enums\TransactionStatus;
use App\Models\MobileDevice;
use App\Models\Shift;
use App\Models\ShiftSession;
use App\Models\TollRate;
use App\Models\TollTransaction;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Services\AuditLogger;
use App\Services\ShiftService;
use App\Services\VehicleRegistry;
use App\Support\PlateNumber;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Central side of the Android app: hands a phone everything it needs to collect offline,
 * and idempotently stores the shifts and tickets the phone pushes back.
 */
class MobileSyncService
{
    public function __construct(
        private ShiftService $shifts,
        private VehicleRegistry $vehicles,
        private AuditLogger $audit,
    ) {}

    /** @return array<string, mixed> */
    public function bootstrap(MobileDevice $device): array
    {
        $device->loadMissing(['booth', 'user']);
        $today = now();

        return [
            'user' => [
                'id' => $device->user->id,
                'name' => $device->user->name,
                'phone' => $device->user->phone,
                'role' => $device->user->role->value,
            ],
            'device' => [
                'code' => $device->code,
                'name' => $device->name,
                'ticket_prefix' => $device->ticketPrefix(),
            ],
            'booth' => $device->booth->only(['id', 'code', 'name']),
            'shifts' => Shift::query()->active()->orderBy('sort_order')->get(['id', 'name', 'start_time', 'end_time'])->toArray(),
            'categories' => $this->categories(),
            'settings' => collect(Settings::all())
                ->only(['bridge_name_bn', 'bridge_name_en', 'receipt_header', 'receipt_footer', 'helpline', 'currency'])
                ->all(),
            'ticket_sequence' => [
                'date' => $today->format('ymd'),
                'last' => $this->lastSequence($device, $today->format('ymd')),
            ],
            'server_time' => $today->toDateTimeString(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sessions
     * @param  list<array<string, mixed>>  $tickets
     * @return array{sessions: list<string>, tickets: list<string>, rejected: list<array{id: string, reason: string}>}
     */
    public function ingest(MobileDevice $device, array $sessions, array $tickets): array
    {
        $device->loadMissing(['booth', 'user']);

        $result = DB::transaction(function () use ($device, $sessions, $tickets): array {
            $rejected = [];
            $acceptedSessions = $this->storeSessions($device, $sessions, $rejected);
            $acceptedTickets = $this->storeTickets($device, $tickets, $rejected);

            $this->reconcileClosedSessions(array_merge(
                $acceptedSessions,
                array_column(array_filter($tickets, fn (array $row) => in_array($row['id'], $acceptedTickets, true)), 'shift_session_id'),
            ));

            return ['sessions' => $acceptedSessions, 'tickets' => $acceptedTickets, 'rejected' => $rejected];
        });

        $device->update(['last_synced_at' => now(), 'last_seen_at' => now()]);

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{id: string, reason: string}>  $rejected
     * @return list<string> accepted session ids
     */
    private function storeSessions(MobileDevice $device, array $rows, array &$rejected): array
    {
        if ($rows === []) {
            return [];
        }

        $existing = ShiftSession::query()->whereKey(array_column($rows, 'id'))->get()->keyBy('id');
        $accepted = [];
        $auditEntries = [];

        foreach ($rows as $row) {
            $session = $existing->get($row['id']);

            if ($session && (int) $session->mobile_device_id !== $device->id) {
                $rejected[] = ['id' => $row['id'], 'reason' => 'This shift belongs to another device.'];

                continue;
            }

            if (! $session) {
                $session = new ShiftSession;
                $session->id = $row['id'];
                $session->fill([
                    'booth_id' => $device->booth_id,
                    'shift_id' => $row['shift_id'],
                    'user_id' => $device->user_id,
                    'mobile_device_id' => $device->id,
                    'status' => ShiftStatus::Open,
                    'opened_at' => $row['opened_at'],
                    'opening_cash' => $row['opening_cash'] ?? 0,
                    'synced_at' => now(),
                ])->save();

                $auditEntries[] = $this->auditEntry(AuditEvent::ShiftOpened, $session, $device->user, $session->opened_at, [
                    'opening_cash' => $session->opening_cash,
                    'device' => $device->code,
                ]);
            }

            if ($session->isOpen() && $row['status'] === ShiftStatus::Closed->value) {
                $session->update([
                    'status' => ShiftStatus::Closed,
                    'closed_at' => $row['closed_at'],
                    'declared_cash' => $row['declared_cash'] ?? 0,
                    'notes' => $row['notes'] ?? null,
                    'synced_at' => now(),
                ]);

                $auditEntries[] = $this->auditEntry(AuditEvent::ShiftClosed, $session, $device->user, $session->closed_at, [
                    'declared_cash' => $session->declared_cash,
                    'device' => $device->code,
                ]);
            }

            $accepted[] = $session->id;
        }

        $this->audit->logMany($auditEntries);

        return $accepted;
    }

    /**
     * New tickets are inserted in bulk; a ticket pushed again only updates its print count (reprints).
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{id: string, reason: string}>  $rejected
     * @return list<string> accepted ticket ids
     */
    private function storeTickets(MobileDevice $device, array $rows, array &$rejected): array
    {
        if ($rows === []) {
            return [];
        }

        $existing = TollTransaction::query()->whereKey(array_column($rows, 'id'))->get(['id', 'mobile_device_id', 'print_count'])->keyBy('id');
        $sessionOwners = ShiftSession::query()
            ->whereKey(array_unique(array_column($rows, 'shift_session_id')))
            ->where('mobile_device_id', $device->id)
            ->pluck('user_id', 'id');
        $takenNumbers = TollTransaction::query()->whereIn('ticket_no', array_column($rows, 'ticket_no'))->pluck('id', 'ticket_no');
        $categories = VehicleCategory::query()->whereKey(array_unique(array_column($rows, 'vehicle_category_id')))->get(['id', 'is_exempt'])->keyBy('id');

        $prefix = $device->ticketPrefix().'-';
        $now = now();
        $accepted = [];
        $newRows = [];
        $auditEntries = [];

        foreach ($rows as $row) {
            $ticket = $existing->get($row['id']);

            if ($ticket) {
                if ((int) $ticket->mobile_device_id !== $device->id) {
                    $rejected[] = ['id' => $row['id'], 'reason' => 'This ticket belongs to another device.'];

                    continue;
                }

                if ((int) $row['print_count'] > $ticket->print_count) {
                    $ticket->update(['print_count' => (int) $row['print_count'], 'synced_at' => $now]);
                    $auditEntries[] = $this->auditEntry(AuditEvent::TicketReprinted, $ticket, $device->user, $now, ['print_count' => $ticket->print_count]);
                }

                $accepted[] = $ticket->id;

                continue;
            }

            $reason = match (true) {
                ! $sessionOwners->has($row['shift_session_id']) => 'The shift of this ticket was not found on the server.',
                ! str_starts_with($row['ticket_no'], $prefix) => 'The ticket number does not belong to this device.',
                $takenNumbers->has($row['ticket_no']) => 'This ticket number is already used.',
                default => null,
            };

            if ($reason !== null) {
                $rejected[] = ['id' => $row['id'], 'reason' => $reason];

                continue;
            }

            $isExempt = (bool) $categories->get($row['vehicle_category_id'])?->is_exempt;
            $plate = PlateNumber::normalize($row['plate_number'] ?? null);

            $newRows[] = [
                'id' => $row['id'],
                'ticket_no' => $row['ticket_no'],
                'booth_id' => $device->booth_id,
                'shift_session_id' => $row['shift_session_id'],
                'user_id' => $sessionOwners->get($row['shift_session_id']),
                'mobile_device_id' => $device->id,
                'vehicle_category_id' => $row['vehicle_category_id'],
                'amount' => $isExempt ? 0 : $row['amount'],
                'status' => $isExempt ? TransactionStatus::Exempt->value : TransactionStatus::Paid->value,
                'exempt_reason' => $isExempt ? ($row['exempt_reason'] ?? null) : null,
                'plate_number' => $plate,
                'plate_source' => $plate ? PlateSource::Manual->value : PlateSource::None->value,
                'print_count' => max(1, (int) $row['print_count']),
                'issued_at' => $row['issued_at'],
                'synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $takenNumbers->put($row['ticket_no'], $row['id']);
            $accepted[] = $row['id'];
        }

        foreach (array_chunk($newRows, 500) as $chunk) {
            TollTransaction::query()->insert($chunk);
        }

        $this->vehicles->recordPasses($newRows);

        foreach ($newRows as $row) {
            $auditEntries[] = $this->auditEntry(
                AuditEvent::TicketIssued,
                (new TollTransaction)->forceFill(['id' => $row['id']]),
                $device->user,
                Carbon::parse($row['issued_at']),
                ['ticket_no' => $row['ticket_no'], 'amount' => $row['amount'], 'plate_number' => $row['plate_number'], 'device' => $device->code],
            );
        }

        $this->audit->logMany($auditEntries);

        return $accepted;
    }

    /**
     * Expected cash and the difference are always recalculated on the server from the stored tickets,
     * also when late tickets of an already closed shift arrive.
     *
     * @param  list<string>  $sessionIds
     */
    private function reconcileClosedSessions(array $sessionIds): void
    {
        if ($sessionIds === []) {
            return;
        }

        ShiftSession::query()
            ->whereKey(array_unique($sessionIds))
            ->where('status', ShiftStatus::Closed->value)
            ->get()
            ->each(function (ShiftSession $session): void {
                $summary = $this->shifts->summary($session);
                $expected = (float) $session->opening_cash + $summary['revenue'];

                $session->update([
                    'expected_cash' => $expected,
                    'cash_difference' => (float) $session->declared_cash - $expected,
                    'transaction_count' => $summary['vehicles'],
                    'synced_at' => now(),
                ]);
            });
    }

    /**
     * Active categories with the rate in force now and every upcoming rate change,
     * so the phone charges the right amount even after a scheduled change while offline.
     *
     * @return list<array<string, mixed>>
     */
    private function categories(): array
    {
        $categories = VehicleCategory::query()
            ->select(['vehicle_categories.id', 'code', 'name_bn', 'name_en', 'icon', 'color', 'is_exempt', 'sort_order'])
            ->active()
            ->ordered()
            ->withCurrentAmount()
            ->get();

        /** @var Collection<int, Collection<int, TollRate>> $upcoming */
        $upcoming = TollRate::query()
            ->whereIn('vehicle_category_id', $categories->modelKeys())
            ->where('effective_from', '>', now())
            ->orderBy('effective_from')
            ->get(['vehicle_category_id', 'amount', 'effective_from'])
            ->groupBy('vehicle_category_id');

        return $categories->map(fn (VehicleCategory $category): array => [
            'id' => $category->id,
            'code' => $category->code,
            'name_bn' => $category->name_bn,
            'name_en' => $category->name_en,
            'icon' => $category->icon,
            'color' => $category->color,
            'is_exempt' => $category->is_exempt,
            'rates' => [
                ['amount' => $category->is_exempt ? '0.00' : number_format((float) $category->current_amount, 2, '.', ''), 'from' => null],
                ...$upcoming->get($category->id, collect())->map(fn (TollRate $rate): array => [
                    'amount' => $category->is_exempt ? '0.00' : $rate->amount,
                    'from' => $rate->effective_from->toDateTimeString(),
                ])->all(),
            ],
        ])->all();
    }

    /** Highest ticket number this phone has already pushed for the given day (ymd). */
    private function lastSequence(MobileDevice $device, string $day): int
    {
        $last = TollTransaction::query()
            ->where('mobile_device_id', $device->id)
            ->where('ticket_no', 'like', $device->ticketPrefix().'-'.$day.'-%')
            ->max('ticket_no');

        return $last ? (int) substr($last, -6) : 0;
    }

    /**
     * @param  array<string, mixed>  $new
     * @return array{event: AuditEvent, subject: Model, new: array<string, mixed>, actor: User, at: CarbonInterface}
     */
    private function auditEntry(AuditEvent $event, Model $subject, User $actor, CarbonInterface $at, array $new): array
    {
        return ['event' => $event, 'subject' => $subject, 'new' => $new, 'actor' => $actor, 'at' => $at];
    }
}
