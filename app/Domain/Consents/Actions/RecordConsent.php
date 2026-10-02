<?php

namespace App\Domain\Consents\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Consents\Enums\ConsentMethod;
use App\Domain\Consents\Models\Consent;
use App\Domain\Consents\Models\ConsentTemplate;
use App\Domain\Documents\Actions\StoreDocument;
use App\Domain\Documents\Enums\DocumentCategory;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra la aceptación de la versión activa de una plantilla. En papel
 * exige el escaneo firmado, que queda como documento del cliente.
 */
class RecordConsent
{
    public function __construct(
        private readonly StoreDocument $storeDocument,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(
        Member $member,
        ConsentTemplate $template,
        ConsentMethod $method,
        string $signedName,
        ?UploadedFile $scan,
        User $actor,
        ?string $ipAddress,
    ): Consent {
        if (! $template->is_active) {
            throw ValidationException::withMessages(['templateId' => 'Esa versión de la plantilla ya no está vigente.']);
        }

        if ($method === ConsentMethod::Paper && $scan === null) {
            throw ValidationException::withMessages(['scan' => 'Adjunte el documento firmado.']);
        }

        $alreadyAccepted = $member->consents()
            ->where('consent_template_id', $template->id)
            ->whereNull('revoked_at')
            ->exists();

        if ($alreadyAccepted) {
            throw ValidationException::withMessages(['templateId' => 'El cliente ya aceptó esta versión.']);
        }

        return DB::transaction(function () use ($member, $template, $method, $signedName, $scan, $actor, $ipAddress) {
            $consent = $member->consents()->create([
                'consent_template_id' => $template->id,
                'method' => $method,
                'signed_name' => $signedName,
                'accepted_at' => now(),
                'ip_address' => $method === ConsentMethod::Digital ? $ipAddress : null,
                'captured_by' => $actor->id,
            ]);

            if ($scan !== null) {
                $document = $this->storeDocument->execute(
                    $member,
                    $scan,
                    DocumentCategory::Consent,
                    "{$template->title} v{$template->version}",
                    $actor,
                    $consent,
                );
                $consent->update(['document_id' => $document->id]);
            }

            $this->audit->log('consents', AuditEvent::ConsentAccepted, $consent, $actor, [
                'member_id' => $member->id,
                'type' => $template->type->value,
                'version' => $template->version,
                'method' => $method->value,
            ]);

            return $consent;
        });
    }
}
