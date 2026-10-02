<?php

namespace App\Domain\Physiotherapy\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Staff\Models\Staff;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Integrante del equipo tratante. Al retirarlo se marca `revoked_at` y
 * pierde el acceso desde ese momento.
 */
#[Fillable(['physiotherapy_record_id', 'staff_id', 'granted_by', 'granted_at', 'revoked_at'])]
#[WithoutTimestamps]
class PhysiotherapyRecordStaff extends Model
{
    protected $table = 'physiotherapy_record_staff';

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
