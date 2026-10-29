<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideApprovalRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'return', 'reject'])],
            'comment' => ['nullable', 'required_unless:decision,approve', 'string', 'max:2000'],
            'signature' => ['nullable', 'required_if:decision,approve', 'string'],
        ];
    }
}
