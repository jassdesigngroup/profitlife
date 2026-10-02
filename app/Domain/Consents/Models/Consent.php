<?php

namespace App\Domain\Consents\Models;

use App\Domain\Consents\Enums\ConsentMethod;
use App\Domain\Documents\Models\Document;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aceptación de una versión concreta de una plantilla. No se borra: se revoca.
 */
#[Fillable([
    'member_id', 'consent_template_id', 'method', 'signed_name', 'document_id', 'accepted_at', 'revoked_at',
    'ip_address', 'captured_by',
])]
class Consent extends Model
{
    protected function casts(): array
    {
        return [
            'method' => ConsentMethod::class,
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<ConsentTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ConsentTemplate::class, 'consent_template_id');
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function capturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
