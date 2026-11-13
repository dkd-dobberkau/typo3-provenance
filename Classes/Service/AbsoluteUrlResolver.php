<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Service;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Builds the absolute URLs that provenance records, signatures and the
 * well-known endpoints must publish.
 *
 * TYPO3 allows a relative site base such as "/", and URLs generated from it
 * are relative too. The specification needs absolute URLs, so whatever the
 * base lacks (scheme, host, port) is taken from the current request.
 */
final readonly class AbsoluteUrlResolver
{
    /**
     * The site base as an absolute URL with a trailing slash.
     */
    public function siteUrl(Site $site, ServerRequestInterface $request): string
    {
        return rtrim($this->resolve($site->getBase(), $request), '/') . '/';
    }

    /**
     * Scheme, host and port of the site, without the base path.
     */
    public function origin(Site $site, ServerRequestInterface $request): string
    {
        return rtrim($this->resolve($site->getBase()->withPath('')->withQuery('')->withFragment(''), $request), '/');
    }

    /**
     * The absolute URL of a page of the site.
     */
    public function pageUrl(Site $site, int $pageUid, ServerRequestInterface $request): string
    {
        return $this->resolve($site->getRouter()->generateUri($pageUid), $request);
    }

    public function resolve(UriInterface $uri, ServerRequestInterface $request): string
    {
        $requestUri = $request->getUri();
        if ($uri->getHost() === '') {
            $uri = $uri->withHost($requestUri->getHost())->withPort($requestUri->getPort());
        }
        if ($uri->getScheme() === '') {
            $uri = $uri->withScheme($requestUri->getScheme());
        }

        return (string)$uri;
    }
}
