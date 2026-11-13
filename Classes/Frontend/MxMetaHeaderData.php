<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Frontend;

use CmsProvenance\Provenance\Domain\Repository\ProvenanceRecordRepository;
use CmsProvenance\Provenance\Service\AbsoluteUrlResolver;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsAllowedCallable;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * USER content object for page.headerData (see the "provenance-mx" site set).
 * Emits MX meta tags for pages that have a provenance record. Output is part
 * of the cached page.
 */
final readonly class MxMetaHeaderData
{
    public function __construct(
        private ProvenanceRecordRepository $recordRepository,
        private JsonLdBuilder $jsonLdBuilder,
        private MxMetaTagBuilder $mxMetaTagBuilder,
        private AbsoluteUrlResolver $absoluteUrlResolver,
    ) {}

    // Required for userFunc callbacks since TYPO3 14; ignored by TYPO3 13.
    #[AsAllowedCallable]
    public function render(string $content, array $conf, ServerRequestInterface $request): string
    {
        $pageInformation = $request->getAttribute('frontend.page.information');
        $site = $request->getAttribute('site');
        if (!$pageInformation instanceof PageInformation || !$site instanceof Site) {
            return '';
        }

        $record = $this->recordRepository->findByPage($pageInformation->getId());
        if ($record === null) {
            return '';
        }

        $page = $pageInformation->getPageRecord();
        // The generator does not appear in MX tags.
        $jsonLd = $this->jsonLdBuilder->build($record, (string)($page['title'] ?? ''), (int)($page['SYS_LASTCHANGED'] ?? 0), '');
        $summary = trim((string)($page['abstract'] ?? '')) ?: trim((string)($page['description'] ?? ''));

        return $this->mxMetaTagBuilder->render($this->mxMetaTagBuilder->build(
            $jsonLd,
            $this->absoluteUrlResolver->pageUrl($site, $pageInformation->getId(), $request),
            $summary,
            (string)$site->getSettings()->get('provenance.mx.trainingDataPolicy', '')
        ));
    }
}
