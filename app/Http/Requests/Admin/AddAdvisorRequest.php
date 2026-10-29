<?php

namespace App\Http\Requests\Admin;

use App\Models\Club;
use Illuminate\Foundation\Http\FormRequest;

class AddAdvisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage', Club::class);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'session' => ['required', 'exists:sessions_academic,name'],
            'user_id' => ['required', 'exists:staff,user_id'],
        ];
    }
}
