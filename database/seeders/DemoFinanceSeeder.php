<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\HandoverStatus;
use App\Enums\PaymentStatus;
use App\Models\CashHandover;
use App\Models\Club;
use App\Models\Event;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\Ledger;
use App\Services\Membership\MembershipService;
use App\Services\Time\SessionResolver;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * GPS Club's demo finance, written through the real flows so the finance page shows a full year: opening balance,
 * membership fees paid online (with receipts) and in cash (with the handover photo), and Beach Cleanup money.
 */
class DemoFinanceSeeder extends Seeder
{
    public function run(Ledger $ledger, MembershipService $memberships, SessionResolver $sessions): void
    {
        $club = Club::where('name', 'GPS Club')->first();
        if (! $club || $club->ledger()->exists()) {
            return;
        }
        $session = $sessions->current();
        $treasurer = $club->roleAssignments()->where('session_id', $session->id)->where('position', 'Treasurer')->first()?->user;
        $sld = User::where('email', 'sld.officer.demo@qiu.edu.my')->first();
        $ledger->setOpeningBalance($club, 50000, $session->starts_on, 'Carried forward from last session', $sld ?? $treasurer);

        $committee = $club->roleAssignments()->pluck('user_id');
        $students = Student::where('status', 'active')->whereNotNull('user_id')->whereNotIn('user_id', $committee)->orderBy('student_number')->get()->map->user->take(3)->values();
        foreach ($students as $i => $student) {
            $membership = $memberships->join($club, $student, true);
            $cash = $i === 2;
            $payment = $membership->payments()->create([
                'club_id' => $club->id, 'user_id' => $student->id, 'method' => $cash ? 'cash' : 'online',
                'amount_sen' => $memberships->fee($club)->amount_sen, 'status' => PaymentStatus::Confirmed, 'verified_at' => now(),
                'collected_by' => $cash ? $treasurer?->id : null,
                'receipt_path' => $cash ? null : Storage::disk('local')->putFileAs("payment-receipts/{$club->id}", $this->picture("Bank transfer receipt\nRM 10.00 to GPS Club (QIU)\nRef DEMO{$i}"), "demo-{$i}.png"),
            ]);
            if ($cash) {
                CashHandover::create([
                    'payment_id' => $payment->id, 'student_id' => $student->id, 'committee_user_id' => $treasurer->id,
                    'status' => HandoverStatus::Completed, 'cash_check' => 'committee', 'expires_at' => now()->addDays(7),
                    'photo_path' => Storage::disk('local')->putFileAs('cash-handovers/demo', $this->picture("Cash handover photo\nRM 10.00 next to {$student->name}'s QR"), "handover-{$payment->id}.png"),
                    'accepted_at' => now(), 'student_ready_at' => now(), 'committee_ready_at' => now(), 'photo_taken_at' => now(),
                    'student_done_at' => now(), 'committee_done_at' => now(), 'completed_at' => now(),
                ]);
            }
            $memberships->activate($payment, $cash ? "Membership fee (cash) – {$student->name}" : null);
        }

        $event = Event::where('club_id', $club->id)->where('status', DocumentStatus::Approved)->first();
        if ($event && $treasurer) {
            $ledger->manual($club, today(), 'Sponsorship from Kedai Runcit Ipoh', 30000, 0, null, $treasurer, eventId: $event->id, method: 'transfer');
            $ledger->manual($club, today(), 'Gloves and bin bags', 0, 18500, $this->picture("Receipt\nGloves x40, bin bags x20\nRM 185.00"), $treasurer, eventId: $event->id, method: 'cash');
        }
    }

    /** A plain generated picture standing in for a real receipt or photo in the demo. */
    private function picture(string $text): UploadedFile
    {
        $image = imagecreatetruecolor(640, 420);
        imagefill($image, 0, 0, imagecolorallocate($image, 246, 246, 249));
        $ink = imagecolorallocate($image, 26, 26, 31);
        foreach (explode("\n", $text) as $i => $line) {
            imagestring($image, 5, 40, 60 + $i * 36, $line, $ink);
        }
        $path = tempnam(sys_get_temp_dir(), 'demo').'.png';
        imagepng($image, $path);

        return new UploadedFile($path, basename($path), 'image/png', null, true);
    }
}
