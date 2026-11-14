<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\DataHandling;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class FileMetadataTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['filemetadata'];
    protected array $testExtensionsToLoad = ['cms-provenance/provenance'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/FileMetadata.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function licenseAndDigitalSourceTypeAreStored(): void
    {
        $this->saveMetadata([
            'tx_provenance_license' => 'https://creativecommons.org/licenses/by/4.0/',
            'tx_provenance_digital_source_type' => 'digitalCapture',
        ]);

        $row = BackendUtility::getRecord('sys_file_metadata', 1);
        self::assertSame('https://creativecommons.org/licenses/by/4.0/', $row['tx_provenance_license']);
        self::assertSame('digitalCapture', $row['tx_provenance_digital_source_type']);
    }

    #[Test]
    public function nonUrlLicenseIsRejected(): void
    {
        $this->saveMetadata(['tx_provenance_license' => 'CC BY']);

        self::assertSame('', BackendUtility::getRecord('sys_file_metadata', 1)['tx_provenance_license']);
    }

    /**
     * @param array<string, string> $fields
     */
    private function saveMetadata(array $fields): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['sys_file_metadata' => [1 => $fields]], []);
        $dataHandler->process_datamap();
    }
}
