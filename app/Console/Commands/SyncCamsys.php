<?php

namespace App\Console\Commands;

use App\Services\Camsys\CamsysSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('camsys:sync')]
#[Description('Upsert students from camsys (SELECT only)')]
class SyncCamsys extends Command
{
    public function handle(CamsysSync $sync): int
    {
        $run = $sync->syncAll('schedule');
        if ($run->error) {
            $this->error($run->error);

            return self::FAILURE;
        }
        $this->info("Synced {$run->upserted}/{$run->records_seen} students.");

        return self::SUCCESS;
    }
}
