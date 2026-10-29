<?php

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;

class IndemnityRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $phone = ['regex:/^[0-9+\-\s()]{7,20}$/'];

        return [
            'emergency_name' => ['required', 'string', 'max:150'],
            'emergency_mobile_phone' => ['required', ...$phone],
            'emergency_work_phone' => ['nullable', ...$phone],
            'emergency_relationship' => ['required', 'string', 'max:50'],
            'alternate_name' => ['nullable', 'string', 'max:150'],
            'alternate_phone' => ['nullable', 'required_with:alternate_name', ...$phone],
            'address' => ['required', 'string', 'max:500'],
            'consent' => ['accepted'],
            'signature' => ['required', 'string'],
        ];
    }

    /** @return array<string, string|null> */
    public function fields(): array
    {
        return collect($this->validated())->except(['consent', 'signature'])->all();
    }
}
