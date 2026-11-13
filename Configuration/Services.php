<?php

declare(strict_types=1);

use CmsProvenance\Provenance\WellKnown\WellKnownHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// Every service implementing WellKnownHandlerInterface, in any extension with
// autoconfigure enabled, is handed to the WellKnownRouter.
return static function (ContainerConfigurator $configurator, ContainerBuilder $containerBuilder): void {
    $containerBuilder->registerForAutoconfiguration(WellKnownHandlerInterface::class)
        ->addTag('provenance.well_known_handler');
};
