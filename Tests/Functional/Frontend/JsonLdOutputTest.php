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
    public function recordWithoutAnAuthorUriClaimsOnlyLevelOne(): void
    {
        self::assertSame(1, $this->fetchJsonLd('/anonymous-author')['cpsConformance']);
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

    #[Test]
    public function recordListsImagesOfThePage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/MediaPages.csv');

        $image = $this->fetchJsonLd('/')['image'][0];

        self::assertSame('urn:uuid:00000000-0000-4000-8000-0000000000f1', $image['@id']);
        self::assertStringStartsWith('http://localhost/fileadmin/', $image['contentUrl']);
        self::assertSame(str_repeat('1', 64), $image['sha256']);
        self::assertSame('https://creativecommons.org/licenses/by/4.0/', $image['license']);
    }

    #[Test]
    public function incompleteMediaKeepLevelTwo(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/MediaPages.csv');

        $jsonLd = $this->fetchJsonLd('/');

        self::assertSame(2, $jsonLd['cpsConformance']);
        self::assertSame(['@type' => 'ImageObject', 'contentUrl' => 'http://localhost/fileadmin/legacy.png', 'encodingFormat' => 'image/png'], $jsonLd['image'][1]);
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
