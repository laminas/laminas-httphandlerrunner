<?php

namespace LaminasTest\HttpHandlerRunner;

use Laminas\HttpHandlerRunner\ConfigProvider;
use PHPUnit\Framework\TestCase;

class ConfigProviderTest extends TestCase
{
    /** @var ConfigProvider */
    private $provider;

    public function setUp(): void
    {
        $this->provider = new ConfigProvider();
    }

    public function testReturnedArrayContainsDependencies(): void
    {
        $config = ($this->provider)();
        $this->assertArrayHasKey('dependencies', $config);
        $this->assertIsArray($config['dependencies']);
    }
}
