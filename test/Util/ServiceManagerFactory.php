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
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace LmcCorsTest\Util;

use Laminas\ModuleManager\ModuleManagerInterface;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Base test case to be used when a new service manager instance is required
 *
 * @license MIT
 * @link    https://github.com/zf-fr/zfr-cors
 * @author  Marco Pivetta <ocramius@gmail.com>
 * @author  Florent Blaison <florent.blaison@gmail.com>
 */
abstract class ServiceManagerFactory
{
    /**
     * @var array
     */
    private static array $config = [];

    /**
     * @static
     * @param array $config
     * @return void
     */
    public static function setApplicationConfig(array $config): void
    {
        static::$config = $config;
    }

    /**
     * @static
     * @return array
     */
    public static function getApplicationConfig(): array
    {
        return static::$config;
    }

    /**
     * @param array|null $config
     * @return ServiceManager
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function getServiceManager(array|null $config = null): ServiceManager
    {
        $config = $config ?: static::getApplicationConfig();
        $serviceManager = new ServiceManager();
        $serviceManagerConfig = new ServiceManagerConfig(
            $config['service_manager'] ?? []
        );
        $serviceManagerConfig->configureServiceManager($serviceManager);

        $serviceManager->setService('ApplicationConfig', $config);

        /* @var $moduleManager ModuleManagerInterface */
        $moduleManager = $serviceManager->get('ModuleManager');

        $moduleManager->loadModules();

        return $serviceManager;
    }
}
