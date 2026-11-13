<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\Command;

use CmsProvenance\Provenance\Command\BackfillCommand;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class BackfillCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BackfillScenario.csv');
    }

    #[Test]
    public function createsRecordsForStandardPagesWithoutOne(): void
    {
        $tester = $this->runBackfill();

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(3, $this->countDocuments());
        $root = $this->documentOfPage(1);
        self::assertMatchesRegularExpression('/^urn:uuid:[0-9a-f-]{36}$/', $root['document_uri']);
        self::assertSame(1700000000, (int)$root['first_published_at']);
        self::assertSame(1710000000, (int)$root['last_substantive_revision_at']);
        self::assertSame('1', (string)$root['authors']);
        self::assertSame(1, (int)$root['pid']);
    }

    #[Test]
    public function pageWithoutHistoryGetsRecordWithoutAuthor(): void
    {
        $this->runBackfill();

        $document = $this->documentOfPage(4);
        self::assertSame('', (string)$document['authors']);
        self::assertSame(1720000000, (int)$document['last_substantive_revision_at']);
    }

    #[Test]
    public function foldersTranslationsAndCoveredPagesAreLeftAlone(): void
    {
        $this->runBackfill();

        self::assertSame(0, (int)BackendUtility::getRecord('pages', 2)['provenance_document_uid']);
        self::assertSame(0, (int)BackendUtility::getRecord('pages', 5)['provenance_document_uid']);
        self::assertSame(1, (int)BackendUtility::getRecord('pages', 3)['provenance_document_uid']);
    }

    #[Test]
    public function secondRunCreatesNothing(): void
    {
        $this->runBackfill();
        $this->runBackfill();

        self::assertSame(3, $this->countDocuments());
    }

    #[Test]
    public function dryRunWritesNothing(): void
    {
        $tester = $this->runBackfill(['--dry-run' => true]);

        self::assertSame(1, $this->countDocuments());
        self::assertStringContainsString('2 pages', $tester->getDisplay());
    }

    private function runBackfill(array $options = []): CommandTester
    {
        $tester = new CommandTester($this->get(BackfillCommand::class));
        $tester->execute($options);

        return $tester;
    }

    private function documentOfPage(int $pageUid): array
    {
        $documentUid = (int)BackendUtility::getRecord('pages', $pageUid)['provenance_document_uid'];
        self::assertGreaterThan(0, $documentUid, 'Page ' . $pageUid . ' has no provenance record');

        return BackendUtility::getRecord('tx_provenance_document', $documentUid);
    }

    private function countDocuments(): int
    {
        return $this->getConnectionPool()->getConnectionForTable('tx_provenance_document')
            ->count('*', 'tx_provenance_document', ['deleted' => 0]);
    }
}
