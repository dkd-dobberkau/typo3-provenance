<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\Media;

use PHPUnit\Framework\Attributes\Test;
use CmsProvenance\Provenance\Hook\FlushMediaPages;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class MediaCacheFlushTest extends FunctionalTestCase
{
    private const GIF_WHITE = "GIF89a\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";
    private const GIF_BLACK = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";

    protected array $testExtensionsToLoad = ['cms-provenance/provenance'];

    // The testing framework disables the page cache; these tests need a real one.
    protected array $configurationToUseInTestInstance = [
        'SYS' => ['caching' => ['cacheConfigurations' => ['pages' => [
            'backend' => \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class,
        ]]]],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Files.csv');
        $this->get(ConnectionPool::class)->getConnectionForTable('sys_file_metadata')
            ->insert('sys_file_metadata', ['uid' => 1, 'file' => 1, 'sys_language_uid' => 0]);
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function metadataSaveFlushesTaggedPages(): void
    {
        $cache = $this->pageCache();
        $cache->set('page-with-file-1', 'html', ['sys_file_1']);
        $cache->set('page-with-file-2', 'html', ['sys_file_2']);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['sys_file_metadata' => [1 => ['tx_provenance_license' => 'https://creativecommons.org/licenses/by/4.0/']]], []);
        $dataHandler->process_datamap();

        self::assertFalse($cache->has('page-with-file-1'));
        self::assertTrue($cache->has('page-with-file-2'));
    }

    #[Test]
    public function publishingMetadataFromAWorkspaceFlushesTaggedPages(): void
    {
        $cache = $this->pageCache();
        $cache->set('page-with-file-1', 'html', ['sys_file_1']);

        $this->get(FlushMediaPages::class)->processCmdmap_postProcess(
            'version',
            'sys_file_metadata',
            1,
            ['action' => 'publish', 'swapWith' => 2],
            GeneralUtility::makeInstance(DataHandler::class)
        );

        self::assertFalse($cache->has('page-with-file-1'));
    }

    #[Test]
    public function fileReplaceFlushesTaggedPages(): void
    {
        @mkdir($this->instancePath . '/fileadmin', 0777, true);
        $source = $this->instancePath . '/new.gif';
        file_put_contents($source, self::GIF_WHITE);
        $storage = $this->get(StorageRepository::class)->findByUid(1);
        $file = $storage->addFile($source, $storage->getRootLevelFolder(), 'new.gif');
        $cache = $this->pageCache();
        $cache->set('page-with-new-file', 'html', ['sys_file_' . $file->getUid()]);
        $cache->set('unrelated-page', 'html', ['sys_file_999']);

        $replacement = $this->instancePath . '/replacement.gif';
        file_put_contents($replacement, self::GIF_BLACK);
        $storage->replaceFile($file, $replacement);

        self::assertFalse($cache->has('page-with-new-file'));
        self::assertTrue($cache->has('unrelated-page'));
    }

    private function pageCache(): FrontendInterface
    {
        return $this->get(CacheManager::class)->getCache('pages');
    }
}
