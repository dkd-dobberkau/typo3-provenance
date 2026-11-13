<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Unit\Service;

use CmsProvenance\Provenance\Service\AbsoluteUrlResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class AbsoluteUrlResolverTest extends UnitTestCase
{
    public static function siteUrlDataProvider(): \Generator
    {
        yield 'absolute base is kept' => ['https://example.org/', 'https://request.test/page', 'https://example.org/'];
        yield 'absolute base gets a trailing slash' => ['https://example.org/en', 'https://request.test/page', 'https://example.org/en/'];
        yield 'relative base takes scheme and host from the request' => ['/', 'https://request.test/page', 'https://request.test/'];
        yield 'relative base with path keeps its path' => ['/en/', 'http://request.test:8080/en/page', 'http://request.test:8080/en/'];
        yield 'scheme-relative base takes the scheme from the request' => ['//example.org/', 'https://request.test/page', 'https://example.org/'];
    }

    #[Test]
    #[DataProvider('siteUrlDataProvider')]
    public function siteUrlIsAlwaysAbsolute(string $base, string $requestUrl, string $expected): void
    {
        $site = new Site('main', 1, ['base' => $base]);

        self::assertSame($expected, (new AbsoluteUrlResolver())->siteUrl($site, new ServerRequest($requestUrl)));
    }

    #[Test]
    public function originDropsTheBasePath(): void
    {
        $site = new Site('main', 1, ['base' => '/en/']);

        self::assertSame(
            'https://request.test',
            (new AbsoluteUrlResolver())->origin($site, new ServerRequest('https://request.test/en/page'))
        );
    }

    #[Test]
    public function resolveCompletesARelativeUri(): void
    {
        self::assertSame(
            'https://request.test/a-page?x=1#part',
            (new AbsoluteUrlResolver())->resolve(new Uri('/a-page?x=1#part'), new ServerRequest('https://request.test/other'))
        );
    }

    #[Test]
    public function resolveKeepsAnAbsoluteUri(): void
    {
        self::assertSame(
            'https://example.org/a-page',
            (new AbsoluteUrlResolver())->resolve(new Uri('https://example.org/a-page'), new ServerRequest('https://request.test/'))
        );
    }
}
