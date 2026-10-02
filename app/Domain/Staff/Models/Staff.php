<?php

namespace App\Domain\Staff\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Shared\Enums\DocumentType;
use App\Domain\Staff\Enums\StaffStatus;
use App\Domain\Staff\Policies\StaffPolicy;
use App\Support\Concerns\HasLocationScope;
use App\Support\Scopes\LocationScope;
use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'user_id', 'first_name', 'last_name', 'document_type', 'document_number', 'phone', 'job_title',
    'professional_license', 'photo_path', 'calendar_color', 'is_bookable', 'status', 'hired_on',
])]
#[UseFactory(StaffFactory::class)]
#[UsePolicy(StaffPolicy::class)]
class Staff extends Model
{
    use HasFactory, HasLocationScope, LogsActivity, SoftDeletes;

    protected $table = 'staff';

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'status' => StaffStatus::class,
            'is_bookable' => 'boolean',
            'hired_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Location, $this>
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class)
            ->using(LocationStaff::class)
            ->withPivot(['id', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }

    public function isActive(): bool
    {
        return $this->status === StaffStatus::Active;
    }

    /**
     * Un empleado es visible para quien comparte al menos una de sus sedes.
     *
     * @param  Builder<static>  $query
     * @param  list<int>  $locationIds
     */
    public function applyLocationRestriction(Builder $query, array $locationIds): void
    {
        $query->whereHas('locations', fn (Builder $q) => $q->whereIn('locations.id', $locationIds));
    }

    /**
     * @return list<int>
     */
    public function locationIds(): array
    {
        return $this->locations()
            ->withoutGlobalScope(LocationScope::class)
            ->pluck('locations.id')
            ->map(fn ($id) => (int) $id)
            ->all();
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

        $query->where(function (Builder $q) use ($like) {
            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])
                ->orWhere('document_number', 'like', $like)
                ->orWhere('job_title', 'like', $like)
                ->orWhereHas('user', fn (Builder $u) => $u->where('email', 'like', $like));
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Sin documento ni teléfono: el registro de auditoría no guarda datos personales sensibles.
        return LogOptions::defaults()
            ->useLogName('staff')
            ->logOnly(['first_name', 'last_name', 'job_title', 'professional_license', 'calendar_color', 'is_bookable', 'status', 'hired_on'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
