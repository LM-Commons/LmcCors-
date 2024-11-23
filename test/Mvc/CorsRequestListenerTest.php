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

namespace LmcCorsTest\Mvc;

use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Request as HttpRequest;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\MvcEvent;
use Laminas\Mvc\RouteListener;
use Laminas\Router\Http\TreeRouteStack;
use LmcCors\Mvc\CorsRequestListener;
use LmcCors\Options\CorsOptions;
use LmcCors\Service\CorsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for {@see \LmcCors\Service\CorsService}
 *
 * @group  Coverage
 */
#[CoversClass(CorsRequestListener::class)]
class CorsRequestListenerTest extends TestCase
{
    protected CorsService $corsService;

    protected CorsOptions $corsOptions;

    protected CorsRequestListener $corsListener;

    public function setUp(): void
    {
        $this->corsOptions  = new CorsOptions();
        $this->corsService  = new CorsService($this->corsOptions);
        $this->corsListener = new CorsRequestListener($this->corsService);
    }

    public function testAttach(): void
    {
        $eventManager = $this->getMockBuilder(EventManagerInterface::class)->getMock();

        $matcher = $this->exactly(2);

        $eventManager
            ->expects($matcher)
            ->method('attach')
            ->willReturnCallback(function (string $event, callable $callback, int $priority) {
                match ($event) {
                    MvcEvent::EVENT_ROUTE => '',
                    MvcEvent::EVENT_FINISH => '',
                };
            });
        $this->corsListener->attach($eventManager);
    }

    public function testReturnNothingForNonCorsRequest(): void
    {
        $mvcEvent = new MvcEvent();
        $request  = new HttpRequest();
        $response = new HttpResponse();

        $mvcEvent
            ->setRequest($request)
            ->setResponse($response);

        $this->assertNull($this->corsListener->onCorsPreflight($mvcEvent));
        $this->assertNull($this->corsListener->onCorsRequest($mvcEvent));
    }

    public function testImmediatelyReturnResponseForPreflightCorsRequest(): void
    {
        $mvcEvent = new MvcEvent();
        $request  = new HttpRequest();
        $response = new HttpResponse();
        $router   = new TreeRouteStack();

        $request->setMethod('OPTIONS');
        $request->getHeaders()->addHeaderLine('Origin', 'http://example.com');
        $request->getHeaders()->addHeaderLine('Access-Control-Request-Method', 'POST');

        $mvcEvent
            ->setRequest($request)
            ->setResponse($response)
            ->setRouter($router);

        $this->assertInstanceOf(HttpResponse::class, $this->corsListener->onCorsPreflight($mvcEvent));
    }

    public function testReturnNothingForNormalAuthorizedCorsRequest(): void
    {
        $mvcEvent = new MvcEvent();
        $request  = new HttpRequest();
        $response = new HttpResponse();

        $request->getHeaders()->addHeaderLine('Origin', 'http://example.com');

        $this->corsOptions->setAllowedOrigins(['http://example.com']);

        $mvcEvent
            ->setRequest($request)
            ->setResponse($response);

        $this->assertNull($this->corsListener->onCorsRequest($mvcEvent));
    }

    public function testReturnUnauthorizedResponseForNormalUnauthorizedCorsRequest(): void
    {
        $mvcEvent = new MvcEvent();
        $request  = new HttpRequest();
        $response = new HttpResponse();

        $request->getHeaders()->addHeaderLine('Origin', 'http://unauthorized-domain.com');

        $mvcEvent
            ->setRequest($request)
            ->setResponse($response);

        $this->corsListener->onCorsRequest($mvcEvent);

        // NOTE: a new response is created for security purpose
        $newResponse = $mvcEvent->getResponse();
        $this->assertNotEquals($response, $newResponse);
        $this->assertEquals(403, $newResponse->getStatusCode());
        $this->assertEquals('', $newResponse->getContent());
    }

    public function testImmediatelyReturnBadRequestResponseForInvalidOriginHeaderValue(): void
    {
        $mvcEvent = new MvcEvent();
        $request  = new HttpRequest();
        $response = new HttpResponse();
        $router   = new TreeRouteStack();

        $request->getHeaders()->addHeaderLine('Origin', 'file:');

        $mvcEvent
            ->setRequest($request)
            ->setResponse($response)
            ->setRouter($router);

        $returnedResponse = $this->corsListener->onCorsPreflight($mvcEvent);

        $this->assertEquals($response, $returnedResponse);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('', $response->getContent());
    }

    /**
     * Application always triggers `MvcEvent::EVENT_FINISH` and since the `CorsRequestListener` is listening on it, we
     * should handle the exception aswell.
     *
     * @return void
     */
    public function testOnCorsRequestCanHandleInvalidOriginHeaderValue(): void
    {
        $mvcEvent = new MvcEvent();
        $request  = new HttpRequest();
        $response = new HttpResponse();

        $request->getHeaders()->addHeaderLine('Origin', 'file:');

        $mvcEvent
            ->setRequest($request)
            ->setResponse($response);

        $this->assertNull($this->corsListener->onCorsRequest($mvcEvent));
    }

    public function testPreflightWorksWithMethodRoutes(): void
    {
        $mvcEvent = new MvcEvent();
        $request  = new HttpRequest();
        $request->setUri('/foo');
        $request->setMethod('OPTIONS');
        $request->getHeaders()->addHeaderLine('Origin', 'http://example.com');
        $request->getHeaders()->addHeaderLine('Access-Control-Request-Method', 'GET');
        $response = new HttpResponse();
        $router   = new TreeRouteStack();
        $router
            ->addRoutes([
                'home' => [
                    'type'          => 'literal',
                    'options'       => [
                        'route' => '/foo',
                    ],
                    'may_terminate' => false,
                    'child_routes'  => [
                        'get' => [
                            'type'    => 'method',
                            'options' => [
                                'verb'     => 'get',
                                'defaults' => [
                                    CorsOptions::ROUTE_PARAM => [
                                        'allowed_origins' => ['http://example.com'],
                                        'allowed_methods' => ['GET'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $mvcEvent
            ->setRequest($request)
            ->setResponse($response)
            ->setRouter($router);

        $events = new EventManager();
        $this->corsListener->attach($events);
        (new RouteListener())->attach($events);

        $event = new MvcEvent(MvcEvent::EVENT_ROUTE);
        $event->setRouter($router);
        $event->setRequest($request);

        $shortCircuit = function ($r) {
            $this->assertInstanceOf(HttpResponse::class, $r);
            $this->assertEquals(200, $r->getStatusCode());
            $this->assertEquals('GET', $r->getHeaders()->get('Access-Control-Allow-Methods')->getFieldValue());
            $this->assertEquals(
                'http://example.com',
                $r->getHeaders()->get('Access-Control-Allow-Origin')->getFieldValue()
            );
            return true;
        };
        $events->triggerEventUntil($shortCircuit, $event);
    }
}
