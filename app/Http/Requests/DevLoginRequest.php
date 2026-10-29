<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DevLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app()->isLocal();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['email' => ['required', 'email']];
    }
}
