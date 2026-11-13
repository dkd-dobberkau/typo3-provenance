<?php

declare(strict_types=1);

namespace CmsProvenance\TestWellKnown;

use CmsProvenance\Provenance\WellKnown\WellKnownHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;

final class RootHandler implements WellKnownHandlerInterface
{
    public function getSubPath(): string
    {
        return '';
    }

    public function handle(ServerRequestInterface $request, string $remainder): ResponseInterface
    {
        return new JsonResponse(['handler' => 'root', 'remainder' => $remainder]);
    }
}
