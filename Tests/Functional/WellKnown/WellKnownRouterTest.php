<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\WellKnown;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class WellKnownRouterTest extends FunctionalTestCase
{
    private const BASE = 'http://localhost/.well-known/content-provenance';

    protected array $testExtensionsToLoad = [
        'cms-provenance/provenance',
        __DIR__ . '/Fixtures/Extensions/test_wellknown',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../DataHandling/Fixtures/BaseScenario.csv');
        $this->get(SiteWriter::class)->createNewBasicSite('main', 1, 'http://localhost/');
    }

    #[Test]
    public function rootPathGoesToTheRootHandler(): void
    {
        self::assertSame(['handler' => 'root', 'remainder' => ''], $this->json($this->request(self::BASE)));
        self::assertSame(['handler' => 'root', 'remainder' => ''], $this->json($this->request(self::BASE . '/')));
    }

    #[Test]
    public function subPathGoesToItsHandlerWithTheRemainder(): void
    {
        self::assertSame(['handler' => 'echo', 'remainder' => ''], $this->json($this->request(self::BASE . '/echo')));
        self::assertSame(['handler' => 'echo', 'remainder' => 'a/b'], $this->json($this->request(self::BASE . '/echo/a/b')));
    }

    #[Test]
    public function subPathMustMatchAWholeSegment(): void
    {
        $response = $this->request(self::BASE . '/echoes');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'not found'], $this->json($response));
    }

    #[Test]
    public function everyResponseAllowsCrossOriginReads(): void
    {
        foreach ([self::BASE . '/echo', self::BASE . '/unknown'] as $url) {
            $response = $this->request($url);
            self::assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'), $url);
            self::assertStringContainsString('Link', $response->getHeaderLine('Access-Control-Expose-Headers'), $url);
        }
    }

    #[Test]
    public function preflightIsAnsweredWithoutHandler(): void
    {
        $response = $this->request(self::BASE . '/echo', 'OPTIONS');

        self::assertSame(204, $response->getStatusCode());
        self::assertStringContainsString('GET', $response->getHeaderLine('Access-Control-Allow-Methods'));
    }

    #[Test]
    public function writingMethodsAreRejected(): void
    {
        $response = $this->request(self::BASE . '/echo', 'POST');

        self::assertSame(405, $response->getStatusCode());
        self::assertStringContainsString('GET', $response->getHeaderLine('Allow'));
    }

    #[Test]
    public function otherWellKnownPathsAreLeftToTypo3(): void
    {
        self::assertNotSame(['handler' => 'root', 'remainder' => ''], $this->json($this->request('http://localhost/.well-known/content-provenance-other'), false));
        self::assertSame(404, $this->request('http://localhost/.well-known/security.txt')->getStatusCode());
    }

    private function request(string $url, string $method = 'GET'): ResponseInterface
    {
        return $this->executeFrontendSubRequest((new InternalRequest($url))->withMethod($method));
    }

    private function json(ResponseInterface $response, bool $strict = true): ?array
    {
        $data = json_decode((string)$response->getBody(), true);
        if ($strict) {
            self::assertIsArray($data, (string)$response->getBody());
        }

        return is_array($data) ? $data : null;
    }
}
