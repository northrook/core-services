<?php

declare(strict_types=1);

namespace Core\Service;

// Pathfinder cache using array-store
// Request
// Headers
// Auth?
// Settings
use Override;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Core shared services.
 *
 * - Available throughout the framework.
 *
 * @author Martin Nielsen
 */
final class CoreServiceBundle extends AbstractBundle
{
    #[Override]
    public function getPath() : string
    {
        return \dirname( __DIR__ );
    }

    /**
     * @param array<array-key, mixed> $config
     * @param ContainerConfigurator   $container
     * @param ContainerBuilder        $builder
     *
     * @return void
     */
    #[Override]
    public function loadExtension(
        array                 $config,
        ContainerConfigurator $container,
        ContainerBuilder      $builder,
    ) : void {

        $services = $container->services();

        $services->defaults()
            ->tag( 'controller.service_arguments' )
            ->autowire();

        $services
            // Request and Response headers
            ->set( Headers::class )
            ->args( [
                service( 'request_stack' ),
                service( 'router.request_context' ),
            ] )

            // Find and return registered paths
            ->set( Pathfinder::class )

            // Current Request handler
            ->set( Request::class )
            ->args( [service( 'request_stack' ), service( 'http_kernel' )] )

            // Settings handler
            ->set( Settings::class );

    }
}