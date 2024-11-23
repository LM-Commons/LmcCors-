<?php
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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use LmcCors\Module;

/**
 * Tests for {@see \LmcCors\Module}
 *
 * @license MIT
 * @author  Marco Pivetta <ocramius@gmail.com>
 *
 * @group Coverage
 */
#[CoversClass('\LmcCors\Module')]
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
        $mvcEvent       = $this->getMockBuilder('Laminas\Mvc\MvcEvent')->getMock();
        $application    = $this->getMockBuilder('Laminas\Mvc\Application')
            ->disableOriginalConstructor()
            ->getMock();
        $eventManager   = $this->getMockBuilder('Laminas\EventManager\EventManagerInterface')->getMock();
        $serviceManager = $this->getMockBuilder('Laminas\ServiceManager\ServiceManager')->getMock();
        $corsListener   = $this->getMockBuilder('LmcCors\Mvc\CorsRequestListener')
            ->disableOriginalConstructor()
            ->getMock();

        $mvcEvent->expects($this->any())->method('getTarget')->willReturn($application);
        $application->expects($this->any())->method('getEventManager')->willReturn($eventManager);
        $application->expects($this->any())->method('getServiceManager')->willReturn($serviceManager);
        $serviceManager
            ->expects($this->any())
            ->method('get')
            ->with('LmcCors\Mvc\CorsRequestListener')
            ->willReturn($corsListener);

        $corsListener->expects($this->once())->method('attach')->with($eventManager);

        $module->onBootstrap($mvcEvent);
    }
}
