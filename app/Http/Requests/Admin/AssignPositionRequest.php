<?php

namespace App\Http\Requests\Admin;

use App\Models\Club;
use Illuminate\Foundation\Http\FormRequest;

class AssignPositionRequest extends FormRequest
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
            'student_number' => ['required', 'string', 'exists:students,student_number'],
            'position' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['student_number.exists' => 'No student with that ID in the synced camsys records.'];
    }
}
