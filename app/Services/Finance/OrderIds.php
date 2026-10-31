<?php

namespace App\Services\Finance;

use App\Models\Club;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Traceable transaction IDs: CLUB_EVENT_0001 for an event, CLUB_MEMBERSHIP_0001 for membership fees,
 * CLUB_WITHDRAWAL_0001 / CLUB_GENERAL_0001 otherwise. Each event has its own running number.
 */
class OrderIds
{
    public function forEvent(Event $event): string
    {
        return $this->next($this->code($event->club->name).'_'.$this->code($event->name));
    }

    public function forClub(Club $club, string $kind): string
    {
        return $this->next($this->code($club->name).'_'.strtoupper($kind));
    }

    private function code(string $name): string
    {
        return strtoupper(Str::slug(Str::limit($name, 30, '')));
    }

    private function next(string $prefix): string
    {
        $number = DB::transaction(function () use ($prefix): int {
            DB::table('order_counters')->insertOrIgnore(['prefix' => $prefix, 'last' => 0]);
            DB::table('order_counters')->where('prefix', $prefix)->increment('last');

            return (int) DB::table('order_counters')->where('prefix', $prefix)->value('last');
        });

        return $prefix.'_'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
