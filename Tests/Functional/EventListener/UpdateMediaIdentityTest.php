<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Event\BeforeFileDeletedEvent;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class UpdateMediaIdentityTest extends FunctionalTestCase
{
    // 1x1 GIFs that differ in their palette; FAL checks that bytes match the extension.
    private const GIF_WHITE = "GIF89a\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";
    private const GIF_BLACK = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";

    protected array $testExtensionsToLoad = ['cms-provenance/provenance'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Media/Fixtures/Files.csv');
        $this->setUpBackendUser(1);
        @mkdir($this->instancePath . '/fileadmin', 0777, true);
    }

    #[Test]
    public function addedFileGetsIdentifierAndHash(): void
    {
        $file = $this->addFile('new.gif', self::GIF_WHITE);

        $row = $this->fileRow($file->getUid());
        self::assertMatchesRegularExpression('/^urn:uuid:/', $row['tx_provenance_media_uri']);
        self::assertSame(hash('sha256', self::GIF_WHITE), $row['tx_provenance_sha256']);
    }

    #[Test]
    public function replacedFileGetsNewHashAndClosesHistory(): void
    {
        $file = $this->addFile('new.gif', self::GIF_WHITE);
        $replacement = $this->instancePath . '/replacement.gif';
        file_put_contents($replacement, self::GIF_BLACK);

        $this->storage()->replaceFile($file, $replacement);

        self::assertSame(hash('sha256', self::GIF_BLACK), $this->fileRow($file->getUid())['tx_provenance_sha256']);
        $history = $this->history();
        self::assertCount(2, $history);
        self::assertNotNull($history[0]['valid_until']);
        self::assertNull($history[1]['valid_until']);
    }

    #[Test]
    public function deletedFileClosesHistory(): void
    {
        $file = $this->addFile('new.gif', self::GIF_WHITE);

        $this->storage()->deleteFile($file);

        self::assertNotNull($this->history()[0]['valid_until']);
    }

    #[Test]
    public function deletingAProcessedFileLeavesOtherHistoriesOpen(): void
    {
        $file = $this->addFile('new.gif', self::GIF_WHITE);
        // A thumbnail whose sys_file_processedfile uid equals the uid of a media file.
        $thumbnail = new ProcessedFile($file, ProcessedFile::CONTEXT_IMAGEPREVIEW, [], ['uid' => $file->getUid(), 'identifier' => '/_processed_/t.gif', 'name' => 't.gif', 'storage' => 1, 'originalfilesha1' => $file->getSha1(), 'task_type' => ProcessedFile::CONTEXT_IMAGEPREVIEW, 'configuration' => '']);

        $this->get(EventDispatcherInterface::class)->dispatch(new BeforeFileDeletedEvent($thumbnail));

        self::assertNull($this->history()[0]['valid_until']);
    }

    private function addFile(string $name, string $content): File
    {
        $source = $this->instancePath . '/' . $name;
        file_put_contents($source, $content);
        $storage = $this->storage();

        return $storage->addFile($source, $storage->getRootLevelFolder(), $name);
    }

    private function storage(): ResourceStorage
    {
        return $this->get(StorageRepository::class)->findByUid(1);
    }

    /**
     * @return array<string, mixed>
     */
    private function fileRow(int $uid): array
    {
        return $this->get(ConnectionPool::class)->getConnectionForTable('sys_file')
            ->select(['*'], 'sys_file', ['uid' => $uid])->fetchAssociative();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function history(): array
    {
        return $this->get(ConnectionPool::class)->getConnectionForTable('tx_provenance_media_hash')
            ->select(['*'], 'tx_provenance_media_hash', [], [], ['uid' => 'ASC'])->fetchAllAssociative();
    }
}
