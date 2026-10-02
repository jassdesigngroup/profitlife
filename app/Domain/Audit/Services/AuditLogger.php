<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * Registro explícito de eventos de auditoría (los que no son un simple
 * cambio de modelo). La IP se añade en AppServiceProvider.
 *
 * No se guardan datos personales en `properties`: solo identificadores,
 * nombres de roles/permisos y valores de estado.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(
        string $logName,
        AuditEvent $event,
        ?Model $subject = null,
        ?User $causer = null,
        array $properties = [],
        ?string $description = null,
    ): Activity {
        $logger = activity($logName)
            ->event($event->value)
            ->withProperties($properties);

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        if ($causer !== null) {
            $logger->causedBy($causer);
        } else {
            $logger->causedByAnonymous();
        }

        /** @var Activity */
        return $logger->log($description ?? $event->label());
    }
}
