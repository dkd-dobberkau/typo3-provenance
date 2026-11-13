<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Hook;

use CmsProvenance\Provenance\Service\DocumentIdentifierGenerator;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Gives every new provenance document its stable identifier, a first
 * publication date (now, unless one is given) and the CMS version that created it. Both fields are read-only in the backend; on
 * updates any submitted value for the identifier is dropped so it never changes.
 */
final readonly class AssignDocumentIdentifier
{
    private const TABLE = 'tx_provenance_document';

    public function __construct(
        private DocumentIdentifierGenerator $identifierGenerator,
        private Typo3Version $typo3Version,
    ) {}

    public function processDatamap_preProcessFieldArray(
        array &$fieldArray,
        string $table,
        int|string $id,
        DataHandler $dataHandler
    ): void {
        if ($table !== self::TABLE) {
            return;
        }

        if (MathUtility::canBeInterpretedAsInteger($id)) {
            unset($fieldArray['document_uri']);
            return;
        }

        if (trim((string)($fieldArray['document_uri'] ?? '')) === '') {
            $fieldArray['document_uri'] = $this->identifierGenerator->generate();
        }
        if ((int)($fieldArray['first_published_at'] ?? 0) <= 0) {
            $fieldArray['first_published_at'] = $GLOBALS['EXEC_TIME'] ?? time();
        }
        $fieldArray['cms_version'] = 'TYPO3 ' . $this->typo3Version->getVersion();
    }
}
