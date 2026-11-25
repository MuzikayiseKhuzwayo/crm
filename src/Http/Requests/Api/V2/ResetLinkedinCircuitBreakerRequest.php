<?php

namespace VentureDrake\LaravelCrm\Http\Requests\Api\V2;

use Illuminate\Foundation\Http\FormRequest;

class ResetLinkedinCircuitBreakerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resume' => ['nullable', 'boolean'],
            'campaign_id' => ['nullable', 'string', 'uuid'],
        ];
    }
}
