<?php

namespace App\Domain\Memberships\Models;

use App\Domain\Identity\Models\User;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Congelación. `ends_on` es el día en que se reanuda (exclusivo): los días
 * congelados son ends_on − starts_on. Nulo = abierta.
 */
#[Fillable(['membership_id', 'starts_on', 'ends_on', 'reason', 'created_by'])]
class MembershipFreeze extends Model
{
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Membership, $this>
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(?CarbonImmutable $asOf = null): bool
    {
        $asOf ??= BusinessDate::today();

        return $this->ends_on === null || $this->ends_on->greaterThan($asOf);
    }

    /**
     * Días congelados hasta $asOf (o hasta el fin, si ya terminó).
     */
    public function days(?CarbonImmutable $asOf = null): int
    {
        $asOf ??= BusinessDate::today();
        $end = $this->ends_on === null || $this->ends_on->greaterThan($asOf) ? $asOf : $this->ends_on;

        return max(0, (int) $this->starts_on->diffInDays($end));
    }
}
