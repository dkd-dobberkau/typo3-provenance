# Content Provenance

Machine-readable provenance records for pages and content elements, emitted as JSON-LD.

TYPO3 reference implementation of **Layer 1** of the
[Content Provenance Specification](https://github.com/dkd-dobberkau/content-provenance-spec). Conformance level reached: **L2 Provenance**.

This is the core extension. The optional extensions [`provenance_signing`](https://github.com/dkd-dobberkau/typo3-provenance-signing), [`provenance_facts`](https://github.com/dkd-dobberkau/typo3-provenance-facts) and [`provenance_audit`](https://github.com/dkd-dobberkau/typo3-provenance-audit) build on it.

> **Status: experimental, pre-release.** Milestones M2 and M3 of the
> [PRD](https://github.com/dkd-dobberkau/cms-provenance.org/blob/main/docs/prd-typo3-extensions.md)
> are in place: data model, backend forms, JSON-LD output and backfill. A site
> using it reaches conformance level L2. Nothing here is ready for production use.

## Scope

- Provenance record data model for `pages` and `tt_content`
- Backend forms (Provenance tab, sources as inline records)
- JSON-LD output in the page head
- Canonicalisation service shared with the signing and audit extensions
- Public PHP API and PSR-14 events for sub-extensions
- CLI: `provenance:document:show`, `provenance:document:validate`, `provenance:backfill`

## Requirements

- TYPO3 13.4 LTS or 14
- PHP 8.2 or later

## Installation

Not yet released. Once a first version is tagged:

```bash
composer require cms-provenance/provenance
```

Extension key: [`provenance`](https://extensions.typo3.org/extension/provenance)

## Links

- Project site: <https://cms-provenance.org>
- Specification: <https://github.com/dkd-dobberkau/content-provenance-spec>
- Product requirements (PRD): <https://github.com/dkd-dobberkau/cms-provenance.org/blob/main/docs/prd-typo3-extensions.md>
- Conformance test suite: <https://github.com/dkd-dobberkau/content-provenance-conformance>

## Usage

1. Install the extension and update the database schema
   (`vendor/bin/typo3 extension:setup`).
2. Add the site set **Content Provenance: JSON-LD output**
   (`cms-provenance/provenance`) to your site configuration:

   ```yaml
   dependencies:
     - cms-provenance/provenance
   ```

   It adds `page.headerData.1794` and expects the page object to be named
   `page`.
3. Give existing pages a provenance record:

   ```bash
   vendor/bin/typo3 provenance:backfill --dry-run   # see what would happen
   vendor/bin/typo3 provenance:backfill
   ```

   Covers standard pages in the default language. The first publication date
   is the page creation date, the author the oldest history entry.
4. Edit records in the page properties, tab **Provenance**: authors, sources,
   last substantive revision, last confirmation.

Every page with a visible record now carries one JSON-LD block in `<head>`:

```json
{
  "@context": ["https://schema.org", "https://cms-provenance.org/ns/v1"],
  "@type": "WebPage",
  "@id": "urn:uuid:d02f539f-07c2-482a-bef4-435f91895da9",
  "headline": "Home",
  "author": [{ "@type": "Person", "@id": "https://orcid.org/0000-0001-2345-6789", "name": "Ada Lovelace" }],
  "datePublished": "2026-11-13T16:51:05Z",
  "lastSubstantiveRevision": "2026-11-13T16:58:51Z",
  "cpsConformance": 2,
  "generator": "TYPO3 14.3.11 + provenance 0.1.0"
}
```

Authors appear with their external identity and real name only; backend
usernames are never published.

### Extending the output

Listen to `CmsProvenance\Provenance\Event\BeforeJsonLdEmittedEvent` to add or
change properties. The signing and facts extensions use it to add signatures
and claims to the same block.

## Development

```bash
composer install
composer test              # unit and functional tests
composer test:functional   # functional tests only, SQLite by default
```

Functional tests use SQLite unless `typo3DatabaseDriver` and the related
`typo3Database*` variables point to another database. CI runs the suite against
TYPO3 13.4 and 14 on PHP 8.2 and 8.4.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
