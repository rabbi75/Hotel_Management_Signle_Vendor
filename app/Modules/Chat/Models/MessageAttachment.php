<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use App\Support\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The row that ties an uploaded file to a message.
 *
 * The bytes themselves live in the media library; this model keeps the
 * denormalised name/mime/size so a thread can be listed without touching the
 * media table for every attachment.
 *
 * @property int $id
 * @property int $company_id
 * @property int $message_id
 * @property string $name
 * @property string|null $mime_type
 * @property int $size
 */
class MessageAttachment extends Model implements HasMedia
{
    use BelongsToCompany, InteractsWithMedia;

    public const COLLECTION = 'chat-attachment';

    protected $fillable = ['message_id', 'name', 'mime_type', 'size'];

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function url(): ?string
    {
        return $this->getFirstMediaUrl(self::COLLECTION) ?: null;
    }

    public function thumbnailUrl(): ?string
    {
        if (! $this->isImage()) {
            return null;
        }

        return $this->getFirstMediaUrl(self::COLLECTION, 'thumb') ?: $this->url();
    }

    public function isImage(): bool
    {
        return $this->mime_type !== null && str_starts_with($this->mime_type, 'image/');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLECTION)->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections(self::COLLECTION)
            ->nonQueued()
            ->fit(Fit::Contain, 320, 320);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['size' => 'integer'];
    }
}
