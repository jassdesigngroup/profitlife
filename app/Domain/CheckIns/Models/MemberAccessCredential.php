<?php

namespace App\Domain\CheckIns\Models;

use App\Domain\CheckIns\Enums\CredentialType;
use App\Domain\Members\Models\Member;
use App\Support\Concerns\HasLocationScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Date;

/**
 * Credencial de acceso del cliente (QR). Se busca por el hash SHA-256 del
 * token; el token cifrado solo sirve para volver a mostrar el código.
 */
#[Fillable(['member_id', 'type', 'token_hash', 'token_encrypted', 'is_active', 'expires_at', 'last_used_at'])]
#[Hidden(['token_hash', 'token_encrypted'])]
class MemberAccessCredential extends Model
{
    use HasLocationScope;

    protected function casts(): array
    {
        return [
            'type' => CredentialType::class,
            'token_encrypted' => 'encrypted',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Hereda la visibilidad del cliente.
     *
     * @param  Builder<static>  $query
     * @param  list<int>  $locationIds
     */
    public function applyLocationRestriction(Builder $query, array $locationIds): void
    {
        $query->whereHas('member');
    }

    /**
     * @return list<int>
     */
    public function locationIds(): array
    {
        return $this->member()->withoutGlobalScopes()->first()?->locationIds() ?? [];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isUsable(): bool
    {
        return $this->is_active && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', Date::now()));
    }
}
