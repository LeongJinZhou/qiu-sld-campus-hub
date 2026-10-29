<?php

namespace App\Services\Events;

/** SLD poster guidelines (CLAUDE.md §6 Events). Every item must be confirmed before a poster is accepted. */
class PosterRules
{
    public const CHECKLIST = [
        'logos' => 'Student Activity logo and club logo (zone A, top corners)',
        'organiser' => 'Organiser',
        'title' => 'Event title (zone B)',
        'tagline' => 'Tagline',
        'date_day' => 'Date and day',
        'time' => 'Time from – to',
        'venue' => 'Venue or platform',
        'registration' => 'Registration method',
        'cca' => 'CCA points',
        'contact' => 'Contact name and phone (zone E)',
        'real_photos' => 'Real QIU photos only — no human or AI-generated people',
    ];

    /** @param array<string, bool> $ticked @return list<string> labels still missing */
    public function missing(array $ticked): array
    {
        return array_values(array_map(
            fn (string $key) => self::CHECKLIST[$key],
            array_filter(array_keys(self::CHECKLIST), fn (string $key) => empty($ticked[$key])),
        ));
    }
}
