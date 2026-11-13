<?php

declare(strict_types=1);

$ll = 'LLL:EXT:provenance/Resources/Private/Language/locallang_db.xlf:';

return [
    'ctrl' => [
        'title' => $ll . 'tx_provenance_document',
        'label' => 'document_uri',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        // Inline child of workspace-aware records (pages, tt_content, documents)
        'versioningWS' => true,
        // Stored next to the page or content element they describe
        'security' => ['ignorePageTypeRestriction' => true],
        'enablecolumns' => ['disabled' => 'hidden'],
        'typeicon_classes' => ['default' => 'provenance-document'],
    ],
    'columns' => [
        'document_uri' => [
            'label' => $ll . 'tx_provenance_document.document_uri',
            'description' => $ll . 'tx_provenance_document.document_uri.description',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'eval' => 'trim,unique',
                'readOnly' => true,
            ],
        ],
        'first_published_at' => [
            'label' => $ll . 'tx_provenance_document.first_published_at',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'readOnly' => true,
            ],
        ],
        'last_substantive_revision_at' => [
            'label' => $ll . 'tx_provenance_document.last_substantive_revision_at',
            'description' => $ll . 'tx_provenance_document.last_substantive_revision_at.description',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
            ],
        ],
        'last_maintained_at' => [
            'label' => $ll . 'tx_provenance_document.last_maintained_at',
            'description' => $ll . 'tx_provenance_document.last_maintained_at.description',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
            ],
        ],
        'authors' => [
            'label' => $ll . 'tx_provenance_document.authors',
            'config' => [
                'type' => 'group',
                'allowed' => 'be_users',
                'size' => 5,
            ],
        ],
        'reviewer' => [
            'label' => $ll . 'tx_provenance_document.reviewer',
            'config' => [
                'type' => 'group',
                'allowed' => 'be_users',
                'size' => 1,
                'maxitems' => 1,
            ],
        ],
        'sources' => [
            'label' => $ll . 'tx_provenance_document.sources',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_provenance_source',
                'foreign_field' => 'document_uid',
                'foreign_sortby' => 'sorting',
                'appearance' => [
                    'collapseAll' => false,
                    'levelLinksPosition' => 'bottom',
                    'useSortable' => true,
                ],
            ],
        ],
        'content_hash' => [
            'label' => $ll . 'tx_provenance_document.content_hash',
            'config' => [
                'type' => 'input',
                'size' => 64,
                'readOnly' => true,
            ],
        ],
        'signature' => [
            'label' => $ll . 'tx_provenance_document.signature',
            'config' => [
                'type' => 'text',
                'rows' => 3,
                'readOnly' => true,
            ],
        ],
        'signature_key_id' => [
            'label' => $ll . 'tx_provenance_document.signature_key_id',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'readOnly' => true,
            ],
        ],
        'cms_version' => [
            'label' => $ll . 'tx_provenance_document.cms_version',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'readOnly' => true,
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'document_uri, first_published_at, last_substantive_revision_at, last_maintained_at,
                --div--;' . $ll . 'tab.authors, authors, reviewer,
                --div--;' . $ll . 'tab.sources, sources,
                --div--;' . $ll . 'tab.signature, content_hash, signature, signature_key_id,
                --div--;' . $ll . 'tab.conformance, cms_version,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access, hidden',
        ],
    ],
];
