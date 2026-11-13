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

A site claims a level in each record (``cpsConformance``) and in the audit
endpoint.

..  list-table::
    :header-rows: 1
    :widths: 20 40 40

    *   -   Level
        -   Requires
        -   Installed extensions
    *   -   L1 Discoverable
        -   Sitemap, canonical URLs, basic schema.org
        -   none (a well-run TYPO3 site)
    *   -   L2 Provenance
        -   A complete record on every page
        -   ``provenance``
    *   -   L3 Verifiable
        -   Signed records
        -   plus ``provenance_signing``
    *   -   L4 Citable
        -   Addressable facts and the audit endpoint
        -   plus ``provenance_audit`` and ``provenance_facts``

..  _introduction-what-it-is-not:

What these extensions are not
=============================

They do not rank or score content, do not enforce an editorial policy and do
not need a commercial registry or service. Everything runs inside your TYPO3
installation; verification works with public information only.
