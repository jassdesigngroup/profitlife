<?php

namespace App\Domain\Consents\Models;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Consents\Enums\ConsentType;
use App\Domain\Consents\Policies\ConsentTemplatePolicy;
use Database\Factories\ConsentTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Texto legal versionado. Una versión aceptada por algún cliente no se
 * modifica: los cambios se publican como una versión nueva.
 */
#[Fillable(['type', 'title', 'body', 'version', 'is_active'])]
#[UseFactory(ConsentTemplateFactory::class)]
#[UsePolicy(ConsentTemplatePolicy::class)]
class ConsentTemplate extends Model
{
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'type' => ConsentType::class,
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('consents')
            ->logOnly(['type', 'title', 'version', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) => AuditEvent::describeModelEvent('plantilla de consentimiento', $event));
    }
}
