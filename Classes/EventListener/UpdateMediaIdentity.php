<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\EventListener;

use CmsProvenance\Provenance\Media\MediaCacheTags;
use CmsProvenance\Provenance\Media\MediaIdentity;
use CmsProvenance\Provenance\Media\MediaRefresh;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Event\AfterFileAddedToIndexEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileUpdatedInIndexEvent;
use TYPO3\CMS\Core\Resource\Event\BeforeFileDeletedEvent;
use TYPO3\CMS\Core\Resource\File;

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
        private MediaRefresh $mediaRefresh,
    ) {}

    #[AsEventListener(identifier: 'provenance/media-identity-added')]
    public function added(AfterFileAddedToIndexEvent $event): void
    {
        $this->mediaRefresh->refresh($event->getFileUid());
    }

    #[AsEventListener(identifier: 'provenance/media-identity-updated')]
    public function updated(AfterFileUpdatedInIndexEvent $event): void
    {
        $this->mediaRefresh->refresh($event->getFile()->getUid());
    }

    // Before, not after: once deleted, the index row that names the
    // identifier may be gone.
    #[AsEventListener(identifier: 'provenance/media-identity-removed')]
    public function removed(BeforeFileDeletedEvent $event): void
    {
        $file = $event->getFile();
        // Processed files (thumbnails) fire this event too; their uid is a
        // sys_file_processedfile uid and must not be read as a sys_file uid.
        if ($file instanceof File) {
            $this->mediaIdentity->retire($file->getUid());
            $this->mediaCacheTags->flush($file->getUid());
        }
    }

}
