<?php

namespace Atwx\Checkout\Tests\Fixture;

use Atwx\Checkout\Model\Order;
use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;

/**
 * Counts how often the order hooks fire, so tests can assert that
 * onOrderCompleted (etc.) is invoked exactly once.
 *
 * @extends Extension<Order>
 */
class OrderHookSpyExtension extends Extension implements TestOnly
{
    public static int $completedCount = 0;
    public static int $cancelledCount = 0;
    public static int $refundedCount = 0;

    public static function reset(): void
    {
        self::$completedCount = 0;
        self::$cancelledCount = 0;
        self::$refundedCount = 0;
    }

    public function onOrderCompleted(): void
    {
        self::$completedCount++;
    }

    public function onOrderCancelled(): void
    {
        self::$cancelledCount++;
    }

    public function onOrderRefunded(): void
    {
        self::$refundedCount++;
    }
}
