<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\WellKnown;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Answers one sub-path of /.well-known/content-provenance. Implementations are
 * found automatically (autoconfiguration); the router takes care of CORS,
 * preflight, allowed methods and unknown paths.
 */
interface WellKnownHandlerInterface
{
    /**
     * Path segment below /.well-known/content-provenance, for example "keys".
     * An empty string handles the endpoint itself.
     */
    public function getSubPath(): string;

    /**
     * @param string $remainder the rest of the path after the sub-path, without leading slash
     */
    public function handle(ServerRequestInterface $request, string $remainder): ResponseInterface;
}
