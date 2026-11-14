<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\Frontend;

use B13\AiBotsLoveMarkdown\Event\AfterFrontMatterForPageIsCreatedEvent;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The Markdown version of a page (EXT:ai_bots_love_markdown) names its
 * provenance record and points to the signed HTML. The page is rendered for
 * real; the event is dispatched as the extension does it, without loading the
 * extension, whose packaging triggers a TYPO3 deprecation.
 */
final class MarkdownFrontMatterTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance'];

    private const B13_FRONT_MATTER = [
        'title' => 'Root',
        'url' => 'http://localhost/',
        'author' => 'Ada Lovelace',
        'date' => '2026-05-01',
        'modified' => '2026-05-14',
        'type' => 'website',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/MxPages.csv');
        $this->setUpFrontendRootPage(1, ['EXT:provenance/Tests/Functional/Frontend/Fixtures/mx.typoscript']);
        $this->get(SiteWriter::class)->createNewBasicSite('main', 1, 'http://localhost/');
    }

    #[Test]
    public function frontMatterNamesTheRecordAndTheSignedDocument(): void
    {
        $cps = $this->frontMatter()['cps'];

        self::assertSame('urn:uuid:7c2f0e1a-0000-4000-8000-000000000011', $cps['record']);
        self::assertSame(2, $cps['conformance']);
        self::assertSame('http://localhost/', $cps['signedDocument']);
    }

    #[Test]
    public function mxFieldsFollowTheThreeZones(): void
    {
        $frontMatter = $this->frontMatter();

        self::assertSame('info-doc', $frontMatter['type']);
        self::assertSame('2026-05-14', $frontMatter['created']);
        self::assertArrayNotHasKey('date', $frontMatter);
        self::assertSame('http://localhost/', $frontMatter['mx']['canonicalUri']);
        self::assertSame('2026-11-01', $frontMatter['mx']['maintainedDate']);
    }

    /**
     * @return array<string, mixed>
     */
    private function frontMatter(): array
    {
        $html = (string)$this->executeFrontendSubRequest(new InternalRequest('http://localhost/'))->getBody();
        $event = new AfterFrontMatterForPageIsCreatedEvent(self::B13_FRONT_MATTER, ['uid' => 1], $html, null, null);

        return $this->get(EventDispatcherInterface::class)->dispatch($event)->frontMatter;
    }
}
