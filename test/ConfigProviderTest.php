<?php

declare(strict_types=1);

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
        self::assertArrayHasKey('dependencies', $config);
        self::assertIsArray($config['dependencies']);
    }
}
