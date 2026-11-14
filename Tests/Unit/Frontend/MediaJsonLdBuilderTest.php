<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Unit\Frontend;

use CmsProvenance\Provenance\Domain\Model\MediaItem;
use CmsProvenance\Provenance\Frontend\MediaJsonLdBuilder;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class MediaJsonLdBuilderTest extends UnitTestCase
{
    #[Test]
    public function fullItemBecomesImageObject(): void
    {
        $item = new MediaItem(
            fileUid: 1,
            metadataUid: 1,
            name: 'photo.jpg',
            mimeType: 'image/jpeg',
            publicUrl: '/fileadmin/photo.jpg',
            mediaUri: 'urn:uuid:00000000-0000-4000-8000-000000000001',
            sha256: str_repeat('a', 64),
            creator: 'dpa',
            copyright: '© dpa',
            source: 'https://example.org/original',
            license: 'https://creativecommons.org/licenses/by/4.0/',
            digitalSourceType: 'digitalCapture',
        );

        self::assertSame(['image' => [[
            '@type' => 'ImageObject',
            '@id' => 'urn:uuid:00000000-0000-4000-8000-000000000001',
            'contentUrl' => 'https://example.org/fileadmin/photo.jpg',
            'sha256' => str_repeat('a', 64),
            'encodingFormat' => 'image/jpeg',
            'creator' => ['name' => 'dpa'],
            'copyrightNotice' => '© dpa',
            'license' => 'https://creativecommons.org/licenses/by/4.0/',
            'isBasedOn' => 'https://example.org/original',
            'digitalSourceType' => 'https://cv.iptc.org/newscodes/digitalsourcetype/digitalCapture',
        ]]], $this->build([$item]));
    }

    #[Test]
    public function unknownValuesAreLeftOut(): void
    {
        self::assertSame(
            ['image' => [['@type' => 'ImageObject', 'encodingFormat' => 'image/jpeg']]],
            $this->build([$this->item('photo.jpg', 'image/jpeg')])
        );
    }

    #[Test]
    public function sourceThatIsNoWebAddressIsLeftOut(): void
    {
        $entry = $this->build([$this->item('photo.jpg', 'image/jpeg', source: 'Archiv')])['image'][0];

        self::assertArrayNotHasKey('isBasedOn', $entry);
    }

    #[Test]
    public function mediaAreGroupedByKindInPageOrder(): void
    {
        $result = $this->build([
            $this->item('a.mp3', 'audio/mpeg'),
            $this->item('b.jpg', 'image/jpeg'),
            $this->item('c.mp4', 'video/mp4'),
            $this->item('d.png', 'image/png'),
        ]);

        self::assertSame(['audio', 'image', 'video'], array_keys($result));
        self::assertSame(['image/jpeg', 'image/png'], array_column($result['image'], 'encodingFormat'));
        self::assertSame('AudioObject', $result['audio'][0]['@type']);
        self::assertSame('VideoObject', $result['video'][0]['@type']);
    }

    #[Test]
    public function noMediaGiveNoMembers(): void
    {
        self::assertSame([], $this->build([]));
    }

    /**
     * @param list<MediaItem> $items
     * @return array<string, mixed>
     */
    private function build(array $items): array
    {
        return (new MediaJsonLdBuilder())->build($items, static fn(string $url): string => 'https://example.org' . $url);
    }

    private function item(string $name, string $mimeType, ?string $source = null): MediaItem
    {
        return new MediaItem(1, 1, $name, $mimeType, null, null, null, null, null, $source, null, null);
    }
}
