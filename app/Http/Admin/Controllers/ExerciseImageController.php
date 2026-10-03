<?php

namespace App\Http\Admin\Controllers;

use App\Domain\Training\Models\Exercise;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve la imagen (disco privado) de un ejercicio de la biblioteca.
 */
class ExerciseImageController
{
    public function __invoke(int $exercise): StreamedResponse
    {
        $exercise = Exercise::withTrashed()->findOrFail($exercise);
        Gate::authorize('viewAny', Exercise::class);

        $disk = Storage::disk(config('profitlife.documents.disk'));
        abort_if($exercise->image_path === null || ! $disk->exists($exercise->image_path), 404);

        return $disk->response($exercise->image_path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
