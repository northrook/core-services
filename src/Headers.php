<?php

declare(strict_types=1);

namespace Core\Service;

use Symfony\Component\HttpFoundation\{HeaderBag, RequestStack, ResponseHeaderBag};
use Symfony\Component\Routing\RequestContext;

final readonly class Headers
{
    public HeaderBag $request;

    public ResponseHeaderBag $response;

    // either do public properties
    // or use custom methods?

    public function __construct(
        RequestStack          $stack,
        public RequestContext $requestContext,
    ) {
        $this->request  = $stack->getCurrentRequest()->headers;
        $this->response = new ResponseHeaderBag();
    }
}