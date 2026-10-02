<?php

namespace App\Domain\Physiotherapy\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Physiotherapy\Enums\ClinicalAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora de acceso a la historia clínica. Solo inserción, sin contenido clínico.
 */
#[Fillable(['user_id', 'member_id', 'subject_type', 'subject_id', 'action', 'ip_address', 'user_agent'])]
class ClinicalAccessLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'action' => ClinicalAction::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
