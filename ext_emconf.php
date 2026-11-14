<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Content Provenance',
    'description' => 'Machine-readable provenance records for pages and content elements, emitted as JSON-LD.',
    'category' => 'fe',
    'state' => 'experimental',
    'version' => '0.0.0',
    'constraints' => [
        'depends' => [
        'typo3' => '13.4.0-14.99.99',
        'php' => '8.2.0-8.4.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'filemetadata' => '13.4.0-14.99.99',
        ],
    ],
];
