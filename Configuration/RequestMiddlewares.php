<?php

declare(strict_types=1);

use CmsProvenance\Provenance\WellKnown\WellKnownRouter;

return [
    'frontend' => [
        'cms-provenance/well-known' => [
            'target' => WellKnownRouter::class,
            'after' => ['typo3/cms-frontend/site'],
            'before' => ['typo3/cms-frontend/base-redirect-resolver'],
        ],
    ],
];
