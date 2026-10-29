<?php

namespace App\Services\Approvals;

use App\Enums\ChainRole;
use App\Enums\DocumentType;
use App\Models\Document;

/** Ordered approval chains per document type (CLAUDE.md §6). */
class Chains
{
    /** Changing any of these on resubmission restarts the whole chain. */
    public const MATERIAL_KEYS = ['budget', 'starts_at', 'ends_at'];

    /** @return list<ChainRole> */
    public function for(Document $document): array
    {
        return match ($document->type) {
            DocumentType::Proposal => array_values(array_filter([
                ChainRole::Preparer,
                ChainRole::Advisor,
                $document->event?->is_faculty_event ? ChainRole::Dean : null,
                ChainRole::SldOfficer,
                ChainRole::AssistantRegistrar,
                ChainRole::Registrar,
                ChainRole::Bursar,
                ChainRole::Coo,
            ])),
            DocumentType::FundWithdrawal => [
                ChainRole::Secretary, ChainRole::President, ChainRole::Advisor,
                ChainRole::AssistantRegistrar, ChainRole::Bursar, ChainRole::Coo,
            ],
            DocumentType::AgmReport => [ChainRole::Secretary, ChainRole::President, ChainRole::Advisor, ChainRole::SldOfficer],
            // The official 13 template is signed by the preparer, the President and the Advisor.
            DocumentType::Minutes => [ChainRole::Preparer, ChainRole::President, ChainRole::Advisor, ChainRole::SldOfficer],
            DocumentType::FinanceRecord, DocumentType::AttendanceList, DocumentType::PostMortem => [
                ChainRole::Preparer, ChainRole::Advisor, ChainRole::SldOfficer,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function isMaterialChange(array $before, array $after): bool
    {
        foreach (self::MATERIAL_KEYS as $key) {
            if (($before[$key] ?? null) != ($after[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
