<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\Documents\DocumentPresenter;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function show(Document $document, DocumentPresenter $presenter): Response
    {
        Gate::authorize('view', $document);

        return Inertia::render('Documents/Show', [
            'document' => $presenter->present($document),
            'canEdit' => $document->event && $document->status->isEditable() && request()->user()->can('update', $document->event),
        ]);
    }
}
