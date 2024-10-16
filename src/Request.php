<?php

declare(strict_types=1);

namespace Core\Service;

use Northrook\Trait\PropertyAccessor;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation as Http;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

/**
 * Provides access to the current {@see Http\Request}.
 *
 * @property Http\Request $current
 * @property string       $routeName
 * @property string       $routeRoot
 * @property string       $pathInfo
 * @property string       $route
 * @property string       $method
 * @property string       $type
 * @property string       $controller
 * @property bool         $isHtmx     Checks for the 'HX-Request' header
 * @property bool         $isJson
 * @property bool         $isHtml
 */
final class Request
{
    use PropertyAccessor;

    private array $cache = [];

    /**
     * @param RequestStack $stack
     * @param bool         $createOnEmpty
     */
    public function __construct(
        public readonly RequestStack $stack,
        bool                         $createOnEmpty = false,
    ) {
        if ( ! $this->stack->getCurrentRequest() && $createOnEmpty ) {
            $this->stack->push( Http\Request::createFromGlobals() );
        }
    }

    /**
     * @param string $property
     *
     * @return null|bool|Http\Request|string
     */
    public function __get( string $property ) : Http\Request|string|bool|null
    {
        return match ( $property ) {
            'current'    => $this->currentRequest(),
            'route'      => $this->route(),
            'routeName'  => $this->routeName(),
            'routeRoot'  => $this->routeRoot(),
            'pathInfo'   => $this->currentRequest()->getPathInfo(),
            'method'     => $this->currentRequest()->getMethod(),
            'controller' => $this->requestController(),
            'type'       => $this->type(),
            'isHtmx'     => $this->type( 'application/htmx' ),
            'isJson'     => $this->type( 'application/json' ),
            'isHtml'     => $this->type( 'text/html' ),
            default      => throw new RuntimeException( 'Undefined property: '.$property ),
        };
    }

    /**
     * @param ?string $get
     *
     * @return null|array|bool|float|Http\ParameterBag|int|string
     */
    public function attributes( ?string $get = null ) : Http\ParameterBag|array|string|int|bool|float|null
    {
        return $get ? $this->currentRequest()->attributes->get( $get ) : $this->currentRequest()->attributes;
    }

    /**
     * @param ?string $get {@see Http\Request::get}
     *
     * @return null|array|bool|float|Http\ParameterBag|int|string
     */
    public function parameters( ?string $get = null ) : Http\ParameterBag|array|string|int|bool|float|null
    {
        return $get ? $this->currentRequest()->get( $get ) : $this->currentRequest()->attributes;
    }

    /**
     * @param ?string $get {@see  InputBag::get}
     *
     * @return null|bool|float|Http\InputBag|int|string
     */
    public function query( ?string $get = null ) : Http\InputBag|string|int|float|bool|null
    {
        return $get ? $this->currentRequest()->query->get( $get ) : $this->currentRequest()->query;
    }

    /**
     * @param ?string $get {@see Http\InputBag::get}
     *
     * @return null|bool|float|Http\InputBag|int|string
     */
    public function cookies( ?string $get = null ) : Http\InputBag|string|int|float|bool|null
    {
        return $get ? $this->currentRequest()->cookies->get( $get ) : $this->currentRequest()->cookies;
    }

    /**
     * @param ?string $get {@see  SessionInterface::get}
     *
     * @return FlashBagAwareSessionInterface
     */
    public function session( ?string $get = null ) : FlashBagAwareSessionInterface
    {
        try {
            return $get ? $this->currentRequest()->getSession()->get( $get ) : $this->currentRequest()->getSession();
        }
        catch ( Http\Exception\SessionNotFoundException $exception ) {
            throw new Http\Exception\LogicException( message  : 'Sessions are disabled. Enable them in "config/packages/framework".', previous : $exception );
        }
    }

    /**
     * @param ?string $get {@see Http\HeaderBag::get} Returns null if the header is not set
     * @param ?string $has {@see Http\HeaderBag::has} Checks if the headerBag contains the header
     *
     * @return null|bool|Http\HeaderBag|string
     */
    public function headerBag( ?string $get = null, ?string $has = null ) : Http\HeaderBag|string|bool|null
    {
        if ( ! $get && ! $has ) {
            return $this->currentRequest()->headers;
        }

        return $get ? $this->currentRequest()->headers->get( $get ) : $this->currentRequest()->headers->has( $has );
    }

    public function flashBag() : FlashBagInterface
    {
        return $this->session()->getFlashBag();
    }

    // :: $request->__get methods

    /**
     * Public access via magic {@see Request::$current};.
     *
     * @return Http\Request
     */
    private function currentRequest() : Http\Request
    {
        return $this->stack->getCurrentRequest()
               ?? throw new Http\Exception\LogicException( $this::class.' could not resolve the current Http\\Request, the RequestStack is empty.' );
    }

    /**
     * Return the current requestType, or match against it.
     *
     * - Pass `null` to return the current requestType as string
     *
     * @param ?string $is
     *
     * @return bool|string
     */
    private function type( ?string $is = null ) : bool|string
    {
        $this->cache['type'] = match ( true ) {
            $this->headerBag( has : 'hx-request' )   => 'application/htmx',
            $this->headerBag( has : 'content-type' ) => \strstr( $this->headerBag( get : 'content-type' ), ';', true ),
            default                                  => 'text/plain',
        };

        return $is ? $is === $this->cache['type'] : $this->cache['type'];
    }

    /**
     * Resolve and cache the current route key.
     *
     * @return string
     */
    private function route() : string
    {
        return $this->cache['route'] ??= $this->currentRequest()->attributes->get( 'route' ) ?? '';
    }

    /**
     * Resolve and cache the current route name.
     *
     * @return string
     */
    private function routeName() : string
    {
        return $this->cache['routeName'] ??= $this->currentRequest()->get( '_route' ) ?? '';
    }

    /**
     * Resolve and cache the current route root name.
     *
     * @return string
     */
    private function routeRoot() : string
    {
        return \strstr( $this->routeName(), ':', true ) ?: $this->routeName();
    }

    /**
     * Resolve and cache the controller and method for this request.
     *
     * - Returns `null` if no `_controller` parameter is defined.
     *
     * @return ?string
     */
    private function requestController() : ?string
    {
        return $this->cache['controller'] ??= ( \is_array( $controller = $this->parameters( '_controller' ) ) )
                ? \implode( '::', $controller )
                : $controller;
    }
}
