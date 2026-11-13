<?php

namespace App\Http\Requests;

use App\Enums\ShiftStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A batch of shifts and tickets created offline on a phone.
 * Owner, booth, device and ticket status are never taken from the payload.
 */
class MobilePushRequest extends FormRequest
{
    private const DATE_FORMAT = 'date_format:Y-m-d H:i:s';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sessions' => ['present', 'array', 'max:50'],
            'sessions.*.id' => ['required', 'uuid', 'distinct'],
            'sessions.*.shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'sessions.*.status' => ['required', Rule::enum(ShiftStatus::class)],
            'sessions.*.opened_at' => ['required', self::DATE_FORMAT],
            'sessions.*.opening_cash' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'sessions.*.closed_at' => ['nullable', 'required_if:sessions.*.status,closed', self::DATE_FORMAT],
            'sessions.*.declared_cash' => ['nullable', 'required_if:sessions.*.status,closed', 'numeric', 'min:0', 'max:9999999'],
            'sessions.*.notes' => ['nullable', 'string', 'max:1000'],

            'tickets' => ['present', 'array', 'max:500'],
            'tickets.*.id' => ['required', 'uuid', 'distinct'],
            'tickets.*.ticket_no' => ['required', 'string', 'max:30', 'distinct'],
            'tickets.*.shift_session_id' => ['required', 'uuid'],
            'tickets.*.vehicle_category_id' => ['required', 'integer', 'exists:vehicle_categories,id'],
            'tickets.*.amount' => ['required', 'numeric', 'min:0', 'max:99999'],
            'tickets.*.exempt_reason' => ['nullable', 'string', 'max:255'],
            'tickets.*.plate_number' => ['nullable', 'string', 'max:40'],
            'tickets.*.print_count' => ['required', 'integer', 'min:0', 'max:255'],
            'tickets.*.issued_at' => ['required', self::DATE_FORMAT],
        ];
    }
}
