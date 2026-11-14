<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\Backend;

use CmsProvenance\Provenance\Backend\PageOverview;
use CmsProvenance\Provenance\Backend\PageSubtree;
use CmsProvenance\Provenance\Event\ModifyPageOverviewRowEvent;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class PageOverviewTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PageOverview.csv');
    }

    #[Test]
    public function completeRecordReachesLevelTwo(): void
    {
        $row = $this->row(1);

        self::assertSame('Root', $row['title']);
        self::assertTrue($row['hasRecord']);
        self::assertSame(2, $row['level']);
        self::assertSame([], $row['missing']);
        self::assertSame('2026-05-14T09:00:00Z', $row['datePublished']);
        self::assertSame('2026-11-01T08:00:00Z', $row['maintainedDate']);
        self::assertTrue($row['mayEdit']);
    }

    #[Test]
    public function incompleteRecordStaysAtLevelOneWithReasons(): void
    {
        $row = $this->row(2);

        self::assertSame(1, $row['level']);
        self::assertSame(['Author "Grace Hopper" has no profile URI.'], $row['missing']);
    }

    #[Test]
    public function pageWithoutRecordHasNoLevel(): void
    {
        $row = $this->row(3);

        self::assertFalse($row['hasRecord']);
        self::assertNull($row['level']);
    }

    #[Test]
    public function hiddenRecordIsMarkedNotMissing(): void
    {
        $row = $this->row(4);

        self::assertTrue($row['hasRecord']);
        self::assertTrue($row['recordHidden']);
        self::assertSame(2, $row['level']);
    }

    #[Test]
    public function hiddenPageIsMarked(): void
    {
        self::assertTrue($this->row(5)['pageHidden']);
    }

    #[Test]
    public function onlyStandardPagesAreListed(): void
    {
        self::assertSame([1, 2, 3, 4, 5], array_column($this->rows(), 'uid'));
    }

    #[Test]
    public function listenersCanAddColumns(): void
    {
        $this->getContainer()->set('provenance.test.page-overview-listener', new class () {
            public function __invoke(ModifyPageOverviewRowEvent $event): void
            {
                $row = $event->getRow();
                $row['extra']['signed'] = 'page ' . $event->getPageUid();
                $event->setRow($row);
            }
        });
        $this->get(ListenerProvider::class)->addListener(ModifyPageOverviewRowEvent::class, 'provenance.test.page-overview-listener');

        self::assertSame(['signed' => 'page 1'], $this->row(1)['extra']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        return $this->get(PageOverview::class)->rows(1, PageSubtree::ALL, $this->setUpBackendUser(1));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $uid): array
    {
        foreach ($this->rows() as $row) {
            if ($row['uid'] === $uid) {
                return $row;
            }
        }
        self::fail('No row for page ' . $uid);
    }
}
