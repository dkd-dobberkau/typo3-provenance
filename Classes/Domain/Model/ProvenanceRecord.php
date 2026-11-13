<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Domain\Model;

/**
 * Layer 1 provenance record of one document, as read for publication.
 * Timestamps are Unix timestamps; null means "not set".
 */
final readonly class ProvenanceRecord
{
    /**
     * @param list<Author> $authors
     * @param list<Source> $sources
     */
    public function __construct(
        public string $documentUri,
        public ?int $firstPublishedAt,
        public ?int $lastSubstantiveRevisionAt,
        public ?int $lastMaintainedAt,
        public array $authors,
        public ?Author $reviewer,
        public array $sources,
    ) {}
}
