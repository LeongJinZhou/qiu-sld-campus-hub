<?php

namespace App\Http\Requests\Admin;

use App\Enums\ApproverRole;
use App\Models\Club;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage', Club::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $creating = $this->route('staff') === null;

        return [
            'email' => $creating
                ? ['required', 'email', 'max:255', 'regex:/@qiu\.edu\.my$/i', 'unique:staff,email']
                : ['prohibited'],
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'approver_role' => ['nullable', Rule::enum(ApproverRole::class)],
            'active' => [$creating ? 'prohibited' : 'required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['email.regex' => 'Staff accounts must use a @qiu.edu.my email.'];
    }
}
