<?php

namespace App\Domain\Training\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Training\Policies\ExercisePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ejercicio de la biblioteca. Es común a todas las sedes.
 */
#[Fillable(['name', 'slug', 'description', 'instructions', 'video_url', 'image_path', 'is_active', 'created_by'])]
#[UsePolicy(ExercisePolicy::class)]
class Exercise extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsToMany<MuscleGroup, $this>
     */
    public function muscleGroups(): BelongsToMany
    {
        return $this->belongsToMany(MuscleGroup::class)->withPivot('is_primary');
    }

    /**
     * @return BelongsToMany<Equipment, $this>
     */
    public function equipment(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Enlace embebible de YouTube o Vimeo, o null.
     */
    public function embedUrl(): ?string
    {
        $url = (string) $this->video_url;

        if (preg_match('~(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([\w-]{11})~', $url, $m)) {
            return "https://www.youtube-nocookie.com/embed/{$m[1]}";
        }

        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return "https://player.vimeo.com/video/{$m[1]}";
        }

        return null;
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
            $query->where('name', 'like', $like);
        }
    }
}
