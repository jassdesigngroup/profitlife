<?php

namespace App\Http\Admin\Controllers;

use App\Domain\Members\Models\Member;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve la foto privada del cliente a quien puede ver al cliente.
 */
class MemberPhotoController
{
    public function __invoke(Member $member): StreamedResponse
    {
        Gate::authorize('view', $member);

        $disk = Storage::disk(config('profitlife.documents.disk'));
        abort_if($member->photo_path === null || ! $disk->exists($member->photo_path), 404);

        return $disk->response($member->photo_path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
