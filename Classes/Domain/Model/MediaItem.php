<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Domain\Model;

/**
 * A media file used on a page, with what is known about its origin. null
 * means "not known"; a hash is null too when it was computed for earlier bytes.
 */
final readonly class MediaItem
{
    public function __construct(
        public int $fileUid,
        public int $metadataUid,
        public string $name,
        public string $mimeType,
        public ?string $publicUrl,
        public ?string $mediaUri,
        public ?string $sha256,
        public ?string $creator,
        public ?string $copyright,
        public ?string $source,
        public ?string $license,
        public ?string $digitalSourceType,
    ) {}
}
