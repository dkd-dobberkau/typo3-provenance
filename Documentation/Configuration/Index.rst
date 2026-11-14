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

..  confval:: cms-provenance/provenance-mx
    :name: set-provenance-mx
    :type: site set

    Optional. Also emits the record of each page as MX meta tags
    (`Machine Experience <https://mx.allabout.network/>`__, drafts of The
    Gathering), through ``page.headerData.1795``: ``author``,
    ``mx:canonicalUri``, ``mx:summary``, ``mx:conformsTo``,
    ``mx:trainingDataPolicy``, ``mx:created``, ``mx:modified`` and
    ``mx:maintainedDate``. The summary is the page's :guilabel:`Abstract`,
    else its :guilabel:`Description`. ``mx:conformsTo`` names this
    specification, never MX itself. The tags are not signed; the signed
    record stays the JSON-LD block.

    MX Level 2 needs all of ``mx:canonicalUri``, ``mx:summary``,
    ``mx:conformsTo`` and ``mx:trainingDataPolicy``. A page without a summary,
    or a site without the setting below, stays at MX Level 1; nothing claims
    a level.

..  confval:: provenance.mx.trainingDataPolicy
    :name: setting-mx-training-data-policy
    :type: string
    :default: (empty)

    Site setting of the set above: whether pages may be used to train AI
    models, for example "Permitted with attribution." Sent as
    ``mx:trainingDataPolicy``; left empty, the tag is not sent.

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

..  _configuration-media:

Media provenance
================

Records list the images, videos and audio files referenced by the content
elements of a page. Install the system extension ``filemetadata``
(``composer require typo3/cms-filemetadata``) so editors can enter creator,
copyright and source; without it only licence and digital source type are
available.

Every media file gets a stable identifier and a SHA-256 of its bytes when it is
uploaded or replaced, and whenever the FAL indexer finds that its bytes changed.
Files changed outside TYPO3, for example over FTP, are only noticed by the
indexer: keep the scheduler task :guilabel:`File Abstraction Layer: Update
storage index` running, or the record keeps the old hash. For files that
existed before the extension was installed, or whose hash could not be computed
earlier, run:

..  code-block:: bash

    vendor/bin/typo3 provenance:backfill-media

Hashing reads every media file once; on large file storages run it outside
peak hours. ``--dry-run`` only reports how many files would be updated.

..  _configuration-markdown:

Markdown version of pages
=========================

With ``b13/ai-bots-love-markdown`` installed (``composer require
b13/ai-bots-love-markdown``), every page is also available as Markdown. The
provenance extension adds to its YAML front matter:

``cps``
    The identifier of the record (``record``), the level the page reaches
    (``conformance``) and ``signedDocument``, the URL of the HTML page. When the
    page is signed, also ``contentHash`` and ``key``. The signature covers the
    HTML page, not the Markdown; verify a page through ``signedDocument``.

MX fields
    With the site set ``cms-provenance/provenance-mx``, the front matter follows
    the three zones of the MX Carrier Formats note: ``created`` (instead of
    ``date``), ``modified``, ``type: info-doc``, ``tags`` from keywords and
    categories, and the MX fields under ``mx``.

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
