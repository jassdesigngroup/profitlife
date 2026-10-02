<?php

namespace App\Domain\Physiotherapy\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Physiotherapy\Enums\ClinicalAction;
use App\Domain\Physiotherapy\Enums\NoteType;
use App\Domain\Physiotherapy\Models\ClinicalNote;
use App\Domain\Physiotherapy\Services\ClinicalAccess;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adenda: aclara o corrige una nota firmada. Se firma al crearla.
 */
class AddClinicalAddendum
{
    public function __construct(
        private readonly SignClinicalNote $sign,
        private readonly ClinicalAccess $access,
    ) {}

    public function execute(ClinicalNote $parent, User $actor, string $text, string $password): ClinicalNote
    {
        $text = trim($text);

        if ($text === '') {
            throw ValidationException::withMessages(['addendumText' => 'Escriba la adenda.']);
        }

        if (! $parent->isSigned() || $parent->type === NoteType::Addendum) {
            throw ValidationException::withMessages(['addendumText' => 'Solo se agregan adendas a notas firmadas.']);
        }

        $this->sign->confirmPassword($actor, $password);

        return DB::transaction(function () use ($parent, $actor, $text) {
            $note = ClinicalNote::query()->create([
                'physiotherapy_record_id' => $parent->physiotherapy_record_id,
                'physiotherapy_session_id' => $parent->physiotherapy_session_id,
                'author_id' => $actor->staff->id,
                'type' => NoteType::Addendum,
                'parent_note_id' => $parent->id,
                'body' => ['text' => $text],
                'signed_at' => Date::now(),
                'signed_by' => $actor->staff->id,
            ]);

            $this->access->log($actor, $parent->record->member_id, $note, ClinicalAction::Create);
            $this->access->log($actor, $parent->record->member_id, $note, ClinicalAction::Sign);

            return $note;
        });
    }
}
