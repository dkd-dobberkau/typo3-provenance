<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Domain\Model;

/**
 * Public view of a backend user as author or reviewer. Deliberately carries no
 * username: only the display name and the external identity are published.
 */
final readonly class Author
{
    public function __construct(
        public ?string $name,
        public ?string $identityUri,
        public bool $identityVerified,
    ) {}
}
