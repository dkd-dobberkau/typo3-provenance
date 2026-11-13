..  _introduction:

============
Introduction
============

Schema.org describes what a page is. It does not say who made an assertion,
when, or whether to trust it. The
`Content Provenance Specification <https://github.com/dkd-dobberkau/content-provenance-spec>`__
(CPS) closes that gap with four layers that build on each other. These
extensions implement them for TYPO3.

..  _introduction-layers:

Layers and extensions
=====================

..  list-table::
    :header-rows: 1
    :widths: 25 35 40

    *   -   Layer
        -   Extension
        -   What a site gains
    *   -   1 Provenance record
        -   ``provenance`` (required)
        -   Every page carries a JSON-LD record: stable identifier, authors,
            publication and revision dates, sources.
    *   -   2 Signature
        -   ``provenance_signing``
        -   The record and the page text are signed with an Ed25519 site key.
            Anyone can verify that the published text is unchanged.
    *   -   3 Facts
        -   ``provenance_facts``
        -   Individual statements in the text become addressable and citable.
    *   -   4 Audit endpoint
        -   ``provenance_audit``
        -   ``/.well-known/content-provenance`` lists all published documents
            for auditors and AI agents.

..  _introduction-levels:

Conformance levels
==================

Each page claims the level it actually reaches in its record
(``cpsConformance``); the audit endpoint claims the lowest level among its
pages. The level is computed, not configured. Levels are cumulative, so a page
whose record is incomplete stays at L1 even when it is signed and has facts.

..  list-table::
    :header-rows: 1
    :widths: 20 40 40

    *   -   Level
        -   Requires
        -   Installed extensions
    *   -   L1 Discoverable
        -   XML sitemap and a canonical URL on every page
        -   none; for example ``typo3/cms-seo``. The extensions assume L1, the
            conformance suite checks it
    *   -   L2 Provenance
        -   A complete record: at least one author, every author with a public
            profile URI (see :ref:`editors-identity`), every source with a
            description and an access date
        -   ``provenance``
    *   -   L3 Verifiable
        -   The record is signed
        -   plus ``provenance_signing``
    *   -   L4 Citable
        -   The audit endpoint is live. Facts are optional; those a page has
            are addressable
        -   plus ``provenance_audit`` and ``provenance_facts``

..  _introduction-what-it-is-not:

What these extensions are not
=============================

They do not rank or score content, do not enforce an editorial policy and do
not need a commercial registry or service. Everything runs inside your TYPO3
installation; verification works with public information only.
