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

namespace LmcCorsTest\Service;

use Laminas\Http\Request as HttpRequest;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\Http\RouteMatch;
use LmcCors\Exception\DisallowedOriginException;
use LmcCors\Exception\InvalidOriginException;
use LmcCors\Options\CorsOptions;
use LmcCors\Service\CorsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for {@see \LmcCors\Service\CorsService}
 *
 * @group Coverage
 */
#[CoversClass(CorsService::class)]
class CorsServiceTest extends TestCase
{
    protected CorsService $corsService;

    protected HttpResponse $response;

    protected HttpRequest $request;

    protected MvcEvent $event;

    protected CorsOptions $corsOptions;

    /**
     * Set up
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->corsOptions = new CorsOptions(
            [
                'allowed_origins'     => ['http://example.com'],
                'allowed_methods'     => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
                'allowed_headers'     => ['Content-Type', 'Accept'],
                'exposed_headers'     => ['Location'],
                'max_age'             => 10,
                'allowed_credentials' => true,
            ]
        );

        $this->corsService = new CorsService($this->corsOptions);
    }

    public function testCanDetectCorsRequest(): void
    {
        $request = new HttpRequest();

        $this->assertFalse($this->corsService->isCorsRequest($request));

        $request->getHeaders()->addHeaderLine('Origin', 'http://example.com');
        $this->assertTrue($this->corsService->isCorsRequest($request));
    }

    public function testIsNotCorsRequestIfNotACrossRequest(): void
    {
        $request = new HttpRequest();
        $request->setUri('http://example.com');

        $request->getHeaders()->addHeaderLine('Origin', 'http://example.com');
        $this->assertFalse($this->corsService->isCorsRequest($request));
    }

    public function testCanDetectPreflightRequest(): void
    {
        $request = new HttpRequest();

        $this->assertFalse($this->corsService->isPreflightRequest($request));

        $request->setMethod('OPTIONS');
        $this->assertFalse($this->corsService->isPreflightRequest($request));

        $request->getHeaders()->addHeaderLine('Origin', 'http://example.com');
        $this->assertFalse($this->corsService->isPreflightRequest($request));

        $request->getHeaders()->addHeaderLine('Access-Control-Request-Method', 'POST');
        $this->assertTrue($this->corsService->isPreflightRequest($request));
    }

    public function testProperlyCreatePreflightResponse(): void
    {
        $request = new HttpRequest();
        $request->getHeaders()->addHeaderLine('Origin', 'http://example.com');

        $response = $this->corsService->createPreflightCorsResponse($request);

        $headers = $response->getHeaders();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('', $response->getContent());
        $this->assertEquals('http://example.com', $headers->get('Access-Control-Allow-Origin')->getFieldValue());
        $this->assertEquals(
            'GET, POST, PUT, DELETE, OPTIONS',
            $headers->get('Access-Control-Allow-Methods')->getFieldValue()
        );
        $this->assertEquals('Content-Type, Accept', $headers->get('Access-Control-Allow-Headers')->getFieldValue());
        $this->assertEquals(10, $headers->get('Access-Control-Max-Age')->getFieldValue());
        $this->assertEquals(0, $headers->get('Content-Length')->getFieldValue());

        $this->assertEquals('true', $headers->get('Access-Control-Allow-Credentials')->getFieldValue());
    }

    public function testDoesNotAddAllowCredentialsHeadersIfAsked(): void
    {
        $request = new HttpRequest();
        $request->getHeaders()->addHeaderLine('Origin', 'http://example.com');
        $this->corsOptions->setAllowedCredentials(false);

        $response = $this->corsService->createPreflightCorsResponse($request);

        $headers = $response->getHeaders();
        $this->assertFalse($headers->has('Access-Control-Allow-Credentials'));
    }

    public function testCanReturnWildCardAllowOrigin(): void
    {
        $request = new HttpRequest();
        $request->getHeaders()->addHeaderLine('Origin', 'http://funny-origin.com');
        $this->corsOptions->setAllowedOrigins(['*']);

        $response = $this->corsService->createPreflightCorsResponse($request);

        $headers = $response->getHeaders();
        $this->assertEquals('http://funny-origin.com', $headers->get('Access-Control-Allow-Origin')->getFieldValue());
    }

    public function testCanReturnWildCardSubDomainAllowOrigin(): void
    {
        $request = new HttpRequest();
        $request->getHeaders()->addHeaderLine('Origin', 'http://subdomain.example.com');
        $this->corsOptions->setAllowedOrigins(['*.example.com']);

        $response = $this->corsService->createPreflightCorsResponse($request);

        $headers     = $response->getHeaders();
        $headerValue = $headers->get('Access-Control-Allow-Origin')->getFieldValue();
        $this->assertEquals('http://subdomain.example.com', $headerValue);
    }

    public function testCanReturnWildCardSubDomainWithSchemeAllowOrigin(): void
    {
        $request = new HttpRequest();
        $request->getHeaders()->addHeaderLine('Origin', 'https://subdomain.example.com');
        $this->corsOptions->setAllowedOrigins(['https://*.example.com']);

        $response = $this->corsService->createPreflightCorsResponse($request);

        $headers     = $response->getHeaders();
        $headerValue = $headers->get('Access-Control-Allow-Origin')->getFieldValue();
        $this->assertEquals('https://subdomain.example.com', $headerValue);
    }

    public function testReturnNullForMissMatchedWildcardSubDomainOrigin(): void
    {
        $request = new HttpRequest();
        $request->getHeaders()->addHeaderLine('Origin', 'http://subdomain.example.org');
        $this->corsOptions->setAllowedOrigins(['*.example.com']);

        $response = $this->corsService->createPreflightCorsResponse($request);

        $headers = $response->getHeaders();
        $this->assertEquals('null', $headers->get('Access-Control-Allow-Origin')->getFieldValue());
    }

    public function testReturnNullForRootDomainOnWildcardSubDomainOrigin(): void
    {
        $request = new HttpRequest();
        $request->getHeaders()->addHeaderLine('Origin', 'http://example.com');
        $this->corsOptions->setAllowedOrigins(['*.example.com']);

        $response = $this->corsService->createPreflightCorsResponse($request);

        $headers = $response->getHeaders();
        $this->assertEquals('null', $headers->get('Access-Control-Allow-Origin')->getFieldValue());
    }

    public function testReturnNullForDifferentSchemeOnWildcardSubDomainOrigin(): void
    {
        $request = new HttpRequest();
        $request->getHeaders()->addHeaderLine('Origin', 'https://example.com');
        $this->corsOptions->setAllowedOrigins(['http://*.example.com']);

        $response = $this->corsService->createPreflightCorsResponse($request);

        $headers = $response->getHeaders();
        $this->assertEquals('null', $headers->get('Access-Control-Allow-Origin')->getFieldValue());
    }

    public function testReturnNullForUnknownOrigin(): void
    {
        $request = new HttpRequest();
        $request->getHeaders()->addHeaderLine('Origin', 'http://unauthorized-origin.com');

        $response = $this->corsService->createPreflightCorsResponse($request);

        $headers = $response->getHeaders();
        $this->assertEquals('null', $headers->get('Access-Control-Allow-Origin')->getFieldValue());
    }

    public function testEnsureVaryHeaderForNoOrigin(): void
    {
        $response = new HttpResponse();

        $this->corsService->ensureVaryHeader($response);

        $headers = $response->getHeaders();

        $this->assertFalse($headers->get('Origin'));
        $this->assertNotFalse($headers->get('Vary'));
        //$this->assertContains('Origin', $headers->get('Vary')->getFieldValue());
        $this->assertStringContainsString('Origin', $headers->get('Vary')->getFieldValue());
    }

    public function testEnsureNoVaryHeaderWhenAcceptsAnyOrigin(): void
    {
        $response    = new HttpResponse();
        $corsService = new CorsService(new CorsOptions([
            'allowed_origins' => ['*'],
        ]));

        $corsService->ensureVaryHeader($response);

        $headers = $response->getHeaders();

        $this->assertFalse($headers->get('Origin'));
        $this->assertFalse($headers->get('Vary'));
    }

    public function testCanPopulateNormalCorsRequest(): void
    {
        $request  = new HttpRequest();
        $response = new HttpResponse();

        $request->getHeaders()->addHeaderLine('Origin', 'http://example.com');

        $this->corsService->populateCorsResponse($request, $response);

        $headers = $response->getHeaders();

        $this->assertEquals('http://example.com', $headers->get('Access-Control-Allow-Origin')->getFieldValue());
        $this->assertEquals('Location', $headers->get('Access-Control-Expose-Headers')->getFieldValue());
    }

    public function testRefuseNormalCorsRequestIfUnauthorized(): void
    {
        $request  = new HttpRequest();
        $response = new HttpResponse();

        $request->getHeaders()->addHeaderLine('Origin', 'http://unauthorized.com');

        $this->expectException(DisallowedOriginException::class);
        $this->expectExceptionMessage('The origin "http://unauthorized.com" is not authorized');

        $this->corsService->populateCorsResponse($request, $response);
    }

    public function testAddVaryHeaderInNormalRequest(): void
    {
        $request  = new HttpRequest();
        $response = new HttpResponse();

        $request->getHeaders()->addHeaderLine('Origin', 'http://example.com');

        $this->corsService->populateCorsResponse($request, $response);

        $headers = $response->getHeaders();
        $this->assertTrue($headers->has('Vary'));
    }

    public function testAppendVaryHeaderInNormalRequest(): void
    {
        $request  = new HttpRequest();
        $response = new HttpResponse();

        $request->getHeaders()->addHeaderLine('Origin', 'http://example.com');
        $response->getHeaders()->addHeaderLine('Vary', 'Foo');

        $this->corsService->populateCorsResponse($request, $response);

        $headers = $response->getHeaders();
        $this->assertTrue($headers->has('Vary'));
        $this->assertEquals('Foo, Origin', $headers->get('Vary')->getFieldValue());
    }

    public function testPopulatesAllowCredentialsNormalCorsRequest(): void
    {
        $request  = new HttpRequest();
        $response = new HttpResponse();

        $request->getHeaders()->addHeaderLine('Origin', 'http://example.com');

        $this->corsService->populateCorsResponse($request, $response);

        $headers = $response->getHeaders();
        $this->assertNotNull($headers);
        $this->assertEquals('true', $headers->get('Access-Control-Allow-Credentials')->getFieldValue());
    }

    public function testCanDetectCorsRequestFromSameHostButDifferentPort(): void
    {
        $request = new HttpRequest();
        $request->setUri('http://example.com');
        $request->getHeaders()->addHeaderLine('Origin', 'http://example.com:9000');
        $this->assertTrue($this->corsService->isCorsRequest($request));
    }

    public function testCanDetectCorsRequestFromSameHostButDifferentScheme(): void
    {
        $request = new HttpRequest();
        $request->setUri('https://example.com');
        $request->getHeaders()->addHeaderLine('Origin', 'http://example.com');
        $this->assertTrue($this->corsService->isCorsRequest($request));
    }

    public function testCanHandleUnconfiguredRouteMatch(): void
    {
        $routeMatch = new RouteMatch([]);

        $request = new HttpRequest();
        $request->getHeaders()->addHeaderLine('Origin', 'http://example.com');
        $response = $this->corsService->createPreflightCorsResponseWithRouteOptions($request, $routeMatch);

        $headers = $response->getHeaders();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('', $response->getContent());
        $this->assertEquals('http://example.com', $headers->get('Access-Control-Allow-Origin')->getFieldValue());
        $this->assertEquals(
            'GET, POST, PUT, DELETE, OPTIONS',
            $headers->get('Access-Control-Allow-Methods')->getFieldValue()
        );
        $this->assertEquals('Content-Type, Accept', $headers->get('Access-Control-Allow-Headers')->getFieldValue());
        $this->assertEquals(10, $headers->get('Access-Control-Max-Age')->getFieldValue());
        $this->assertEquals(0, $headers->get('Content-Length')->getFieldValue());

        $this->assertEquals('true', $headers->get('Access-Control-Allow-Credentials')->getFieldValue());
    }

    public function testCanHandleConfiguredRouteMatch(): void
    {
        $routeMatchParameters = [
            CorsOptions::ROUTE_PARAM => [
                'allowed_origins'     => ['http://example.org'],
                'allowed_methods'     => ['POST', 'DELETE', 'OPTIONS'],
                'allowed_headers'     => ['Content-Type', 'Accept', 'Cookie'],
                'exposed_headers'     => ['Location'],
                'max_age'             => 5,
                'allowed_credentials' => false,
            ],
        ];

        $routeMatch = new RouteMatch($routeMatchParameters);

        $request = new HttpRequest();
        $request->getHeaders()->addHeaderLine('Origin', 'http://example.org');
        $response = $this->corsService->createPreflightCorsResponseWithRouteOptions($request, $routeMatch);

        $headers = $response->getHeaders();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('', $response->getContent());
        $this->assertEquals('http://example.org', $headers->get('Access-Control-Allow-Origin')->getFieldValue());
        $this->assertEquals(
            'POST, DELETE, OPTIONS',
            $headers->get('Access-Control-Allow-Methods')->getFieldValue()
        );
        $this->assertEquals(
            'Content-Type, Accept, Cookie',
            $headers->get('Access-Control-Allow-Headers')->getFieldValue()
        );
        $this->assertEquals(5, $headers->get('Access-Control-Max-Age')->getFieldValue());
        $this->assertEquals(0, $headers->get('Content-Length')->getFieldValue());

        $this->assertFalse($headers->has('Access-Control-Allow-Credentials'));
    }

    /**
     * @see https://github.com/zf-fr/zfr-cors/issues/44
     */
    public function testDoesNotCrashApplicationOnInvalidOriginValue(): void
    {
        $request = new HttpRequest();
        $request->setUri('https://example.com');
        $request->getHeaders()->addHeaderLine('Origin', 'file:');
        $this->expectException(InvalidOriginException::class);
        $this->corsService->isCorsRequest($request);
    }

    /**
     * @see https://github.com/zf-fr/zfr-cors/issues/57
     */
    public function testCanPopulateNormalCorsRequestWithRouteMatch(): void
    {
        $request              = new HttpRequest();
        $response             = new HttpResponse();
        $routeMatchParameters = [
            CorsOptions::ROUTE_PARAM => [
                'allowed_origins' => ['http://example.org'],
            ],
        ];

        $routeMatch = new RouteMatch($routeMatchParameters);

        $request->getHeaders()->addHeaderLine('Origin', 'http://example.org');

        $response = $this->corsService->populateCorsResponse($request, $response, $routeMatch);
        $this->assertInstanceOf(HttpResponse::class, $response);
        $this->assertEquals(
            'http://example.org',
            $response->getHeaders()->get('Access-Control-Allow-Origin')->getFieldValue()
        );
    }

    /**
     * @see https://github.com/zf-fr/zfr-cors/issues/57
     */
    public function testCanPopulateNormalCorsRequestWithRouteMatchRewriteException(): void
    {
        $request              = new HttpRequest();
        $response             = new HttpResponse();
        $routeMatchParameters = [
            CorsOptions::ROUTE_PARAM => [
                'allowed_origins' => ['http://example.org'],
            ],
        ];

        $routeMatch = new RouteMatch($routeMatchParameters);

        $request->getHeaders()->addHeaderLine('Origin', 'http://example.com');

        $this->expectException(DisallowedOriginException::class);
        $this->corsService->populateCorsResponse($request, $response, $routeMatch);
    }
}
