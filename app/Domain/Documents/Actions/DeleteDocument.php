<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Documents\Models\Document;
use App\Domain\Identity\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Borrado lógico; el archivo se conserva para la trazabilidad. Un documento
 * que respalda un consentimiento no se puede borrar.
 */
class DeleteDocument
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(Document $document, User $actor): void
    {
        if ($document->documentable_type === 'consent') {
            throw ValidationException::withMessages(['document' => 'Este documento respalda un consentimiento y no se puede eliminar.']);
        }

        $document->delete();

        $this->audit->log('documents', AuditEvent::DocumentDeleted, $document, $actor, ['member_id' => $document->member_id]);
    }
}
