<?php

declare(strict_types=1);

use CmsProvenance\Provenance\Hook\AssignDocumentIdentifier;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['provenance']
    = AssignDocumentIdentifier::class;
