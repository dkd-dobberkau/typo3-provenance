# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Backend module "Provenance" with the submodule "Pages": per page of a
  subtree, whether it has a record, its level without a signature and what
  is missing. Sub-extensions add submodules; `PageSubtree` and
  `ModifyPageOverviewRowEvent` are their API. `ConformanceLevel::missing()`
  explains why a record stays at L1.

- Site set `cms-provenance/provenance-mx` (optional): the record of each page
  as MX meta tags (`<meta name="mx:…">`, Machine Experience drafts of The
  Gathering), with the site setting `provenance.mx.trainingDataPolicy`.

- `ConformanceLevel` service: the level a record reaches (specification
  Section 4, Draft 0.4), computed from the record. An incomplete record (no
  author, an author without profile URI, a source without description or
  access date) stays at L1.
- `PublicPageRepository`: decides which pages the public well-known endpoints
  may reveal. A page counts as public only if it and every page above it are
  visible to anonymous visitors.
- `AbsoluteUrlResolver` service: absolute site, origin and page URLs even when
  the site base is relative (such as `/`), completing scheme and host from the
  request. The sub-extensions use it for every URL they publish.
- Manual for all four extensions in `Documentation/` (milestone M7):
  installation to L4, configuration, editorial guide, verification,
  developer API and known limits.
- `WellKnownRouter`: one middleware for `/.well-known/content-provenance`
  and its sub-paths, with shared CORS, preflight and method handling.
  Extensions answer a sub-path by implementing `WellKnownHandlerInterface`.
- JSON-LD output of the page provenance record via the site set
  `cms-provenance/provenance` (milestone M3), with the
  `BeforeJsonLdEmittedEvent` for sub-extensions. Output is escaped so record
  content cannot leave the script element; usernames are never published.
- `provenance:backfill` command (with `--dry-run`) creating records for
  existing standard pages, taking the author from the oldest history entry.
- New documents get a first publication date when none is given.
- Core data model (milestone M2): tables `tx_provenance_document` and
  `tx_provenance_source`, provenance record on pages and content elements,
  external identity fields on backend users.
- `last_maintained_at` field for the MX `maintainedDate`, separate from the
  last substantive revision.
- Stable `urn:uuid` document identifier assigned on creation and never changed;
  the creating TYPO3 version is recorded.
- Unit and functional tests, GitHub Actions CI for TYPO3 13.4 and 14 with a
  Trivy scan.
- Repository skeleton: composer.json, ext_emconf.php, Services.yaml,
  extension icon, contribution guide (milestone M1).

### Changed

- Breaking: the conformance level is computed instead of chosen. The field
  `conformance_level` of the provenance record is removed. After updating,
  flush the caches; the audit listing shows the new levels once each page has
  been rendered again.

### Fixed

- The access date of sources was one day early on servers east of UTC: date
  fields hold midnight in the server time zone and were formatted in UTC.
