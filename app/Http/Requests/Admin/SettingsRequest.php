<?php

namespace App\Http\Requests\Admin;

use App\Models\Club;
use Illuminate\Foundation\Http\FormRequest;

/** One form per settings section; `section` picks which rules apply. */
class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage', Club::class);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return match ($this->route('section')) {
            'programmes' => [
                'items' => ['present', 'array'],
                'items.*.programme_code' => ['required', 'string', 'max:50', 'distinct'],
                'items.*.abbreviation' => ['required', 'string', 'max:20'],
            ],
            'intakes' => [
                'items' => ['present', 'array'],
                'items.*.intake_number' => ['required', 'integer', 'min:1', 'max:20', 'distinct'],
                'items.*.month' => ['required', 'integer', 'min:1', 'max:12'],
            ],
            'statuses' => [
                'active' => ['required', 'array', 'min:1'],
                'active.*' => ['required', 'string', 'max:50'],
                'alumni' => ['required', 'array'],
                'alumni.*' => ['required', 'string', 'max:50'],
            ],
            'sessions' => [
                'name' => ['required', 'regex:/^\d{4}\/\d{4}$/', 'unique:sessions_academic,name'],
            ],
            default => abort(404),
        };
    }
}
