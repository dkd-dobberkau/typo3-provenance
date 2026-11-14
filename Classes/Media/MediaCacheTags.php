<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Media;

use TYPO3\CMS\Core\Cache\CacheManager;

/**
 * Pages are tagged with the files their record lists; the tag is the one
 * TYPO3's own cache auto-tagging uses for files. Flushing it makes those
 * pages render, and be signed, anew with the current file data.
 */
final readonly class MediaCacheTags
{
    public function __construct(
        private CacheManager $cacheManager,
    ) {}

    public function tagFor(int $fileUid): string
    {
        return 'sys_file_' . $fileUid;
    }

    public function flush(int $fileUid): void
    {
        $this->cacheManager->flushCachesInGroupByTags('pages', [$this->tagFor($fileUid)]);
    }
}
