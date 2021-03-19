<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters
        ->set('container.dumper.inline_factories', true)
        ->set('container.dumper.inline_class_loader', true)
    ;
};
