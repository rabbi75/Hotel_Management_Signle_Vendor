<?php

declare(strict_types=1);

namespace App\Modules\Blog\Enums;

use App\Support\Enums\Concerns\HasLabel;

/**
 * How a post's stored body should be interpreted when it is rendered.
 *
 * Neither format is trusted: markdown is converted with raw HTML stripped, and
 * the `Html` case means "produced by the rich-text editor", not "safe".
 */
enum BodyFormat: string
{
    use HasLabel;

    case Markdown = 'markdown';
    case Html = 'html';

    public function label(): string
    {
        return match ($this) {
            self::Markdown => 'Markdown',
            self::Html => 'Rich text',
        };
    }
}
