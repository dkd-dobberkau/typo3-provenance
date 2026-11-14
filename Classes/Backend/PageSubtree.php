<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Backend;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

/**
 * The pages of a subtree that a backend user may see, for the overviews of
 * the Provenance module. Live pages in the default language only; hidden
 * pages are included, because editors need to find them. A page the user
 * may not read hides its subpages as well, as in the page tree.
 */
final readonly class PageSubtree
{
    public const ALL = 999;

    public function __construct(private ConnectionPool $connectionPool) {}

    /**
     * @return list<int> in page tree order: each page followed by its subpages
     */
    public function pageIds(int $rootUid, int $depth, BackendUserAuthentication $user): array
    {
        if ($rootUid <= 0 || $user->isInWebMount($rootUid) === null || $this->readable(['uid' => [$rootUid]], $user) === []) {
            return [];
        }
        // One query per level; children are collected per parent, in sorting order.
        $children = [];
        $parents = [$rootUid];
        for ($level = 0; $level < $depth && $parents !== []; $level++) {
            $rows = $this->readable(['pid' => $parents], $user);
            $parents = [];
            foreach ($rows as [$uid, $pid]) {
                $children[$pid][] = $uid;
                $parents[] = $uid;
            }
        }

        return $this->inTreeOrder($rootUid, $children);
    }

    /**
     * @param array<int, list<int>> $children
     * @return list<int>
     */
    private function inTreeOrder(int $uid, array $children): array
    {
        $pageIds = [$uid];
        foreach ($children[$uid] ?? [] as $child) {
            $pageIds = [...$pageIds, ...$this->inTreeOrder($child, $children)];
        }

        return $pageIds;
    }

    /**
     * @param array{uid?: list<int>, pid?: list<int>} $condition
     * @return list<array{int, int}> uid and pid
     */
    private function readable(array $condition, BackendUserAuthentication $user): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $field = array_key_first($condition);
        $queryBuilder
            ->select('uid', 'pid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->in($field, $queryBuilder->createNamedParameter($condition[$field], Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                QueryHelper::stripLogicalOperatorPrefix($user->getPagePermsClause(Permission::PAGE_SHOW))
            )
            ->orderBy('pid')
            ->addOrderBy('sorting');

        return array_map(
            static fn(array $row): array => [(int)$row['uid'], (int)$row['pid']],
            $queryBuilder->executeQuery()->fetchAllAssociative()
        );
    }
}
