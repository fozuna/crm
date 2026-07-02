<?php
declare(strict_types=1);

namespace App\Services;

final class ServiceOrderRichText
{
    private array $allowedTags = ['p', 'br', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'a'];

    public function sanitize(mixed $value): string
    {
        $html = trim((string) $value);
        if ($html === '') {
            return '';
        }

        if (!class_exists(\DOMDocument::class)) {
            return $this->sanitizeFallback($html);
        }

        $wrapped = '<div>' . $html . '</div>';
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<?xml encoding="utf-8" ?>' . $wrapped,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded !== true) {
            return $this->sanitizeFallback($html);
        }

        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            return $this->sanitizeFallback($html);
        }

        $this->sanitizeNode($root, $dom);
        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return trim($output);
    }

    public function toPlainText(mixed $value): string
    {
        $html = $this->sanitize($value);
        if ($html === '') {
            return '';
        }

        $text = preg_replace('#<(br|/p|/li|/ul|/ol)>#i', "\n", $html);
        $text = preg_replace('#<li>#i', '- ', (string) $text);
        $text = strip_tags((string) $text);
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", (string) $text);
        return trim((string) $text);
    }

    private function sanitizeNode(\DOMNode $node, \DOMDocument $dom): void
    {
        if (!$node->hasChildNodes()) {
            return;
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }

            if (!$child instanceof \DOMElement) {
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);
            if (!in_array($tag, $this->allowedTags, true)) {
                $this->unwrapNode($child, $dom);
                continue;
            }

            $this->sanitizeAttributes($child);
            $this->sanitizeNode($child, $dom);
        }
    }

    private function sanitizeAttributes(\DOMElement $element): void
    {
        $tag = strtolower($element->tagName);
        $allowed = $tag === 'a' ? ['href', 'target', 'rel'] : [];

        for ($i = $element->attributes->length - 1; $i >= 0; $i--) {
            $attr = $element->attributes->item($i);
            if (!$attr instanceof \DOMAttr) {
                continue;
            }

            $name = strtolower($attr->name);
            if (!in_array($name, $allowed, true)) {
                $element->removeAttributeNode($attr);
                continue;
            }

            if ($tag === 'a' && $name === 'href') {
                $href = trim($attr->value);
                if (!preg_match('#^(https?://|mailto:)#i', $href)) {
                    $element->removeAttribute('href');
                    continue;
                }
                $element->setAttribute('href', $href);
                $element->setAttribute('target', '_blank');
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    private function unwrapNode(\DOMElement $element, \DOMDocument $dom): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    private function sanitizeFallback(string $html): string
    {
        $sanitized = strip_tags($html, '<p><br><ul><ol><li><strong><b><em><i><a>');
        $sanitized = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', (string) $sanitized);
        $sanitized = preg_replace('/\s+on\w+="[^"]*"/i', '', (string) $sanitized);
        $sanitized = preg_replace("/\s+on\w+='[^']*'/i", '', (string) $sanitized);
        $sanitized = preg_replace('/\s+style="[^"]*"/i', '', (string) $sanitized);
        $sanitized = preg_replace("/\s+style='[^']*'/i", '', (string) $sanitized);

        return trim((string) $sanitized);
    }
}
