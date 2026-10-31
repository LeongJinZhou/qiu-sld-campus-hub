<?php

namespace App\Services\Finance;

use App\Models\Club;
use App\Models\Event;
use App\Models\MembershipDrive;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Traceable transaction IDs. A student's payment or registration reads
 * CLUB_PURPOSE_MATRIC_NAME_YYYYMMDD_HHMM_TRANSACTION, where PURPOSE is the event, MEMBERSHIPDRIVE-<drive>
 * or NORMALREGISTER, and TRANSACTION is the bank reference once the receipt is read (T0001… until then, and
 * for cash). Club entries with no student (withdrawals, general) keep CLUB_KIND_0001.
 */
class OrderIds
{
    public const SEPARATOR = '_';

    public function forStudent(Club $club, ?Event $event, User $user, CarbonInterface $at, ?string $reference = null): string
    {
        $user->loadMissing('student');
        $parts = [
            $this->code($club->name, 30),
            $event ? $this->code($event->name, 30) : $this->membershipPurpose($club, $at),
            $this->code($user->student?->student_number ?? 'STAFF', 20),
            $this->code($user->name, 30),
            $at->copy()->timezone(config('app.timezone'))->format('Ymd'),
            $at->copy()->timezone(config('app.timezone'))->format('Hi'),
            $this->reference($reference) ?: $this->number($this->code($club->name, 30).'_TXN', 'T'),
        ];

        return implode(self::SEPARATOR, $parts);
    }

    /** Swaps the transaction part (the last one) for the bank reference read off the receipt. */
    public function withReference(string $orderId, string $reference): string
    {
        $parts = explode(self::SEPARATOR, $orderId);
        $code = $this->reference($reference);
        if (count($parts) < 7 || $code === '') {
            return $orderId;
        }
        $parts[count($parts) - 1] = $code;

        return implode(self::SEPARATOR, $parts);
    }

    public function forEvent(Event $event): string
    {
        return $this->number($this->code($event->club->name, 30).'_'.$this->code($event->name, 30));
    }

    public function forClub(Club $club, string $kind): string
    {
        return $this->number($this->code($club->name, 30).'_'.strtoupper($kind));
    }

    /** Bank references keep letters and digits only, upper case: "mbb 123-456" → MBB123456. */
    public function reference(?string $reference): string
    {
        return substr(strtoupper((string) preg_replace('/[^a-z0-9]/i', '', (string) $reference)), 0, 40);
    }

    private function membershipPurpose(Club $club, CarbonInterface $at): string
    {
        $drive = MembershipDrive::where(fn ($q) => $q->whereNull('club_id')->orWhere('club_id', $club->id))
            ->where('starts_at', '<=', $at)->where('ends_at', '>=', $at)->orderByDesc('club_id')->first();

        return $drive ? 'MEMBERSHIPDRIVE-'.$this->code($drive->title, 30) : 'NORMALREGISTER';
    }

    private function code(string $name, int $limit): string
    {
        return strtoupper(Str::slug(Str::limit(Str::ascii($name), $limit, ''))) ?: 'NA';
    }

    private function number(string $prefix, string $lead = ''): string
    {
        $number = DB::transaction(function () use ($prefix): int {
            DB::table('order_counters')->insertOrIgnore(['prefix' => $prefix, 'last' => 0]);
            DB::table('order_counters')->where('prefix', $prefix)->increment('last');

            return (int) DB::table('order_counters')->where('prefix', $prefix)->value('last');
        });

        return ($lead === '' ? $prefix.'_' : $lead).str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
