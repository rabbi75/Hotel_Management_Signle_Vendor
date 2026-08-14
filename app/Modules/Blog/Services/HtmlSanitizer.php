<?php

declare(strict_types=1);

namespace App\Modules\Blog\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Reduces rich-text-editor output to an allow-listed subset of HTML.
 *
 * TipTap runs in the author's browser, so its output arrives over the wire like
 * any other request body and is worth exactly as much trust as a curl command.
 * Everything here is an allow list — element names, attribute names and URL
 * schemes — because a deny list of "dangerous" markup is a list that is always
 * one browser release out of date.
 */
class HtmlSanitizer
{
    /** @var list<string> */
    private const ALLOWED_ELEMENTS = [
        'p', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's', 'code', 'pre', 'blockquote',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li',
        'a', 'img',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'figure', 'figcaption', 'span', 'div',
    ];

    /** @var array<string, list<string>> */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'],
        'code' => ['class'],
        'pre' => ['class'],
    ];

    /** @var list<string> */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        // The wrapper forces UTF-8 interpretation; without it DOMDocument
        // assumes ISO-8859-1 and mangles every non-ASCII character.
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="__root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('__root');

        if (! $loaded || ! $root instanceof DOMElement) {
            return '';
        }

        $this->clean($root);

        $output = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= (string) $document->saveHTML($child);
        }

        return trim($output);
    }

    protected function clean(DOMElement $element): void
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                // Comments, CDATA and processing instructions carry no content
                // a reader needs and have historically been parser-confusion
                // vectors; they go.
                $element->removeChild($child);

                continue;
            }

            $name = strtolower($child->nodeName);

            if (! in_array($name, self::ALLOWED_ELEMENTS, true)) {
                $this->unwrap($child);

                continue;
            }

            $this->stripAttributes($child, $name);
            $this->clean($child);
        }
    }

    /**
     * Replace a disallowed element with its children, so removing a `<font>`
     * does not also delete the sentence inside it.
     */
    protected function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent instanceof DOMNode) {
            return;
        }

        // Script and style hold code, not prose: their text must go with them.
        if (in_array(strtolower($element->nodeName), ['script', 'style', 'iframe', 'object', 'embed'], true)) {
            $parent->removeChild($element);

            return;
        }

        $this->clean($element);

        foreach (iterator_to_array($element->childNodes) as $child) {
            $parent->insertBefore($child, $element);
        }

        $parent->removeChild($element);
    }

    protected function stripAttributes(DOMElement $element, string $name): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$name] ?? [];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $attributeName = strtolower($attribute->nodeName);

            if (! in_array($attributeName, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if (in_array($attributeName, ['href', 'src'], true) && ! $this->isSafeUrl($attribute->nodeValue ?? '')) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        if ($name === 'a' && $element->getAttribute('target') === '_blank') {
            // Without this an opened tab can reach back through window.opener.
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    protected function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true);
    }
}
