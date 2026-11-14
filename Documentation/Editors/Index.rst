..  _editors:

===========
For editors
===========

The value of provenance is created by editors, not by software. The
extensions publish what you enter; machines and auditors trust it only as far
as it is accurate. This chapter explains what to enter and when.

..  _editors-record:

The provenance record of a page
===============================

Open the page properties and switch to the tab :guilabel:`Provenance`. A page
has at most one record. If there is none yet, create it there; most sites
create records for existing pages once with a command, so you will usually
find one.

:guilabel:`Document identifier`
    Assigned automatically, never changes, not even when the page moves or
    its URL changes. You cannot edit it, and that is intended.

:guilabel:`First published`
    Set when the record is created. It is the date the content first went
    public.

:guilabel:`Authors`
    The people who wrote the content. Choose them from the backend users.
    Only their real name and their public profile are published, never the
    login name. See :ref:`editors-identity`.

:guilabel:`Reviewer`
    Optional. Someone who checked the content before or after publication.

..  _editors-revisions:

Last substantive revision and last confirmation
===============================================

These two dates answer different questions, and keeping them apart is what
makes them useful.

:guilabel:`Last substantive revision`
    Set it when the **meaning** of the content changed: a new figure, a
    corrected statement, an added or removed claim, a new conclusion. Do
    **not** set it for typo fixes, layout changes, new images that illustrate
    the same thing, or a republish.

:guilabel:`Last confirmed as accurate`
    Set it when you have checked the content and it is still correct, without
    changing it. Regular reviews of evergreen content are the typical case.
    The value is published as ``maintainedDate``.

..  tip::

    "The page was edited on 3 March" says little. "The figures were last
    changed on 3 March and the page was confirmed accurate on 1 October" is
    what a reader, or a machine deciding whether to quote you, needs.

..  _editors-sources:

Sources
=======

Add a source for every external document the content relies on.

:guilabel:`Source URL`
    Where the source can be read.

:guilabel:`What does this source support?`
    One sentence: which statement in the content it backs. "Annual report
    2025, revenue figure in the second paragraph" is better than "Report".

:guilabel:`Access date`
    When you read it. Web sources change; the date tells readers which
    version you saw.

:guilabel:`Archive URL`
    Optional but recommended: a snapshot, for example from the Internet
    Archive (``https://web.archive.org/``), so the source can still be read
    when the original moves or changes.

..  _editors-media:

Images, videos and audio
========================

The record of a page lists the images, videos and audio files of its content
elements, with what is known about where they come from. Enter this once per
file, in the file's metadata (:guilabel:`Filelist`, edit the file's metadata);
every page that uses the file shows it.

:guilabel:`Creator`, :guilabel:`Copyright`, :guilabel:`Source`
    Who made the file, the copyright notice and where it comes from. A source
    that is a web address is published as the file's origin. These fields
    need the system extension :guilabel:`filemetadata`.

:guilabel:`Licence (URL)`
    The web address of the licence, for example
    ``https://creativecommons.org/licenses/by/4.0/``.

:guilabel:`How the media came to exist`
    The IPTC digital source type: a photo or recording from real life, a
    digitised original, human or AI edits, or created with generative AI.
    Leave it empty if you do not know; nothing is published then.

Missing data never lowers the level of a page. The :guilabel:`Media` column of
the Provenance module shows which files of a page lack a creator or licence
and links to their metadata.

..  _editors-identity:

Your public identity
====================

An administrator can add a public profile URL to your backend user, such as
your ORCID (``https://orcid.org/…``) or a staff page, in the user record under
:guilabel:`Provenance`. It is published as your identifier on every page you
author. The field :guilabel:`External identity verified` is set by the person
who confirmed that the profile is really yours.

..  _editors-facts:

Facts
=====

Facts make single statements citable: a figure, a date, a definition. An AI
system or a fact checker can then point to exactly that statement rather than
the whole page. Add facts sparingly, for the statements people are most likely
to quote.

The quickest way is the :guilabel:`Fact` button in the rich text editor:
select the words that state the fact, press :guilabel:`Fact`, fill in the
dialog and press :guilabel:`Create fact`. The selected words become the quote.
The text itself is not changed; reload the form to see the new fact in the
tab :guilabel:`Provenance facts`. The button needs a saved content element.

You can also open the tab :guilabel:`Provenance facts` and add a fact there.
The fields are the same:

:guilabel:`Fact ID`
    A short, unique, lowercase name such as ``employees-2025``. It becomes
    part of the address ``…/page#fact-employees-2025`` and must not change
    later.

:guilabel:`Quoted text`
    The exact words in the content element that state the fact, copied from
    the text. Keep the quote inside one paragraph and without bold, italic or
    links in between; otherwise it cannot be marked on the page (it stays
    citable, but readers do not see where it is).

:guilabel:`Subject`
    What the fact is about, as an address (URI): your organisation's page,
    a Wikidata entry, a product page.

:guilabel:`Predicate`
    The property the fact states. Use a schema.org term such as
    ``numberOfEmployees``, ``foundingDate`` or ``areaServed``, or a full URI
    from another vocabulary.

:guilabel:`Value` or :guilabel:`Value as URI`
    The stated value, as text ("180") or as an address.

:guilabel:`Evidence`
    Optional: the document that proves the fact.

:guilabel:`Confidence`
    *Measured* for counted or audited figures, *Estimated* for projections
    and approximations, *Asserted* for statements without further proof.

:guilabel:`Valid from`, :guilabel:`Valid until`
    Optional: the period the fact refers to, for example a fiscal year.

Example:

..  list-table::
    :widths: 30 70

    *   -   Content
        -   "At the end of 2025 the company employed 180 people."
    *   -   Fact ID
        -   ``employees-2025``
    *   -   Quoted text
        -   ``180 people``
    *   -   Subject
        -   ``https://example.org/about``
    *   -   Predicate
        -   ``numberOfEmployees``
    *   -   Value
        -   ``180``
    *   -   Evidence
        -   ``https://example.org/annual-report-2025.pdf``
    *   -   Confidence
        -   Measured
    *   -   Valid from / until
        -   2025-01-01 / 2025-12-31

..  _editors-facts-wikidata:

Researching a fact in Wikidata
------------------------------

With the optional extension ``provenance_wikidata`` the :guilabel:`Fact`
dialog has a field :guilabel:`Look up in Wikidata` above the fact's fields.

#.  Type what the fact is about, for example "Ashmolean", and pick the right
    entry from the list. The subject becomes its Wikidata address, for
    example ``http://www.wikidata.org/entity/Q636400``.
#.  The dialog lists what Wikidata states about the entry, with the source
    Wikidata gives where there is one.
#.  Press :guilabel:`Use` next to the statement that matches your text. It
    fills :guilabel:`Predicate`, :guilabel:`Value` (or :guilabel:`Value as
    URI`) and :guilabel:`Evidence`.
#.  Check every field before you press :guilabel:`Create fact`. Wikidata is a
    source, not proof: if your text says something else, your text and your
    own evidence count.

Wikidata addresses do not change when an article is renamed and are the same
in every language, which makes the fact easier to check later. If Wikidata is
not reachable, the dialog says so and you fill in the fields by hand as
before.

..  _editors-module:

The Provenance module
=====================

The module :guilabel:`Content > Provenance` (:guilabel:`Web > Provenance` in
TYPO3 13) gives an overview of the page you select in the page tree and the
pages below it. Choose how many levels with :guilabel:`Levels`. It shows the
live version in the default language and changes nothing.

:guilabel:`Pages`
    For each page: whether it has a provenance record, the level the record
    reaches without a signature (L1 or L2) and, for L1, what is missing, for
    example "Author "Ada" has no profile URI." Hidden pages and hidden
    records are marked. Click a page title to edit its properties.

:guilabel:`Facts` (with the facts extension)
    All facts on the selected pages: fact ID, quote, subject, predicate,
    value, evidence, confidence, validity and page. Wikidata addresses are
    shortened to their ID, such as ``Q636400``. Search by fact ID, quote,
    subject, value or evidence, and filter by predicate or confidence.
    Hidden facts, hidden elements and hidden pages are marked. The edit
    button opens the content element the fact belongs to. How facts are made
    is described in :ref:`editors-facts`.

..  _editors-checklist:

Checklist before publishing
===========================

*   Authors are set and correct.
*   Every external figure or quote has a source with a sentence on what it
    supports.
*   If the meaning changed: :guilabel:`Last substantive revision` is set.
*   Quotable figures and dates have facts, with quotes copied exactly.
*   Nothing in the record is hidden that should be public: a hidden record or
    fact is not published.
