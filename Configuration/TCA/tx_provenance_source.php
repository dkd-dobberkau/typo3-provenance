<?php

declare(strict_types=1);

$ll = 'LLL:EXT:provenance/Resources/Private/Language/locallang_db.xlf:';

return [
    'ctrl' => [
        'title' => $ll . 'tx_provenance_source',
        'label' => 'url',
        'label_alt' => 'description',
        'sortby' => 'sorting',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        // Inline child of workspace-aware records (pages, tt_content, documents)
        'versioningWS' => true,
        // Stored next to the page or content element they describe
        'security' => ['ignorePageTypeRestriction' => true],
        'hideTable' => true,
        'typeicon_classes' => ['default' => 'provenance-source'],
    ],
    'columns' => [
        'document_uid' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'url' => [
            'label' => $ll . 'tx_provenance_source.url',
            'config' => [
                'type' => 'link',
                'allowedTypes' => ['url'],
                'required' => true,
            ],
        ],
        'description' => [
            'label' => $ll . 'tx_provenance_source.description',
            'config' => [
                'type' => 'text',
                'rows' => 3,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'accessed_at' => [
            'label' => $ll . 'tx_provenance_source.accessed_at',
            'config' => [
                'type' => 'datetime',
                'format' => 'date',
                'required' => true,
            ],
        ],
        'archive_url' => [
            'label' => $ll . 'tx_provenance_source.archive_url',
            'config' => [
                'type' => 'link',
                'allowedTypes' => ['url'],
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'url, description, accessed_at, archive_url',
        ],
    ],
];
