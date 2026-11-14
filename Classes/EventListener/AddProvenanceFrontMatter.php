<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\EventListener;

use B13\AiBotsLoveMarkdown\Event\AfterFrontMatterForPageIsCreatedEvent;
use CmsProvenance\Provenance\Markdown\ProvenanceFrontMatter;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Adds the provenance record to the front matter of the Markdown version of a
 * page. Only fires when EXT:ai_bots_love_markdown is installed.
 */
final readonly class AddProvenanceFrontMatter
{
    public function __construct(
        private ProvenanceFrontMatter $provenanceFrontMatter,
    ) {}

    #[AsEventListener(identifier: 'provenance/markdown-front-matter')]
    public function __invoke(AfterFrontMatterForPageIsCreatedEvent $event): void
    {
        $event->frontMatter = $this->provenanceFrontMatter->extend($event->frontMatter, $event->html);
    }
}
