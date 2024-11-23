<?php

declare(strict_types=1);

/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace LmcCors\Mvc;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Request as HttpRequest;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\MvcEvent;
use LmcCors\Exception\DisallowedOriginException;
use LmcCors\Exception\InvalidOriginException;
use LmcCors\Service\CorsService;

/**
 * CorsRequestListener
 */
class CorsRequestListener extends AbstractListenerAggregate
{
    protected CorsService $corsService;

    /**
     * Whether or not a preflight request was detected
     */
    protected bool $isPreflight = false;

    public function __construct(CorsService $corsService)
    {
        $this->corsService = $corsService;
    }

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        // Preflight can be handled during the route event, and should return early
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'onCorsPreflight'], 2);

        // "in"flight should be handled during "FINISH" to ensure we operate on the actual route being returned
        $this->listeners[] = $events->attach(MvcEvent::EVENT_FINISH, [$this, 'onCorsRequest'], 100);
    }

    /**
     * Handle a CORS preflight request
     *
     * @return null|HttpResponse|void
     */
    public function onCorsPreflight(MvcEvent $event)
    {
        // Reset state flag
        $this->isPreflight = false;

        /** @var HttpRequest $request */
        $request = $event->getRequest();

        if (! $request instanceof HttpRequest) {
            return;
        }

        try {
            $isCorsRequest = $this->corsService->isCorsRequest($request);
        } catch (InvalidOriginException $exception) {
            /** @var HttpResponse $response */
            $response = $event->getResponse();
            $response->setStatusCode(400);
            $response->setReasonPhrase($exception->getMessage());
            return $response;
        }

        // If this isn't a preflight, done
        if (! $isCorsRequest || ! $this->corsService->isPreflightRequest($request)) {
            return;
        }

        // Preflight -- return a response now!
        $this->isPreflight = true;

        $router = $event->getRouter();

        $requestForMatching = clone $request;
        // Use the request method for route detection, which is being used during the request.
        $requestForMatching->setMethod($request->getHeader('Access-Control-Request-Method')->getFieldValue());

        $routeMatch = $router->match($requestForMatching);

        return $this->corsService->createPreflightCorsResponseWithRouteOptions($request, $routeMatch);
    }

    /**
     * Handle a CORS request (non-preflight, normal CORS request)
     */
    public function onCorsRequest(MvcEvent $event): void
    {
        // Do nothing if we previously created a preflight response
        if ($this->isPreflight) {
            return;
        }

        /** @var HttpRequest $request */
        $request = $event->getRequest();
        /** @var HttpResponse $response */
        $response = $event->getResponse();

        if (! $request instanceof HttpRequest) {
            return;
        }

        try {
            $isCorsRequest = $this->corsService->isCorsRequest($request);
        } catch (InvalidOriginException $exception) {
            // InvalidOriginException should already be handled by `CorsRequestListener::onCorsPreflight`
            return;
        }

        // Also ensure that the vary header is set when no origin is set
        // to prevent reverse proxy caching a wrong request; causing all of the following
        // requests to fail due to missing CORS headers.
        if (! $isCorsRequest) {
            if (! $request->getHeader('Origin')) {
                $this->corsService->ensureVaryHeader($response);
            }
            return;
        }

        // This is the second step of the CORS request, and we let ZF continue
        // processing the response
        try {
            $response = $this->corsService->populateCorsResponse($request, $response, $event->getRouteMatch());
        } catch (DisallowedOriginException $exception) {
            $response = new HttpResponse(); // Clear response for security
            $response->setStatusCode(403)
                     ->setReasonPhrase($exception->getMessage());
        }

        $event->setResponse($response);
    }
}
