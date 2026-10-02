<?php

namespace App\Domain\Documents\Models;

use App\Domain\Documents\Enums\DocumentCategory;
use App\Domain\Documents\Enums\DocumentSensitivity;
use App\Domain\Documents\Policies\DocumentPolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Archivo privado. Se guarda fuera de `public/` y solo se descarga por la
 * ruta autorizada, identificado por su `uuid`.
 */
#[Fillable([
    'documentable_type', 'documentable_id', 'member_id', 'category', 'sensitivity', 'title', 'disk', 'path',
    'original_name', 'mime_type', 'size_bytes', 'checksum', 'is_visible_to_member', 'uploaded_by',
])]
#[UsePolicy(DocumentPolicy::class)]
class Document extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Document $document) {
            $document->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'category' => DocumentCategory::class,
            'sensitivity' => DocumentSensitivity::class,
            'size_bytes' => 'integer',
            'is_visible_to_member' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isClinical(): bool
    {
        return $this->sensitivity === DocumentSensitivity::Clinical;
    }

    public function humanSize(): string
    {
        $kb = $this->size_bytes / 1024;

        return $kb >= 1024 ? number_format($kb / 1024, 1, ',', '.').' MB' : number_format(max($kb, 1), 0, ',', '.').' KB';
    }
}
