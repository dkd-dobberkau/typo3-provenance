<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\Command;

use CmsProvenance\Provenance\Command\BackfillMediaCommand;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class BackfillMediaCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance'];

    // The testing framework disables the page cache; the flush test needs a real one.
    protected array $configurationToUseInTestInstance = [
        'SYS' => ['caching' => ['cacheConfigurations' => ['pages' => [
            'backend' => Typo3DatabaseBackend::class,
        ]]]],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Media/Fixtures/Files.csv');
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('sys_file');
        // A second image next to photo.jpg; manual.pdf and clip.youtube are no media.
        $connection->insert('sys_file', ['uid' => 4, 'storage' => 1, 'identifier' => '/logo.png', 'name' => 'logo.png', 'extension' => 'png', 'mime_type' => 'image/png']);
        @mkdir($this->instancePath . '/fileadmin', 0777, true);
        foreach ([1 => 'photo.jpg', 4 => 'logo.png'] as $uid => $name) {
            file_put_contents($this->instancePath . '/fileadmin/' . $name, $name);
            $connection->update('sys_file', ['sha1' => sha1($name)], ['uid' => $uid]);
        }
    }

    #[Test]
    public function fillsIdentifierAndHashOfUnidentifiedMedia(): void
    {
        $tester = $this->runBackfill([]);

        self::assertStringContainsString('2 media files', $tester->getDisplay());
        self::assertSame(hash('sha256', 'photo.jpg'), BackendUtility::getRecord('sys_file', 1)['tx_provenance_sha256']);
        self::assertSame(hash('sha256', 'logo.png'), BackendUtility::getRecord('sys_file', 4)['tx_provenance_sha256']);
        self::assertSame('', BackendUtility::getRecord('sys_file', 2)['tx_provenance_media_uri']);
    }

    #[Test]
    public function pagesListingABackfilledFileAreFlushed(): void
    {
        $cache = $this->get(CacheManager::class)->getCache('pages');
        $cache->set('page-with-photo', 'html', ['sys_file_1']);

        $this->runBackfill([]);

        self::assertFalse($cache->has('page-with-photo'));
    }

    #[Test]
    public function dryRunWritesNothing(): void
    {
        $tester = $this->runBackfill(['--dry-run' => true]);

        self::assertStringContainsString('2 media files', $tester->getDisplay());
        self::assertSame('', BackendUtility::getRecord('sys_file', 1)['tx_provenance_media_uri']);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function runBackfill(array $options): CommandTester
    {
        $tester = new CommandTester($this->get(BackfillMediaCommand::class));
        $tester->execute($options);
        self::assertSame(0, $tester->getStatusCode());

        return $tester;
    }
}
