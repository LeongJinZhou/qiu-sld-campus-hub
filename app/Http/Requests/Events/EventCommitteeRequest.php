<?php

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;

class EventCommitteeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('event'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'student_number' => ['required', 'string', 'exists:students,student_number'],
            'position' => ['required', 'string', 'max:100'],
            'responsibility' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['student_number.exists' => 'No student with that ID in the synced camsys records.'];
    }
}
