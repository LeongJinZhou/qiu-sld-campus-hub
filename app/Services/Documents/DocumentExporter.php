<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Models\Document;

/** Official file for a document in its template's native format (DOCX/XLSX), or as PDF via LibreOffice. */
class DocumentExporter
{
    public function __construct(
        private readonly ProposalDocx $proposal,
        private readonly AttendanceListExport $attendance,
        private readonly FundWithdrawalDocx $fund,
        private readonly MinutesDocx $minutes,
        private readonly PostMortemExport $postMortem,
        private readonly AgmReportDocx $agmReport,
        private readonly PdfConverter $pdf,
    ) {}

    /** @return list<string> formats this document can be downloaded in */
    public function formats(Document $document): array
    {
        return match ($document->type) {
            DocumentType::Proposal => $document->latestVersion ? ['pdf', 'docx'] : [],
            DocumentType::AttendanceList => ['pdf', 'xlsx'],
            DocumentType::PostMortem => $document->latestVersion ? ['pdf', 'xlsx'] : [],
            DocumentType::FundWithdrawal, DocumentType::Minutes, DocumentType::AgmReport => $document->latestVersion ? ['pdf', 'docx'] : [],
            default => [],
        };
    }

    public function export(Document $document, string $format): string
    {
        $native = match ($document->type) {
            DocumentType::Proposal => $this->proposal->render($document),
            DocumentType::AttendanceList => $this->attendance->render($document->event),
            DocumentType::FundWithdrawal => $this->fund->render($document),
            DocumentType::Minutes => $this->minutes->render($document),
            DocumentType::PostMortem => $this->postMortem->render($document),
            DocumentType::AgmReport => $this->agmReport->render($document),
            default => abort(404),
        };
        if ($format !== 'pdf') {
            return $native;
        }

        $pdf = $this->pdf->convert($native);
        @unlink($native);

        return $pdf;
    }
}
