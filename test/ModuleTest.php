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

namespace LmcCorsTest;

use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceManager;
use LmcCors\Module;
use LmcCors\Mvc\CorsRequestListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function serialize;
use function unserialize;

/**
 * Tests for {@see \LmcCors\Module}
 *
 * @group Coverage
 */
#[CoversClass(Module::class)]
class ModuleTest extends TestCase
{
    public function testGetConfig()
    {
        $module = new Module();

        $this->assertIsArray($module->getConfig());
        $this->assertSame($module->getConfig(), unserialize(serialize($module->getConfig())), 'Config is serializable');
    }

    public function testAssertListenerIsCorrectlyRegistered()
    {
        $module         = new Module();
        $mvcEvent       = $this->getMockBuilder(MvcEvent::class)->getMock();
        $application    = $this->getMockBuilder(Application::class)
            ->disableOriginalConstructor()
            ->getMock();
        $eventManager   = $this->getMockBuilder(EventManagerInterface::class)->getMock();
        $serviceManager = $this->getMockBuilder(ServiceManager::class)->getMock();
        $corsListener   = $this->getMockBuilder(CorsRequestListener::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mvcEvent->expects($this->any())->method('getTarget')->willReturn($application);
        $application->expects($this->any())->method('getEventManager')->willReturn($eventManager);
        $application->expects($this->any())->method('getServiceManager')->willReturn($serviceManager);
        $serviceManager
            ->expects($this->any())
            ->method('get')
            ->with(CorsRequestListener::class)
            ->willReturn($corsListener);

        $corsListener->expects($this->once())->method('attach')->with($eventManager);

        $module->onBootstrap($mvcEvent);
    }
}
