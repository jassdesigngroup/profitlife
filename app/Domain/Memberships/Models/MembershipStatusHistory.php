<?php

namespace App\Domain\Memberships\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solo inserción. `changed_by` nulo = cambio automático (tarea diaria).
 */
#[Fillable(['membership_id', 'from_status', 'to_status', 'reason', 'changed_by'])]
class MembershipStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from_status' => MembershipStatus::class,
            'to_status' => MembershipStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
