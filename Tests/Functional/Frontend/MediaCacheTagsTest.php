<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * A cached page names the files its record lists, so changing one of them
 * can flush exactly the pages that need a new record.
 */
final class MediaCacheTagsTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance'];

    // The testing framework disables the page cache; this test needs a real one.
    protected array $configurationToUseInTestInstance = [
        'SYS' => ['caching' => ['cacheConfigurations' => ['pages' => [
            'backend' => Typo3DatabaseBackend::class,
        ]]]],
    ];

    #[Test]
    public function cachedPageIsTaggedWithItsFiles(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProvenancePages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/MediaPages.csv');
        $this->setUpFrontendRootPage(1, ['EXT:provenance/Tests/Functional/Frontend/Fixtures/page.typoscript']);
        $this->get(SiteWriter::class)->createNewBasicSite('main', 1, 'http://localhost/');

        $this->executeFrontendSubRequest(new InternalRequest('http://localhost/'));

        $backend = $this->get(CacheManager::class)->getCache('pages')->getBackend();
        self::assertNotSame([], $backend->findIdentifiersByTag('sys_file_1'));
        self::assertNotSame([], $backend->findIdentifiersByTag('sys_file_2'));
    }
}
