<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Hook;

use CmsProvenance\Provenance\Media\MediaCacheTags;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Saving a file's metadata (creator, licence, …) changes the records of the
 * pages using that file; their cached output is flushed so they are
 * rendered and signed again.
 */
final readonly class FlushMediaPages
{
    private const TABLE = 'sys_file_metadata';

    public function __construct(
        private MediaCacheTags $mediaCacheTags,
    ) {}

    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        int|string $id,
        array $fieldArray,
        DataHandler $dataHandler
    ): void {
        if ($table !== self::TABLE) {
            return;
        }
        $uid = MathUtility::canBeInterpretedAsInteger($id) ? (int)$id : (int)($dataHandler->substNEWwithIDs[$id] ?? 0);
        $fileUid = (int)(BackendUtility::getRecord(self::TABLE, $uid, 'file')['file'] ?? 0);
        if ($fileUid > 0) {
            $this->mediaCacheTags->flush($fileUid);
        }
    }
}
