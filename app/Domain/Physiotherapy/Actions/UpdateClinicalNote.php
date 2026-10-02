<?php

namespace App\Domain\Physiotherapy\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Physiotherapy\Enums\ClinicalAction;
use App\Domain\Physiotherapy\Models\ClinicalNote;
use App\Domain\Physiotherapy\Services\ClinicalAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edita una nota sin firmar y los datos de su sesión.
 */
class UpdateClinicalNote
{
    public function __construct(private readonly ClinicalAccess $access) {}

    /**
     * @param  array<string, ?string>  $sections
     * @param  array{pain_scale: ?int, summary_for_member: ?string}  $session
     */
    public function execute(ClinicalNote $note, User $actor, array $sections, array $session): void
    {
        if ($note->isSigned()) {
            throw ValidationException::withMessages(['sections' => 'La nota ya está firmada: agregue una adenda.']);
        }

        DB::transaction(function () use ($note, $actor, $sections, $session) {
            $type = $note->session?->session_type;
            $note->forceFill(['body' => $type ? RecordPhysiotherapySession::sections($type, $sections) : $sections])->save();
            $note->session?->forceFill([
                'pain_scale' => $session['pain_scale'],
                'summary_for_member' => $session['summary_for_member'] ?: null,
            ])->save();

            $this->access->log($actor, $note->record->member_id, $note, ClinicalAction::Update);
        });
    }
}
