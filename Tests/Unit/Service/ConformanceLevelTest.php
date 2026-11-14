<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Unit\Service;

use CmsProvenance\Provenance\Service\ConformanceLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ConformanceLevelTest extends UnitTestCase
{
    private static function completeRecord(): array
    {
        return [
            '@context' => ['https://schema.org', 'https://cms-provenance.org/ns/v1'],
            '@type' => 'WebPage',
            '@id' => 'urn:uuid:00000000-0000-4000-8000-000000000001',
            'author' => [['@type' => 'Person', '@id' => 'https://example.org/people/ada', 'name' => 'Ada']],
            'datePublished' => '2026-11-13T10:00:00Z',
            'lastSubstantiveRevision' => '2026-11-13T11:00:00Z',
            'citation' => [['@type' => 'WebPage', 'url' => 'https://example.org/source', 'name' => 'Source', 'accessDate' => '2026-11-13']],
            'generator' => 'TYPO3 14.3 + provenance 0.1',
        ];
    }

    public static function incompleteRecordDataProvider(): \Generator
    {
        foreach (['@context', '@type', '@id', 'author', 'datePublished', 'lastSubstantiveRevision', 'generator'] as $member) {
            $record = self::completeRecord();
            unset($record[$member]);
            yield 'without ' . $member => [$record];
        }
        yield 'without the vocabulary in the context' => [['@context' => 'https://schema.org'] + self::completeRecord()];
        yield 'with no author' => [['author' => []] + self::completeRecord()];
        yield 'with an author without URI' => [['author' => [['@type' => 'Person', 'name' => 'Ada']]] + self::completeRecord()];
        yield 'with one of two authors without URI' => [
            ['author' => [self::completeRecord()['author'][0], ['@type' => 'Person', 'name' => 'Bob']]] + self::completeRecord(),
        ];
        yield 'with a source without access date' => [
            ['citation' => [['@type' => 'WebPage', 'url' => 'https://example.org/source', 'name' => 'Source']]] + self::completeRecord(),
        ];
        yield 'with a source without description' => [
            ['citation' => [['@type' => 'WebPage', 'url' => 'https://example.org/source', 'accessDate' => '2026-11-13']]] + self::completeRecord(),
        ];
    }

    #[Test]
    public function completeRecordIsRecognised(): void
    {
        self::assertTrue((new ConformanceLevel())->isComplete(self::completeRecord()));
    }

    #[Test]
    public function recordWithoutSourcesIsComplete(): void
    {
        $record = self::completeRecord();
        unset($record['citation']);

        self::assertTrue((new ConformanceLevel())->isComplete($record));
    }

    #[Test]
    #[DataProvider('incompleteRecordDataProvider')]
    public function incompleteRecordIsRecognised(array $record): void
    {
        self::assertFalse((new ConformanceLevel())->isComplete($record));
    }

    public static function levelDataProvider(): \Generator
    {
        $complete = self::completeRecord();
        $withFacts = $complete + ['claim' => [['@type' => 'Claim']]];
        $incomplete = ['author' => []] + $withFacts;

        yield 'incomplete record' => [$incomplete, true, true, 1];
        yield 'complete record, unsigned' => [$withFacts, false, true, 2];
        yield 'signed without audit endpoint' => [$withFacts, true, false, 3];
        yield 'signed with audit endpoint and facts' => [$withFacts, true, true, 4];
        yield 'signed with audit endpoint and no facts' => [$complete, true, true, 4];
    }

    #[Test]
    #[DataProvider('levelDataProvider')]
    public function levelIsTheHighestOneWhoseRequirementsAreMet(array $record, bool $signed, bool $auditEndpoint, int $expected): void
    {
        self::assertSame($expected, (new ConformanceLevel())->of($record, $signed, $auditEndpoint));
    }

    public static function missingDataProvider(): \Generator
    {
        yield 'complete record' => [self::completeRecord(), []];
        yield 'no vocabulary' => [
            ['@context' => 'https://schema.org'] + self::completeRecord(),
            ['The record does not use the vocabulary https://cms-provenance.org/ns/v1.'],
        ];
        foreach (['@type', '@id', 'datePublished', 'lastSubstantiveRevision', 'generator'] as $member) {
            $record = self::completeRecord();
            unset($record[$member]);
            yield 'without ' . $member => [$record, ['The record has no ' . $member . '.']];
        }
        yield 'no author' => [['author' => []] + self::completeRecord(), ['The record names no author.']];
        yield 'author without URI' => [
            ['author' => [['@type' => 'Person', 'name' => 'Ada']]] + self::completeRecord(),
            ['Author "Ada" has no profile URI.'],
        ];
        yield 'author without name and URI' => [
            ['author' => [['@type' => 'Person']]] + self::completeRecord(),
            ['An author has no profile URI.'],
        ];
        yield 'source without access date' => [
            ['citation' => [['url' => 'https://example.org/a', 'name' => 'A']]] + self::completeRecord(),
            ['Source https://example.org/a has no access date.'],
        ];
        yield 'source without description' => [
            ['citation' => [['url' => 'https://example.org/a', 'accessDate' => '2026-11-13']]] + self::completeRecord(),
            ['Source https://example.org/a has no description.'],
        ];
        $record = ['author' => [['@type' => 'Person', 'name' => 'Ada']], 'citation' => [['url' => 'https://example.org/a']]] + self::completeRecord();
        unset($record['generator']);
        yield 'several problems in order' => [$record, [
            'The record has no generator.',
            'Author "Ada" has no profile URI.',
            'Source https://example.org/a has no description.',
            'Source https://example.org/a has no access date.',
        ]];
    }

    /**
     * @param array<string, mixed> $record
     * @param list<string> $expected
     */
    #[Test]
    #[DataProvider('missingDataProvider')]
    public function missingExplainsWhatKeepsTheRecordBelowL2(array $record, array $expected): void
    {
        self::assertSame($expected, (new ConformanceLevel())->missing($record));
    }
}
