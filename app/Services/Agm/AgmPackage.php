<?php

namespace App\Services\Agm;

use App\Enums\DocumentStatus;
use App\Models\Agm;
use App\Models\GeneratedFile;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\AuditLogger;
use App\Services\Finance\Ledger;
use DomainException;
use Illuminate\Support\Facades\Notification;

/** The AGM package goes to SLD together: AGM Report, Financial Report, Full Member List, Annual Activities Report. */
class AgmPackage
{
    public function __construct(private readonly Ledger $ledger, private readonly AuditLogger $audit) {}

    /** @return array<string, array{label: string, ready: bool}> */
    public function checklist(Agm $agm): array
    {
        $report = $agm->report;
        $annual = GeneratedFile::where('club_id', $agm->club_id)->where('session_id', $agm->session_id)
            ->where('kind', GeneratedFile::ANNUAL_REPORT)->whereNotNull('generated_at')->exists();

        return [
            'report' => ['label' => 'AGM Report (signed and submitted)', 'ready' => in_array($report?->status, [DocumentStatus::InReview, DocumentStatus::Approved], true)],
            'finance' => ['label' => 'Financial Report (02 Finance Record)', 'ready' => $this->ledger->isReady($agm->club)],
            'members' => ['label' => 'Full Member List (10)', 'ready' => true],
            'annual' => ['label' => 'Annual Activities Report (12)', 'ready' => $annual],
        ];
    }

    public function submit(Agm $agm, User $user): void
    {
        if ($agm->package_submitted_at) {
            throw new DomainException('The AGM package has already been submitted.');
        }
        $missing = array_filter($this->checklist($agm), fn ($item) => ! $item['ready']);
        if ($missing) {
            throw new DomainException('Not ready yet: '.implode(', ', array_column($missing, 'label')).'.');
        }
        $agm->update(['package_submitted_at' => now()]);
        $this->audit->log('agm.package_submitted', $agm);
        Notification::send(User::role(['admin', 'superadmin'])->get(), new DocumentNotification(
            "AGM package: {$agm->club->name}",
            "{$user->name} submitted the AGM Report, Financial Report, Full Member List and Annual Activities Report.",
            route('admin.agm.index'),
        ));
    }
}
