<?php

namespace App\Services\Approvals;

use App\Enums\ChainRole;
use App\Enums\DocumentType;
use App\Models\Document;

/** Ordered approval chains per document type. */
class Chains
{
    /** @return list<ChainRole> */
    public function for(Document $document): array
    {
        return match ($document->type) {
            // The preparer confirms and submits, the Advisor signs, SLD has the final say.
            DocumentType::Proposal => [ChainRole::Preparer, ChainRole::Advisor, ChainRole::SldOfficer],
            // Treasurer → President → Advisor → SLD; Finance signs the exported form on paper, then transfer and receipt.
            DocumentType::FundWithdrawal => [ChainRole::Treasurer, ChainRole::President, ChainRole::Advisor, ChainRole::SldOfficer],
            // The first signature on every form is whoever prepares and submits it, with their own position.
            DocumentType::AgmReport => [ChainRole::Preparer, ChainRole::President, ChainRole::Advisor, ChainRole::SldOfficer],
            // The official 13 template is signed by the preparer, the President and the Advisor.
            DocumentType::Minutes => [ChainRole::Preparer, ChainRole::President, ChainRole::Advisor, ChainRole::SldOfficer],
            DocumentType::FinanceRecord, DocumentType::AttendanceList, DocumentType::PostMortem => [
                ChainRole::Preparer, ChainRole::Advisor, ChainRole::SldOfficer,
            ],
        };
    }

    /**
     * Approvals are bound to the exact version signed: any change to the signed content restarts the review, so no
     * approver's signature ever stands under text they did not see. (Earlier signatures stay on record.)
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function isMaterialChange(array $before, array $after): bool
    {
        return $this->canonical($before) !== $this->canonical($after);
    }

    /** @param array<string, mixed> $data */
    private function canonical(array $data): string
    {
        $sort = function (array $a) use (&$sort): array {
            if (! array_is_list($a)) {
                ksort($a);
            }

            return array_map(fn ($v) => is_array($v) ? $sort($v) : $v, $a);
        };

        return (string) json_encode($sort($data));
    }
}
