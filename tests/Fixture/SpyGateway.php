<?php

namespace Atwx\Checkout\Tests\Fixture;

use Atwx\Checkout\Model\Payment;
use Atwx\Checkout\Payment\GatewayResult;
use Atwx\Checkout\Payment\PaymentGateway;
use Atwx\Checkout\Payment\PaymentStatus;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\TestOnly;

/**
 * A gateway that simply records the config it was constructed with, so tests can
 * assert how GatewayRegistry resolves config values (e.g. backtick env vars).
 */
class SpyGateway implements PaymentGateway, TestOnly
{
    public array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function getCode(): string
    {
        return 'spy';
    }

    public function getLabel(): string
    {
        return 'Spy';
    }

    public function initiate(Payment $payment, string $returnUrl, string $webhookUrl): GatewayResult
    {
        return GatewayResult::redirect($returnUrl);
    }

    public function fetchStatus(Payment $payment): PaymentStatus
    {
        return PaymentStatus::Pending;
    }

    public function handleWebhook(HTTPRequest $request): ?Payment
    {
        return null;
    }
}
