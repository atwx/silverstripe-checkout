<?php

namespace Atwx\Checkout\Payment;

use InvalidArgumentException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;

/**
 * Central registry of configured payment gateways. Configured via YAML:
 *
 *   Atwx\Checkout\Payment\GatewayRegistry:
 *     default: 'mollie'
 *     gateways:
 *       mollie:
 *         class: Atwx\Checkout\Payment\Mollie\MollieGateway
 *         api_key: '`MOLLIE_API_KEY`'
 */
class GatewayRegistry
{
    use Configurable;

    private static string $default = 'mock';

    /**
     * Map of gateway code => config array. Each entry needs a `class`; remaining
     * keys are passed to the gateway constructor as its config.
     */
    private static array $gateways = [];

    public static function get(string $code): PaymentGateway
    {
        $gateways = self::config()->get('gateways');
        if (empty($gateways[$code]) || empty($gateways[$code]['class'])) {
            throw new InvalidArgumentException("Unknown or misconfigured payment gateway: {$code}");
        }

        $config = $gateways[$code];
        $class = $config['class'];
        unset($config['class']);

        // Config::get() does not resolve the backtick env-var syntax (only the
        // Injector does), so resolve `ENV_VAR` references here.
        foreach ($config as $key => $value) {
            $config[$key] = self::resolveEnv($value);
        }

        return Injector::inst()->createWithArgs($class, [$config]);
    }

    /**
     * Resolve `ENV_VAR` backtick references in a config value to their env value,
     * mirroring the Injector's behaviour.
     */
    private static function resolveEnv(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^`.+`$/', $value)) {
            return preg_replace_callback('/`(?<name>[^`]+)`/', static function ($m) {
                $env = Environment::getEnv($m['name']);
                return $env === false ? '' : (string) $env;
            }, $value);
        }
        return $value;
    }

    public static function getDefault(): PaymentGateway
    {
        return self::get(self::config()->get('default'));
    }

    /**
     * @return array<string, PaymentGateway> code => gateway
     */
    public static function getAvailable(): array
    {
        $result = [];
        foreach (array_keys((array) self::config()->get('gateways')) as $code) {
            $result[$code] = self::get($code);
        }
        return $result;
    }
}
