<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\Direction;
use App\Enums\PlateSource;
use App\Enums\TransactionStatus;
use App\Exceptions\TollException;
use App\Models\TollTransaction;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Support\PlateNumber;
use Illuminate\Support\Facades\DB;

/**
 * Issuing tickets and every manual override on them (void, category correction, reprint).
 */
class TollTransactionService
{
    public function __construct(
        private RateService $rates,
        private ShiftService $shifts,
        private TicketNumberGenerator $tickets,
        private VehicleRegistry $vehicles,
        private AuditLogger $audit,
    ) {}

    public function issue(
        User $operator,
        VehicleCategory $category,
        ?string $plateNumber = null,
        PlateSource $plateSource = PlateSource::Manual,
        ?Direction $direction = null,
        ?string $exemptReason = null,
        ?float $anprConfidence = null,
        ?string $plateImage = null,
    ): TollTransaction {
        $session = $this->shifts->currentSession($operator)
            ?? throw TollException::because('Open a shift before issuing tickets.');

        if (! $category->is_active) {
            throw TollException::because('This vehicle category is inactive.');
        }

        if ($category->is_exempt && blank($exemptReason)) {
            throw TollException::because('A reason is required for exempt vehicles.');
        }

        $plate = PlateNumber::normalize($plateNumber);
        $issuedAt = now();

        return DB::transaction(function () use ($operator, $category, $plate, $plateSource, $direction, $exemptReason, $anprConfidence, $plateImage, $session, $issuedAt): TollTransaction {
            $transaction = TollTransaction::query()->create([
                'ticket_no' => $this->tickets->next($session->booth, $issuedAt),
                'booth_id' => $session->booth_id,
                'shift_session_id' => $session->id,
                'user_id' => $operator->id,
                'vehicle_category_id' => $category->id,
                'amount' => $this->rates->amountFor($category),
                'status' => $category->is_exempt ? TransactionStatus::Exempt : TransactionStatus::Paid,
                'exempt_reason' => $category->is_exempt ? $exemptReason : null,
                'plate_number' => $plate,
                'plate_source' => $plate ? $plateSource : PlateSource::None,
                'plate_image' => $plateImage,
                'anpr_confidence' => $anprConfidence,
                'direction' => $direction,
                'print_count' => 1,
                'issued_at' => $issuedAt,
            ]);

            $this->vehicles->recordPasses([[
                'plate_number' => $plate,
                'vehicle_category_id' => $category->id,
                'issued_at' => $issuedAt,
            ]]);

            $this->audit->log(AuditEvent::TicketIssued, $transaction, new: [
                'ticket_no' => $transaction->ticket_no,
                'category' => $category->code,
                'amount' => $transaction->amount,
                'plate_number' => $plate,
            ], actor: $operator);

            return $transaction->setRelation('category', $category)->setRelation('operator', $operator);
        });
    }

    public function void(TollTransaction $transaction, string $reason, User $actor, User $approver): TollTransaction
    {
        $this->guardNotVoided($transaction);

        return DB::transaction(function () use ($transaction, $reason, $actor, $approver): TollTransaction {
            $old = $transaction->only(['status', 'amount']);

            $transaction->update([
                'status' => TransactionStatus::Voided,
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'approved_by' => $approver->id,
                'void_reason' => $reason,
            ]);

            $this->audit->log(AuditEvent::TicketVoided, $transaction, $old, ['status' => TransactionStatus::Voided->value], $reason, $approver, $actor);

            return $transaction;
        });
    }

    public function correctCategory(TollTransaction $transaction, VehicleCategory $category, string $reason, User $actor, User $approver, ?string $exemptReason = null): TollTransaction
    {
        $this->guardNotVoided($transaction);

        if ($transaction->vehicle_category_id === $category->id) {
            throw TollException::because('The ticket already has this category.');
        }

        return DB::transaction(function () use ($transaction, $category, $reason, $actor, $approver, $exemptReason): TollTransaction {
            $old = ['vehicle_category_id' => $transaction->vehicle_category_id, 'amount' => $transaction->amount, 'status' => $transaction->status->value];

            $transaction->update([
                'vehicle_category_id' => $category->id,
                'amount' => $this->rates->amountFor($category, $transaction->issued_at),
                'status' => $category->is_exempt ? TransactionStatus::Exempt : TransactionStatus::Paid,
                'exempt_reason' => $category->is_exempt ? ($exemptReason ?: $reason) : null,
                'approved_by' => $approver->id,
            ]);

            $this->audit->log(
                AuditEvent::CategoryCorrected,
                $transaction,
                $old,
                $transaction->only(['vehicle_category_id', 'amount']) + ['status' => $transaction->status->value],
                $reason,
                $approver,
                $actor,
            );

            return $transaction;
        });
    }

    public function registerReprint(TollTransaction $transaction, User $actor, ?string $reason = null): TollTransaction
    {
        $this->guardNotVoided($transaction);

        $transaction->increment('print_count');

        $this->audit->log(AuditEvent::TicketReprinted, $transaction, new: ['print_count' => $transaction->print_count], reason: $reason, actor: $actor);

        return $transaction;
    }

    private function guardNotVoided(TollTransaction $transaction): void
    {
        if ($transaction->isVoided()) {
            throw TollException::because('This ticket has already been voided.');
        }
    }
}
