..  _start:

==================
Content Provenance
==================

:Extension key:
    provenance

:Package name:
    cms-provenance/provenance

:Version:
    |release|

:Language:
    en

:Author:
    Content Provenance contributors

:License:
    This document is published under the
    `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
    license.

:Rendered:
    |today|

----

Makes every page of a TYPO3 site carry a machine-readable, verifiable record
of who published it, when, and on what sources. This is the TYPO3 reference
implementation of the
`Content Provenance Specification <https://github.com/dkd-dobberkau/content-provenance-spec>`__.

This manual covers all four extensions: the core extension ``provenance`` and
the optional ``provenance_signing``, ``provenance_audit`` and
``provenance_facts``.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What the extensions do, the four layers and the conformance levels.

    ..  card:: :ref:`Installation <installation>`

        From an existing TYPO3 site to conformance level L4, step by step.

    ..  card:: :ref:`For editors <editors>`

        Authors, sources, revisions and facts: what editors need to know.

    ..  card:: :ref:`Verification <verification>`

        How to check that a site is conformant, and what to do when it is not.

..  toctree::
    :maxdepth: 2
    :titlesonly:
    :hidden:

    Introduction/Index
    Installation/Index
    Configuration/Index
    Editors/Index
    Verification/Index
    Developers/Index
    KnownProblems/Index
