<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Unit\Frontend;

use CmsProvenance\Provenance\Domain\Model\Author;
use CmsProvenance\Provenance\Domain\Model\ProvenanceRecord;
use CmsProvenance\Provenance\Domain\Model\Source;
use CmsProvenance\Provenance\Frontend\JsonLdBuilder;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class JsonLdBuilderTest extends UnitTestCase
{
    private const GENERATOR = 'TYPO3 14.3.0 + provenance 0.1.0';

    #[Test]
    public function buildMapsACompleteRecordToTheSpecificationFields(): void
    {
        $record = new ProvenanceRecord(
            documentUri: 'urn:uuid:7c2f0e1a-0000-4000-8000-000000000001',
            firstPublishedAt: 1778749200,
            lastSubstantiveRevisionAt: 1778752800,
            lastMaintainedAt: 1794564000,
            authors: [new Author('Ada Lovelace', 'https://orcid.org/0000-0001-2345-6789', true)],
            reviewer: new Author('Grace Hopper', 'https://example.org/people/grace', false),
            sources: [new Source('https://example.org/report', 'Annual report 2025', 1778716800, 'https://web.archive.org/x')],
        );

        $jsonLd = (new JsonLdBuilder())->build($record, 'The provenance gap', 1794560400, self::GENERATOR);

        self::assertSame([
            '@context' => ['https://schema.org', 'https://cms-provenance.org/ns/v1'],
            '@type' => 'WebPage',
            '@id' => 'urn:uuid:7c2f0e1a-0000-4000-8000-000000000001',
            'headline' => 'The provenance gap',
            'author' => [
                ['@type' => 'Person', '@id' => 'https://orcid.org/0000-0001-2345-6789', 'name' => 'Ada Lovelace'],
            ],
            'reviewedBy' => ['@type' => 'Person', '@id' => 'https://example.org/people/grace', 'name' => 'Grace Hopper'],
            'datePublished' => '2026-05-14T09:00:00Z',
            'dateModified' => '2026-11-13T09:00:00Z',
            'lastSubstantiveRevision' => '2026-05-14T10:00:00Z',
            'maintainedDate' => '2026-11-13T10:00:00Z',
            'citation' => [
                [
                    '@type' => 'WebPage',
                    'url' => 'https://example.org/report',
                    'name' => 'Annual report 2025',
                    'accessDate' => '2026-05-14',
                    'archivedAt' => 'https://web.archive.org/x',
                ],
            ],
            'generator' => self::GENERATOR,
        ], $jsonLd);
    }

    #[Test]
    public function accessDateIsTheCalendarDayTheEditorEnteredInTheServerTimeZone(): void
    {
        $previousTimeZone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
        try {
            // TYPO3 stores a date field as midnight in the server time zone: 13 November, 00:00 in Berlin.
            $berlinMidnight = (new \DateTimeImmutable('2026-11-13 00:00:00', new \DateTimeZone('Europe/Berlin')))->getTimestamp();
            $record = new ProvenanceRecord(
                documentUri: 'urn:uuid:7c2f0e1a-0000-4000-8000-000000000003',
                firstPublishedAt: null,
                lastSubstantiveRevisionAt: null,
                lastMaintainedAt: null,
                authors: [],
                reviewer: null,
                sources: [new Source('https://example.org/report', 'Report', $berlinMidnight, null)],
            );

            $jsonLd = (new JsonLdBuilder())->build($record, '', 0, self::GENERATOR);
        } finally {
            date_default_timezone_set($previousTimeZone);
        }

        self::assertSame('2026-11-13', $jsonLd['citation'][0]['accessDate']);
    }

    #[Test]
    public function buildOmitsFieldsWithoutValue(): void
    {
        $record = new ProvenanceRecord(
            documentUri: 'urn:uuid:7c2f0e1a-0000-4000-8000-000000000002',
            firstPublishedAt: null,
            lastSubstantiveRevisionAt: null,
            lastMaintainedAt: null,
            authors: [],
            reviewer: null,
            sources: [],
        );

        $jsonLd = (new JsonLdBuilder())->build($record, '', 0, self::GENERATOR);

        self::assertSame(
            ['@context', '@type', '@id', 'generator'],
            array_keys($jsonLd)
        );
    }

    #[Test]
    public function authorsWithoutNameAndIdentityAreLeftOut(): void
    {
        $record = new ProvenanceRecord(
            documentUri: 'urn:uuid:7c2f0e1a-0000-4000-8000-000000000003',
            firstPublishedAt: null,
            lastSubstantiveRevisionAt: null,
            lastMaintainedAt: null,
            authors: [new Author(null, null, false), new Author(null, 'https://orcid.org/0000-0002-0000-0000', false)],
            reviewer: null,
            sources: [],
        );

        $jsonLd = (new JsonLdBuilder())->build($record, '', 0, self::GENERATOR);

        self::assertSame(
            [['@type' => 'Person', '@id' => 'https://orcid.org/0000-0002-0000-0000']],
            $jsonLd['author']
        );
    }
}
