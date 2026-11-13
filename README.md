# Content Provenance

Machine-readable provenance records for pages and content elements, emitted as JSON-LD.

TYPO3 reference implementation of **Layer 1** of the
[Content Provenance Specification](https://github.com/dkd-dobberkau/content-provenance-spec). Conformance level reached: **L2 Provenance**.

This is the core extension. The optional extensions [`provenance_signing`](https://github.com/dkd-dobberkau/typo3-provenance-signing), [`provenance_facts`](https://github.com/dkd-dobberkau/typo3-provenance-facts) and [`provenance_audit`](https://github.com/dkd-dobberkau/typo3-provenance-audit) build on it.

> **Status: experimental, pre-release.** This repository currently contains the
> skeleton only (milestone M1 of the [PRD](https://github.com/dkd-dobberkau/cms-provenance.org/blob/main/docs/prd-typo3-extensions.md)). Nothing here is
> ready for production use.

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

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
