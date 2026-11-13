<?php

namespace App\Http\Requests;

use App\Rules\BangladeshMobileNumber;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class MobileLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', new BangladeshMobileNumber],
            'password' => ['required', 'digits_between:4,5'],
            'device_uuid' => ['required', 'uuid'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array{phone: ?string, password: string} */
    public function credentials(): array
    {
        return [
            'phone' => PhoneNumber::normalize($this->validated('phone')),
            'password' => $this->validated('password'),
        ];
    }
}
