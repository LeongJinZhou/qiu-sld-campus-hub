<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Services\Documents\DocumentPresenter;
use App\Services\Documents\PdfConverter;
use App\Services\Documents\ProposalDocx;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    public function show(Document $document, DocumentPresenter $presenter): Response
    {
        Gate::authorize('view', $document);

        return Inertia::render('Documents/Show', [
            'document' => $presenter->present($document),
            'downloads' => $document->type === DocumentType::Proposal && $document->latestVersion ? ['docx', 'pdf'] : [],
            'canEdit' => $document->event && $document->status->isEditable() && request()->user()->can('update', $document->event),
        ]);
    }

    /** The filled official template: DOCX, or PDF through LibreOffice so it matches the original exactly. */
    public function download(Document $document, string $format, ProposalDocx $proposal, PdfConverter $pdf): BinaryFileResponse
    {
        Gate::authorize('view', $document);
        abort_unless($document->type === DocumentType::Proposal && in_array($format, ['docx', 'pdf'], true), 404);

        $docx = $proposal->render($document);
        $file = $format === 'pdf' ? $pdf->convert($docx) : $docx;
        if ($format === 'pdf') {
            @unlink($docx);
        }
        $name = ($document->event->name ?? 'Document')." - {$document->type->label()}.{$format}";

        return response()->download($file, $name)->deleteFileAfterSend();
    }
}
