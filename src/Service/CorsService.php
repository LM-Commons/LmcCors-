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

namespace LmcCors\Service;

use Laminas\Http\Header;
//use Laminas\Mvc\Router\Http\RouteMatch as DeprecatedRouteMatch;
use Laminas\Http\Headers;
use Laminas\Http\Request as HttpRequest;
use Laminas\Http\Response as HttpResponse;
use Laminas\Router\Http\RouteMatch;
use Laminas\Uri\UriFactory;
use LmcCors\Exception\DisallowedOriginException;
use LmcCors\Exception\InvalidOriginException;
use LmcCors\Options\CorsOptions;

use function fnmatch;
use function implode;
use function in_array;
use function sprintf;
use function strtoupper;

/**
 * Service that offers a simple mechanism to handle CORS requests
 *
 * This service closely follow the specification here: https://developer.mozilla.org/en-US/docs/HTTP/Access_control_CORS
 */
class CorsService
{
    protected CorsOptions $options;

    public function __construct(CorsOptions $options)
    {
        $this->options = $options;
    }

    /**
     * Check if the HTTP request is a CORS request by checking if the Origin header is present and that the
     * request URI is not the same as the one in the Origin
     */
    public function isCorsRequest(HttpRequest $request): bool
    {
        $headers = $request->getHeaders();

        if (! $headers->has('Origin')) {
            return false;
        }

        $origin = $headers->get('Origin');

        if (! $origin instanceof Header\Origin) {
            throw InvalidOriginException::fromInvalidHeaderValue();
        }

        $originUri  = UriFactory::factory($origin->getFieldValue());
        $requestUri = $request->getUri();

        // According to the spec (http://tools.ietf.org/html/rfc6454#section-4), we should check host, port and scheme

        return ! ($originUri->getHost() === $requestUri->getHost())
            || ! ($originUri->getPort() === $requestUri->getPort())
            || ! ($originUri->getScheme() === $requestUri->getScheme());
    }

    /**
     * Check if the CORS request is a preflight request
     */
    public function isPreflightRequest(HttpRequest $request): bool
    {
        return $this->isCorsRequest($request)
            && strtoupper($request->getMethod()) === 'OPTIONS'
            && $request->getHeaders()->has('Access-Control-Request-Method');
    }

    /**
     * Create a preflight response by adding the corresponding headers
     */
    public function createPreflightCorsResponse(HttpRequest $request): HttpResponse
    {
        $response = new HttpResponse();
        $response->setStatusCode(200);

        $headers = $response->getHeaders();

        $headers->addHeaderLine('Access-Control-Allow-Origin', $this->getAllowedOriginValue($request));
        $headers->addHeaderLine('Access-Control-Allow-Methods', implode(', ', $this->options->getAllowedMethods()));
        $headers->addHeaderLine('Access-Control-Allow-Headers', implode(', ', $this->options->getAllowedHeaders()));
        $headers->addHeaderLine('Access-Control-Max-Age', $this->options->getMaxAge());
        $headers->addHeaderLine('Content-Length', 0);

        if ($this->options->getAllowedCredentials()) {
            $headers->addHeaderLine('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    /**
     * Create a preflight response by adding the corresponding headers which are merged with per-route configuration
     */
    public function createPreflightCorsResponseWithRouteOptions(
        HttpRequest $request,
        RouteMatch|null $routeMatch = null
    ): HttpResponse {
        $options = $this->options;
        if ($routeMatch instanceof RouteMatch) {
            $options->setFromArray($routeMatch->getParam(CorsOptions::ROUTE_PARAM) ?: []);
        }
        return $this->createPreflightCorsResponse($request);
    }

    /**
     * Populate a simple CORS response
     *
     * @throws DisallowedOriginException
     */
    public function populateCorsResponse(
        HttpRequest $request,
        HttpResponse $response,
        RouteMatch|null $routeMatch = null
    ): HttpResponse {
        if ($routeMatch instanceof RouteMatch) {
            $this->options->setFromArray($routeMatch->getParam(CorsOptions::ROUTE_PARAM) ?: []);
        }

        $origin = $this->getAllowedOriginValue($request);

        // If $origin is "null", then it means that the origin is not allowed. As this is
        // a simple request, it is useless to continue the processing as it will be refused
        // by the browser anyway, so we throw an exception
        if ($origin === 'null') {
            $origin       = $request->getHeader('Origin');
            $originHeader = $origin ? $origin->getFieldValue() : '';
            throw new DisallowedOriginException(
                sprintf(
                    'The origin "%s" is not authorized',
                    $originHeader
                )
            );
        }

        $headers = $response->getHeaders();
        $headers->addHeaderLine('Access-Control-Allow-Origin', $origin);
        $headers->addHeaderLine('Access-Control-Expose-Headers', implode(', ', $this->options->getExposedHeaders()));

        $headers = $this->ensureVaryHeader($response);

        if ($this->options->getAllowedCredentials()) {
            $headers->addHeaderLine('Access-Control-Allow-Credentials', 'true');
        }

        $response->setHeaders($headers);

        return $response;
    }

    /**
     * Get a single value for the "Access-Control-Allow-Origin" header
     *
     * According to the spec, it is not valid to set multiple origins separated by commas. Only accepted
     * value are wildcard ("*"), an exact domain or a null string.
     *
     * @link http://www.w3.org/TR/cors/#access-control-allow-origin-response-header
     */
    protected function getAllowedOriginValue(HttpRequest $request): string
    {
        $allowedOrigins = $this->options->getAllowedOrigins();

        $origin = $request->getHeader('Origin');

        if ($origin) {
            $origin = $origin->getFieldValue();
            if (in_array('*', $allowedOrigins)) {
                return $origin;
            }
            foreach ($allowedOrigins as $allowedOrigin) {
                if (fnmatch($allowedOrigin, $origin)) {
                    return $origin;
                }
            }
        }

        return 'null';
    }

    /**
     * Ensure that the Vary header is set.
     *
     * @link http://www.w3.org/TR/cors/#resource-implementation
     */
    public function ensureVaryHeader(HttpResponse $response): Headers
    {
        $headers = $response->getHeaders();
        // If the origin is not "*", we should add the "Origin" value to the "Vary" header
        // See more: http://www.w3.org/TR/cors/#resource-implementation
        $allowedOrigins = $this->options->getAllowedOrigins();

        if (in_array('*', $allowedOrigins)) {
            return $headers;
        }
        if ($headers->has('Vary')) {
            $varyHeader = $headers->get('Vary');
            $varyValue  = $varyHeader->getFieldValue() . ', Origin';

            $headers->removeHeader($varyHeader);
            $headers->addHeaderLine('Vary', $varyValue);
        } else {
            $headers->addHeaderLine('Vary', 'Origin');
        }

        return $headers;
    }
}
