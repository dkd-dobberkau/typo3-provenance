<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Media;

/**
 * Brings a file's identity up to date and, if it changed, flushes the pages
 * that list the file, so their records are re-issued.
 */
final readonly class MediaRefresh
{
    public function __construct(
        private MediaIdentity $mediaIdentity,
        private MediaCacheTags $mediaCacheTags,
    ) {}

    /**
     * @return bool whether the identifier or the hash changed
     */
    public function refresh(int $fileUid): bool
    {
        if (!$this->mediaIdentity->update($fileUid)) {
            return false;
        }
        $this->mediaCacheTags->flush($fileUid);

        return true;
    }
}
