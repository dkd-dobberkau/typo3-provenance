<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Frontend;

/**
 * The provenance record of a page as MX meta tags (Machine Experience, drafts
 * of The Gathering): `<meta name="mx:…">` in the page head, as the MX Carrier
 * Formats note maps MX fields to HTML. Built from the same JSON-LD record the
 * page publishes, so both say the same. The tags are not part of the signed
 * record.
 */
final class MxMetaTagBuilder
{
    // The specification this record follows, for mx:conformsTo.
    public const SPECIFICATION = 'https://github.com/dkd-dobberkau/content-provenance-spec/blob/draft-0.4/specification.md';

    /**
     * @param array<string, mixed> $jsonLd the record as JsonLdBuilder builds it
     * @param string $canonicalUri absolute URL of the page
     * @param string $summary one or two sentences about the page, empty if there are none
     * @param string $trainingDataPolicy the site's policy for AI training, empty if it has none
     * @return array<string, string> content by meta name, without empty fields
     */
    public function build(array $jsonLd, string $canonicalUri, string $summary, string $trainingDataPolicy): array
    {
        $authors = array_filter(array_map(
            static fn(array $person): string => (string)($person['name'] ?? ''),
            $jsonLd['author'] ?? []
        ));

        $tags = [
            'author' => implode(', ', $authors),
            'mx:canonicalUri' => $canonicalUri,
            'mx:summary' => trim($summary),
            'mx:conformsTo' => self::SPECIFICATION,
            'mx:trainingDataPolicy' => trim($trainingDataPolicy),
            'mx:created' => $this->date($jsonLd['datePublished'] ?? null),
            // Pages that were never edited after creation have no dateModified yet.
            'mx:modified' => $this->date($jsonLd['dateModified'] ?? $jsonLd['lastSubstantiveRevision'] ?? null),
            'mx:maintainedDate' => $this->date($jsonLd['maintainedDate'] ?? null),
        ];

        return array_filter($tags, static fn(string $content): bool => $content !== '');
    }

    /**
     * @param array<string, string> $tags content by meta name
     */
    public function render(array $tags): string
    {
        $html = [];
        foreach ($tags as $name => $content) {
            $html[] = sprintf(
                '<meta name="%s" content="%s">',
                htmlspecialchars($name, ENT_QUOTES | ENT_HTML5),
                htmlspecialchars($content, ENT_QUOTES | ENT_HTML5)
            );
        }

        return implode("\n", $html);
    }

    /**
     * MX dates are ISO 8601 calendar dates (YYYY-MM-DD); the record carries date-times.
     */
    private function date(mixed $dateTime): string
    {
        return is_string($dateTime) ? substr($dateTime, 0, 10) : '';
    }
}
