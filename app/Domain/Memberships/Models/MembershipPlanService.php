<?php

namespace App\Domain\Memberships\Models;

use App\Domain\Appointments\Models\Service;
use App\Domain\Memberships\Enums\SessionPeriod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sesiones de un servicio incluidas en un plan (nulo = ilimitadas).
 */
#[Fillable(['membership_plan_id', 'service_id', 'sessions_included', 'period'])]
#[WithoutTimestamps]
class MembershipPlanService extends Model
{
    protected $table = 'membership_plan_service';

    protected function casts(): array
    {
        return [
            'sessions_included' => 'integer',
            'period' => SessionPeriod::class,
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function isUnlimited(): bool
    {
        return $this->sessions_included === null;
    }
}
