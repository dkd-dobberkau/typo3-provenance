<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Hook;

use CmsProvenance\Provenance\Media\MediaCacheTags;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Saving a file's metadata (creator, licence, …), or publishing it from a
 * workspace, changes the records of the pages using that file; their cached
 * output is flushed so they are rendered and signed again.
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
        $this->flushPagesOf(MathUtility::canBeInterpretedAsInteger($id) ? (int)$id : (int)($dataHandler->substNEWwithIDs[$id] ?? 0));
    }

    /**
     * Publishing a workspace version changes the live metadata without a
     * datamap save on the live row.
     */
    public function processCmdmap_postProcess(
        string $command,
        string $table,
        int|string $id,
        mixed $value,
        DataHandler $dataHandler
    ): void {
        if ($table === self::TABLE && $command === 'version' && MathUtility::canBeInterpretedAsInteger($id)) {
            $this->flushPagesOf((int)$id);
        }
    }

    private function flushPagesOf(int $metadataUid): void
    {
        $fileUid = (int)(BackendUtility::getRecord(self::TABLE, $metadataUid, 'file')['file'] ?? 0);
        if ($fileUid > 0) {
            $this->mediaCacheTags->flush($fileUid);
        }
    }
}
