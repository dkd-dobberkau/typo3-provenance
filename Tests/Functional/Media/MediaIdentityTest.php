<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\Media;

use CmsProvenance\Provenance\Media\MediaIdentity;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class MediaIdentityTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Files.csv');
        $this->writeFile(1, 'photo.jpg', 'first version');
        $this->writeFile(2, 'manual.pdf', 'a manual');
    }

    #[Test]
    public function assignsIdentifierAndHash(): void
    {
        self::assertTrue($this->get(MediaIdentity::class)->update(1));

        $file = BackendUtility::getRecord('sys_file', 1);
        self::assertMatchesRegularExpression('/^urn:uuid:[0-9a-f-]{36}$/', $file['tx_provenance_media_uri']);
        self::assertSame(hash('sha256', 'first version'), $file['tx_provenance_sha256']);
        $history = $this->history();
        self::assertCount(1, $history);
        self::assertSame($file['tx_provenance_media_uri'], $history[0]['media_uri']);
        self::assertSame(hash('sha256', 'first version'), $history[0]['sha256']);
        self::assertNull($history[0]['valid_until']);
    }

    #[Test]
    public function keepsIdentifierWhenBytesChange(): void
    {
        $identity = $this->get(MediaIdentity::class);
        $identity->update(1);
        $uri = BackendUtility::getRecord('sys_file', 1)['tx_provenance_media_uri'];

        $this->writeFile(1, 'photo.jpg', 'second version');
        self::assertTrue($identity->update(1));

        $file = BackendUtility::getRecord('sys_file', 1);
        self::assertSame($uri, $file['tx_provenance_media_uri']);
        self::assertSame(hash('sha256', 'second version'), $file['tx_provenance_sha256']);
        $history = $this->history();
        self::assertCount(2, $history);
        self::assertSame(hash('sha256', 'first version'), $history[0]['sha256']);
        self::assertNotNull($history[0]['valid_until']);
        self::assertSame(hash('sha256', 'second version'), $history[1]['sha256']);
        self::assertNull($history[1]['valid_until']);
    }

    #[Test]
    public function unchangedBytesWriteNothing(): void
    {
        $identity = $this->get(MediaIdentity::class);
        $identity->update(1);

        self::assertFalse($identity->update(1));
        self::assertCount(1, $this->history());
    }

    #[Test]
    public function unreadableFileIsLoggedAndKeepsOldHash(): void
    {
        $identity = $this->get(MediaIdentity::class);
        $identity->update(1);
        unlink($this->instancePath . '/fileadmin/photo.jpg');
        $this->setSha1(1, sha1('gone'));

        self::assertFalse($identity->update(1));
        self::assertSame(hash('sha256', 'first version'), BackendUtility::getRecord('sys_file', 1)['tx_provenance_sha256']);
        self::assertCount(1, $this->history());
    }

    #[Test]
    public function nonMediaFilesAreSkipped(): void
    {
        self::assertFalse($this->get(MediaIdentity::class)->update(2));
        self::assertSame('', BackendUtility::getRecord('sys_file', 2)['tx_provenance_media_uri']);
    }

    #[Test]
    public function onlineMediaHelpersAreNotMedia(): void
    {
        $identity = $this->get(MediaIdentity::class);

        self::assertFalse($identity->isMedia(['mime_type' => 'video/youtube', 'extension' => 'youtube']));
        self::assertTrue($identity->isMedia(['mime_type' => 'audio/mpeg', 'extension' => 'mp3']));
        self::assertFalse($identity->isMedia(['mime_type' => 'application/pdf', 'extension' => 'pdf']));
    }

    #[Test]
    public function retireClosesHistory(): void
    {
        $identity = $this->get(MediaIdentity::class);
        $identity->update(1);

        $identity->retire(1);

        self::assertNotNull($this->history()[0]['valid_until']);
    }

    private function writeFile(int $uid, string $name, string $content): void
    {
        $path = $this->instancePath . '/fileadmin/' . $name;
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $content);
        $this->setSha1($uid, sha1($content));
    }

    private function setSha1(int $uid, string $sha1): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable('sys_file')
            ->update('sys_file', ['sha1' => $sha1, 'size' => 1], ['uid' => $uid]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function history(): array
    {
        return $this->get(ConnectionPool::class)->getConnectionForTable('tx_provenance_media_hash')
            ->select(['*'], 'tx_provenance_media_hash', [], [], ['uid' => 'ASC'])
            ->fetchAllAssociative();
    }
}
