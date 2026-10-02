<?php

namespace App\Domain\Physiotherapy\Actions;

use App\Domain\Consents\Enums\ConsentType;
use App\Domain\Consents\Models\Consent;
use App\Domain\Identity\Models\User;
use App\Domain\Physiotherapy\Enums\ClinicalAction;
use App\Domain\Physiotherapy\Enums\NoteType;
use App\Domain\Physiotherapy\Models\ClinicalNote;
use App\Domain\Physiotherapy\Services\ClinicalAccess;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Firma electrónica simple: el autor reingresa su contraseña. La nota queda
 * inmutable. La evaluación inicial exige el consentimiento de tratamiento
 * clínico vigente.
 */
class SignClinicalNote
{
    public function __construct(private readonly ClinicalAccess $access) {}

    public function execute(ClinicalNote $note, User $actor, string $password): void
    {
        $this->confirmPassword($actor, $password);

        if ($note->isSigned()) {
            throw ValidationException::withMessages(['password' => 'La nota ya estaba firmada.']);
        }

        if ($note->type === NoteType::Evaluation && ! self::hasClinicalConsent($note->record->member_id)) {
            throw ValidationException::withMessages(['password' => 'El paciente no tiene vigente el consentimiento de tratamiento clínico. Regístrelo en la ficha antes de firmar la evaluación.']);
        }

        $note->forceFill(['signed_at' => Date::now(), 'signed_by' => $actor->staff->id])->save();

        $this->access->log($actor, $note->record->member_id, $note, ClinicalAction::Sign);
    }

    public static function hasClinicalConsent(int $memberId): bool
    {
        return Consent::query()
            ->where('member_id', $memberId)
            ->whereNull('revoked_at')
            ->whereHas('template', fn ($q) => $q->where('type', ConsentType::ClinicalTreatment))
            ->exists();
    }

    public function confirmPassword(User $actor, string $password): void
    {
        $key = 'clinical-sign:'.$actor->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['password' => 'Demasiados intentos. Espere un minuto.']);
        }

        if (! Hash::check($password, (string) $actor->password)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['password' => 'La contraseña no es correcta.']);
        }

        RateLimiter::clear($key);
    }
}
