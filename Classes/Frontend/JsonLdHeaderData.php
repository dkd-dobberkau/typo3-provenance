<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Frontend;

use CmsProvenance\Provenance\Domain\Repository\PageMediaRepository;
use CmsProvenance\Provenance\Domain\Repository\ProvenanceRecordRepository;
use CmsProvenance\Provenance\Event\BeforeJsonLdEmittedEvent;
use CmsProvenance\Provenance\Media\MediaCacheTags;
use CmsProvenance\Provenance\Service\AbsoluteUrlResolver;
use CmsProvenance\Provenance\Service\ConformanceLevel;
use CmsProvenance\Provenance\Service\GeneratorName;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsAllowedCallable;
use TYPO3\CMS\Core\Cache\CacheDataCollector;
use TYPO3\CMS\Core\Cache\CacheTag;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * USER content object for page.headerData (see the "provenance" site set).
 * Output is part of the cached page, so it costs nothing per request.
 */
final readonly class JsonLdHeaderData
{
    public function __construct(
        private ProvenanceRecordRepository $recordRepository,
        private JsonLdBuilder $jsonLdBuilder,
        private EventDispatcherInterface $eventDispatcher,
        private ConformanceLevel $conformanceLevel,
        private GeneratorName $generatorName,
        private PageMediaRepository $pageMediaRepository,
        private MediaJsonLdBuilder $mediaJsonLdBuilder,
        private MediaCacheTags $mediaCacheTags,
        private AbsoluteUrlResolver $urlResolver,
        private Context $context,
    ) {}

    // Required for userFunc callbacks since TYPO3 14; ignored by TYPO3 13.
    #[AsAllowedCallable]
    public function render(string $content, array $conf, ServerRequestInterface $request): string
    {
        $pageInformation = $request->getAttribute('frontend.page.information');
        if (!$pageInformation instanceof PageInformation) {
            return '';
        }

        $record = $this->recordRepository->findByPage($pageInformation->getId());
        if ($record === null) {
            return '';
        }

        $page = $pageInformation->getPageRecord();
        $jsonLd = $this->jsonLdBuilder->build(
            $record,
            (string)($page['title'] ?? ''),
            (int)($page['SYS_LASTCHANGED'] ?? 0),
            $this->generatorName->value()
        );
        // Before the event, so facts and signing see the media too.
        $jsonLd += $this->media($pageInformation->getId(), $request);
        $event = $this->eventDispatcher->dispatch(new BeforeJsonLdEmittedEvent($jsonLd, $record, $request));
        $jsonLd = $event->getJsonLd();
        if ($jsonLd === []) {
            return '';
        }
        // Unsigned at this point: L2 at most. The signing extension raises the
        // level once the page is signed.
        $jsonLd['cpsConformance'] = $this->conformanceLevel->of($jsonLd, false, false);

        // JSON_HEX_TAG turns "<" and ">" into < and >, so editor
        // content can never close the script element.
        $json = json_encode(
            $jsonLd,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );

        return '<script type="application/ld+json">' . $json . '</script>';
    }

    /**
     * The media members of the record; tags the cached page with each listed
     * file, so a change to one of them re-issues this record.
     *
     * @return array<string, mixed>
     */
    private function media(int $pageUid, ServerRequestInterface $request): array
    {
        // Content language and overlay mode as the frontend renders the page,
        // including fallbacks to another language.
        $languageAspect = $this->context->getAspect('language');
        $items = $languageAspect instanceof LanguageAspect
            ? $this->pageMediaRepository->findByPage($pageUid, $languageAspect->getContentId(), $languageAspect->getOverlayType())
            : $this->pageMediaRepository->findByPage($pageUid, 0);
        $cacheCollector = $request->getAttribute('frontend.cache.collector');
        if ($cacheCollector instanceof CacheDataCollector) {
            foreach ($items as $item) {
                $cacheCollector->addCacheTags(new CacheTag($this->mediaCacheTags->tagFor($item->fileUid)));
            }
        }

        return $this->mediaJsonLdBuilder->build(
            $items,
            fn(string $url): string => $this->urlResolver->resolve(new Uri($url), $request)
        );
    }
}
