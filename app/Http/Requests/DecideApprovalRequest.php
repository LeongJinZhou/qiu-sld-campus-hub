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
            'comment' => ['nullable', 'required_if:decision,reject', 'required_without:sections', 'exclude_if:decision,approve', 'string', 'max:2000'],
            // Returning a proposal: the parts to fix, each with what is wrong.
            'sections' => ['nullable', 'array', 'max:20', 'prohibited_unless:decision,return'],
            'sections.*.key' => ['required', 'string', 'max:40', 'distinct'],
            'sections.*.note' => ['required', 'string', 'max:500'],
            'signature' => ['nullable', 'required_if:decision,approve', 'string'],
            'signature_strokes' => ['nullable', 'required_if:decision,approve', 'string', 'max:307200'],
        ];
    }
}
