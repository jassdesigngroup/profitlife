<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Documents\Enums\DocumentCategory;
use App\Domain\Documents\Models\Document;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Guarda un archivo en el disco privado y registra sus metadatos. La
 * extensión se toma del tipo real del archivo, no del nombre que trae.
 */
class StoreDocument
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(
        Member $member,
        UploadedFile $file,
        DocumentCategory $category,
        string $title,
        User $actor,
        ?Model $documentable = null,
    ): Document {
        $uuid = (string) Str::uuid();
        $disk = config('profitlife.documents.disk');
        $extension = $file->guessExtension() ?: 'bin';

        $path = $file->storeAs("members/{$member->id}/documents", "{$uuid}.{$extension}", $disk);

        $document = new Document([
            'documentable_type' => ($documentable ?? $member)->getMorphClass(),
            'documentable_id' => ($documentable ?? $member)->getKey(),
            'member_id' => $member->id,
            'category' => $category,
            'sensitivity' => $category->sensitivity(),
            'title' => $title,
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'is_visible_to_member' => false,
            'uploaded_by' => $actor->id,
        ]);
        $document->uuid = $uuid;
        $document->save();

        $this->audit->log('documents', AuditEvent::DocumentUploaded, $document, $actor, [
            'member_id' => $member->id,
            'category' => $category->value,
            'sensitivity' => $document->sensitivity->value,
        ]);

        return $document;
    }
}
