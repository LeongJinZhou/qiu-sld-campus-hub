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
            // Approving needs only the signature and the "no problems" tick; returning or rejecting needs the parts
            // that are wrong (proposals) or a comment.
            'comment' => ['exclude_if:decision,approve', 'nullable', 'required_without:sections', 'string', 'max:2000'],
            'no_problems' => ['exclude_unless:decision,approve', 'accepted'],
            'sections' => ['exclude_if:decision,approve', 'nullable', 'array', 'max:20'],
            'accept_exceptions' => ['nullable', 'boolean'],
            'sections.*.key' => ['required', 'string', 'max:40', 'distinct'],
            'sections.*.note' => ['required', 'string', 'max:500'],
            'signature' => ['nullable', 'required_if:decision,approve', 'string'],
            'signature_strokes' => ['nullable', 'required_if:decision,approve', 'string', 'max:307200'],
        ];
    }
}
