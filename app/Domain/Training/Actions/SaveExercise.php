<?php

namespace App\Domain\Training\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Training\Models\Exercise;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Crea o edita un ejercicio de la biblioteca con sus grupos musculares
 * (principales y secundarios), equipo e imagen opcional (disco privado).
 */
class SaveExercise
{
    /**
     * @param  array{name: string, description: ?string, instructions: ?string, video_url: ?string, is_active: bool, primary: list<int>, secondary: list<int>, equipment: list<int>}  $data
     */
    public function execute(?Exercise $exercise, array $data, ?UploadedFile $image, bool $removeImage, User $actor): Exercise
    {
        $disk = Storage::disk(config('profitlife.documents.disk'));

        return DB::transaction(function () use ($exercise, $data, $image, $removeImage, $actor, $disk) {
            $attributes = [
                'name' => $data['name'],
                'description' => $data['description'] ?: null,
                'instructions' => $data['instructions'] ?: null,
                'video_url' => $data['video_url'] ?: null,
                'is_active' => $data['is_active'],
            ];

            if ($exercise === null) {
                $slug = Str::slug($data['name']);
                $attributes['slug'] = Exercise::withTrashed()->where('slug', $slug)->exists() ? $slug.'-'.Str::lower(Str::random(4)) : $slug;
                $attributes['created_by'] = $actor->id;
                $exercise = Exercise::query()->create($attributes);
            } else {
                $exercise->update($attributes);
            }

            if (($image !== null || $removeImage) && $exercise->image_path) {
                $disk->delete($exercise->image_path);
                $exercise->forceFill(['image_path' => null])->save();
            }

            if ($image !== null) {
                $path = $image->storeAs('exercises', $exercise->id.'-'.Str::lower(Str::random(10)).'.'.$image->guessExtension(), config('profitlife.documents.disk'));
                $exercise->forceFill(['image_path' => $path])->save();
            }

            $secondary = array_values(array_diff($data['secondary'], $data['primary']));
            $exercise->muscleGroups()->sync(
                collect($data['primary'])->mapWithKeys(fn ($id) => [$id => ['is_primary' => true]])
                    ->union(collect($secondary)->mapWithKeys(fn ($id) => [$id => ['is_primary' => false]]))
                    ->all()
            );
            $exercise->equipment()->sync($data['equipment']);

            return $exercise;
        });
    }
}
