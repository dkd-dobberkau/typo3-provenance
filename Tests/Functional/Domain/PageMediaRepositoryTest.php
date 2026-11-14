<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\Domain;

use CmsProvenance\Provenance\Domain\Model\MediaItem;
use CmsProvenance\Provenance\Domain\Repository\PageMediaRepository;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
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

    #[Test]
    public function workspaceDraftsOfMetadataAreIgnored(): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable('sys_file_metadata')->insert('sys_file_metadata', [
            'uid' => 99, 'file' => 1, 'sys_language_uid' => 0, 't3ver_wsid' => 1, 't3ver_oid' => 1,
            'creator' => 'Draft creator', 'tx_provenance_license' => 'https://example.org/draft-licence',
        ]);

        $photo = $this->find(1, 0)[0];

        self::assertSame('Ada Lovelace', $photo->creator);
        self::assertSame('https://creativecommons.org/licenses/by/4.0/', $photo->license);
        self::assertSame(1, $photo->metadataUid);
    }

    #[Test]
    public function referencesInFieldsTheContentTypeDoesNotShowAreSkipped(): void
    {
        $connection = $this->get(ConnectionPool::class);
        // Switched from textmedia to text: the old asset reference stays in the database.
        $connection->getConnectionForTable('tt_content')->insert('tt_content', ['uid' => 20, 'pid' => 1, 'colPos' => 2, 'sorting' => 1, 'CType' => 'text']);
        $connection->getConnectionForTable('sys_file_reference')->insert('sys_file_reference', ['uid' => 20, 'pid' => 1, 'uid_local' => 5, 'uid_foreign' => 20, 'tablenames' => 'tt_content', 'fieldname' => 'assets']);
        // A textmedia element shows "assets", not "image".
        $connection->getConnectionForTable('sys_file_reference')->insert('sys_file_reference', ['uid' => 21, 'pid' => 1, 'uid_local' => 6, 'uid_foreign' => 12, 'tablenames' => 'tt_content', 'fieldname' => 'image']);

        $names = array_map(static fn(MediaItem $item): string => $item->name, $this->find(1, 0));

        self::assertSame(['photo.jpg', 'clip.mp4', 'song.mp3'], $names);
    }

    #[Test]
    public function connectedTranslationsFallBackToDefaultElementsInMixedMode(): void
    {
        $this->addTranslationOfElement11WithSong();

        // 11 is shown as its translation (song), 10 and 12 untranslated from the default language.
        self::assertSame(['song.mp3', 'clip.mp4', 'photo.jpg'], $this->names(1, 1, LanguageAspect::OVERLAYS_MIXED));
    }

    #[Test]
    public function hiddenUntranslatedElementsAreLeftOutWhenOverlaysAreStrict(): void
    {
        $this->addTranslationOfElement11WithSong();

        self::assertSame(['song.mp3'], $this->names(1, 1, LanguageAspect::OVERLAYS_ON));
        // Element 14 is a translation without a default-language parent.
        self::assertSame(['song.mp3', 'photo.jpg'], $this->names(1, 1, LanguageAspect::OVERLAYS_ON_WITH_FLOATING));
    }

    private function addTranslationOfElement11WithSong(): void
    {
        $connection = $this->get(ConnectionPool::class);
        $connection->getConnectionForTable('tt_content')->insert('tt_content', ['uid' => 30, 'pid' => 1, 'colPos' => 0, 'sorting' => 128, 'sys_language_uid' => 1, 'l18n_parent' => 11, 'CType' => 'textmedia']);
        $connection->getConnectionForTable('sys_file_reference')->insert('sys_file_reference', ['uid' => 30, 'pid' => 1, 'uid_local' => 7, 'uid_foreign' => 30, 'tablenames' => 'tt_content', 'fieldname' => 'assets', 'sys_language_uid' => 1]);
    }

    /**
     * @return list<string>
     */
    private function names(int $pageUid, int $languageId, string $overlayType): array
    {
        return array_map(
            static fn(MediaItem $item): string => $item->name,
            $this->get(PageMediaRepository::class)->findByPage($pageUid, $languageId, $overlayType)
        );
    }

    /**
     * @return list<MediaItem>
     */
    private function find(int $pageUid, int $languageId): array
    {
        return $this->get(PageMediaRepository::class)->findByPage($pageUid, $languageId);
    }
}
