<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class MxMetaTagsOutputTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/MxPages.csv');
        $this->setUpFrontendRootPage(1, ['EXT:provenance/Tests/Functional/Frontend/Fixtures/mx.typoscript']);
        $this->get(SiteWriter::class)->createNewBasicSite('main', 1, 'http://localhost/');
    }

    #[Test]
    public function pageWithRecordCarriesMxTags(): void
    {
        $this->setTrainingDataPolicy('Permitted with attribution.');
        $tags = $this->metaTags('/');

        self::assertSame('Ada Lovelace', $tags['author']);
        self::assertSame('http://localhost/', $tags['mx:canonicalUri']);
        self::assertSame('What the report says & why.', $tags['mx:summary']);
        self::assertStringContainsString('content-provenance-spec', $tags['mx:conformsTo']);
        self::assertSame('Permitted with attribution.', $tags['mx:trainingDataPolicy']);
        self::assertSame('2026-05-14', $tags['mx:created']);
        self::assertSame('2026-11-01', $tags['mx:maintainedDate']);
    }

    #[Test]
    public function summaryFallsBackToTheDescription(): void
    {
        $tags = $this->metaTags('/description-only');

        self::assertSame('Only a description.', $tags['mx:summary']);
        self::assertSame('http://localhost/description-only', $tags['mx:canonicalUri']);
        self::assertArrayNotHasKey('mx:maintainedDate', $tags);
    }

    #[Test]
    public function pageWithoutRecordHasNoMxTags(): void
    {
        self::assertSame([], array_filter($this->metaTags('/without-record'), static fn(string $name): bool => str_starts_with($name, 'mx:'), ARRAY_FILTER_USE_KEY));
    }

    #[Test]
    public function withoutAPolicyTheTagIsLeftOut(): void
    {
        $this->setTrainingDataPolicy('');

        self::assertArrayNotHasKey('mx:trainingDataPolicy', $this->metaTags('/'));
    }

    private function setTrainingDataPolicy(string $policy): void
    {
        $this->get(SiteWriter::class)->writeSettings('main', ['provenance' => ['mx' => ['trainingDataPolicy' => $policy]]]);
        // Site settings are cached across the tests of this class.
        $this->get(CacheManager::class)->flushCaches();
    }

    /**
     * @return array<string, string> content by meta name
     */
    private function metaTags(string $path): array
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost' . $path));
        self::assertSame(200, $response->getStatusCode());
        preg_match_all('#<meta name="([^"]+)" content="([^"]*)"#', (string)$response->getBody(), $matches, PREG_SET_ORDER);
        $tags = [];
        foreach ($matches as [, $name, $content]) {
            $tags[$name] = html_entity_decode($content, ENT_QUOTES | ENT_HTML5);
        }

        return $tags;
    }
}
