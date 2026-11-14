<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Domain\Repository;

use CmsProvenance\Provenance\Domain\Model\MediaItem;
use CmsProvenance\Provenance\Media\MediaIdentity;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * The media files referenced by the visible content elements of a page, in
 * the order they appear: by column, by position in the column, by position
 * in the element. Each file is listed once. Three queries per page, however
 * many content elements it has.
 */
final readonly class PageMediaRepository
{
    // Provided by EXT:filemetadata; without it these stay unknown.
    private const OPTIONAL_METADATA = ['creator', 'copyright', 'source'];
    // Not translatable (l10n_mode exclude), always read from the default language.
    private const FILE_METADATA = ['tx_provenance_license', 'tx_provenance_digital_source_type'];

    public function __construct(
        private ConnectionPool $connectionPool,
        private MediaIdentity $mediaIdentity,
        private ResourceFactory $resourceFactory,
    ) {}

    /**
     * @return list<MediaItem>
     */
    public function findByPage(int $pageUid, int $languageId): array
    {
        $fileUids = $this->referencedFileUids($pageUid, $languageId);
        if ($fileUids === []) {
            return [];
        }
        $files = $this->files($fileUids);
        $metadata = $this->metadata($fileUids, $languageId);

        $items = [];
        foreach ($fileUids as $fileUid) {
            $file = $files[$fileUid] ?? null;
            if ($file === null || !$this->mediaIdentity->isMedia($file)) {
                continue;
            }
            $default = $metadata[$fileUid][0] ?? [];
            $translated = $metadata[$fileUid][$languageId] ?? $default;
            $items[] = new MediaItem(
                fileUid: $fileUid,
                metadataUid: (int)($translated['uid'] ?? 0),
                name: (string)$file['name'],
                mimeType: (string)$file['mime_type'],
                publicUrl: $this->publicUrl($file),
                mediaUri: $this->value($file['tx_provenance_media_uri']),
                sha256: $file['tx_provenance_sha256_of'] === $file['sha1'] ? $this->value($file['tx_provenance_sha256']) : null,
                creator: $this->value($translated['creator'] ?? null),
                copyright: $this->value($translated['copyright'] ?? null),
                source: $this->value($translated['source'] ?? null),
                license: $this->value($default['tx_provenance_license'] ?? null),
                digitalSourceType: $this->value($default['tx_provenance_digital_source_type'] ?? null),
            );
        }

        return $items;
    }

    /**
     * @return list<int> in page order, without duplicates
     */
    private function referencedFileUids(int $pageUid, int $languageId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->setRestrictions(new FrontendRestrictionContainer());
        $rows = $queryBuilder
            ->select('reference.uid_local')
            ->from('sys_file_reference', 'reference')
            ->join(
                'reference',
                'tt_content',
                'content',
                $queryBuilder->expr()->eq('content.uid', $queryBuilder->quoteIdentifier('reference.uid_foreign'))
            )
            ->where(
                $queryBuilder->expr()->eq('reference.tablenames', $queryBuilder->createNamedParameter('tt_content')),
                $queryBuilder->expr()->eq('content.pid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->in('content.sys_language_uid', $queryBuilder->createNamedParameter([$languageId, -1], Connection::PARAM_INT_ARRAY))
            )
            ->orderBy('content.colPos')
            ->addOrderBy('content.sorting')
            ->addOrderBy('reference.sorting_foreign')
            ->executeQuery()
            ->fetchFirstColumn();

        return array_values(array_unique(array_map(intval(...), $rows)));
    }

    /**
     * @param list<int> $fileUids
     * @return array<int, array<string, mixed>> by uid
     */
    private function files(array $fileUids): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder
            ->select('*')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($fileUids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('missing', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        return array_column($rows, null, 'uid');
    }

    /**
     * @param list<int> $fileUids
     * @return array<int, array<int, array<string, mixed>>> by file uid, then language
     */
    private function metadata(array $fileUids, int $languageId): array
    {
        $fields = ['uid', 'file', 'sys_language_uid', ...self::FILE_METADATA];
        foreach (self::OPTIONAL_METADATA as $field) {
            if (isset($GLOBALS['TCA']['sys_file_metadata']['columns'][$field])) {
                $fields[] = $field;
            }
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_metadata');
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder
            ->select(...$fields)
            ->from('sys_file_metadata')
            ->where(
                $queryBuilder->expr()->in('file', $queryBuilder->createNamedParameter($fileUids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->in('sys_language_uid', $queryBuilder->createNamedParameter(array_unique([0, $languageId]), Connection::PARAM_INT_ARRAY)),
                // Live rows only: a draft in a workspace must not reach the signed live record.
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $metadata = [];
        foreach ($rows as $row) {
            $metadata[(int)$row['file']][(int)$row['sys_language_uid']] = $row;
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $file
     */
    private function publicUrl(array $file): ?string
    {
        try {
            return $this->resourceFactory->getFileObject((int)$file['uid'], $file)->getPublicUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    private function value(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
