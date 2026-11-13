<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Domain\Repository;

use CmsProvenance\Provenance\Domain\Model\Author;
use CmsProvenance\Provenance\Domain\Model\ProvenanceRecord;
use CmsProvenance\Provenance\Domain\Model\Source;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Reads published provenance records: live workspace only, hidden and deleted
 * documents are skipped.
 */
final readonly class ProvenanceRecordRepository
{
    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function findByPage(int $pageUid): ?ProvenanceRecord
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $documentUid = (int)$queryBuilder
            ->select('provenance_document_uid')
            ->from('pages')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();

        return $documentUid > 0 ? $this->findByUid($documentUid) : null;
    }

    public function findByUid(int $documentUid): ?ProvenanceRecord
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_provenance_document');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, 0));
        $row = $queryBuilder
            ->select('*')
            ->from('tx_provenance_document')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($documentUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false || trim((string)$row['document_uri']) === '') {
            return null;
        }

        $reviewers = $this->findAuthors((string)$row['reviewer']);

        return new ProvenanceRecord(
            documentUri: (string)$row['document_uri'],
            firstPublishedAt: $this->timestampOrNull($row['first_published_at']),
            lastSubstantiveRevisionAt: $this->timestampOrNull($row['last_substantive_revision_at']),
            lastMaintainedAt: $this->timestampOrNull($row['last_maintained_at']),
            authors: $this->findAuthors((string)$row['authors']),
            reviewer: $reviewers[0] ?? null,
            sources: $this->findSources($documentUid),
            conformanceLevel: (int)$row['conformance_level'],
        );
    }

    /**
     * @return list<Author> in the order given by the comma-separated uid list
     */
    private function findAuthors(string $userUidList): array
    {
        $userUids = GeneralUtility::intExplode(',', $userUidList, true);
        if ($userUids === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');
        // Authorship stays valid when an account is later disabled.
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $rows = $queryBuilder
            ->select('uid', 'realName', 'external_identity_uri', 'external_identity_verified')
            ->from('be_users')
            ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($userUids, Connection::PARAM_INT_ARRAY)))
            ->executeQuery()
            ->fetchAllAssociative();

        $rowsByUid = array_column($rows, null, 'uid');
        $authors = [];
        foreach ($userUids as $userUid) {
            if (!isset($rowsByUid[$userUid])) {
                continue;
            }
            $row = $rowsByUid[$userUid];
            $authors[] = new Author(
                name: $this->stringOrNull($row['realName']),
                identityUri: $this->stringOrNull($row['external_identity_uri']),
                identityVerified: (bool)$row['external_identity_verified'],
            );
        }

        return $authors;
    }

    /**
     * @return list<Source>
     */
    private function findSources(int $documentUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_provenance_source');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, 0));
        $rows = $queryBuilder
            ->select('url', 'description', 'accessed_at', 'archive_url')
            ->from('tx_provenance_source')
            ->where($queryBuilder->expr()->eq('document_uid', $queryBuilder->createNamedParameter($documentUid, Connection::PARAM_INT)))
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(
            fn(array $row): Source => new Source(
                url: (string)$row['url'],
                description: (string)$row['description'],
                accessedAt: $this->timestampOrNull($row['accessed_at']),
                archiveUrl: $this->stringOrNull($row['archive_url']),
            ),
            $rows
        );
    }

    private function timestampOrNull(mixed $value): ?int
    {
        return (int)$value > 0 ? (int)$value : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}
