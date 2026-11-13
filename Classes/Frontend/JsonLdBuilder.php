<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Frontend;

use CmsProvenance\Provenance\Domain\Model\Author;
use CmsProvenance\Provenance\Domain\Model\ProvenanceRecord;
use CmsProvenance\Provenance\Domain\Model\Source;

/**
 * Turns a provenance record into the JSON-LD structure defined by the Content
 * Provenance Specification (schema.org plus the cms-provenance.org/ns/v1
 * vocabulary). Fields without a value are left out rather than emitted empty.
 */
final class JsonLdBuilder
{
    public const CONTEXT = ['https://schema.org', 'https://cms-provenance.org/ns/v1'];

    /**
     * @param int $pageLastChangedAt last change of the page content, 0 if unknown
     * @return array<string, mixed>
     */
    public function build(ProvenanceRecord $record, string $headline, int $pageLastChangedAt, string $generator): array
    {
        $authors = array_values(array_filter(array_map($this->person(...), $record->authors)));
        $reviewer = $record->reviewer !== null ? $this->person($record->reviewer) : null;

        $jsonLd = [
            '@context' => self::CONTEXT,
            '@type' => 'WebPage',
            '@id' => $record->documentUri,
            'headline' => $headline !== '' ? $headline : null,
            'author' => $authors !== [] ? $authors : null,
            'reviewedBy' => $reviewer,
            'datePublished' => $this->dateTime($record->firstPublishedAt),
            'dateModified' => $this->dateTime($pageLastChangedAt > 0 ? $pageLastChangedAt : null),
            'lastSubstantiveRevision' => $this->dateTime($record->lastSubstantiveRevisionAt),
            'maintainedDate' => $this->dateTime($record->lastMaintainedAt),
            'citation' => $record->sources !== [] ? array_map($this->citation(...), $record->sources) : null,
            'generator' => $generator,
        ];

        return array_filter($jsonLd, static fn(mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, string>|null null when there is nothing publishable about the person
     */
    private function person(Author $author): ?array
    {
        if ($author->identityUri === null && $author->name === null) {
            return null;
        }

        return array_filter([
            '@type' => 'Person',
            '@id' => $author->identityUri,
            'name' => $author->name,
        ], static fn(?string $value): bool => $value !== null);
    }

    /**
     * @return array<string, string>
     */
    private function citation(Source $source): array
    {
        return array_filter([
            '@type' => 'WebPage',
            'url' => $source->url,
            'name' => $source->description !== '' ? $source->description : null,
            // A date field holds midnight in the server time zone; formatting it in
            // UTC would give the previous day east of Greenwich.
            'accessDate' => $source->accessedAt !== null ? date('Y-m-d', $source->accessedAt) : null,
            'archivedAt' => $source->archiveUrl,
        ], static fn(?string $value): bool => $value !== null);
    }

    private function dateTime(?int $timestamp): ?string
    {
        return $timestamp !== null ? gmdate('Y-m-d\TH:i:s\Z', $timestamp) : null;
    }
}
