<?php

declare(strict_types=1);

use CmsProvenance\Provenance\Backend\Controller\PagesModuleController;

/**
 * The "Provenance" module in Web (TYPO3 13) / Content (TYPO3 14, which keeps
 * "web" as alias). Sub-extensions add submodules with parent "provenance".
 */
return [
    'provenance' => [
        'parent' => 'web',
        'position' => ['after' => 'web_info'],
        'access' => 'user',
        'path' => '/module/web/provenance',
        'iconIdentifier' => 'module-provenance',
        'labels' => 'LLL:EXT:provenance/Resources/Private/Language/locallang_mod.xlf',
        'navigationComponent' => '@typo3/backend/tree/page-tree-element',
        'appearance' => [
            'dependsOnSubmodules' => true,
        ],
        'showSubmoduleOverview' => true,
    ],
    'provenance_pages' => [
        'parent' => 'provenance',
        'access' => 'user',
        'path' => '/module/web/provenance/pages',
        'iconIdentifier' => 'module-provenance',
        'labels' => 'LLL:EXT:provenance/Resources/Private/Language/locallang_mod_pages.xlf',
        'navigationComponent' => '@typo3/backend/tree/page-tree-element',
        'routes' => [
            '_default' => [
                'target' => PagesModuleController::class . '::handleRequest',
            ],
        ],
        'moduleData' => [
            'depth' => 1,
        ],
    ],
];
