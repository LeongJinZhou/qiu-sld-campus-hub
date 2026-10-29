<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Event;
use Illuminate\Database\Seeder;

/**
 * Redraws the generated demo logos and posters for clubs and events that already exist, e.g. after the public
 * storage folder was emptied. Changes no database rows.  php artisan db:seed --class=RestoreDemoMediaSeeder
 */
class RestoreDemoMediaSeeder extends Seeder
{
    public function run(): void
    {
        $glyphs = array_column(DemoClubsSeeder::CLUBS, 5, 0);
        foreach (Club::where('logo_path', 'like', 'club-logos/demo-%')->get() as $club) {
            DemoMedia::logo($club->name, $this->index($club->logo_path), $glyphs[$club->name] ?? '★');
        }
        foreach (Event::with('club')->where('poster_path', 'like', 'posters/demo-%')->get() as $event) {
            $n = $this->index($event->poster_path);
            DemoMedia::poster($event->name, $event->club->name, $event->starts_at->format('D, j M Y · g:i A'), (string) $event->venue, "{$n} CCA", $n);
        }
    }

    private function index(string $path): int
    {
        return (int) preg_replace('/\D/', '', basename($path));
    }
}
