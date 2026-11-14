<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\Domain;

use CmsProvenance\Provenance\Domain\Model\MediaItem;
use CmsProvenance\Provenance\Domain\Repository\PageMediaRepository;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class PageMediaRepositoryTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['filemetadata'];
    protected array $testExtensionsToLoad = ['cms-provenance/provenance'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PageMedia.csv');
    }

    #[Test]
    public function listsMediaInPageOrder(): void
    {
        // Column 0 by sorting (element 11, then 10), then column 1 (element 12).
        $names = array_map(static fn(MediaItem $item): string => $item->name, $this->find(1, 0));

        self::assertSame(['photo.jpg', 'clip.mp4', 'song.mp3'], $names);
    }

    #[Test]
    public function itemCarriesIdentityAndMetadata(): void
    {
        $photo = $this->find(1, 0)[0];

        self::assertSame(1, $photo->fileUid);
        self::assertSame(1, $photo->metadataUid);
        self::assertSame('image/jpeg', $photo->mimeType);
        self::assertStringEndsWith('fileadmin/photo.jpg', (string)$photo->publicUrl);
        self::assertSame('urn:uuid:00000000-0000-4000-8000-000000000001', $photo->mediaUri);
        self::assertSame(str_repeat('1', 64), $photo->sha256);
        self::assertSame('Ada Lovelace', $photo->creator);
        self::assertSame('© Ada', $photo->copyright);
        self::assertSame('https://example.org/original', $photo->source);
        self::assertSame('https://creativecommons.org/licenses/by/4.0/', $photo->license);
        self::assertSame('digitalCapture', $photo->digitalSourceType);
    }

    #[Test]
    public function fileUsedTwiceIsListedOnce(): void
    {
        $uids = array_map(static fn(MediaItem $item): int => $item->fileUid, $this->find(1, 0));

        self::assertSame([1, 2, 7], $uids);
    }

    #[Test]
    public function hiddenContentAndHiddenReferencesAreSkipped(): void
    {
        $names = array_map(static fn(MediaItem $item): string => $item->name, $this->find(1, 0));

        self::assertNotContains('hidden-ref.png', $names);
        self::assertNotContains('in-hidden-element.png', $names);
    }

    #[Test]
    public function translatedMetadataIsUsedExceptLicenceAndSourceType(): void
    {
        $items = $this->find(1, 1);

        self::assertCount(1, $items);
        self::assertSame('Ada Lovelace (fr)', $items[0]->creator);
        self::assertSame(2, $items[0]->metadataUid);
        self::assertSame('https://creativecommons.org/licenses/by/4.0/', $items[0]->license);
        self::assertSame('digitalCapture', $items[0]->digitalSourceType);
    }

    #[Test]
    public function pdfAndOnlineMediaAreSkipped(): void
    {
        $names = array_map(static fn(MediaItem $item): string => $item->name, $this->find(1, 0));

        self::assertNotContains('manual.pdf', $names);
        self::assertNotContains('talk.youtube', $names);
    }

    #[Test]
    public function staleHashIsNullAndEmptyFieldsAreNull(): void
    {
        $clip = $this->find(1, 0)[1];

        self::assertNull($clip->sha256);
        self::assertNull($clip->creator);
        self::assertNull($clip->license);
        self::assertNull($clip->digitalSourceType);
    }

    #[Test]
    public function pageWithoutMediaGivesEmptyList(): void
    {
        self::assertSame([], $this->find(2, 0));
    }

    /**
     * @return list<MediaItem>
     */
    private function find(int $pageUid, int $languageId): array
    {
        return $this->get(PageMediaRepository::class)->findByPage($pageUid, $languageId);
    }
}
