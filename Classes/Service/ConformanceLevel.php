<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Service;

/**
 * Determines the conformance level a Document reaches (specification
 * Section 4) from its JSON-LD record, so that no record claims more than it
 * offers. Levels are cumulative: an incomplete record stays at L1 even if it
 * is signed and lists facts.
 *
 * L1 itself (sitemap, canonical URLs) is the site's concern and cannot be
 * seen in a record; it is assumed here and checked by the conformance suite.
 */
final readonly class ConformanceLevel
{
    private const VOCABULARY = 'https://cms-provenance.org/ns/v1';
    private const REQUIRED = ['@type', '@id', 'datePublished', 'lastSubstantiveRevision', 'generator'];

    /**
     * @param array<string, mixed> $record
     * @param bool $signed whether the record carries a valid signature
     * @param bool $auditEndpoint whether the site serves the Audit Endpoint
     */
    public function of(array $record, bool $signed, bool $auditEndpoint): int
    {
        if (!$this->isComplete($record)) {
            return 1;
        }
        if (!$signed) {
            return 2;
        }

        // Facts are optional for L4; whether the listed ones conform to Section 3.3
        // is checked by the conformance suite, not here.
        return $auditEndpoint ? 4 : 3;
    }

    /**
     * Section 3.1: every member of the record table that is not optional.
     *
     * @param array<string, mixed> $record
     */
    public function isComplete(array $record): bool
    {
        $context = $record['@context'] ?? null;
        if (!in_array(self::VOCABULARY, is_array($context) ? $context : [$context], true)) {
            return false;
        }
        foreach (self::REQUIRED as $member) {
            if (!is_string($record[$member] ?? null) || $record[$member] === '') {
                return false;
            }
        }

        $authors = $record['author'] ?? null;
        if (!is_array($authors) || $authors === [] || !array_is_list($authors)) {
            return false;
        }
        foreach ($authors as $author) {
            if (!$this->hasValue($author, '@id')) {
                return false;
            }
        }

        $sources = $record['citation'] ?? [];
        if (!is_array($sources)) {
            return false;
        }
        foreach ($sources as $source) {
            if (!$this->hasValue($source, 'url') || !$this->hasValue($source, 'name') || !$this->hasValue($source, 'accessDate')) {
                return false;
            }
        }

        return true;
    }

    private function hasValue(mixed $object, string $member): bool
    {
        return is_array($object) && is_string($object[$member] ?? null) && $object[$member] !== '';
    }
}
