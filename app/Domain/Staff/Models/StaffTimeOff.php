<?php

namespace App\Domain\Staff\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ausencia de un profesional (vacaciones, incapacidad, permiso).
 */
#[Fillable(['staff_id', 'starts_at', 'ends_at', 'reason', 'created_by'])]
class StaffTimeOff extends Model
{
    protected $table = 'staff_time_off';

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
