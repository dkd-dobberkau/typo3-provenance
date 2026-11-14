<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Domain\Repository;

use CmsProvenance\Provenance\Domain\Model\MediaItem;
use CmsProvenance\Provenance\Media\MediaIdentity;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * The media files referenced by the visible content elements of a page, in
 * the order they appear: by column, by position in the column, by position
 * in the element. Each file is listed once, and only from fields the
 * element's content type shows. Four queries per page, however
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
     * @param int $languageId the language content is taken from (LanguageAspect::getContentId())
     * @param string $overlayType one of the LanguageAspect::OVERLAYS_* constants
     * @return list<MediaItem>
     */
    public function findByPage(int $pageUid, int $languageId, string $overlayType = LanguageAspect::OVERLAYS_OFF): array
    {
        $fileUids = $this->referencedFileUids($pageUid, $languageId, $overlayType);
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
    private function referencedFileUids(int $pageUid, int $languageId, string $overlayType): array
    {
        $elements = $this->shownElements($pageUid, $languageId, $overlayType);
        if ($elements === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->setRestrictions(new FrontendRestrictionContainer());
        $references = $queryBuilder
            ->select('uid_local', 'uid_foreign', 'fieldname')
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter('tt_content')),
                $queryBuilder->expr()->in('uid_foreign', $queryBuilder->createNamedParameter(array_keys($elements), Connection::PARAM_INT_ARRAY))
            )
            ->orderBy('sorting_foreign')
            ->executeQuery()
            ->fetchAllAssociative();
        $byElement = [];
        foreach ($references as $reference) {
            $byElement[(int)$reference['uid_foreign']][] = $reference;
        }

        $fileUids = [];
        foreach ($elements as $elementUid => $contentType) {
            foreach ($byElement[$elementUid] ?? [] as $reference) {
                if ($this->isShown($contentType, (string)$reference['fieldname'])) {
                    $fileUids[] = (int)$reference['uid_local'];
                }
            }
        }

        return array_values(array_unique($fileUids));
    }

    /**
     * The content elements the frontend renders for the language, as
     * uid => CType in page order. Without overlays (free mode) these are the
     * records of the language; with overlays, default-language elements are
     * replaced by their translation, kept untranslated in mixed mode and left
     * out otherwise, and translations without a parent count only with
     * floating records.
     *
     * @return array<int, string>
     */
    private function shownElements(int $pageUid, int $languageId, string $overlayType): array
    {
        $overlays = $languageId > 0 && $overlayType !== LanguageAspect::OVERLAYS_OFF;
        $languages = $overlays ? [0, -1, $languageId] : [$languageId, -1];
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->setRestrictions(new FrontendRestrictionContainer());
        $rows = $queryBuilder
            ->select('uid', 'sys_language_uid', 'l18n_parent', 'CType')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->in('sys_language_uid', $queryBuilder->createNamedParameter(array_unique($languages), Connection::PARAM_INT_ARRAY))
            )
            ->orderBy('colPos')
            ->addOrderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();

        if (!$overlays) {
            return array_column($rows, 'CType', 'uid');
        }

        $translations = [];
        foreach ($rows as $row) {
            if ((int)$row['sys_language_uid'] === $languageId && (int)$row['l18n_parent'] > 0) {
                $translations[(int)$row['l18n_parent']] = $row;
            }
        }
        $elements = [];
        foreach ($rows as $row) {
            $isTranslation = (int)$row['sys_language_uid'] === $languageId;
            if (!$isTranslation) {
                $shown = $translations[(int)$row['uid']] ?? ($overlayType === LanguageAspect::OVERLAYS_MIXED ? $row : null);
            } else {
                $isFloating = (int)$row['l18n_parent'] === 0;
                $shown = $isFloating && $overlayType === LanguageAspect::OVERLAYS_ON_WITH_FLOATING ? $row : null;
            }
            if ($shown !== null) {
                $elements[(int)$shown['uid']] = (string)$shown['CType'];
            }
        }

        return $elements;
    }

    /**
     * DataHandler keeps file references when an editor switches the content
     * type; only fields the type shows are on the page. Unknown types are
     * kept, as nothing can be said about them.
     */
    private function isShown(string $contentType, string $field): bool
    {
        $types = $GLOBALS['TCA']['tt_content']['types'] ?? [];
        if (!isset($types[$contentType]['showitem'])) {
            return true;
        }

        return in_array($field, $this->fieldsOf((string)$types[$contentType]['showitem']), true);
    }

    /**
     * @return list<string> field names of a showitem string, palettes expanded
     */
    private function fieldsOf(string $showitem): array
    {
        $fields = [];
        foreach (explode(',', $showitem) as $item) {
            $parts = array_map(trim(...), explode(';', $item));
            if ($parts[0] === '--palette--') {
                $palette = $GLOBALS['TCA']['tt_content']['palettes'][$parts[2] ?? '']['showitem'] ?? '';
                $fields = [...$fields, ...$this->fieldsOf((string)$palette)];
            } elseif ($parts[0] !== '' && !str_starts_with($parts[0], '--')) {
                $fields[] = $parts[0];
            }
        }

        return $fields;
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
