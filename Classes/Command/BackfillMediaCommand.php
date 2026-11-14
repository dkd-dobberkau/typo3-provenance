<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Command;

use CmsProvenance\Provenance\Media\MediaIdentity;
use CmsProvenance\Provenance\Media\MediaRefresh;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Assigns identifiers and hashes to media files indexed before the extension
 * was installed, and retries files whose hash could not be computed. Bytes
 * changed outside TYPO3 are detected by the FAL indexer (sys_file.sha1), not
 * by this command.
 */
#[AsCommand(
    name: 'provenance:backfill-media',
    description: 'Assign provenance identifiers and SHA-256 hashes to media files that lack a current one.'
)]
final class BackfillMediaCommand extends Command
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly MediaIdentity $mediaIdentity,
        private readonly MediaRefresh $mediaRefresh,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report how many media files would be updated.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $files = array_values(array_filter($this->findOutdatedFiles(), $this->mediaIdentity->isMedia(...)));

        if ((bool)$input->getOption('dry-run')) {
            $io->note(sprintf('Dry run: %d media files would be updated.', count($files)));
            return Command::SUCCESS;
        }

        $updated = 0;
        foreach ($files as $file) {
            $updated += $this->mediaRefresh->refresh((int)$file['uid']) ? 1 : 0;
        }
        $io->success(sprintf('Updated %d media files.', $updated));
        if ($updated < count($files)) {
            $io->warning(sprintf('%d media files could not be read; see the log.', count($files) - $updated));
        }
        return Command::SUCCESS;
    }

    /**
     * @return list<array{uid: int, mime_type: string, extension: string}>
     */
    private function findOutdatedFiles(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select('uid', 'mime_type', 'extension')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('missing', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('tx_provenance_media_uri', $queryBuilder->createNamedParameter('')),
                    $queryBuilder->expr()->neq('tx_provenance_sha256_of', $queryBuilder->quoteIdentifier('sha1'))
                )
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
