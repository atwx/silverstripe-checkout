<?php

namespace Atwx\Checkout\Tests\Unit;

use Atwx\Checkout\Payment\GatewayRegistry;
use Atwx\Checkout\Payment\Mock\MockGateway;
use Atwx\Checkout\Tests\Fixture\SpyGateway;
use InvalidArgumentException;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;

class GatewayRegistryTest extends SapphireTest
{
    protected $usesDatabase = false;

    protected function setUp(): void
    {
        parent::setUp();
        Config::modify()->set(GatewayRegistry::class, 'default', 'mock');
        Config::modify()->set(GatewayRegistry::class, 'gateways', [
            'mock' => ['class' => MockGateway::class, 'result' => 'paid'],
            'spy' => ['class' => SpyGateway::class, 'api_key' => '`CHECKOUT_TEST_KEY`'],
        ]);
    }

    public function testGetReturnsConfiguredGateway(): void
    {
        $this->assertInstanceOf(MockGateway::class, GatewayRegistry::get('mock'));
    }

    public function testGetDefaultUsesConfiguredDefault(): void
    {
        $this->assertSame('mock', GatewayRegistry::getDefault()->getCode());
    }

    public function testUnknownGatewayThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GatewayRegistry::get('does-not-exist');
    }

    public function testGetAvailableListsAllConfigured(): void
    {
        $this->assertSame(['mock', 'spy'], array_keys(GatewayRegistry::getAvailable()));
    }

    /**
     * Regression guard: Config::get() does not resolve the backtick env syntax,
     * so GatewayRegistry must resolve `ENV_VAR` references itself.
     */
    public function testBacktickEnvVarIsResolved(): void
    {
        Environment::setEnv('CHECKOUT_TEST_KEY', 'test_resolved_value_123');
        $gateway = GatewayRegistry::get('spy');
        $this->assertInstanceOf(SpyGateway::class, $gateway);
        $this->assertSame('test_resolved_value_123', $gateway->config['api_key']);
    }
}
