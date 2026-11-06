<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Booth;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('toll:create-admin {--name=} {--email=} {--password=} {--pin=}')]
#[Description('Create (or reset) a Super Admin account for a fresh installation')]
class CreateAdminCommand extends Command
{
    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?? text('Name', required: true),
            'email' => $this->option('email') ?? text('Email', required: true),
            'password' => $this->option('password') ?? password('Password (min 8 characters)', required: true),
            'approval_pin' => $this->option('pin') ?? password('Approval PIN (4-8 digits)', required: true),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
            'approval_pin' => ['required', 'digits_between:4,8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->updateOrCreate(['email' => $data['email']], [
            ...$data,
            'role' => UserRole::SuperAdmin,
            'booth_id' => Booth::current()?->id,
            'is_active' => true,
            'locale' => 'bn',
        ]);

        $this->info("Super Admin [{$user->email}] is ready.");

        return self::SUCCESS;
    }
}
