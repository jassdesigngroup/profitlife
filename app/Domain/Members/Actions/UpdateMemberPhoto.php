<?php

namespace App\Domain\Members\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * La foto se guarda en el disco privado; se sirve por una ruta autorizada.
 * Con `$photo = null` se elimina.
 */
class UpdateMemberPhoto
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(Member $member, ?UploadedFile $photo, User $actor): void
    {
        $disk = Storage::disk(config('profitlife.documents.disk'));
        $previous = $member->photo_path;

        $path = $photo === null
            ? null
            : $photo->storeAs("members/{$member->id}", 'photo-'.Str::lower(Str::random(12)).'.'.$photo->guessExtension(), config('profitlife.documents.disk'));

        $member->forceFill(['photo_path' => $path])->saveQuietly();

        if ($previous !== null) {
            $disk->delete($previous);
        }

        $this->audit->log('members', AuditEvent::PhotoUpdated, $member, $actor, ['removed' => $photo === null]);
    }
}
