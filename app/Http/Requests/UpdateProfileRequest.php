<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_photo' => ['boolean'],
            'hidden_sections' => ['array'],
            'hidden_sections.*' => ['string', Rule::in(User::HIDEABLE_SECTIONS)],
            // Lets alumni keep signing in after their QIU account closes, so it must be a non-QIU address.
            'personal_email' => ['nullable', 'email', 'max:255', 'not_regex:/@qiu\.edu\.my$/i'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['personal_email.not_regex' => 'Use a personal email, not your QIU email.'];
    }
}
