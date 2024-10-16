<?php

declare(strict_types=1);

namespace Core\Service;

use Symfony\Component\HttpFoundation\{HeaderBag, RequestStack, ResponseHeaderBag};
use function Support\toString;

/**
 * Access both Request and Response headers as an autowired service.
 */
final readonly class Headers
{
    public HeaderBag $request;

    public ResponseHeaderBag $response;

    public function __construct( RequestStack $stack )
    {
        $this->request  = $stack->getCurrentRequest()->headers;
        $this->response = new ResponseHeaderBag();
    }

    /**
     * Set one or more response headers.
     *
     * - Assigned to the {@see Headers::$response} property.
     * - Will replace existing headers by default.
     *
     * @param array<string, null|array|bool|float|int|string>|string $set
     * @param null|array|bool|float|int|string                       $value
     * @param bool                                                   $replace
     *
     * @return ResponseHeaderBag
     */
    public function __invoke( string|array $set, bool|string|int|float|array|null $value = null, bool $replace = true ) : ResponseHeaderBag
    {
        // Allows setting multiple values
        if ( \is_array( $set ) ) {
            foreach ( $set as $key => $value ) {
                $this->__invoke( $key, $value, $replace );
            }

            return $this->response;
        }

        $value = match ( true ) {
            \is_bool( $value ) => $value ? 'true' : 'false',
            \is_string( $value ), \is_null( $value ), \array_is_list( $value ), => $value,
            default => toString( $value ),
        };

        $this->response->set( $set, $value, $replace );

        return $this->response;
    }
}
