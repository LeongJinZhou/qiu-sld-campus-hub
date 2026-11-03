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
            // Any active staff member, even one who has never signed in (their account is created on assignment).
            'staff_id' => ['nullable', 'required_without:user_id', 'integer', 'exists:staff,id,active,1'],
            'user_id' => ['nullable', 'integer', 'exists:staff,user_id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['staff_id.required_without' => 'Choose a staff member.', 'staff_id.exists' => 'That staff member is not active.'];
    }
}
