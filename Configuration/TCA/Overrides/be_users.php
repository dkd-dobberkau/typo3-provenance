<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Both fields are exclude fields: non-admin users need explicit access
// through their backend group. The verified flag in particular should only be
// granted to the people who confirm external identities.
ExtensionManagementUtility::addTCAcolumns('be_users', [
    'external_identity_uri' => [
        'exclude' => true,
        'label' => 'LLL:EXT:provenance/Resources/Private/Language/locallang_db.xlf:be_users.external_identity_uri',
        'description' => 'LLL:EXT:provenance/Resources/Private/Language/locallang_db.xlf:be_users.external_identity_uri.description',
        'config' => [
            'type' => 'link',
            'allowedTypes' => ['url'],
        ],
    ],
    'external_identity_verified' => [
        'exclude' => true,
        'label' => 'LLL:EXT:provenance/Resources/Private/Language/locallang_db.xlf:be_users.external_identity_verified',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
        ],
    ],
]);

ExtensionManagementUtility::addToAllTCAtypes(
    'be_users',
    '--div--;locallang_db.xlfab.provenance, external_identity_uri, external_identity_verified'
);
