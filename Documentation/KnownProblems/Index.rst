..  _known-problems:

=====================
Limits of version 0.1
=====================

These are known and tracked; they do not make a site non-conformant.

*   **Default language only.** Records, backfill, signatures in the audit
    listing and facts cover the default language. Translated pages render
    the record of the default-language page.
*   **No save button for substantive revisions.** Editors set
    :guilabel:`Last substantive revision` and :guilabel:`Last confirmed as
    accurate` by hand; the split save button described in the product
    requirements is not built yet.
*   **No conformance badge in the page tree.**
*   **Facts are entered as quotes**, not marked in the rich text editor. A
    quote across formatting is not anchored on the page.
*   **Pages are signed when rendered.** A page not visited since the key was
    created or the cache was flushed appears unsigned in the audit listing.
*   **Uncached content inside the content area breaks signatures.** See
    :ref:`configuration-uncached`.
*   **One key per installation**, not per site.
*   **No key revocation list.** Rotating replaces a key; the specification
    has not defined revocation yet.
*   **The audit listing is computed per request**, with HTTP caching only.
    Very large page trees may need a server-side cache.
*   **Not on Packagist or in the TER yet.** Install from the repositories, see
    :ref:`installation-packages`.
