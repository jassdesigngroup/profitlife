<?php

namespace App\Domain\Physiotherapy\Models;

use App\Domain\Physiotherapy\Enums\NoteType;
use App\Domain\Physiotherapy\Policies\ClinicalNotePolicy;
use App\Domain\Staff\Models\Staff;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nota clínica. El cuerpo (secciones de la plantilla) va cifrado. Una vez
 * firmada no cambia: las correcciones son adendas (parent_note_id).
 */
#[Fillable([
    'physiotherapy_record_id', 'physiotherapy_session_id', 'author_id', 'type', 'parent_note_id', 'body', 'signed_at',
    'signed_by', 'is_visible_to_member',
])]
#[Hidden(['body'])]
#[UsePolicy(ClinicalNotePolicy::class)]
class ClinicalNote extends Model
{
    protected function casts(): array
    {
        return [
            'type' => NoteType::class,
            'body' => 'encrypted:array',
            'signed_at' => 'datetime',
            'is_visible_to_member' => 'boolean',
        ];
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    /**
     * @return BelongsTo<PhysiotherapyRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(PhysiotherapyRecord::class, 'physiotherapy_record_id');
    }

    /**
     * @return BelongsTo<PhysiotherapySession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PhysiotherapySession::class, 'physiotherapy_session_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'author_id')->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'signed_by')->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_note_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function addenda(): HasMany
    {
        return $this->hasMany(self::class, 'parent_note_id')->orderBy('id');
    }
}
