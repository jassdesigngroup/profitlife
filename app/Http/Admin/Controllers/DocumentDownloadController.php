<?php

namespace App\Http\Admin\Controllers;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Documents\Models\Document;
use App\Domain\Physiotherapy\Enums\ClinicalAction;
use App\Domain\Physiotherapy\Services\ClinicalAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga autorizada de un documento privado. Cada descarga queda en la auditoría.
 */
class DocumentDownloadController
{
    public function __invoke(Request $request, Document $document, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize('view', $document);

        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), 404);

        $audit->log('documents', AuditEvent::DocumentDownloaded, $document, $request->user(), [
            'member_id' => $document->member_id,
            'sensitivity' => $document->sensitivity->value,
        ]);

        if ($document->isClinical()) {
            app(ClinicalAccess::class)->log($request->user(), $document->member_id, $document, ClinicalAction::Download);
        }

        return $disk->download($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
