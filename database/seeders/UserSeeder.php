<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Booth;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Demo accounts as [name, phone, role, approval PIN]. All log in with User::DEFAULT_PIN.
     *
     * @var list<array{0: string, 1: string, 2: UserRole, 3: ?string}>
     */
    private const USERS = [
        ['Super Admin', '01700000001', UserRole::SuperAdmin, '1234'],
        ['Plaza Manager', '01700000002', UserRole::PlazaManager, null],
        ['Booth Operator', '01700000003', UserRole::BoothOperator, null],
    ];

    /**
     * Seeds the Super Admin everywhere; Plaza Manager and Booth Operator demo accounts
     * are seeded only when APP_ENV=local (needs BoothSeeder first).
     * On a real booth / server create the first admin with: php artisan toll:create-admin
     */
    public function run(): void
    {
        $booth = Booth::query()->where('code', config('toll.booth_code'))->firstOrFail();

        foreach (self::USERS as [$name, $phone, $role, $pin]) {
            if ($role !== UserRole::SuperAdmin && ! app()->isLocal()) {
                continue;
            }

            User::query()->updateOrCreate(['phone' => $phone], [
                'name' => $name,
                'password' => User::DEFAULT_PIN,
                'role' => $role,
                'booth_id' => $booth->id,
                'approval_pin' => $pin,
                'is_active' => true,
                'locale' => 'bn',
            ]);
        }
    }
}
