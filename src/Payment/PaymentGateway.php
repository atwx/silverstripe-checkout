<?php

namespace Atwx\Checkout\Payment;

use Atwx\Checkout\Model\Payment;
use SilverStripe\Control\HTTPRequest;

/**
 * Contract every payment gateway must fulfil. The checkout module knows nothing
 * about concrete providers — only this interface.
 */
interface PaymentGateway
{
    /** Stable code, e.g. 'mollie', 'mock'. Stored on Payment.GatewayCode. */
    public function getCode(): string;

    /** Human-readable, translatable label for UI. */
    public function getLabel(): string;

    /**
     * Create the provider-side payment and return how to proceed.
     * Implementations should persist the provider payment id onto $payment.
     *
     * @param string $returnUrl  Where the customer is sent back to after paying.
     * @param string $webhookUrl Server-to-server status callback URL.
     */
    public function initiate(Payment $payment, string $returnUrl, string $webhookUrl): GatewayResult;

    /** Fetch the current, normalised status from the provider. */
    public function fetchStatus(Payment $payment): PaymentStatus;

    /**
     * Resolve the local Payment a webhook refers to (does not change state itself).
     * Returns null if the payment cannot be resolved.
     */
    public function handleWebhook(HTTPRequest $request): ?Payment;
}
