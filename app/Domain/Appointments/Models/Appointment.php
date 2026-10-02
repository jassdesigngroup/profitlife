<?php

namespace App\Domain\Appointments\Models;

use App\Domain\Appointments\Enums\AppointmentSource;
use App\Domain\Appointments\Enums\AppointmentStatus;
use App\Domain\Appointments\Policies\AppointmentPolicy;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\Room;
use App\Domain\Members\Models\Member;
use App\Domain\Physiotherapy\Models\PhysiotherapySession;
use App\Domain\Staff\Models\Staff;
use App\Support\Concerns\HasLocationScope;
use App\Support\Money;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cita de un cliente con un profesional en una sede. `ends_at` es el fin de
 * la sesión; el margen del servicio se suma al comprobar cruces.
 */
#[Fillable([
    'member_id', 'staff_id', 'service_id', 'location_id', 'room_id', 'starts_at', 'ends_at', 'status', 'source', 'notes',
    'price_cents', 'rescheduled_from_id', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'created_by',
])]
#[UseFactory(AppointmentFactory::class)]
#[UsePolicy(AppointmentPolicy::class)]
class Appointment extends Model
{
    use HasFactory, HasLocationScope, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'source' => AppointmentSource::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'price_cents' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from_id')->withoutGlobalScopes();
    }

    /**
     * @return HasMany<AppointmentStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class)->latest('id');
    }

    /**
     * Sesión de fisioterapia registrada para esta cita.
     *
     * @return HasOne<PhysiotherapySession, $this>
     */
    public function physiotherapySession(): HasOne
    {
        return $this->hasOne(PhysiotherapySession::class);
    }

    /**
     * @return HasMany<SessionCredit, $this>
     */
    public function credits(): HasMany
    {
        return $this->hasMany(SessionCredit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Comprobante de la sesión suelta (renglón con billable = appointment).
     */
    public function invoice(): ?Invoice
    {
        return Invoice::query()->withoutGlobalScopes()
            ->whereHas('items', fn (Builder $q) => $q->where('billable_type', 'appointment')->where('billable_id', $this->id))
            ->latest('id')
            ->first();
    }

    /**
     * Sesiones descontadas netas (consumo menos devoluciones) por esta cita.
     */
    public function creditsUsed(): int
    {
        return -1 * (int) $this->credits()->sum('delta');
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function price(): ?Money
    {
        return $this->price_cents === null ? null : Money::ofCents($this->price_cents);
    }

    /**
     * Fin del bloque en la agenda (sesión + margen del servicio).
     */
    public function blockEndsAt(): CarbonInterface
    {
        return $this->ends_at->copy()->addMinutes((int) $this->service?->buffer_minutes);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', AppointmentStatus::activeValues());
    }

    /**
     * Citas que un profesional sin `appointments.view-all` puede ver: las suyas.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('appointments.view-all')) {
            $query->where('staff_id', $user->staff?->id ?? 0);
        }
    }
}
