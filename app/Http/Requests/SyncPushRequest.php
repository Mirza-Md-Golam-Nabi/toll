<?php

namespace App\Http\Requests;

use App\Services\Sync\SyncRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'booth_code' => ['required', 'string'],
            'table' => ['required', Rule::in(array_keys(SyncRegistry::PUSH))],
            'records' => ['required', 'array', 'max:1000'],
            'records.*.id' => ['required', 'uuid'],
        ];
    }

    /**
     * Full record rows. `validated()` would keep only the validated `id` key; the columns
     * themselves are whitelisted later by SyncRegistry::clean().
     *
     * @return array<int, array<string, mixed>>
     */
    public function records(): array
    {
        return array_values(array_filter($this->input('records', []), 'is_array'));
    }
}
