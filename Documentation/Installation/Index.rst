..  _installation:

============
Installation
============

This chapter takes an existing TYPO3 site to conformance level L4. Each step
says which level it unlocks; you can stop after any of them.

..  _installation-requirements:

Requirements
============

*   TYPO3 13.4 LTS or 14, installed with Composer
*   PHP 8.2 or later; **PHP 8.4** for ``provenance_signing``, which uses the
    built-in HTML5 parser
*   PHP extensions ``intl`` and ``sodium`` (``sodium`` for signing)
*   A page tree whose content sits inside ``<main>`` (see
    :ref:`configuration-content-area`)
*   An XML sitemap and canonical URLs, for example with ``typo3/cms-seo``:
    together they make level L1, on which all higher levels build

..  _installation-packages:

Step 1: Install the packages
============================

The extensions are not on Packagist yet. Add their repositories, then require
them:

..  code-block:: bash

    composer config repositories.provenance vcs https://github.com/dkd-dobberkau/typo3-provenance
    composer config repositories.provenance-signing vcs https://github.com/dkd-dobberkau/typo3-provenance-signing
    composer config repositories.provenance-audit vcs https://github.com/dkd-dobberkau/typo3-provenance-audit
    composer config repositories.provenance-facts vcs https://github.com/dkd-dobberkau/typo3-provenance-facts

    composer require \
        "cms-provenance/provenance:@dev" \
        "cms-provenance/provenance-signing:@dev" \
        "cms-provenance/provenance-audit:@dev" \
        "cms-provenance/provenance-facts:@dev"

Leave out the packages you do not need. Then create the database tables:

..  code-block:: bash

    vendor/bin/typo3 extension:setup

..  _installation-site-sets:

Step 2: Add the site sets (L2)
==============================

Add the sets to the site configuration, for example
:file:`config/sites/main/config.yaml`, or in the backend module
:guilabel:`Site Management > Sites`:

..  code-block:: yaml
    :caption: config/sites/main/config.yaml

    dependencies:
      - cms-provenance/provenance
      - cms-provenance/provenance-facts

``cms-provenance/provenance`` writes the JSON-LD record into ``<head>``.
``cms-provenance/provenance-facts`` marks facts in the content. The signing
and audit extensions need no site set.

..  _installation-backfill:

Step 3: Give existing pages a record (L2)
=========================================

New pages get a record when an editor creates one in the page properties.
For existing pages, the backfill command creates default records:

..  code-block:: bash

    vendor/bin/typo3 provenance:backfill --dry-run   # shows what would happen
    vendor/bin/typo3 provenance:backfill

It covers standard pages in the default language. The first publication date
is the page creation date, the author the user of the oldest history entry.
Running it again only picks up pages that are still without a record.

A page reaches L2 only when its record is complete: every author needs a
public profile URI in the backend user record (see :ref:`editors-identity`).
Until then the page claims L1.

..  important::

    The author appears in the public record with the real name of that backend
    user (see :ref:`editors-identity`). The oldest history entry is often an
    administrator or an import account rather than the person who wrote the
    text. Check the backend users' real names before running the command, or
    correct the authors afterwards in the page properties.

..  _installation-web-server:

Step 4: Let the well-known paths through
========================================

Auditors and the verifier fetch ``/.well-known/content-provenance`` and its
sub-paths. TYPO3 answers them, so the web server must pass them on like any
other request that is not a file.

Many configurations deny every path with a leading dot. Check yours and make
an exception, for example in nginx:

..  code-block:: nginx

    # Before a rule like  location ~ /\.  { deny all; }
    location ^~ /.well-known/content-provenance {
        try_files $uri /index.php$is_args$args;
    }

With Apache and the ``.htaccess`` shipped by TYPO3 this works without change.
Check it:

..  code-block:: bash

    curl -i https://example.org/.well-known/content-provenance

The answer must come from TYPO3 (JSON, or a JSON 404 while the audit
extension is not installed), not a 403 from the web server.

..  _installation-key:

Step 5: Create the site key (L3)
================================

..  code-block:: bash

    vendor/bin/typo3 provenance:keys:generate
    vendor/bin/typo3 cache:flush

The key is stored in :file:`var/provenance-keys/`, outside the web root. Pages
are signed when TYPO3 renders them next, so the first visit after the cache
flush signs a page. See :ref:`configuration-keys` for backups and rotation.

..  _installation-audit:

Step 6: Audit endpoint and facts (L4)
=====================================

Nothing to do: with ``provenance_audit`` installed,
``/.well-known/content-provenance`` lists the site's documents. With
``provenance_facts`` and its site set, editors can add facts to content
elements (see :ref:`editors-facts`). With the audit endpoint, signed pages
reach L4; facts make them citable down to single statements.

..  _installation-check:

Step 7: Check the result
========================

Open a page and look for ``<script type="application/ld+json">`` in
``<head>``. Then run the verifier from the conformance suite as described in
:ref:`verification`. Every signed page must be reported as ``valid``.
