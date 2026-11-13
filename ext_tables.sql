# Columns TYPO3 cannot derive from TCA. All other fields of the provenance
# tables are created automatically from their TCA definitions.

CREATE TABLE tx_provenance_document (
    document_uri varchar(255) DEFAULT '' NOT NULL,
    KEY document_uri (document_uri)
);

CREATE TABLE tx_provenance_source (
    document_uid int(11) unsigned DEFAULT '0' NOT NULL,
    KEY document (document_uid)
);

CREATE TABLE pages (
    provenance_document_uid int(11) unsigned DEFAULT '0' NOT NULL
);

CREATE TABLE tt_content (
    provenance_document_uid int(11) unsigned DEFAULT '0' NOT NULL
);
