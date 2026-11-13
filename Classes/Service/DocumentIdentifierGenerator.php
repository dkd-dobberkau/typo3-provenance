<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Service;

/**
 * Creates the stable identifier of a provenance document.
 *
 * The identifier is a URN with a random (version 4) UUID, so it does not
 * depend on the page URL and survives moves, renames and domain changes.
 */
final class DocumentIdentifierGenerator
{
    public function generate(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            'urn:uuid:%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
