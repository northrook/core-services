<?php

declare(strict_types=1);

namespace Core\Service;

use Northrook\ArrayStore;
use Northrook\Exception\E_Value;
use Northrook\Logger\Log;
use Northrook\Resource\Path;
use Support\Normalize;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class Pathfinder
{
    private readonly ArrayStore $pathfinderCache;

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly string                $pathfinderCachePath,
    ) {}

    public function get( string $path ) : ?string
    {
        return $this->pathfinder()->get( $this->cacheKey( $path ) ) ?? $this->resolvePath( $path );
    }

    public function getPath( string $path ) : ?Path
    {
        $path = $this->get( $path );
        return $path ? new Path( $path ) : null;
    }

    public function has( string $path ) : bool
    {
        return $this->pathfinder()->has( $this->cacheKey( $path ) );
    }

    private function resolvePath( string $path ) : ?string
    {
        // we already know the cache does not contain the requested $path

        // Normalize separators to a forward slash
        $path = \str_replace( '\\', '/', $path );

        // Determine what, if any, separator is used
        $separator = \str_contains( $path, '/' ) ;

        // If the requested $path has no separator, should be a key
        if ( false === $separator ) {
            $value = $this->parameterBag( get: $path );

            if ( ! $value ) {
                Log::warning( 'No value for {path}.', ['path' => $path] );
                return null;
            }

            Log::info( 'Resolved {value} from  {path}.', ['value' => $value, 'path' => $path] );
            return $value;
        }

        // Split the $path by the first $separator
        [$root, $tail] = \explode( '/', $path, 2 );

        // dump(
        //     // $path,
        //     // $root,
        //     \basename( $tail ),
        //     $tail,
        // );

        // Resolve the $root key
        $parameter = $this->parameterBag( get: $root );

        if ( ! $parameter ) {
            return null;
        }

        $resolved = new Path( [$parameter, $tail] );

        if ( $resolved->exists ) {
            $this->pathfinder()->set( $this->cacheKey( $path ), $resolved->path );
        }

        return $resolved->path;
    }

    /**
     * @param ?string $get {@see ParameterBagInterface::get}
     * @param ?string $has {@see ParameterBagInterface::has}
     *
     * @return null|bool|ParameterBagInterface|string
     */
    private function parameterBag( ?string $get = null, ?string $has = null ) : null|string|bool|ParameterBagInterface
    {
        if ( ! $get && ! $has ) {
            return $this->parameterBag;
        }

        if ( $has ) {
            return $this->parameterBag->has( $has );
        }

        try {
            return $this->parameterBag->get( $get );
        }
        catch ( ParameterNotFoundException $exception ) {
            return E_Value::error(
                '{pathfinder} requested the non-existent paraneter {parameter}.',
                [
                    'pathfinder' => $this::class,
                    'parameter'  => $get,
                ],
                $exception,
                false,
            );
        }
    }

    private function cacheKey( string $string ) : string
    {
        $string = \str_replace( '\\', '/', $string );

        if ( ! \str_contains( $string, '/' ) ) {
            return $string;
        }

        [$root, $tail] = \explode( '/', $string, 2 );
        if ( $tail ) {
            $tail = '/'.\str_replace( '.', ':', $tail );
        }
        return $root.$tail;
    }

    private function pathfinder() : ArrayStore
    {
        return $this->pathfinderCache ??= new ArrayStore(
            $this->pathfinderCachePath,
            $this::class,
        );
    }
}