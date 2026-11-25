<?php

namespace VentureDrake\LaravelCrm\Http\Requests\Api\V2;

use Illuminate\Foundation\Http\FormRequest;

class SyncLinkedinInboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accepted_slugs' => ['nullable', 'array'],
            'accepted_slugs.*' => ['string', 'max:255'],
            'replies' => ['nullable', 'array'],
            'replies.*.slug' => ['required', 'string', 'max:255'],
            'replies.*.snippet' => ['nullable', 'string'],
            'replies.*.replied_at' => ['nullable', 'date'],
        ];
    }
}
