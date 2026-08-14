<?php

declare(strict_types=1);

namespace App\Modules\Blog\Services;

use App\Modules\Blog\Enums\BodyFormat;
use App\Modules\Blog\Models\Post;
use Illuminate\Support\Str;

/**
 * Derives everything about a post that is a function of its body: the safe
 * HTML, the excerpt and the reading time.
 *
 * Kept out of the model so the derivation happens in exactly one place — a
 * second copy is how a post ends up with a reading time that disagrees with
 * its own text.
 */
class PostRenderer
{
    public function __construct(
        protected MarkdownRenderer $markdown,
        protected HtmlSanitizer $sanitizer,
    ) {}

    /**
     * Render the body to HTML that is safe to inject into a page.
     */
    public function render(?string $body, BodyFormat $format): string
    {
        $body ??= '';

        return match ($format) {
            BodyFormat::Markdown => $this->markdown->toHtml($body),
            BodyFormat::Html => $this->sanitizer->sanitize($body),
        };
    }

    /**
     * Whole minutes, rounded up, never zero for a post that has any words.
     */
    public function readingTime(string $html): int
    {
        $words = $this->wordCount($html);

        if ($words === 0) {
            return 0;
        }

        $perMinute = max(1, (int) config('saas.blog.words_per_minute', 200));

        return max(1, (int) ceil($words / $perMinute));
    }

    /**
     * The author's excerpt, or the opening of the body trimmed to the
     * configured length on a word boundary.
     */
    public function excerpt(?string $authored, string $html): string
    {
        if (is_string($authored) && trim($authored) !== '') {
            return trim($authored);
        }

        $text = $this->plainText($html);

        if ($text === '') {
            return '';
        }

        return Str::limit($text, (int) config('saas.blog.excerpt_length', 200), '…');
    }

    /**
     * Recompute the derived columns on a post from its current body.
     */
    public function apply(Post $post): Post
    {
        $html = $this->render($post->body, $post->body_format);

        $post->body_html = $html;
        $post->reading_time = $this->readingTime($html);
        $post->excerpt = $this->excerpt($post->excerpt, $html);

        return $post;
    }

    public function plainText(string $html): string
    {
        $spaced = preg_replace('#<(br|/p|/div|/li|/h[1-6])[^>]*>#i', ' ', $html);
        $text = html_entity_decode(strip_tags($spaced ?? $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    protected function wordCount(string $html): int
    {
        $matched = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’-]*/u', $this->plainText($html));

        return $matched === false ? 0 : $matched;
    }
}
