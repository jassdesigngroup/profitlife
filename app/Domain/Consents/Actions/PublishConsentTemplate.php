<?php

namespace App\Domain\Consents\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Consents\Enums\ConsentType;
use App\Domain\Consents\Models\ConsentTemplate;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Publica una versión nueva de una plantilla y desactiva las anteriores del
 * mismo tipo. Las versiones ya aceptadas quedan intactas.
 */
class PublishConsentTemplate
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(ConsentType $type, string $title, string $body, User $actor): ConsentTemplate
    {
        return DB::transaction(function () use ($type, $title, $body, $actor) {
            $current = ConsentTemplate::query()->where('type', $type)->lockForUpdate()->max('version');

            ConsentTemplate::query()->where('type', $type)->where('is_active', true)->get()
                ->each(fn (ConsentTemplate $t) => $t->update(['is_active' => false]));

            $template = ConsentTemplate::query()->create([
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'version' => ((int) $current) + 1,
                'is_active' => true,
            ]);

            $this->audit->log('consents', AuditEvent::TemplateVersionCreated, $template, $actor, [
                'type' => $type->value,
                'version' => $template->version,
            ]);

            return $template;
        });
    }
}
