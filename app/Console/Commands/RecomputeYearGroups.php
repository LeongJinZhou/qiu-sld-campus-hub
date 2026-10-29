<?php

namespace App\Console\Commands;

use App\Services\Camsys\CamsysSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('students:recompute-year-groups')]
#[Description('Recalculate every student year group from the server date')]
class RecomputeYearGroups extends Command
{
    public function handle(CamsysSync $sync): int
    {
        $this->info('Updated '.$sync->recomputeYearGroups().' year groups.');

        return self::SUCCESS;
    }
}
