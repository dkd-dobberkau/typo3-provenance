<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Backend;

use CmsProvenance\Provenance\Domain\Repository\PageMediaRepository;
use CmsProvenance\Provenance\Domain\Repository\ProvenanceRecordRepository;
use CmsProvenance\Provenance\Event\ModifyPageOverviewRowEvent;
use CmsProvenance\Provenance\Frontend\JsonLdBuilder;
use CmsProvenance\Provenance\Service\ConformanceLevel;
use CmsProvenance\Provenance\Service\GeneratorName;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

/**
 * Rows of the "Pages" overview: per standard page of a subtree, whether it
 * has a provenance record and the level that record reaches without a
 * signature. The record is the stored one; listeners to the frontend event
 * may still change what a page publishes.
 */
final readonly class PageOverview
{
    private const STANDARD_PAGE = 1;

    public function __construct(
        private PageSubtree $pageSubtree,
        private ConnectionPool $connectionPool,
        private ProvenanceRecordRepository $recordRepository,
        private JsonLdBuilder $jsonLdBuilder,
        private ConformanceLevel $conformanceLevel,
        private GeneratorName $generatorName,
        private EventDispatcherInterface $eventDispatcher,
        private PageMediaRepository $pageMediaRepository,
    ) {}

    /**
     * @return list<array{uid: int, title: string, pageHidden: bool, hasRecord: bool, recordHidden: bool, level: ?int, missing: list<string>, datePublished: ?string, lastSubstantiveRevision: ?string, maintainedDate: ?string, mayEdit: bool, media: array{total: int, incomplete: list<array{fileUid: int, metadataUid: int, name: string, lacks: list<string>}>}, extra: array<string, mixed>}>
     */
    public function rows(int $rootUid, int $depth, BackendUserAuthentication $user): array
    {
        $pageIds = $this->pageSubtree->pageIds($rootUid, $depth, $user);
        $pages = $this->pages($pageIds);
        $hiddenDocuments = $this->hiddenDocuments(array_filter(array_column($pages, 'provenance_document_uid')));
        $generator = $this->generatorName->value();
        $mayModifyPages = $user->check('tables_modify', 'pages');

        $rows = [];
        foreach ($pageIds as $pageId) {
            $page = $pages[$pageId] ?? null;
            if ($page === null || (int)$page['doktype'] !== self::STANDARD_PAGE) {
                continue;
            }
            $documentUid = (int)$page['provenance_document_uid'];
            $record = $documentUid > 0 ? $this->recordRepository->findByUid($documentUid, true) : null;
            $jsonLd = $record !== null
                ? $this->jsonLdBuilder->build($record, (string)$page['title'], (int)$page['SYS_LASTCHANGED'], $generator)
                : null;

            $row = [
                'uid' => $pageId,
                'title' => (string)$page['title'],
                'pageHidden' => (bool)$page['hidden'],
                'hasRecord' => $jsonLd !== null,
                'recordHidden' => $jsonLd !== null && isset($hiddenDocuments[$documentUid]),
                'level' => $jsonLd !== null ? $this->conformanceLevel->of($jsonLd, false, false) : null,
                'missing' => $jsonLd !== null ? $this->conformanceLevel->missing($jsonLd) : [],
                'datePublished' => $jsonLd['datePublished'] ?? null,
                'lastSubstantiveRevision' => $jsonLd['lastSubstantiveRevision'] ?? null,
                'maintainedDate' => $jsonLd['maintainedDate'] ?? null,
                'mayEdit' => $mayModifyPages && $user->doesUserHaveAccess($page, Permission::PAGE_EDIT),
                'media' => $this->media($pageId),
                'extra' => [],
            ];
            $rows[] = $this->eventDispatcher->dispatch(new ModifyPageOverviewRowEvent($row, $pageId))->getRow();
        }

        return $rows;
    }

    /**
     * Media data never affects the level; the summary only tells editors
     * which files to complete. Live data and default language, like the rows.
     *
     * @return array{total: int, incomplete: list<array{fileUid: int, metadataUid: int, name: string, lacks: list<string>}>}
     */
    private function media(int $pageId): array
    {
        $items = $this->pageMediaRepository->findByPage($pageId, 0);
        // Without EXT:filemetadata there is no creator field to complete.
        $hasCreatorField = isset($GLOBALS['TCA']['sys_file_metadata']['columns']['creator']);
        $incomplete = [];
        foreach ($items as $item) {
            $lacks = array_keys(array_filter([
                'creator' => $hasCreatorField && $item->creator === null,
                'license' => $item->license === null,
                'identifier' => $item->mediaUri === null,
                'hash' => $item->sha256 === null,
            ]));
            if ($lacks !== []) {
                $incomplete[] = ['fileUid' => $item->fileUid, 'metadataUid' => $item->metadataUid, 'name' => $item->name, 'lacks' => $lacks];
            }
        }

        return ['total' => count($items), 'incomplete' => $incomplete];
    }

    /**
     * @param list<int> $pageIds
     * @return array<int, array<string, mixed>> by uid
     */
    private function pages(array $pageIds): array
    {
        if ($pageIds === []) {
            return [];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $rows = $queryBuilder
            // perms_* for the edit permission, so it needs no second query per page.
            ->select('uid', 'pid', 'title', 'hidden', 'doktype', 'provenance_document_uid', 'SYS_LASTCHANGED', 'perms_userid', 'perms_groupid', 'perms_user', 'perms_group', 'perms_everybody')
            ->from('pages')
            ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($pageIds, Connection::PARAM_INT_ARRAY)))
            ->executeQuery()
            ->fetchAllAssociative();

        return array_column($rows, null, 'uid');
    }

    /**
     * @param array<int|string, mixed> $documentUids
     * @return array<int, true>
     */
    private function hiddenDocuments(array $documentUids): array
    {
        if ($documentUids === []) {
            return [];
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_provenance_document');
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $uids = $queryBuilder
            ->select('uid')
            ->from('tx_provenance_document')
            ->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter(array_map(intval(...), array_values($documentUids)), Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchFirstColumn();

        return array_fill_keys(array_map(intval(...), $uids), true);
    }
}
