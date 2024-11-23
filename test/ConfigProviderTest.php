<?php

namespace LmcCorsTest;

use LmcCors\ConfigProvider;
use PHPUnit\Framework\TestCase;

class ConfigProviderTest extends TestCase
{
    public function testProviderExpectedConfiguration()
    {
        $provider = new ConfigProvider();
        $config = $provider();
        $this->assertArrayHasKey('dependencies', $config);
        $this->assertArrayHasKey('lmc_cors', $config);
        $this->assertArrayHasKey('factories', $config['dependencies']);
    }
}
