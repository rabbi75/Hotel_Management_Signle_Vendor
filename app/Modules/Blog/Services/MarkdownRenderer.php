<?php

declare(strict_types=1);

namespace App\Modules\Blog\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Converts author markdown to HTML under a deliberately hostile configuration.
 *
 * Post bodies are user-authored, so the converter is set up on the assumption
 * that the author is an attacker:
 *
 *  - `html_input: strip` discards embedded HTML entirely, which is what stops
 *    a `<script>` or an `onerror=` attribute surviving the round trip;
 *  - `allow_unsafe_links: false` drops `javascript:` and `data:` hrefs;
 *  - `max_nesting_level` caps the recursion a pathological document can force.
 *
 * The output of this class is the only HTML the blog ever marks as safe.
 */
class MarkdownRenderer
{
    protected ?MarkdownConverter $converter = null;

    public function toHtml(string $markdown): string
    {
        if (trim($markdown) === '') {
            return '';
        }

        try {
            return $this->converter()->convert($markdown)->getContent();
        } catch (CommonMarkException) {
            // A document the parser refuses (nesting bombs, for instance) must
            // not take the request down; an escaped fallback is still readable.
            return '<p>'.nl2br(e($markdown)).'</p>';
        }
    }

    protected function converter(): MarkdownConverter
    {
        if ($this->converter instanceof MarkdownConverter) {
            return $this->converter;
        }

        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
            'renderer' => [
                'soft_break' => "<br />\n",
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new AutolinkExtension);
        $environment->addExtension(new TableExtension);

        return $this->converter = new MarkdownConverter($environment);
    }
}
