<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Event;

use CmsProvenance\Provenance\Domain\Model\ProvenanceRecord;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Dispatched before the JSON-LD block of a page is written. Sub-extensions
 * (signing, facts) add their properties here so that the page keeps a single,
 * validatable JSON-LD document. Setting an empty array suppresses the block.
 */
final class BeforeJsonLdEmittedEvent
{
    /**
     * @param array<string, mixed> $jsonLd
     */
    public function __construct(
        private array $jsonLd,
        private readonly ProvenanceRecord $record,
        private readonly ServerRequestInterface $request,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getJsonLd(): array
    {
        return $this->jsonLd;
    }

    /**
     * @param array<string, mixed> $jsonLd
     */
    public function setJsonLd(array $jsonLd): void
    {
        $this->jsonLd = $jsonLd;
    }

    public function getRecord(): ProvenanceRecord
    {
        return $this->record;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
