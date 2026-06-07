<?php

namespace Atwx\Checkout\Tests\Unit;

use Atwx\Checkout\Model\Order;
use Atwx\Checkout\Tests\Fixture\OrderHookSpyExtension;
use SilverStripe\Dev\SapphireTest;

class OrderTest extends SapphireTest
{
    protected static $required_extensions = [
        Order::class => [OrderHookSpyExtension::class],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        OrderHookSpyExtension::reset();
    }

    public function testMarkAsCompletedTransitionsOnceAndFiresHookOnce(): void
    {
        $order = Order::create(['Status' => 'pending']);
        $order->write();

        $order->markAsCompleted();
        $order->markAsCompleted(); // repeated calls must be no-ops

        $this->assertSame('completed', $order->Status);
        $this->assertNotEmpty($order->CompletedAt);
        $this->assertSame(1, OrderHookSpyExtension::$completedCount);
    }

    public function testCancelDoesNotOverrideCompletedOrder(): void
    {
        $order = Order::create(['Status' => 'pending']);
        $order->write();
        $order->markAsCompleted();

        $order->markAsCancelled();

        $this->assertSame('completed', $order->Status);
        $this->assertSame(0, OrderHookSpyExtension::$cancelledCount);
    }

    public function testRefundFiresHookOnce(): void
    {
        $order = Order::create(['Status' => 'completed']);
        $order->write();

        $order->markAsRefunded();
        $order->markAsRefunded();

        $this->assertSame('refunded', $order->Status);
        $this->assertSame(1, OrderHookSpyExtension::$refundedCount);
    }
}
