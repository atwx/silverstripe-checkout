<?php

namespace Atwx\Checkout\Payment\Mock;

use Atwx\Checkout\Model\Payment;
use Atwx\Checkout\Payment\GatewayResult;
use Atwx\Checkout\Payment\PaymentGateway;
use Atwx\Checkout\Payment\PaymentStatus;
use SilverStripe\Control\HTTPRequest;

/**
 * A gateway with no provider. Configurable to treat every payment as paid or failed.
 * Intended for local development and automated tests.
 *
 *   gateways:
 *     mock:
 *       class: Atwx\Checkout\Payment\Mock\MockGateway
 *       result: 'paid'   # or 'failed'
 */
class MockGateway implements PaymentGateway
{
    private PaymentStatus $result;

    public function __construct(array $config = [])
    {
        $this->result = PaymentStatus::tryFrom($config['result'] ?? 'paid') ?? PaymentStatus::Paid;
    }

    public function getCode(): string
    {
        return 'mock';
    }

    public function getLabel(): string
    {
        return _t(self::class . '.LABEL', 'Test payment (mock)');
    }

    public function initiate(Payment $payment, string $returnUrl, string $webhookUrl): GatewayResult
    {
        $payment->GatewayPaymentID = 'mock_' . uniqid();
        $payment->Status = PaymentStatus::Pending->value;
        $payment->write();

        // Skip the provider entirely and send the customer straight back, so the
        // return/reconcile flow can be exercised without any external service.
        return GatewayResult::redirect($returnUrl);
    }

    public function fetchStatus(Payment $payment): PaymentStatus
    {
        return $this->result;
    }

    public function handleWebhook(HTTPRequest $request): ?Payment
    {
        $id = $request->postVar('id') ?: $request->getVar('id');
        if (!$id) {
            return null;
        }
        return Payment::get()->filter(['GatewayPaymentID' => $id])->first();
    }
}
