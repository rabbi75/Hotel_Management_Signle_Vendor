<?php

declare(strict_types=1);

namespace App\Modules\Media\Enums;

use App\Support\Enums\Concerns\HasLabel;

/**
 * The coarse kind of a file, derived from its MIME type.
 *
 * Used for filtering and for deciding which preview a browser can render, so
 * it stays deliberately small — five buckets a user recognises, not a taxonomy.
 */
enum MediaType: string
{
    use HasLabel;

    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Other = 'other';

    public static function fromMime(string $mime): self
    {
        return match (true) {
            str_starts_with($mime, 'image/') => self::Image,
            str_starts_with($mime, 'video/') => self::Video,
            str_starts_with($mime, 'audio/') => self::Audio,
            self::isDocument($mime) => self::Document,
            default => self::Other,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Image => 'primary',
            self::Video => 'info',
            self::Audio => 'warning',
            self::Document => 'success',
            self::Other => 'neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Image => 'image',
            self::Video => 'video',
            self::Audio => 'music',
            self::Document => 'file-text',
            self::Other => 'file',
        };
    }

    protected static function isDocument(string $mime): bool
    {
        return $mime === 'application/pdf'
            || str_starts_with($mime, 'text/')
            || str_contains($mime, 'word')
            || str_contains($mime, 'excel')
            || str_contains($mime, 'spreadsheet')
            || str_contains($mime, 'presentation')
            || str_contains($mime, 'opendocument');
    }
}
