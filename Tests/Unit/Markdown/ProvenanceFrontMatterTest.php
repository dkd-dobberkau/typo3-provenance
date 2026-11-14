<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Unit\Markdown;

use CmsProvenance\Provenance\Markdown\ProvenanceFrontMatter;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ProvenanceFrontMatterTest extends UnitTestCase
{
    private const RECORD = '{"@context":["https://schema.org","https://cms-provenance.org/ns/v1"],"@type":"WebPage",'
        . '"@id":"urn:uuid:00000000-0000-4000-8000-000000000001","cpsConformance":4,'
        . '"key":"https://example.org/.well-known/content-provenance/keys/key-1",'
        . '"contentHash":"sha256:abc","signature":{"algorithm":"Ed25519","keyId":"key-1","signatureValue":"xyz"}}';

    private const MX_TAGS = '<meta name="author" content="Ada Lovelace">'
        . '<meta name="mx:canonicalUri" content="https://example.org/page">'
        . '<meta name="mx:summary" content="A page.">'
        . '<meta name="mx:conformsTo" content="https://example.org/spec">'
        . '<meta name="mx:trainingDataPolicy" content="Permitted with attribution.">'
        . '<meta name="mx:created" content="2026-05-14">'
        . '<meta name="mx:modified" content="2026-11-14">'
        . '<meta name="mx:maintainedDate" content="2026-11-01">';

    private const B13 = [
        'title' => 'Page',
        'url' => 'https://example.org/page',
        'description' => 'A page.',
        'author' => 'Ada Lovelace',
        'date' => '2026-05-01',
        'modified' => '2026-11-10',
        'keywords' => ['Museum', 'Provenance'],
        'categories' => ['History'],
        'type' => 'website',
    ];

    #[Test]
    public function signedRecordIsReferencedNotClaimedForTheMarkdown(): void
    {
        $frontMatter = $this->extend(self::B13, $this->page(self::RECORD, ''));

        self::assertSame([
            'record' => 'urn:uuid:00000000-0000-4000-8000-000000000001',
            'conformance' => 4,
            'signedDocument' => 'https://example.org/page',
            'contentHash' => 'sha256:abc',
            'key' => 'https://example.org/.well-known/content-provenance/keys/key-1',
        ], $frontMatter['cps']);
    }

    #[Test]
    public function withoutMxTagsTheB13FieldsStayAsTheyAre(): void
    {
        $frontMatter = $this->extend(self::B13, $this->page(self::RECORD, ''));

        unset($frontMatter['cps']);
        self::assertSame(self::B13, $frontMatter);
    }

    #[Test]
    public function mxTagsBecomeTheThreeZonesOfMxFrontMatter(): void
    {
        $frontMatter = $this->extend(self::B13, $this->page(self::RECORD, self::MX_TAGS));

        self::assertSame(
            ['title', 'description', 'author', 'created', 'modified', 'type', 'tags', 'url', 'keywords', 'categories', 'mx', 'cps'],
            array_keys($frontMatter)
        );
        self::assertSame('2026-05-14', $frontMatter['created']);
        self::assertSame('2026-11-14', $frontMatter['modified']);
        self::assertSame('info-doc', $frontMatter['type']);
        self::assertSame(['museum', 'provenance', 'history'], $frontMatter['tags']);
        self::assertSame([
            'canonicalUri' => 'https://example.org/page',
            'summary' => 'A page.',
            'conformsTo' => 'https://example.org/spec',
            'trainingDataPolicy' => 'Permitted with attribution.',
            'maintainedDate' => '2026-11-01',
        ], $frontMatter['mx']);
    }

    #[Test]
    public function unsignedRecordHasNoHashOrKey(): void
    {
        $record = '{"@context":["https://schema.org","https://cms-provenance.org/ns/v1"],"@id":"urn:uuid:1","cpsConformance":2}';

        self::assertSame(
            ['record' => 'urn:uuid:1', 'conformance' => 2, 'signedDocument' => 'https://example.org/page'],
            $this->extend(self::B13, $this->page($record, ''))['cps']
        );
    }

    #[Test]
    public function pageWithoutRecordIsLeftAlone(): void
    {
        $html = '<html><head><script type="application/ld+json">{"@context":"https://schema.org","@type":"WebPage"}</script></head></html>';

        self::assertSame(self::B13, $this->extend(self::B13, $html));
    }

    /**
     * @param array<string, mixed> $frontMatter
     * @return array<string, mixed>
     */
    private function extend(array $frontMatter, string $html): array
    {
        return (new ProvenanceFrontMatter())->extend($frontMatter, $html);
    }

    private function page(string $record, string $metaTags): string
    {
        return '<!DOCTYPE html><html><head><link rel="canonical" href="https://example.org/page">' . $metaTags
            . '<script type="application/ld+json">' . $record . '</script></head><body><main>Text</main></body></html>';
    }
}
