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
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'remove_cover' => ['boolean'],
            'hidden_sections' => ['array'],
            'hidden_sections.*' => ['string', Rule::in(User::HIDEABLE_SECTIONS)],
            // Lets alumni keep signing in after their QIU account closes, so it must be a non-QIU address.
            'personal_email' => ['nullable', 'email', 'max:255', 'not_regex:/@qiu\.edu\.my$/i', function (string $attribute, mixed $value, \Closure $fail): void {
                // One account per personal email, compared case-insensitively.
                if (is_string($value) && User::whereRaw('lower(personal_email) = ?', [mb_strtolower(trim($value))])->whereKeyNot($this->user()?->id)->exists()) {
                    $fail('This email is already linked to another account.');
                }
            }],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['personal_email.not_regex' => 'Use a personal email, not your QIU email.'];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('personal_email'))) {
            $this->merge(['personal_email' => mb_strtolower(trim($this->input('personal_email'))) ?: null]);
        }
    }
}
