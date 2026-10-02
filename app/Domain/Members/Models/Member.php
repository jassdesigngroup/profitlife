<?php

namespace App\Domain\Members\Models;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Consents\Models\Consent;
use App\Domain\Documents\Models\Document;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Enums\Gender;
use App\Domain\Members\Enums\MemberStatus;
use App\Domain\Members\Policies\MemberPolicy;
use App\Domain\Memberships\Enums\AccessScope;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Shared\Enums\DocumentType;
use App\Support\Concerns\HasLocationScope;
use App\Support\Scopes\LocationScope;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Cliente. Su alcance por sede es la sede principal (`home_location_id`);
 * las fases de membresías y citas añadirán las sedes donde tenga servicio.
 */
#[Fillable([
    'user_id', 'member_number', 'home_location_id', 'first_name', 'last_name', 'document_type', 'document_number',
    'birth_date', 'gender', 'email', 'phone', 'address_line', 'city', 'department', 'photo_path', 'status',
    'joined_on', 'created_by',
])]
#[Hidden(['checkin_pin_hash'])]
#[UseFactory(MemberFactory::class)]
#[UsePolicy(MemberPolicy::class)]
class Member extends Model
{
    use HasFactory, HasLocationScope, LogsActivity, Notifiable, SoftDeletes;

    public const ADULT_AGE = 18;

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'gender' => Gender::class,
            'status' => MemberStatus::class,
            'birth_date' => 'date',
            'joined_on' => 'date',
        ];
    }

    public function locationScopeColumn(): string
    {
        return 'home_location_id';
    }

    /**
     * Visible en su sede principal y en las sedes donde tiene una membresía
     * vigente: la de compra y las del plan (o todas, si el plan es global).
     *
     * @param  Builder<static>  $query
     * @param  list<int>  $locationIds
     */
    public function applyLocationRestriction(Builder $query, array $locationIds): void
    {
        $query->where(function (Builder $q) use ($locationIds) {
            $q->whereIn($this->qualifyColumn('home_location_id'), $locationIds)
                ->orWhereHas('memberships', function (Builder $m) use ($locationIds) {
                    $m->withoutGlobalScope(LocationScope::class)
                        ->whereIn('status', MembershipStatus::currentValues())
                        ->where(fn (Builder $mm) => $mm
                            ->whereIn('purchase_location_id', $locationIds)
                            ->orWhereHas('plan', fn (Builder $p) => $p
                                ->where('access_scope', AccessScope::AllLocations)
                                ->orWhereHas('locations', fn (Builder $l) => $l->whereIn('locations.id', $locationIds))));
                });
        });
    }

    /**
     * @return list<int>
     */
    public function locationIds(): array
    {
        $ids = [(int) $this->home_location_id];

        $memberships = $this->memberships()->withoutGlobalScope(LocationScope::class)
            ->whereIn('status', MembershipStatus::currentValues())
            ->with('plan.locations')
            ->get();

        foreach ($memberships as $membership) {
            if ($membership->plan?->access_scope === AccessScope::AllLocations) {
                return Location::query()->withoutGlobalScope(LocationScope::class)->pluck('id')->map(fn ($id) => (int) $id)->all();
            }

            $ids[] = (int) $membership->purchase_location_id;
            foreach ($membership->plan?->locations ?? [] as $location) {
                $ids[] = (int) $location->id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Membresía vigente más reciente (activa, por iniciar, congelada o suspendida).
     */
    public function currentMembership(): ?Membership
    {
        return $this->memberships()->withoutGlobalScope(LocationScope::class)
            ->whereIn('status', MembershipStatus::currentValues())
            ->orderBy('starts_on')
            ->with('plan')
            ->get()
            ->sortBy(fn (Membership $m) => $m->status === MembershipStatus::Active ? 0 : 1)
            ->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function homeLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'home_location_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<EmergencyContact, $this>
     */
    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class)->orderByDesc('is_primary')->orderBy('name');
    }

    /**
     * @return HasMany<MemberNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(MemberNote::class)->orderByDesc('is_pinned')->latest('id');
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }

    public function age(): ?int
    {
        return $this->birth_date?->age;
    }

    public function isMinor(): bool
    {
        $age = $this->age();

        return $age !== null && $age < self::ADULT_AGE;
    }

    public function hasCheckinPin(): bool
    {
        return $this->checkin_pin_hash !== null;
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1));
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
        $digits = preg_replace('/\D/', '', $term);

        $query->where(function (Builder $q) use ($like, $digits) {
            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])
                ->orWhere('member_number', 'like', $like)
                ->orWhere('document_number', 'like', $like)
                ->orWhere('email', 'like', $like);

            if (strlen($digits) >= 3) {
                $q->orWhere('phone', 'like', "%{$digits}%");
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Sin documento, contacto, dirección ni fecha de nacimiento: el registro de auditoría no guarda esos datos.
        return LogOptions::defaults()
            ->useLogName('members')
            ->logOnly(['member_number', 'first_name', 'last_name', 'home_location_id', 'status', 'joined_on', 'user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) => AuditEvent::describeModelEvent('cliente', $event));
    }
}
