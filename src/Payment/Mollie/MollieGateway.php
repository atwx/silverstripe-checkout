<?php

namespace Atwx\Checkout\Payment\Mollie;

use Atwx\Checkout\Model\Payment;
use Atwx\Checkout\Payment\GatewayResult;
use Atwx\Checkout\Payment\PaymentGateway;
use Atwx\Checkout\Payment\PaymentStatus;
use Mollie\Api\MollieApiClient;
use RuntimeException;
use SilverStripe\Control\HTTPRequest;

/**
 * Default gateway, backed by mollie/mollie-api-php.
 * Webhook URL: /checkout/webhook/mollie
 */
class MollieGateway implements PaymentGateway
{
    private string $apiKey;
    private ?MollieApiClient $client = null;

    public function __construct(array $config = [])
    {
        $this->apiKey = (string) ($config['api_key'] ?? '');
    }

    public function getCode(): string
    {
        return 'mollie';
    }

    public function getLabel(): string
    {
        return _t(self::class . '.LABEL', 'Credit card, PayPal, SOFORT … (Mollie)');
    }

    protected function client(): MollieApiClient
    {
        if (!$this->apiKey) {
            throw new RuntimeException('MollieGateway: no API key configured (MOLLIE_API_KEY).');
        }
        if (!$this->client) {
            $this->client = new MollieApiClient();
            $this->client->setApiKey($this->apiKey);
        }
        return $this->client;
    }

    public function initiate(Payment $payment, string $returnUrl, string $webhookUrl): GatewayResult
    {
        $molliePayment = $this->client()->payments->create([
            'amount' => [
                'currency' => $payment->Currency ?: 'EUR',
                'value' => number_format((float) $payment->Amount, 2, '.', ''),
            ],
            'description' => $payment->getPaymentDescription(),
            'redirectUrl' => $returnUrl,
            'webhookUrl' => $webhookUrl,
            'metadata' => [
                'payment_id' => $payment->ID,
                'order_id' => $payment->OrderID,
            ],
        ]);

        $payment->GatewayPaymentID = $molliePayment->id;
        $payment->Status = PaymentStatus::Pending->value;
        $payment->write();

        return GatewayResult::redirect($molliePayment->getCheckoutUrl());
    }

    public function fetchStatus(Payment $payment): PaymentStatus
    {
        if (!$payment->GatewayPaymentID) {
            return PaymentStatus::Pending;
        }
        $molliePayment = $this->client()->payments->get($payment->GatewayPaymentID);

        // Keep a raw copy for auditing.
        $payment->RawResponse = json_encode($molliePayment);

        return $this->mapStatus((string) $molliePayment->status);
    }

    public function handleWebhook(HTTPRequest $request): ?Payment
    {
        $id = $request->postVar('id') ?: $request->getVar('id');
        if (!$id) {
            return null;
        }
        return Payment::get()->filter(['GatewayPaymentID' => $id])->first();
    }

    private function mapStatus(string $mollieStatus): PaymentStatus
    {
        return match ($mollieStatus) {
            'paid' => PaymentStatus::Paid,
            'authorized' => PaymentStatus::Authorized,
            'failed' => PaymentStatus::Failed,
            'canceled' => PaymentStatus::Cancelled,
            'expired' => PaymentStatus::Cancelled,
            'refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Pending, // open, pending
        };
    }
}
