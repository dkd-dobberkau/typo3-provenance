<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\DataHandling;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ProvenanceDocumentTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BaseScenario.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function newDocumentReceivesUrnIdentifierAndCmsVersion(): void
    {
        $uid = $this->createDocument([]);

        $record = BackendUtility::getRecord('tx_provenance_document', $uid);
        self::assertMatchesRegularExpression('/^urn:uuid:[0-9a-f-]{36}$/', $record['document_uri']);
        self::assertSame('TYPO3 ' . (new Typo3Version())->getVersion(), $record['cms_version']);
    }

    #[Test]
    public function newDocumentIsDatedFirstPublishedUnlessGiven(): void
    {
        $dated = $this->createDocument([]);
        $given = $this->createDocument(['first_published_at' => 1700000000]);

        // The hook stamps the request time, which is set before the test runs; time() may be a second later.
        self::assertSame($GLOBALS['EXEC_TIME'], (int)BackendUtility::getRecord('tx_provenance_document', $dated)['first_published_at']);
        self::assertSame(1700000000, (int)BackendUtility::getRecord('tx_provenance_document', $given)['first_published_at']);
    }

    #[Test]
    public function identifierGivenOnCreationIsKept(): void
    {
        $uid = $this->createDocument(['document_uri' => 'urn:uuid:00000000-0000-4000-8000-000000000001']);

        $record = BackendUtility::getRecord('tx_provenance_document', $uid);
        self::assertSame('urn:uuid:00000000-0000-4000-8000-000000000001', $record['document_uri']);
    }

    #[Test]
    public function identifierSurvivesLaterUpdates(): void
    {
        $uid = $this->createDocument([]);
        $identifier = BackendUtility::getRecord('tx_provenance_document', $uid)['document_uri'];

        $this->processDatamap([
            'tx_provenance_document' => [
                $uid => ['document_uri' => '', 'conformance_level' => 3],
            ],
        ]);

        $record = BackendUtility::getRecord('tx_provenance_document', $uid);
        self::assertSame($identifier, $record['document_uri']);
        self::assertSame(3, (int)$record['conformance_level']);
    }

    #[Test]
    public function sourcesAreStoredAsInlineChildrenOfTheDocument(): void
    {
        $dataHandler = $this->processDatamap([
            'tx_provenance_document' => [
                'NEWdocument' => ['pid' => 1, 'sources' => 'NEWsource'],
            ],
            'tx_provenance_source' => [
                'NEWsource' => [
                    'pid' => 1,
                    'url' => 'https://example.org/report',
                    'description' => 'Supports the headline figure',
                    'accessed_at' => 1789862400,
                ],
            ],
        ]);

        $documentUid = (int)$dataHandler->substNEWwithIDs['NEWdocument'];
        $sourceUid = (int)$dataHandler->substNEWwithIDs['NEWsource'];
        $source = BackendUtility::getRecord('tx_provenance_source', $sourceUid);
        self::assertSame($documentUid, (int)$source['document_uid']);
        self::assertSame('https://example.org/report', $source['url']);
    }

    #[Test]
    public function pageCanReferenceADocument(): void
    {
        $documentUid = $this->createDocument([]);

        $this->processDatamap([
            'pages' => [1 => ['provenance_document_uid' => (string)$documentUid]],
        ]);

        self::assertSame($documentUid, (int)BackendUtility::getRecord('pages', 1)['provenance_document_uid']);
    }

    private function createDocument(array $fields): int
    {
        $dataHandler = $this->processDatamap([
            'tx_provenance_document' => ['NEWdocument' => ['pid' => 1] + $fields],
        ]);

        return (int)$dataHandler->substNEWwithIDs['NEWdocument'];
    }

    private function processDatamap(array $datamap): DataHandler
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($datamap, []);
        $dataHandler->process_datamap();
        self::assertSame([], $dataHandler->errorLog, implode(PHP_EOL, $dataHandler->errorLog));

        return $dataHandler;
    }
}
