<?php

namespace Database\Factories;

use App\Enums\PlateSource;
use App\Enums\TransactionStatus;
use App\Models\ShiftSession;
use App\Models\TollTransaction;
use App\Models\VehicleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TollTransaction>
 */
class TollTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_no' => 'T-'.Str::upper(Str::random(10)),
            'shift_session_id' => ShiftSession::factory(),
            'booth_id' => fn (array $attributes) => ShiftSession::query()->find($attributes['shift_session_id'])->booth_id,
            'user_id' => fn (array $attributes) => ShiftSession::query()->find($attributes['shift_session_id'])->user_id,
            'vehicle_category_id' => VehicleCategory::factory(),
            'amount' => 100,
            'status' => TransactionStatus::Paid,
            'plate_source' => PlateSource::None,
            'print_count' => 1,
            'issued_at' => now(),
        ];
    }

    public function exempt(): static
    {
        return $this->state(['status' => TransactionStatus::Exempt, 'amount' => 0, 'exempt_reason' => 'Ambulance']);
    }

    public function voided(): static
    {
        return $this->state(['status' => TransactionStatus::Voided, 'voided_at' => now(), 'void_reason' => 'Mistake']);
    }
}
