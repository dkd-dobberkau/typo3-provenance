<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class JsonLdOutputTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProvenancePages.csv');
        $this->setUpFrontendRootPage(1, ['EXT:provenance/Tests/Functional/Frontend/Fixtures/page.typoscript']);
        $this->get(SiteWriter::class)->createNewBasicSite('main', 1, 'http://localhost/');
    }

    #[Test]
    public function pageWithRecordContainsJsonLdBlock(): void
    {
        $jsonLd = $this->fetchJsonLd('/');

        self::assertNotNull($jsonLd);
        self::assertSame('urn:uuid:7c2f0e1a-0000-4000-8000-000000000001', $jsonLd['@id']);
        self::assertSame('Root', $jsonLd['headline']);
        self::assertSame(2, $jsonLd['cpsConformance']);
        self::assertSame('2026-05-14T09:00:00Z', $jsonLd['datePublished']);
        self::assertStringStartsWith('TYPO3 ', $jsonLd['generator']);
    }

    #[Test]
    public function authorsExposeIdentityAndNameButNeverTheUsername(): void
    {
        $response = $this->fetchBody('/');
        $jsonLd = $this->extractJsonLd($response);

        self::assertSame(
            [['@type' => 'Person', '@id' => 'https://orcid.org/0000-0001-2345-6789', 'name' => 'Ada Lovelace']],
            $jsonLd['author']
        );
        self::assertStringNotContainsString('editor-ada', $response);
        self::assertStringNotContainsString('editor-anon', $response);
    }

    #[Test]
    public function sourceTextCannotBreakOutOfTheScriptElement(): void
    {
        $response = $this->fetchBody('/');

        self::assertStringNotContainsString('<script>alert(1)</script>', $response);
        self::assertSame(
            'Breaks out of </script><script>alert(1)</script>',
            $this->extractJsonLd($response)['citation'][0]['name']
        );
    }

    #[Test]
    public function pageWithoutRecordHasNoJsonLdBlock(): void
    {
        self::assertNull($this->fetchJsonLd('/without-record'));
    }

    #[Test]
    public function hiddenRecordIsNotPublished(): void
    {
        self::assertNull($this->fetchJsonLd('/hidden-record'));
    }

    private function fetchJsonLd(string $path): ?array
    {
        return $this->extractJsonLd($this->fetchBody($path));
    }

    private function fetchBody(string $path): string
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost' . $path));
        self::assertSame(200, $response->getStatusCode());

        return (string)$response->getBody();
    }

    private function extractJsonLd(string $html): ?array
    {
        if (!preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches)) {
            return null;
        }

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }
}
