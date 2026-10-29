<?php

namespace App\Http\Requests\Admin;

use App\Models\Club;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClubRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage', Club::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $club = $this->route('club');

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('clubs', 'name')->ignore($club)],
            'slug' => ['required', 'string', 'max:150', 'alpha_dash', Rule::unique('clubs', 'slug')->ignore($club)],
            'category_id' => ['nullable', 'exists:club_categories,id'],
            'established_year' => ['nullable', 'integer', 'min:1990', 'max:'.now()->year],
            'bio' => ['nullable', 'string', 'max:500'],
            'instagram_handle' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9._]+$/'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
