<?php

namespace App\Http\Requests\Admin;

use App\Enums\GlobalRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole(GlobalRole::SuperAdmin->value)
            && $this->route('user')?->isNot($this->user());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'roles' => ['present', 'array'],
            'roles.*' => [Rule::enum(GlobalRole::class)],
        ];
    }
}
