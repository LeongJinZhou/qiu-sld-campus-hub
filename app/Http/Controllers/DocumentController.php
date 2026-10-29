<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\Documents\DocumentExporter;
use App\Services\Documents\DocumentPresenter;
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
            'downloads' => app(DocumentExporter::class)->formats($document),
            'canEdit' => $document->event && $document->status->isEditable() && request()->user()->can('update', $document->event),
        ]);
    }

    /** The filled official template in its native format, or PDF through LibreOffice so it matches exactly. */
    public function download(Document $document, string $format, DocumentExporter $exporter): BinaryFileResponse
    {
        Gate::authorize('view', $document);
        abort_unless(in_array($format, $exporter->formats($document), true), 404);

        $file = $exporter->export($document, $format);
        $name = ($document->event->name ?? $document->club->name)." - {$document->type->label()}.{$format}";

        return response()->download($file, $name)->deleteFileAfterSend();
    }
}
