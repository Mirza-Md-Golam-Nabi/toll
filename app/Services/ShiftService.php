<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\ShiftStatus;
use App\Exceptions\TollException;
use App\Models\Booth;
use App\Models\Shift;
use App\Models\ShiftSession;
use App\Models\TollTransaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Shift lifecycle: open (with opening cash) -> collect -> close (cash declaration & reconciliation).
 */
class ShiftService
{
    public function __construct(private AuditLogger $audit) {}

    public function currentSession(User $user): ?ShiftSession
    {
        return ShiftSession::query()
            ->open()
            ->where('user_id', $user->id)
            ->with(['shift:id,name,start_time,end_time', 'booth:id,code,name'])
            ->latest('opened_at')
            ->first();
    }

    /** The shift whose time window contains the given moment. */
    public function detectShift(?CarbonInterface $at = null): ?Shift
    {
        $at ??= now();

        return Shift::query()->active()->orderBy('sort_order')->get()
            ->first(fn (Shift $shift) => $shift->covers($at));
    }

    public function boothFor(User $user): Booth
    {
        return $user->booth ?? Booth::current() ?? throw TollException::because('No booth is assigned to this user.');
    }

    public function open(User $user, Shift $shift, float|string $openingCash = 0): ShiftSession
    {
        $booth = $this->boothFor($user);

        return DB::transaction(function () use ($user, $shift, $openingCash, $booth): ShiftSession {
            $busy = ShiftSession::query()->open()->where('booth_id', $booth->id)->lockForUpdate()->exists();

            if ($busy) {
                throw TollException::because('Another shift is already open on this booth. Close it first.');
            }

            $session = ShiftSession::query()->create([
                'booth_id' => $booth->id,
                'shift_id' => $shift->id,
                'user_id' => $user->id,
                'status' => ShiftStatus::Open,
                'opened_at' => now(),
                'opening_cash' => $openingCash ?: 0,
            ]);

            $this->audit->log(AuditEvent::ShiftOpened, $session, new: [
                'shift' => $shift->name,
                'opening_cash' => $session->opening_cash,
            ], actor: $user);

            return $session;
        });
    }

    /** Totals for one session in a single aggregate query. */
    public function summary(ShiftSession $session): array
    {
        $row = TollTransaction::query()
            ->where('shift_session_id', $session->id)
            ->selectRaw(TransactionAggregates::columns())
            ->toBase()
            ->first();

        return TransactionAggregates::normalize($row);
    }

    public function close(ShiftSession $session, float|string $declaredCash, ?string $notes = null, ?User $actor = null): ShiftSession
    {
        if (! $session->isOpen()) {
            throw TollException::because('This shift is already closed.');
        }

        return DB::transaction(function () use ($session, $declaredCash, $notes, $actor): ShiftSession {
            $summary = $this->summary($session);
            $expected = (float) $session->opening_cash + $summary['revenue'];

            $session->update([
                'status' => ShiftStatus::Closed,
                'closed_at' => now(),
                'expected_cash' => $expected,
                'declared_cash' => $declaredCash,
                'cash_difference' => (float) $declaredCash - $expected,
                'transaction_count' => $summary['vehicles'],
                'notes' => $notes,
            ]);

            $this->audit->log(AuditEvent::ShiftClosed, $session, new: [
                'expected_cash' => $session->expected_cash,
                'declared_cash' => $session->declared_cash,
                'cash_difference' => $session->cash_difference,
                'vehicles' => $summary['vehicles'],
            ], reason: $notes, actor: $actor);

            return $session;
        });
    }
}
