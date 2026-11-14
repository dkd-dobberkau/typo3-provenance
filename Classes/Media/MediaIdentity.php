<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Media;

use CmsProvenance\Provenance\Service\DocumentIdentifierGenerator;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\OnlineMediaHelperRegistry;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Stable identifier and SHA-256 of a media file's bytes, plus the history of
 * every hash the file has had. The identifier is kept when the file is
 * replaced, as file versions are on Wikimedia Commons; the history lets a
 * later verifier check copies of earlier versions.
 *
 * Only called from FAL events, DataHandler hooks and the backfill command,
 * never while a page is rendered.
 */
final readonly class MediaIdentity
{
    private const FILE_TABLE = 'sys_file';
    private const HISTORY_TABLE = 'tx_provenance_media_hash';
    private const MEDIA_TYPES = ['image/', 'video/', 'audio/'];

    public function __construct(
        private ConnectionPool $connectionPool,
        private DocumentIdentifierGenerator $identifierGenerator,
        private ResourceFactory $resourceFactory,
        private OnlineMediaHelperRegistry $onlineMediaHelperRegistry,
        private Context $context,
        private LoggerInterface $logger,
    ) {}

    /**
     * Images, videos and audio files with bytes of their own; online media
     * helpers (YouTube, Vimeo) only point elsewhere.
     *
     * @param array<string, mixed> $fileRow a sys_file row with mime_type and extension
     */
    public function isMedia(array $fileRow): bool
    {
        $mimeType = (string)($fileRow['mime_type'] ?? '');
        if (in_array(strtolower((string)($fileRow['extension'] ?? '')), $this->onlineMediaHelperRegistry->getSupportedFileExtensions(), true)) {
            return false;
        }
        foreach (self::MEDIA_TYPES as $prefix) {
            if (str_starts_with($mimeType, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Assigns the identifier if missing and rehashes the file when its bytes
     * changed (sys_file.sha1 differs from the one the hash was computed for).
     *
     * @return bool whether the identifier or the hash changed
     */
    public function update(int $fileUid): bool
    {
        $file = $this->fileRow($fileUid);
        if ($file === null || !$this->isMedia($file)) {
            return false;
        }

        $mediaUri = (string)$file['tx_provenance_media_uri'];
        $fields = [];
        if ($mediaUri === '') {
            $mediaUri = $this->identifierGenerator->generate();
            $fields['tx_provenance_media_uri'] = $mediaUri;
        }

        $newHash = null;
        if ((string)$file['sha1'] !== (string)$file['tx_provenance_sha256_of']) {
            $newHash = $this->hash($fileUid);
            if ($newHash !== null) {
                $fields['tx_provenance_sha256'] = $newHash;
                $fields['tx_provenance_sha256_of'] = (string)$file['sha1'];
            }
        }
        if ($fields === []) {
            return false;
        }

        $hashChanged = $newHash !== null && $newHash !== (string)$file['tx_provenance_sha256'];
        $connection = $this->connectionPool->getConnectionForTable(self::FILE_TABLE);
        $connection->transactional(function () use ($connection, $fileUid, $fields, $mediaUri, $newHash, $hashChanged): void {
            $connection->update(self::FILE_TABLE, $fields, ['uid' => $fileUid]);
            if ($hashChanged) {
                $this->closeHistory($mediaUri);
                $this->connectionPool->getConnectionForTable(self::HISTORY_TABLE)->insert(self::HISTORY_TABLE, [
                    'media_uri' => $mediaUri,
                    'sha256' => $newHash,
                    'valid_from' => $this->now(),
                ]);
            }
        });

        return isset($fields['tx_provenance_media_uri']) || $hashChanged;
    }

    /**
     * Closes the current history entry of a file that leaves the index.
     */
    public function retire(int $fileUid): void
    {
        $mediaUri = (string)($this->fileRow($fileUid)['tx_provenance_media_uri'] ?? '');
        if ($mediaUri !== '') {
            $this->closeHistory($mediaUri);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fileRow(int $fileUid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::FILE_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select('uid', 'mime_type', 'extension', 'sha1', 'tx_provenance_media_uri', 'tx_provenance_sha256', 'tx_provenance_sha256_of')
            ->from(self::FILE_TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($fileUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();

        return $row === false ? null : $row;
    }

    private function hash(int $fileUid): ?string
    {
        try {
            $path = $this->resourceFactory->getFileObject($fileUid)->getForLocalProcessing(false);
            $hash = is_file($path) ? hash_file('sha256', $path) : false;
        } catch (\Throwable $exception) {
            $hash = false;
            $this->logger->warning('Provenance: cannot read file ' . $fileUid . ': ' . $exception->getMessage());
        }
        if ($hash === false) {
            $this->logger->warning('Provenance: no SHA-256 for file ' . $fileUid . ', its bytes cannot be read.');
            return null;
        }

        return $hash;
    }

    private function closeHistory(string $mediaUri): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::HISTORY_TABLE);
        $queryBuilder
            ->update(self::HISTORY_TABLE)
            ->set('valid_until', $this->now())
            ->where(
                $queryBuilder->expr()->eq('media_uri', $queryBuilder->createNamedParameter($mediaUri)),
                $queryBuilder->expr()->isNull('valid_until')
            )
            ->executeStatement();
    }

    private function now(): int
    {
        return (int)$this->context->getPropertyFromAspect('date', 'timestamp');
    }
}
