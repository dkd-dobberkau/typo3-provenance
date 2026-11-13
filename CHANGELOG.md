# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

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
