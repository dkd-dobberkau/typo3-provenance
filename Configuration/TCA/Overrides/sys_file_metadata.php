<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

$ll = 'LLL:EXT:provenance/Resources/Private/Language/locallang_db.xlf:';

// Both fields describe the file, not a translation of its description, so
// every language shows the value of the default language.
ExtensionManagementUtility::addTCAcolumns('sys_file_metadata', [
    'tx_provenance_license' => [
        'label' => $ll . 'sys_file_metadata.tx_provenance_license',
        'description' => $ll . 'sys_file_metadata.tx_provenance_license.description',
        'l10n_mode' => 'exclude',
        'config' => [
            'type' => 'link',
            'allowedTypes' => ['url'],
        ],
    ],
    'tx_provenance_digital_source_type' => [
        'label' => $ll . 'sys_file_metadata.tx_provenance_digital_source_type',
        'l10n_mode' => 'exclude',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            // Terms of https://cv.iptc.org/newscodes/digitalsourcetype/ without the retired ones.
            'items' => [
                ['label' => $ll . 'sys_file_metadata.tx_provenance_digital_source_type.none', 'value' => ''],
                ['label' => $ll . 'digital_source_type.digitalCapture', 'value' => 'digitalCapture'],
                ['label' => $ll . 'digital_source_type.computationalCapture', 'value' => 'computationalCapture'],
                ['label' => $ll . 'digital_source_type.negativeFilm', 'value' => 'negativeFilm'],
                ['label' => $ll . 'digital_source_type.positiveFilm', 'value' => 'positiveFilm'],
                ['label' => $ll . 'digital_source_type.print', 'value' => 'print'],
                ['label' => $ll . 'digital_source_type.humanEdits', 'value' => 'humanEdits'],
                ['label' => $ll . 'digital_source_type.compositeWithTrainedAlgorithmicMedia', 'value' => 'compositeWithTrainedAlgorithmicMedia'],
                ['label' => $ll . 'digital_source_type.algorithmicallyEnhanced', 'value' => 'algorithmicallyEnhanced'],
                ['label' => $ll . 'digital_source_type.digitalCreation', 'value' => 'digitalCreation'],
                ['label' => $ll . 'digital_source_type.dataDrivenMedia', 'value' => 'dataDrivenMedia'],
                ['label' => $ll . 'digital_source_type.trainedAlgorithmicMedia', 'value' => 'trainedAlgorithmicMedia'],
                ['label' => $ll . 'digital_source_type.algorithmicMedia', 'value' => 'algorithmicMedia'],
                ['label' => $ll . 'digital_source_type.screenCapture', 'value' => 'screenCapture'],
                ['label' => $ll . 'digital_source_type.virtualRecording', 'value' => 'virtualRecording'],
                ['label' => $ll . 'digital_source_type.composite', 'value' => 'composite'],
                ['label' => $ll . 'digital_source_type.compositeCapture', 'value' => 'compositeCapture'],
                ['label' => $ll . 'digital_source_type.compositeSynthetic', 'value' => 'compositeSynthetic'],
            ],
            'default' => '',
        ],
    ],
]);

$GLOBALS['TCA']['sys_file_metadata']['palettes']['provenance'] = [
    'label' => $ll . 'sys_file_metadata.palette.provenance',
    'showitem' => 'tx_provenance_license, tx_provenance_digital_source_type',
];

ExtensionManagementUtility::addToAllTCAtypes(
    'sys_file_metadata',
    '--palette--;;provenance',
    '',
    'after:copyright'
);
