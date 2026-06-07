<?php

namespace Atwx\Checkout\Payment;

/**
 * Result of initiating a payment with a gateway.
 *
 * - `redirect`: the customer must be sent to {@see $redirectUrl} (e.g. Mollie hosted checkout).
 * - `completed`: the payment is already settled (e.g. mock/free), no redirect needed.
 */
class GatewayResult
{
    public const TYPE_REDIRECT = 'redirect';
    public const TYPE_COMPLETED = 'completed';

    public function __construct(
        public string $type,
        public ?string $redirectUrl = null
    ) {
    }

    public static function redirect(string $url): self
    {
        return new self(self::TYPE_REDIRECT, $url);
    }

    public static function completed(): self
    {
        return new self(self::TYPE_COMPLETED);
    }

    public function isRedirect(): bool
    {
        return $this->type === self::TYPE_REDIRECT;
    }
}
