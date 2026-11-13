<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Command;

use CmsProvenance\Provenance\Service\DocumentIdentifierGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Creates default provenance records for existing pages, so a site reaches
 * conformance level L2 without editorial work. Only standard pages in the
 * default language and live workspace are covered; the page rows themselves
 * are not touched apart from the reference, so their timestamps stay as they are.
 */
#[AsCommand(
    name: 'provenance:backfill',
    description: 'Create provenance records for pages that do not have one yet.'
)]
final class BackfillCommand extends Command
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly DocumentIdentifierGenerator $identifierGenerator,
        private readonly Typo3Version $typo3Version,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report which pages would get a record.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');
        $pages = $this->findPagesWithoutRecord();

        if ($dryRun) {
            $io->table(['uid', 'title'], array_map(static fn(array $page): array => [$page['uid'], $page['title']], $pages));
            $io->note(sprintf('Dry run: %d pages would get a provenance record.', count($pages)));
            return Command::SUCCESS;
        }

        $connection = $this->connectionPool->getConnectionForTable('tx_provenance_document');
        foreach ($pages as $page) {
            $connection->transactional(fn() => $this->createRecord($page));
        }

        $io->success(sprintf('Created provenance records for %d pages.', count($pages)));
        return Command::SUCCESS;
    }

    /**
     * @return list<array{uid: int, title: string, crdate: int, SYS_LASTCHANGED: int}>
     */
    private function findPagesWithoutRecord(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
            ->select('uid', 'title', 'crdate', 'SYS_LASTCHANGED')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('provenance_document_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('doktype', $queryBuilder->createNamedParameter(PageRepository::DOKTYPE_DEFAULT, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function createRecord(array $page): void
    {
        $now = time();
        $created = (int)$page['crdate'];
        $documentConnection = $this->connectionPool->getConnectionForTable('tx_provenance_document');
        $documentConnection->insert('tx_provenance_document', [
            'pid' => (int)$page['uid'],
            'tstamp' => $now,
            'crdate' => $now,
            'document_uri' => $this->identifierGenerator->generate(),
            'first_published_at' => $created,
            // The last content change is the best available approximation;
            // editors refine it once they mark substantive revisions.
            'last_substantive_revision_at' => (int)$page['SYS_LASTCHANGED'] > 0 ? (int)$page['SYS_LASTCHANGED'] : $created,
            'authors' => (string)($this->findCreatingUser((int)$page['uid']) ?? ''),
            'conformance_level' => 2,
            'cms_version' => 'TYPO3 ' . $this->typo3Version->getVersion(),
        ]);
        $documentUid = (int)$documentConnection->lastInsertId();

        $this->connectionPool->getConnectionForTable('pages')->update(
            'pages',
            ['provenance_document_uid' => $documentUid],
            ['uid' => (int)$page['uid']]
        );
    }

    /**
     * TYPO3 no longer stores the creating user on the record (cruser_id was
     * removed in v12); the oldest history entry by an existing user is used instead.
     */
    private function findCreatingUser(int $pageUid): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_history');
        $queryBuilder->getRestrictions()->removeAll();
        $userUid = $queryBuilder
            ->select('h.userid')
            ->from('sys_history', 'h')
            ->join('h', 'be_users', 'u', (string)$queryBuilder->expr()->and(
                $queryBuilder->expr()->eq('u.uid', $queryBuilder->quoteIdentifier('h.userid')),
                $queryBuilder->expr()->eq('u.deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            ))
            ->where(
                $queryBuilder->expr()->eq('h.tablename', $queryBuilder->createNamedParameter('pages')),
                $queryBuilder->expr()->eq('h.recuid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->gt('h.userid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->orderBy('h.tstamp')
            ->addOrderBy('h.uid')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return $userUid !== false ? (int)$userUid : null;
    }
}
