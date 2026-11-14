<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\Domain;

use CmsProvenance\Provenance\Domain\Repository\PageMediaRepository;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * EXT:filemetadata is only suggested; without it there is no creator,
 * copyright or source, but licence and source type still come through.
 */
final class PageMediaWithoutFilemetadataTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance'];

    #[Test]
    public function mediaAreListedWithoutTheFilemetadataFields(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PageMediaWithoutFilemetadata.csv');

        $photo = $this->get(PageMediaRepository::class)->findByPage(1, 0)[0];

        self::assertSame('photo.jpg', $photo->name);
        self::assertNull($photo->creator);
        self::assertSame('https://creativecommons.org/licenses/by/4.0/', $photo->license);
    }
}
