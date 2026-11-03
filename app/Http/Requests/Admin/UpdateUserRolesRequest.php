<?php

namespace App\Http\Requests\Admin;

use App\Enums\GlobalRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->route('user');

        // Never your own roles; an SLD Officer never touches a Super Admin.
        return $actor?->isSldAdmin() && $target?->isNot($actor)
            && ($actor->hasRole(GlobalRole::SuperAdmin->value) || ! $target->hasRole(GlobalRole::SuperAdmin->value));
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
