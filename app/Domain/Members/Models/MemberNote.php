<?php

namespace App\Domain\Members\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Members\Policies\MemberNotePolicy;
use Database\Factories\MemberNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Nota administrativa del cliente. Nunca contiene información clínica.
 */
#[Fillable(['member_id', 'author_id', 'body', 'is_pinned'])]
#[UseFactory(MemberNoteFactory::class)]
#[UsePolicy(MemberNotePolicy::class)]
class MemberNote extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean'];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
