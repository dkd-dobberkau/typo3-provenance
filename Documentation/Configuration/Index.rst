..  _configuration:

=============
Configuration
=============

..  _configuration-site-sets:

Site sets
=========

..  confval-menu::
    :name: site-sets
    :display: table
    :type:

..  confval:: cms-provenance/provenance
    :name: set-provenance
    :type: site set

    Emits the provenance record of each page as JSON-LD in ``<head>``, through
    ``page.headerData.1794``. Expects the page object to be named ``page``.

..  confval:: cms-provenance/provenance-facts
    :name: set-provenance-facts
    :type: site set

    Marks facts in rendered content elements, through
    ``tt_content.stdWrap.postUserFunc``. If your site package uses that
    property itself, only one of the two takes effect.

..  _configuration-rte:

Fact button in the rich text editor
===================================

The facts extension ships the RTE preset ``provenance``: the TYPO3 default
preset plus the :guilabel:`Fact` button. Enable it with page TSconfig, for
example in the site's :file:`page.tsconfig`:

..  code-block:: typoscript

    RTE.default.preset = provenance

To add the button to your own preset, load the plugin and add the toolbar
item. TYPO3 merges imported YAML lists by position, so repeat the complete
``importModules`` list of your preset:

..  code-block:: yaml

    editor:
      config:
        importModules:
          - { module: '@typo3/rte-ckeditor/plugin/whitespace.js', exports: [ 'Whitespace' ] }
          - { module: '@typo3/rte-ckeditor/plugin/typo3-link.js', exports: [ 'Typo3Link' ] }
          - { module: '@cms-provenance/provenance-facts/fact-plugin.js', exports: [ 'ProvenanceFact' ] }
        toolbar:
          items:
            # … your items …
            - provenanceFact

The button creates the fact through a backend AJAX route with the editor's
permissions; editors need write access to the facts table like for the
:guilabel:`Provenance facts` tab.

..  _configuration-extension-settings:

Extension settings
==================

Set in :guilabel:`Admin Tools > Settings > Extension Configuration` or in
:file:`config/system/settings.php`.

..  confval:: keyDirectory
    :name: provenance-signing-keyDirectory
    :type: string
    :default: var/provenance-keys

    Extension ``provenance_signing``. Directory for the site keys, absolute or
    relative to the project root. Must not be inside the web root.

..  confval:: paginationSize
    :name: provenance-audit-paginationSize
    :type: int
    :default: 100

    Extension ``provenance_audit``. Documents per page of the audit listing,
    1 to 1000.

..  confval:: cacheTtl
    :name: provenance-audit-cacheTtl
    :type: int
    :default: 300

    Extension ``provenance_audit``. ``Cache-Control`` lifetime of the audit
    listing in seconds.

..  _configuration-content-area:

The signed content area
=======================

The signature covers the text of the content area, not the whole page. By
default that is the first ``<main>`` element, or ``<body>`` when there is
none. Navigation, header and footer should therefore sit outside ``<main>``.

Two attributes give finer control:

``data-cps-content``
    Marks the content area explicitly. When present on one or more elements,
    only those are signed.

``data-cps-ignore``
    Excludes an element inside the content area, for example a form with a
    request token or a personalised greeting.

..  code-block:: html

    <main>
        <article data-cps-content>
            … editorial content …
            <form data-cps-ignore> … </form>
        </article>
        <aside>Related links, not signed</aside>
    </main>

..  _configuration-uncached:

Uncached content
================

Pages are signed when TYPO3 generates the cacheable HTML. Uncached parts
(``USER_INT``, ``COA_INT``) are inserted afterwards and would change the text
a verifier sees. Keep them outside the content area or mark them with
``data-cps-ignore``. A page that fails verification for this reason is
reported as ``invalid`` with "content hash does not match".

..  _configuration-keys:

Site keys
=========

*   One active key per installation. Commands:

    ..  code-block:: bash

        vendor/bin/typo3 provenance:keys:generate   # first key
        vendor/bin/typo3 provenance:keys:list
        vendor/bin/typo3 provenance:keys:rotate     # new key, old one stays published

*   The secret key file has mode 0600. The extension refuses to use it when
    other users can read it.
*   **Back up the key directory encrypted**, separately from database dumps.
    Whoever has the secret key can sign in the site's name.
*   After rotating, flush the page cache so pages get signed with the new key.
    Retired keys stay published under
    ``/.well-known/content-provenance/keys`` so older signatures remain
    verifiable.
*   If a key is compromised: rotate, flush the cache, and tell auditors which
    key id to distrust. Revocation is an open question in the specification.
