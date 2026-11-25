<?php

namespace VentureDrake\LaravelCrm\Http\Requests\Api\V2;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FailLinkedinTaskRequest extends FormRequest
{
    public const ERROR_CODES = [
        'already_connected',
        'pending_invite',
        'email_required',
        'profile_unavailable',
        'cannot_message',
        'rate_limited',
        'captcha_detected',
        'auth_expired',
        'transient_dom_error',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lease_token' => ['required', 'string', 'max:255'],
            'error_code' => ['required', 'string', Rule::in(self::ERROR_CODES)],
            'error_message' => ['nullable', 'string'],
        ];
    }
}
