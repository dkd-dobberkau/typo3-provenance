<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Unit\Frontend;

use CmsProvenance\Provenance\Frontend\MxMetaTagBuilder;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class MxMetaTagBuilderTest extends UnitTestCase
{
    private const JSON_LD = [
        'author' => [
            ['@type' => 'Person', '@id' => 'https://orcid.org/0000-0001-2345-6789', 'name' => 'Ada Lovelace'],
            ['@type' => 'Person', 'name' => 'Grace Hopper'],
        ],
        'datePublished' => '2026-05-14T09:00:00Z',
        'dateModified' => '2026-11-13T10:30:00Z',
        'lastSubstantiveRevision' => '2026-06-01T08:00:00Z',
        'maintainedDate' => '2026-11-01T12:00:00Z',
    ];

    #[Test]
    public function mapsTheRecordToMxFields(): void
    {
        $tags = (new MxMetaTagBuilder())->build(self::JSON_LD, 'https://example.org/report', 'What the report says.', 'Permitted with attribution.');

        self::assertSame([
            'author' => 'Ada Lovelace, Grace Hopper',
            'mx:canonicalUri' => 'https://example.org/report',
            'mx:summary' => 'What the report says.',
            'mx:conformsTo' => MxMetaTagBuilder::SPECIFICATION,
            'mx:trainingDataPolicy' => 'Permitted with attribution.',
            'mx:created' => '2026-05-14',
            'mx:modified' => '2026-11-13',
            'mx:maintainedDate' => '2026-11-01',
        ], $tags);
    }

    #[Test]
    public function fieldsWithoutAValueAreLeftOut(): void
    {
        $tags = (new MxMetaTagBuilder())->build(['datePublished' => '2026-05-14T09:00:00Z'], 'https://example.org/report', '  ', '');

        self::assertSame(
            ['mx:canonicalUri' => 'https://example.org/report', 'mx:conformsTo' => MxMetaTagBuilder::SPECIFICATION, 'mx:created' => '2026-05-14'],
            $tags,
        );
    }

    #[Test]
    public function modifiedFallsBackToTheLastSubstantiveRevision(): void
    {
        $jsonLd = self::JSON_LD;
        unset($jsonLd['dateModified']);

        $tags = (new MxMetaTagBuilder())->build($jsonLd, 'https://example.org/report', '', '');

        self::assertSame('2026-06-01', $tags['mx:modified']);
    }

    #[Test]
    public function rendersEscapedMetaElements(): void
    {
        $html = (new MxMetaTagBuilder())->render(['mx:summary' => 'Fish & "chips" <b>']);

        self::assertSame('<meta name="mx:summary" content="Fish &amp; &quot;chips&quot; &lt;b&gt;">', $html);
    }
}
