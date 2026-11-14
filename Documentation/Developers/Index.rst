..  _developers:

==========
Developers
==========

..  _developers-architecture:

How the parts fit together
==========================

#.  The core extension renders the record of the current page as one JSON-LD
    block in ``<head>`` (cached ``USER`` object in ``page.headerData``) and
    dispatches :php:`BeforeJsonLdEmittedEvent`.
#.  The facts extension listens to that event and adds the ``claim`` list.
#.  When TYPO3 has generated the cacheable page, the signing extension
    computes the canonical text (Annex A of the specification), adds
    ``contentHash``, ``key`` and ``signature`` to the block and stores the
    signed record in ``tx_provenancesigning_record``.
#.  The audit extension reads records and stored signatures for
    ``/.well-known/content-provenance``.

All well-known paths are served by one router in the core extension.

..  _developers-events:

Events
======

..  php:namespace:: CmsProvenance\Provenance\Event

..  php:class:: BeforeJsonLdEmittedEvent

    Dispatched before the JSON-LD block of a page is written. Add or change
    properties; set an empty array to suppress the block.

    ..  php:method:: getJsonLd()

        :returntype: array

    ..  php:method:: setJsonLd(array $jsonLd)

    ..  php:method:: getRecord()

        :returntype: CmsProvenance\\Provenance\\Domain\\Model\\ProvenanceRecord

    ..  php:method:: getRequest()

        :returntype: Psr\\Http\\Message\\ServerRequestInterface

Example: use ``Article`` instead of ``WebPage`` for news pages.

..  code-block:: php
    :caption: EXT:my_sitepackage/Classes/EventListener/ArticleType.php

    use CmsProvenance\Provenance\Event\BeforeJsonLdEmittedEvent;
    use TYPO3\CMS\Core\Attribute\AsEventListener;
    use TYPO3\CMS\Frontend\Page\PageInformation;

    #[AsEventListener]
    final class ArticleType
    {
        public function __invoke(BeforeJsonLdEmittedEvent $event): void
        {
            $page = $event->getRequest()->getAttribute('frontend.page.information');
            if ($page instanceof PageInformation && $page->getPageRecord()['doktype'] === 116) {
                $jsonLd = $event->getJsonLd();
                $jsonLd['@type'] = 'NewsArticle';
                $event->setJsonLd($jsonLd);
            }
        }
    }

Records must contain integers only, no fractional numbers (Annex A.4).
Signing fails otherwise and the page is delivered unsigned.

..  _developers-well-known:

Well-known endpoints
====================

..  php:namespace:: CmsProvenance\Provenance\WellKnown

..  php:interface:: WellKnownHandlerInterface

    Answers one sub-path of ``/.well-known/content-provenance``. Services
    implementing it are registered automatically. The router adds CORS
    headers, answers preflight requests, rejects write methods with 405 and
    returns a JSON 404 for unknown sub-paths.

    ..  php:method:: getSubPath()

        :returntype: string

        The path segment, for example ``keys``; an empty string for the
        endpoint itself.

    ..  php:method:: handle(Psr\\Http\\Message\\ServerRequestInterface $request, string $remainder)

        :returntype: Psr\\Http\\Message\\ResponseInterface

The sub-paths are fixed by the specification (Section 3.4): the listing,
``keys``, ``keys/{keyId}`` and ``facts/{factId}``. Do not add your own.

..  _developers-fact-lookups:

Adding a lookup to the fact dialog
==================================

Extensions can help editors research a fact in the :guilabel:`Fact` dialog of
the rich text editor, as ``provenance_wikidata`` does for Wikidata. A lookup
is an ES module listed in the editor configuration key
``provenanceFact.lookups``. Add it in a listener, so no RTE preset has to be
changed:

..  code-block:: php
    :caption: EXT:my_lookup/Classes/EventListener/RegisterLookup.php

    use TYPO3\CMS\Core\Attribute\AsEventListener;
    use TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent;

    #[AsEventListener]
    final class RegisterLookup
    {
        public function __invoke(AfterPrepareConfigurationForEditorEvent $event): void
        {
            $configuration = $event->getConfiguration();
            $configuration['provenanceFact']['lookups'][] = '@my-vendor/my-lookup/lookup.js';
            $event->setConfiguration($configuration);
        }
    }

When the dialog opens, each module is imported and its default export is
called with one object:

``form``
    The dialog's form element.

``quote``
    The selected words.

``panel``
    An empty element above the fact's fields for the lookup's own interface.
    The dialog belongs to the top frame of the backend: create elements with
    ``panel.ownerDocument``.

``fill(values)``
    Sets any of ``subject``, ``predicate``, ``objectLiteral``, ``objectUri``
    and ``sourceUrl`` and marks the changed fields.

A lookup that fails to load is skipped with a console warning. Lookups must
not call external services from the browser; route requests through a
backend AJAX route, as ``provenance_wikidata`` does.

..  _developers-module:

The Provenance module
=====================

The core registers the backend module ``provenance`` (parent ``web``) and its
submodule ``provenance_pages``. Sub-extensions add submodules with
``'parent' => 'provenance'`` in their ``Configuration/Backend/Modules.php``.

..  php:namespace:: CmsProvenance\Provenance\Backend

..  php:class:: PageSubtree

    Public service: the pages of a subtree a backend user may see, live and
    in the default language, hidden pages included, in page tree order.

    ..  php:method:: pageIds(int $rootUid, int $depth, TYPO3\\CMS\\Core\\Authentication\\BackendUserAuthentication $user)

        :returntype: list<int>

        ``PageSubtree::ALL`` (999) as depth means all levels.

..  php:namespace:: CmsProvenance\Provenance\Event

..  php:class:: ModifyPageOverviewRowEvent

    Dispatched for every row of the :guilabel:`Pages` overview. Add values
    under the row's ``extra`` key; each becomes a column. Methods:
    ``getRow()``, ``setRow(array $row)``, ``getPageUid()``.

The facts extension adds the submodule ``provenance_facts`` and two events:

..  php:namespace:: CmsProvenance\ProvenanceFacts\Event

..  php:class:: ModifyFactOverviewRowEvent

    Dispatched for every row of the :guilabel:`Facts` overview. Add values
    under the row's ``extra`` key; each becomes a column, for example a
    Wikidata status. Methods: ``getRow()``, ``setRow(array $row)``.

..  php:class:: BeforeFactOverviewRenderedEvent

    Dispatched before the overview is rendered, with ``getView()`` (the
    ``ModuleTemplate``), ``getPageId()`` and ``getRequest()``, for example to
    add a doc header button such as "Suggest facts".

..  _developers-cli:

Commands
========

..  list-table::
    :header-rows: 1
    :widths: 40 20 40

    *   -   Command
        -   Extension
        -   Purpose
    *   -   ``provenance:backfill [--dry-run]``
        -   provenance
        -   Records for existing standard pages
    *   -   ``provenance:backfill-media [--dry-run]``
        -   provenance
        -   Identifiers and SHA-256 for media files that lack a current one
    *   -   ``provenance:keys:generate``
        -   provenance_signing
        -   First site key
    *   -   ``provenance:keys:rotate``
        -   provenance_signing
        -   New active key; the old one stays published
    *   -   ``provenance:keys:list``
        -   provenance_signing
        -   All keys with state

..  _developers-tables:

Tables
======

..  list-table::
    :header-rows: 1
    :widths: 40 20 40

    *   -   Table
        -   Extension
        -   Content
    *   -   ``tx_provenance_document``
        -   provenance
        -   The record of a page or content element
    *   -   ``tx_provenance_source``
        -   provenance
        -   Sources of a record
    *   -   ``tx_provenance_media_hash``
        -   provenance
        -   Every SHA-256 a media file has had, with ``valid_from`` and
            ``valid_until`` (empty for the current one)
    *   -   ``tx_provenancesigning_record``
        -   provenance_signing
        -   Latest signed record per page and language
    *   -   ``tx_provenancefacts_fact``
        -   provenance_facts
        -   Facts of content elements

``pages`` and ``tt_content`` get ``provenance_document_uid``; ``be_users``
gets ``external_identity_uri`` and ``external_identity_verified``.
``sys_file`` gets ``tx_provenance_media_uri``, ``tx_provenance_sha256`` and
``tx_provenance_sha256_of`` (the ``sha1`` the hash was computed for);
``sys_file_metadata`` gets ``tx_provenance_license`` and
``tx_provenance_digital_source_type``.

..  _developers-media:

Media in the record
===================

``JsonLdHeaderData`` adds the members ``image``, ``video`` and ``audio``
(schema.org ``ImageObject``, ``VideoObject``, ``AudioObject``) before it
dispatches ``BeforeJsonLdEmittedEvent``, so listeners and the signature see
them. ``PageMediaRepository::findByPage()`` reads the file references of the
visible content elements; ``MediaJsonLdBuilder`` turns them into entries.

The cached page is tagged ``sys_file_<uid>`` for every listed file, the tag
TYPO3's own cache auto-tagging uses. Saving a file's metadata, replacing a
file or a changed hash flush that tag, so the page is rendered and signed
again with current data.

..  _developers-tests:

Running the tests
=================

Each extension has unit and functional tests (SQLite by default):

..  code-block:: bash

    composer install
    composer test

The signing extension runs the canonicalisation vectors of the conformance
suite as unit tests; they must pass byte for byte.
