<?php

namespace App\Services\Agm;

use App\Models\AgmPeriod;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Time\SessionResolver;
use DomainException;

/** SLD opens and closes the AGM period; the AGM module is locked outside it (CLAUDE.md §6 AGM). */
class AgmPeriods
{
    public function __construct(private readonly SessionResolver $sessions, private readonly AuditLogger $audit) {}

    public function current(): ?AgmPeriod
    {
        return AgmPeriod::with('session')->whereNull('closed_at')->whereDate('starts_on', '<=', today())
            ->whereDate('report_deadline', '>=', today())->latest('starts_on')->first();
    }

    public function assertOpen(?AgmPeriod $period): AgmPeriod
    {
        if (! $period?->isOpen()) {
            throw new DomainException('The AGM module opens when SLD starts the AGM period.');
        }

        return $period;
    }

    /** @param array{session: string, starts_on: string, agm_deadline: string, report_deadline: string} $data */
    public function open(User $sld, array $data): AgmPeriod
    {
        if ($this->current()) {
            throw new DomainException('An AGM period is already open. Close it first.');
        }
        $period = AgmPeriod::create([
            'session_id' => $this->sessions->forName($data['session'])->id,
            'starts_on' => $data['starts_on'], 'agm_deadline' => $data['agm_deadline'], 'report_deadline' => $data['report_deadline'],
            'opened_by' => $sld->id,
        ]);
        $this->audit->log('agm_period.opened', $period, $data);

        return $period;
    }

    public function close(AgmPeriod $period): void
    {
        $period->update(['closed_at' => now()]);
        $this->audit->log('agm_period.closed', $period);
    }
}
