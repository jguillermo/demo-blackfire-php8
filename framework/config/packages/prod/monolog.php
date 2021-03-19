<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension(
        'monolog',
        [
            'handlers' => [
                'main' => [
                    'type' => 'fingers_crossed',
                    'action_level' => 'info',
                    'handler' => 'nested',
                    'excluded_404s' => ['^/'],
                ],
                'nested' => [
                    'type' => 'stream',
                    'path' => 'php://stderr',
                    'level' => 'debug',
                ],
                'console' => [
                    'type' => 'console',
                    'process_psr_3_messages' => false,
                    'channels' => ['!event', '!doctrine'],
                ],
                'deprecation' => [
                    'type' => 'stream',
                    'path' => 'php://stderr',
                ],
                'deprecation_filter' => [
                    'type' => 'filter',
                    'handler' => 'deprecation',
                    'max_level' => 'info',
                    'channels' => ['php'],
                ],
            ],
        ]
    );
};
