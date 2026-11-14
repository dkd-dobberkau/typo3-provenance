<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Service;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Package\PackageManager;

/**
 * The record's generator: the CMS and the version of this extension.
 */
final readonly class GeneratorName
{
    public function __construct(
        private PackageManager $packageManager,
        private Typo3Version $typo3Version,
    ) {}

    public function value(): string
    {
        $extensionVersion = $this->packageManager->getPackage('provenance')->getPackageMetaData()->getVersion();

        return sprintf('TYPO3 %s + provenance %s', $this->typo3Version->getVersion(), $extensionVersion);
    }
}
