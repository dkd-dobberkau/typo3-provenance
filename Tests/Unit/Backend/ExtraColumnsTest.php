<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Unit\Backend;

use CmsProvenance\Provenance\Backend\ExtraColumns;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ExtraColumnsTest extends UnitTestCase
{
    #[Test]
    public function columnsAreCollectedOverAllRows(): void
    {
        $result = ExtraColumns::normalize([
            ['uid' => 1, 'extra' => ['signed' => 'yes']],
            ['uid' => 2, 'extra' => ['wikidata' => 'confirmed']],
        ]);

        self::assertSame(['signed', 'wikidata'], $result['columns']);
        self::assertSame(['signed' => ['label' => 'yes', 'url' => ''], 'wikidata' => ['label' => '', 'url' => '']], $result['rows'][0]['extra']);
    }

    #[Test]
    public function cellsCanBeLinks(): void
    {
        $result = ExtraColumns::normalize([['extra' => [
            'suggest' => ['label' => 'Suggest facts', 'url' => '/typo3/module/suggest?id=1'],
            'source' => ['label' => 'Wikidata', 'url' => 'https://www.wikidata.org/wiki/Q42'],
        ]]]);

        self::assertSame('/typo3/module/suggest?id=1', $result['rows'][0]['extra']['suggest']['url']);
        self::assertSame('https://www.wikidata.org/wiki/Q42', $result['rows'][0]['extra']['source']['url']);
    }

    #[Test]
    public function unsafeUrlsAreDropped(): void
    {
        $result = ExtraColumns::normalize([['extra' => [
            'a' => ['label' => 'x', 'url' => 'javascript:alert(1)'],
            'b' => ['label' => 'y', 'url' => '//evil.example/'],
        ]]]);

        self::assertSame('', $result['rows'][0]['extra']['a']['url']);
        self::assertSame('', $result['rows'][0]['extra']['b']['url']);
        self::assertSame('x', $result['rows'][0]['extra']['a']['label']);
    }
}
