<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Frontend;

use CmsProvenance\Provenance\Domain\Repository\ProvenanceRecordRepository;
use CmsProvenance\Provenance\Event\BeforeJsonLdEmittedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsAllowedCallable;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Package\PackageManager;
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
        private PackageManager $packageManager,
        private Typo3Version $typo3Version,
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
            $this->generator()
        );
        $event = $this->eventDispatcher->dispatch(new BeforeJsonLdEmittedEvent($jsonLd, $record, $request));
        if ($event->getJsonLd() === []) {
            return '';
        }

        // JSON_HEX_TAG turns "<" and ">" into < and >, so editor
        // content can never close the script element.
        $json = json_encode(
            $event->getJsonLd(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );

        return '<script type="application/ld+json">' . $json . '</script>';
    }

    private function generator(): string
    {
        $extensionVersion = $this->packageManager->getPackage('provenance')->getPackageMetaData()->getVersion();

        return sprintf('TYPO3 %s + provenance %s', $this->typo3Version->getVersion(), $extensionVersion);
    }
}
