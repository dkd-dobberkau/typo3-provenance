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

# Identity of a media file's bytes; written by FAL events and the media backfill.
CREATE TABLE sys_file (
    tx_provenance_media_uri varchar(255) DEFAULT '' NOT NULL,
    tx_provenance_sha256 char(64) DEFAULT '' NOT NULL,
    tx_provenance_sha256_of char(40) DEFAULT '' NOT NULL
);

# Every SHA-256 a media file has had; valid_until is NULL for the current one.
CREATE TABLE tx_provenance_media_hash (
    uid int(11) unsigned NOT NULL auto_increment,
    media_uri varchar(255) DEFAULT '' NOT NULL,
    sha256 char(64) DEFAULT '' NOT NULL,
    valid_from int(11) unsigned DEFAULT '0' NOT NULL,
    valid_until int(11) unsigned DEFAULT NULL,
    PRIMARY KEY (uid),
    KEY media_uri (media_uri),
    KEY sha256 (sha256)
);
