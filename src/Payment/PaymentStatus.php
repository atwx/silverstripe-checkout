<?php

namespace Atwx\Checkout\Payment;

/**
 * Normalised payment status, independent of any specific gateway.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    // Created but not yet paid; the customer can still complete it (e.g. returned
    // from the provider's checkout without paying).
    case Open = 'open';
    case Authorized = 'authorized';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * Whether this status represents a successfully completed payment.
     */
    public function isPaid(): bool
    {
        return $this === self::Paid;
    }

    /**
     * Whether this status is final (no further changes expected).
     */
    /**
     * Whether the customer still has to act (payment not started or abandoned).
     */
    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Failed, self::Cancelled, self::Refunded], true);
    }
}
