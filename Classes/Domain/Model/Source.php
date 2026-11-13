<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Domain\Model;

final readonly class Source
{
    public function __construct(
        public string $url,
        public string $description,
        public ?int $accessedAt,
        public ?string $archiveUrl,
    ) {}
}
