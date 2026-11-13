<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\Domain;

use CmsProvenance\Provenance\Domain\Repository\PublicPageRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class PublicPageRepositoryTest extends FunctionalTestCase
{
    private const NOW = 1800000000;

    protected array $testExtensionsToLoad = ['cms-provenance/provenance'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PageTree.csv');
    }

    #[Test]
    public function publicPageUidsListsStandardPagesOfThePublicTreeOnly(): void
    {
        self::assertSame([1, 2, 3, 11], $this->get(PublicPageRepository::class)->publicPageUids(1, self::NOW));
    }

    public static function pageVisibilityDataProvider(): \Generator
    {
        yield 'public page' => [2, true];
        yield 'public page below a public page' => [3, true];
        yield 'page restricted to a group' => [4, false];
        yield 'page below a page restricted to a group' => [5, false];
        yield 'hidden page' => [6, false];
        yield 'page below a hidden page' => [7, false];
        yield 'page before its start time' => [8, false];
        yield 'page after its end time' => [9, false];
        yield 'page below a folder' => [11, true];
        yield 'deleted page' => [14, false];
        yield 'unknown page' => [999, false];
    }

    #[Test]
    #[DataProvider('pageVisibilityDataProvider')]
    public function isPublicChecksThePageAndAllItsParents(int $pageUid, bool $expected): void
    {
        self::assertSame($expected, $this->get(PublicPageRepository::class)->isPublic($pageUid, self::NOW));
    }
}
