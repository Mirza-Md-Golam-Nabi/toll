<?php

namespace Database\Seeders;

use App\Enums\PlateSource;
use App\Enums\ShiftStatus;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Models\Booth;
use App\Models\Shift;
use App\Models\TicketSequence;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Services\TransactionAggregates;
use App\Services\VehicleRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demo traffic for the last N days (bulk inserts — fast even for tens of thousands of rows).
 * Run: php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    private const DAYS = 30;

    /** Relative traffic weight per category code. */
    private const WEIGHTS = [
        'CAR' => 30, 'MICRO' => 20, '4WD' => 12, 'MINIBUS' => 8, 'BUS' => 8, 'TRUCK_S' => 7,
        'TRUCK_M' => 5, 'TRUCK_L' => 5, 'AGRI' => 3, 'TRAILER' => 1, 'EXEMPT' => 1,
    ];

    private const DISTRICTS = ['DHAKA METRO', 'JASHORE', 'KHULNA', 'MAGURA', 'NARAIL', 'JHENAIDAH', 'GOPALGANJ'];

    public function run(VehicleRegistry $vehicles): void
    {
        $booth = Booth::query()->where('code', config('toll.booth_code'))->firstOrFail();
        $operator = User::query()->where('role', UserRole::BoothOperator)->firstOrFail();
        $shifts = Shift::query()->orderBy('sort_order')->get();
        $categories = VehicleCategory::query()->select('vehicle_categories.*')->withCurrentAmount()->get()->keyBy('code');
        $pool = collect(self::WEIGHTS)->flatMap(fn (int $weight, string $code) => array_fill(0, $weight, $code))->all();

        for ($day = self::DAYS; $day >= 1; $day--) {
            $date = CarbonImmutable::today()->subDays($day);

            // One transaction per day: a failed run never leaves half a day behind.
            DB::transaction(function () use ($booth, $operator, $shifts, $date, $categories, $pool, $vehicles): void {
                $sequence = $this->lastTicketNumber($booth, $date);

                foreach ($shifts as $shift) {
                    $sequence = $this->seedShift($booth, $operator, $shift, $date, $categories, $pool, $sequence, $vehicles);
                }

                TicketSequence::query()->upsert(
                    [['booth_id' => $booth->id, 'sequence_date' => $date->toDateString(), 'last_number' => $sequence]],
                    ['booth_id', 'sequence_date'],
                    ['last_number'],
                );
            });
        }
    }

    /** Highest ticket number already issued that day, so re-running the seeder appends instead of colliding. */
    private function lastTicketNumber(Booth $booth, CarbonImmutable $date): int
    {
        $lastTicketNo = DB::table('toll_transactions')
            ->where('ticket_no', 'like', $this->ticketPrefix($booth, $date).'%')
            ->max('ticket_no');

        return $lastTicketNo ? (int) Str::afterLast($lastTicketNo, '-') : 0;
    }

    private function ticketPrefix(Booth $booth, CarbonImmutable $date): string
    {
        return sprintf('%s-%s-', $booth->code, $date->format('ymd'));
    }

    private function seedShift(Booth $booth, User $operator, Shift $shift, CarbonImmutable $date, $categories, array $pool, int $sequence, VehicleRegistry $vehicles): int
    {
        $openedAt = $date->setTimeFromTimeString($shift->start_time);
        $sessionId = (string) Str::uuid();
        $rows = [];

        for ($i = 0, $count = random_int(90, 220); $i < $count; $i++) {
            $category = $categories[$pool[array_rand($pool)]];
            $issuedAt = $openedAt->addSeconds(random_int(0, 8 * 3600 - 1));
            $plate = random_int(1, 100) <= 40 ? $this->randomPlate() : null;
            $status = $category->is_exempt
                ? TransactionStatus::Exempt
                : (random_int(1, 300) === 1 ? TransactionStatus::Voided : TransactionStatus::Paid);

            $rows[] = [
                'id' => (string) Str::uuid(),
                'ticket_no' => $this->ticketPrefix($booth, $date).sprintf('%06d', ++$sequence),
                'booth_id' => $booth->id,
                'shift_session_id' => $sessionId,
                'user_id' => $operator->id,
                'vehicle_category_id' => $category->id,
                'amount' => $category->is_exempt ? 0 : $category->current_amount,
                'status' => $status->value,
                'exempt_reason' => $category->is_exempt ? 'Ambulance' : null,
                'void_reason' => $status === TransactionStatus::Voided ? 'Wrong category' : null,
                'voided_at' => $status === TransactionStatus::Voided ? $issuedAt : null,
                'plate_number' => $plate,
                'plate_source' => $plate ? PlateSource::Manual->value : PlateSource::None->value,
                'print_count' => 1,
                'issued_at' => $issuedAt,
                'created_at' => $issuedAt,
                'updated_at' => $issuedAt,
            ];
        }

        DB::table('shift_sessions')->insert([
            'id' => $sessionId,
            'booth_id' => $booth->id,
            'shift_id' => $shift->id,
            'user_id' => $operator->id,
            'status' => ShiftStatus::Closed->value,
            'opened_at' => $openedAt,
            'closed_at' => $openedAt->addHours(8),
            'opening_cash' => 500,
            'created_at' => $openedAt,
            'updated_at' => $openedAt,
        ]);

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('toll_transactions')->insert($chunk);
        }

        $this->closeSession($sessionId);
        $vehicles->recordPasses($rows);

        return $sequence;
    }

    /** Fill the reconciliation columns from one aggregate query, with a small random cash difference. */
    private function closeSession(string $sessionId): void
    {
        $totals = TransactionAggregates::normalize(
            DB::table('toll_transactions')->where('shift_session_id', $sessionId)->selectRaw(TransactionAggregates::columns())->first()
        );
        $expected = 500 + $totals['revenue'];
        $declared = $expected + [0, 0, 0, -10, 20, -50][random_int(0, 5)];

        DB::table('shift_sessions')->where('id', $sessionId)->update([
            'expected_cash' => $expected,
            'declared_cash' => $declared,
            'cash_difference' => $declared - $expected,
            'transaction_count' => $totals['vehicles'],
        ]);
    }

    private function randomPlate(): string
    {
        return sprintf('%s-%s %02d-%04d',
            self::DISTRICTS[array_rand(self::DISTRICTS)],
            ['KA', 'KHA', 'GA', 'GHA', 'CHA', 'THA', 'DA', 'BA', 'MA'][random_int(0, 8)],
            random_int(11, 99),
            random_int(1000, 9999),
        );
    }
}
