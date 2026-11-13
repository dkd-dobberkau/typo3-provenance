<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\WellKnown;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\Response;

/**
 * Single entry point for /.well-known/content-provenance and everything
 * below it (RFC 8615). Dispatches to the handler whose sub-path matches the
 * first path segment and applies the same CORS and method rules to all
 * answers, so the provenance extensions do not compete for the prefix.
 */
final class WellKnownRouter implements MiddlewareInterface
{
    public const BASE_PATH = '/.well-known/content-provenance';
    private const ALLOWED_METHODS = 'GET, HEAD, OPTIONS';

    /** @var array<string, WellKnownHandlerInterface> */
    private array $handlers = [];

    /**
     * @param iterable<WellKnownHandlerInterface> $handlers
     */
    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $handler) {
            $this->handlers[trim($handler->getSubPath(), '/')] = $handler;
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if ($path !== self::BASE_PATH && !str_starts_with($path, self::BASE_PATH . '/')) {
            return $handler->handle($request);
        }

        return $this->withCors($this->dispatch($request, trim(substr($path, strlen(self::BASE_PATH)), '/')));
    }

    private function dispatch(ServerRequestInterface $request, string $relativePath): ResponseInterface
    {
        $method = $request->getMethod();
        if ($method === 'OPTIONS') {
            return (new Response())->withStatus(204);
        }
        if ($method !== 'GET' && $method !== 'HEAD') {
            return (new JsonResponse(['error' => 'method not allowed'], 405))->withHeader('Allow', self::ALLOWED_METHODS);
        }

        [$segment, $remainder] = array_pad(explode('/', $relativePath, 2), 2, '');
        $target = $this->handlers[$segment] ?? null;
        if ($target === null) {
            return new JsonResponse(['error' => 'not found'], 404);
        }

        return $target->handle($request, $remainder);
    }

    private function withCors(ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', self::ALLOWED_METHODS)
            ->withHeader('Access-Control-Expose-Headers', 'Link');
    }
}
