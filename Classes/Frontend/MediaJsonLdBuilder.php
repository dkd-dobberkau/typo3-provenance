<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Frontend;

use CmsProvenance\Provenance\Domain\Model\MediaItem;

/**
 * Turns the media of a page into the record members image, video and audio
 * (schema.org ImageObject, VideoObject, AudioObject). As in JsonLdBuilder,
 * unknown values are left out rather than emitted empty.
 */
final class MediaJsonLdBuilder
{
    public const DIGITAL_SOURCE_TYPE_PREFIX = 'https://cv.iptc.org/newscodes/digitalsourcetype/';

    private const KINDS = [
        'image/' => ['image', 'ImageObject'],
        'video/' => ['video', 'VideoObject'],
        'audio/' => ['audio', 'AudioObject'],
    ];

    /**
     * @param list<MediaItem> $items in page order
     * @param \Closure(string): string $absoluteUrl makes a public file URL absolute
     * @return array<string, list<array<string, mixed>>> only the members that have entries
     */
    public function build(array $items, \Closure $absoluteUrl): array
    {
        $members = [];
        foreach ($items as $item) {
            $kind = $this->kind($item->mimeType);
            if ($kind !== null) {
                $members[$kind[0]][] = $this->entry($item, $kind[1], $absoluteUrl);
            }
        }

        return $members;
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(MediaItem $item, string $type, \Closure $absoluteUrl): array
    {
        return array_filter([
            '@type' => $type,
            '@id' => $item->mediaUri,
            'contentUrl' => $item->publicUrl !== null ? $absoluteUrl($item->publicUrl) : null,
            'sha256' => $item->sha256,
            'encodingFormat' => $item->mimeType,
            // Untyped: the field often names an agency rather than a person.
            'creator' => $item->creator !== null ? ['name' => $item->creator] : null,
            'copyrightNotice' => $item->copyright,
            'license' => $item->license,
            'isBasedOn' => $this->webAddress($item->source),
            'digitalSourceType' => $item->digitalSourceType !== null ? self::DIGITAL_SOURCE_TYPE_PREFIX . $item->digitalSourceType : null,
        ], static fn(mixed $value): bool => $value !== null);
    }

    /**
     * @return array{string, string}|null member name and schema.org type
     */
    private function kind(string $mimeType): ?array
    {
        foreach (self::KINDS as $prefix => $kind) {
            if (str_starts_with($mimeType, $prefix)) {
                return $kind;
            }
        }

        return null;
    }

    private function webAddress(?string $value): ?string
    {
        if ($value === null || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string)parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true) ? $value : null;
    }
}
