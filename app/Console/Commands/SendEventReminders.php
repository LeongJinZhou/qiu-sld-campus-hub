<?php

namespace App\Console\Commands;

use App\Enums\ScopeType;
use App\Models\EventDocumentItem;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\DocumentNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('events:send-reminders')]
#[Description('Remind event committees about post-event documents due within 2 days or overdue')]
class SendEventReminders extends Command
{
    public function handle(): int
    {
        $items = EventDocumentItem::with('event')
            ->whereNull('submitted_at')
            ->whereDate('due_on', '<=', now()->addDays(2))
            ->where(fn ($q) => $q->whereNull('reminded_at')->orWhereDate('reminded_at', '<', today()))
            ->get();

        foreach ($items->groupBy('event_id') as $eventItems) {
            $event = $eventItems->first()->event;
            $people = User::whereIn('id', RoleAssignment::where('scope_type', ScopeType::Event)->where('scope_id', $event->id)->select('user_id'))->get();
            $list = $eventItems->map(fn (EventDocumentItem $i) => "{$i->label()} (due {$i->due_on->toFormattedDateString()})")->implode(', ');
            Notification::send($people, new DocumentNotification(
                "Post-event documents due: {$event->name}",
                "Please upload: {$list}.",
                route('events.documents', $event),
            ));
            EventDocumentItem::whereIn('id', $eventItems->pluck('id'))->update(['reminded_at' => now()]);
        }

        $this->info("Reminded {$items->count()} items.");

        return self::SUCCESS;
    }
}
