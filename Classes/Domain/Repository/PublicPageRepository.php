<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Decides which pages the public well-known endpoints may reveal.
 *
 * A page counts as public only if it and every page above it are visible to
 * anonymous visitors: not hidden, not restricted to frontend groups, within
 * start and end time. This is stricter than TYPO3, which passes hiding and
 * access restrictions to subpages only with "extendToSubpages", but it never
 * reveals a page that TYPO3 protects.
 */
final readonly class PublicPageRepository
{
    private const MAX_DEPTH = 100;

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * Standard pages in the default language of the public tree below a site root.
     *
     * @return list<int> sorted page uids
     */
    public function publicPageUids(int $rootPageUid, int $now): array
    {
        $queryBuilder = $this->queryBuilder();
        $rows = $queryBuilder
            ->select('uid', 'pid', 'doktype', 'hidden', 'fe_group', 'starttime', 'endtime')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $rowsByUid = array_column($rows, null, 'uid');
        $children = [];
        foreach ($rows as $row) {
            $children[(int)$row['pid']][] = (int)$row['uid'];
        }

        $visible = [];
        $queue = isset($rowsByUid[$rootPageUid]) ? [$rootPageUid] : [];
        while ($queue !== []) {
            $uid = array_shift($queue);
            $row = $rowsByUid[$uid];
            if (!$this->isVisible($row, $now)) {
                continue;
            }
            if ((int)$row['doktype'] === PageRepository::DOKTYPE_DEFAULT) {
                $visible[] = $uid;
            }
            array_push($queue, ...($children[$uid] ?? []));
        }

        sort($visible);
        return $visible;
    }

    /**
     * Whether the page and all pages above it are visible to anonymous visitors.
     */
    public function isPublic(int $pageUid, int $now): bool
    {
        for ($depth = 0; $pageUid > 0 && $depth < self::MAX_DEPTH; $depth++) {
            $queryBuilder = $this->queryBuilder();
            $row = $queryBuilder
                ->select('pid', 'hidden', 'fe_group', 'starttime', 'endtime', 't3ver_wsid')
                ->from('pages')
                ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)))
                ->executeQuery()
                ->fetchAssociative();
            if ($row === false || (int)$row['t3ver_wsid'] !== 0 || !$this->isVisible($row, $now)) {
                return false;
            }
            $pageUid = (int)$row['pid'];
        }

        return $pageUid === 0;
    }

    private function isVisible(array $page, int $now): bool
    {
        return (int)$page['hidden'] === 0
            && in_array((string)$page['fe_group'], ['', '0'], true)
            && (int)$page['starttime'] <= $now
            && ((int)$page['endtime'] === 0 || (int)$page['endtime'] > $now);
    }

    private function queryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder;
    }
}
