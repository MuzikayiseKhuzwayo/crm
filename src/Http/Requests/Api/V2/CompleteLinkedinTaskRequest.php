<?php

namespace VentureDrake\LaravelCrm\Http\Requests\Api\V2;

use Illuminate\Foundation\Http\FormRequest;

class CompleteLinkedinTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lease_token' => ['required', 'string', 'max:255'],
            'sent_text' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
