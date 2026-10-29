<?php

namespace App\Services;

use App\Models\Club;
use App\Models\SldRequest;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\Meetings\Meetings;
use DomainException;
use Illuminate\Support\Facades\Notification;

/** SLD asks clubs for something; the club replies within 7 working days. */
class SldRequests
{
    public function __construct(private readonly Meetings $meetings, private readonly AuditLogger $audit) {}

    /** @param list<int> $clubIds */
    public function send(User $sld, array $clubIds, string $subject, string $body): int
    {
        $due = today()->addWeekdays(SldRequest::REPLY_WORKING_DAYS);
        foreach (Club::whereIn('id', $clubIds)->get() as $club) {
            $request = SldRequest::create(['club_id' => $club->id, 'created_by' => $sld->id, 'subject' => $subject, 'body' => $body, 'due_on' => $due]);
            $this->audit->log('sld_request.sent', $request);
            Notification::send($this->meetings->committee($club), new DocumentNotification(
                "SLD request: {$subject}",
                "Please reply by {$due->format('D j M Y')} (7 working days).",
                route('clubs.show', $club),
            ));
        }

        return count($clubIds);
    }

    public function reply(SldRequest $request, User $user, string $reply): void
    {
        if ($request->replied_at) {
            throw new DomainException('This request has already been answered.');
        }
        $request->update(['reply' => $reply, 'replied_by' => $user->id, 'replied_at' => now()]);
        $request->club->forceFill(['last_activity_at' => now()])->save();
        $this->audit->log('sld_request.replied', $request);
        Notification::send(User::role(['admin', 'superadmin'])->get(), new DocumentNotification(
            "{$request->club->name} replied: {$request->subject}",
            mb_substr($reply, 0, 200),
            route('admin.requests.index'),
        ));
    }
}
