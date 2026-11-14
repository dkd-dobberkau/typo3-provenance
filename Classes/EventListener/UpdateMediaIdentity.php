<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\EventListener;

use CmsProvenance\Provenance\Media\MediaCacheTags;
use CmsProvenance\Provenance\Media\MediaIdentity;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Event\AfterFileAddedToIndexEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileUpdatedInIndexEvent;
use TYPO3\CMS\Core\Resource\Event\BeforeFileDeletedEvent;

/**
 * Keeps identifier and hash of media files current whenever FAL indexes a
 * file: on upload, on replacement and when the indexer notices changed bytes.
 * Pages listing a changed file are flushed, so their records are re-issued.
 */
final readonly class UpdateMediaIdentity
{
    public function __construct(
        private MediaIdentity $mediaIdentity,
        private MediaCacheTags $mediaCacheTags,
    ) {}

    #[AsEventListener(identifier: 'provenance/media-identity-added')]
    public function added(AfterFileAddedToIndexEvent $event): void
    {
        $this->refresh($event->getFileUid());
    }

    #[AsEventListener(identifier: 'provenance/media-identity-updated')]
    public function updated(AfterFileUpdatedInIndexEvent $event): void
    {
        $this->refresh($event->getFile()->getUid());
    }

    // Before, not after: once deleted, the index row that names the
    // identifier may be gone.
    #[AsEventListener(identifier: 'provenance/media-identity-removed')]
    public function removed(BeforeFileDeletedEvent $event): void
    {
        $file = $event->getFile();
        if (method_exists($file, 'getUid')) {
            $this->mediaIdentity->retire((int)$file->getUid());
            $this->mediaCacheTags->flush((int)$file->getUid());
        }
    }

    private function refresh(int $fileUid): void
    {
        if ($this->mediaIdentity->update($fileUid)) {
            $this->mediaCacheTags->flush($fileUid);
        }
    }
}
