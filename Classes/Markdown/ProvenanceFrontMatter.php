<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Markdown;

/**
 * Adds the provenance of a page to the YAML front matter of its Markdown
 * version (EXT:ai_bots_love_markdown). Everything is read from the rendered
 * HTML, so the Markdown says exactly what the signed page says.
 *
 * The signature covers the canonical text of the HTML document (Annex A of
 * the specification), not the Markdown. The cps block therefore names the
 * record and points to the signed HTML; it never claims the Markdown is signed.
 *
 * When the page carries MX meta tags (site set cms-provenance/provenance-mx),
 * the front matter follows the three zones of MX Carrier Formats §3.1:
 * identity fields at the top, type and tags, and the MX fields under mx.
 */
final class ProvenanceFrontMatter
{
    private const VOCABULARY = 'https://cms-provenance.org/ns/v1';
    // MX content type of a CMS web page (MX Core Metadata §5b.1).
    private const MX_TYPE = 'info-doc';
    private const MX_FIELDS = ['canonicalUri', 'summary', 'conformsTo', 'trainingDataPolicy', 'maintainedDate'];

    /**
     * @param array<string, mixed> $frontMatter as built by EXT:ai_bots_love_markdown
     * @return array<string, mixed>
     */
    public function extend(array $frontMatter, string $html): array
    {
        $document = $this->parse($html);
        $record = $this->record($document);
        if ($record === null) {
            return $frontMatter;
        }

        $mxTags = $this->mxTags($document);
        if ($mxTags !== []) {
            $frontMatter = $this->mxZones($frontMatter, $mxTags);
        }
        $frontMatter['cps'] = array_filter([
            'record' => $record['@id'] ?? null,
            'conformance' => $record['cpsConformance'] ?? null,
            'signedDocument' => $this->canonicalUrl($document) ?? $frontMatter['url'] ?? null,
            'contentHash' => $record['contentHash'] ?? null,
            'key' => $record['key'] ?? null,
        ], static fn(mixed $value): bool => $value !== null);

        return $frontMatter;
    }

    /**
     * @param array<string, mixed> $frontMatter
     * @param array<string, string> $mxTags content by MX field name
     * @return array<string, mixed>
     */
    private function mxZones(array $frontMatter, array $mxTags): array
    {
        $tags = [];
        foreach ([...(array)($frontMatter['keywords'] ?? []), ...(array)($frontMatter['categories'] ?? [])] as $tag) {
            $tags[] = mb_strtolower(trim((string)$tag));
        }

        // Zone 1a and 1b first; MX names the creation date "created".
        $zones = array_filter([
            'title' => $frontMatter['title'] ?? null,
            'description' => $frontMatter['description'] ?? null,
            'author' => $frontMatter['author'] ?? null,
            'created' => $mxTags['created'] ?? $frontMatter['date'] ?? null,
            'modified' => $mxTags['modified'] ?? $frontMatter['modified'] ?? null,
        ], static fn(mixed $value): bool => $value !== null);
        $zones['type'] = self::MX_TYPE;
        $zones['tags'] = array_values(array_unique(array_filter($tags, static fn(string $tag): bool => $tag !== '')));

        // The remaining fields of EXT:ai_bots_love_markdown keep their order;
        // "type" there is og:type, which MX's type replaces.
        foreach ($frontMatter as $key => $value) {
            if (!array_key_exists($key, $zones) && !in_array($key, ['date', 'type'], true)) {
                $zones[$key] = $value;
            }
        }
        $zones['mx'] = [];
        foreach (self::MX_FIELDS as $field) {
            if (($mxTags[$field] ?? '') !== '') {
                $zones['mx'][$field] = $mxTags[$field];
            }
        }

        return $zones;
    }

    private function parse(string $html): \DOMDocument
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    /**
     * The JSON-LD object that uses the provenance vocabulary (Annex A.4).
     *
     * @return array<string, mixed>|null
     */
    private function record(\DOMDocument $document): ?array
    {
        foreach ($document->getElementsByTagName('script') as $script) {
            if (strtolower($script->getAttribute('type')) !== 'application/ld+json') {
                continue;
            }
            $data = json_decode($script->textContent, true);
            $context = is_array($data) ? ($data['@context'] ?? null) : null;
            if (in_array(self::VOCABULARY, is_array($context) ? $context : [$context], true)) {
                return $data;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> content by MX field name, without the "mx:" prefix
     */
    private function mxTags(\DOMDocument $document): array
    {
        $tags = [];
        foreach ($document->getElementsByTagName('meta') as $meta) {
            $name = $meta->getAttribute('name');
            if (str_starts_with($name, 'mx:')) {
                $tags[substr($name, 3)] = trim($meta->getAttribute('content'));
            }
        }

        return $tags;
    }

    private function canonicalUrl(\DOMDocument $document): ?string
    {
        foreach ($document->getElementsByTagName('link') as $link) {
            if (strtolower($link->getAttribute('rel')) === 'canonical' && $link->getAttribute('href') !== '') {
                return $link->getAttribute('href');
            }
        }

        return null;
    }
}
