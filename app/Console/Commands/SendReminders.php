<?php

namespace App\Console\Commands;

use App\Services\Reminders\Reminders;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reminders:send')]
#[Description('Daily deadline reminders: approvals, proposal lead time, indemnity, AGM and replies to SLD')]
class SendReminders extends Command
{
    public function handle(Reminders $reminders): int
    {
        $this->info('Sent '.$reminders->run(CarbonImmutable::today()).' reminders.');

        return self::SUCCESS;
    }
}
