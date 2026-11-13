..  _verification:

============
Verification
============

..  _verification-verifier:

Checking a whole site
=====================

The `conformance suite <https://github.com/dkd-dobberkau/content-provenance-conformance>`__
contains a verifier that does what an auditor does: it reads the audit
endpoint, fetches every signed page, recomputes the content hash and checks
the signature against the published key.

..  code-block:: bash

    git clone https://github.com/dkd-dobberkau/content-provenance-conformance
    cd content-provenance-conformance/reference/python
    uv sync
    uv run python verify_site.py https://example.org/

Output, one line per document:

..  code-block:: text

    valid     https://example.org/
    valid     https://example.org/about
    unsigned  https://example.org/news/old-post

    3 documents: 2 valid, 0 invalid, 1 unsigned

For local development sites with self-signed certificates add
``--insecure``. The command exits with status 1 if any document is invalid,
so it can run in CI after a deployment.

..  _verification-results:

Reading the results
===================

``valid``
    The delivered text and record match the signature.

``unsigned``
    The page has a record but no signature yet. Pages are signed when TYPO3
    renders them; visit the page (or warm the cache) after creating a key or
    flushing the cache.

``invalid``, "content hash does not match the delivered page"
    The text a visitor gets differs from the signed text. Usual causes:
    uncached content inside the content area (see
    :ref:`configuration-uncached`), or a proxy or CDN that rewrites HTML.

``invalid``, "signing key is not published"
    The key endpoint does not list the key id. Check that the web server
    passes ``/.well-known/content-provenance/keys`` to TYPO3 (see
    :ref:`installation-web-server`).

``invalid``, "signature does not verify"
    The record changed after signing, for example by another extension
    modifying the JSON-LD block late in the rendering.

..  _verification-manual:

Looking by hand
===============

..  code-block:: bash

    # The site's listing, with level, keys and documents
    curl -s https://example.org/.well-known/content-provenance

    # Published keys
    curl -s https://example.org/.well-known/content-provenance/keys

    # One fact as a Web Annotation
    curl -s https://example.org/.well-known/content-provenance/facts/employees-2025
