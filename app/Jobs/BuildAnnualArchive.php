<?php

namespace App\Jobs;

use App\Models\Agm;
use App\Services\Reports\AnnualArchive;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Builds a club's annual archive ZIP after SLD acknowledges its AGM (can take a while: runs in the queue). */
class BuildAnnualArchive implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public readonly int $agmId) {}

    public function handle(AnnualArchive $archive): void
    {
        if ($agm = Agm::with('club', 'session', 'report.latestVersion')->find($this->agmId)) {
            $archive->build($agm);
        }
    }
}
