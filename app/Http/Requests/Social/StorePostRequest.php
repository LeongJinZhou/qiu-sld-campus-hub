<?php

namespace App\Http\Requests\Social;

use App\Models\Post;
use App\Services\Social\PostingRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePostRequest extends FormRequest
{
    public const IMAGE_RULES = ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('publish', $this->route('club'));
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([Post::TYPE_GENERAL, Post::TYPE_RECAP])],
            'caption' => ['nullable', 'string', 'max:2200'],
            'event_id' => ['nullable', 'required_if:type,recap', Rule::exists('events', 'id')->where('club_id', $this->route('club')->id)],
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => self::IMAGE_RULES,
        ];
    }

    /** Spam and bad-word checks once the basic fields are valid. @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $eventId = $this->integer('event_id') ?: null;
            foreach (app(PostingRules::class)->postProblems($this->route('club'), $eventId, $this->input('caption')) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        }];
    }
}
