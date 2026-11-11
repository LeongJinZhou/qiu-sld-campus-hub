<?php

namespace App\Services\Moderation;

use App\Support\ContentFilter;

/**
 * One check for everything people type that others read or the assistant receives (posts, comments, assistant
 * questions): first the word filter (English, Malay, Manglish and dialect slang, however it is disguised), then
 * the toxic-comment model.
 */
class ContentModeration
{
    public function __construct(private readonly ToxicityClassifier $classifier) {}

    /** Null when the text is fine; otherwise the message to show. */
    public function problem(?string $text, string $what = 'This'): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        if (ContentFilter::offences($text) !== []) {
            return "{$what} contains language that is not allowed here. Please rephrase it respectfully.";
        }
        if ($this->classifier->isToxic($text)) {
            return "{$what} reads as abusive or insulting. Please rephrase it respectfully.";
        }

        return null;
    }
}
