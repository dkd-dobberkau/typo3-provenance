<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'provenance-document' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:provenance/Resources/Public/Icons/document.svg',
    ],
    'provenance-source' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:provenance/Resources/Public/Icons/source.svg',
    ],
];
