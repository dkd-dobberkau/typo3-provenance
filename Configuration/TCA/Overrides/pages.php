<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

ExtensionManagementUtility::addTCAcolumns('pages', [
    'provenance_document_uid' => [
        'label' => 'LLL:EXT:provenance/Resources/Private/Language/locallang_db.xlf:pages.provenance_document_uid',
        'config' => [
            'type' => 'inline',
            'foreign_table' => 'tx_provenance_document',
            'maxitems' => 1,
            'appearance' => [
                'collapseAll' => false,
                'expandSingle' => true,
            ],
        ],
    ],
]);

ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    '--div--;LLL:EXT:provenance/Resources/Private/Language/locallang_db.xlf:tab.provenance, provenance_document_uid'
);
