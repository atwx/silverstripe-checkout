<?php

namespace Atwx\Checkout\Tests\Unit;

use Atwx\Checkout\Model\Order;
use Atwx\Checkout\Tests\Fixture\OrderHookSpyExtension;
use SilverStripe\Dev\SapphireTest;

class OrderTest extends SapphireTest
{
    protected $usesDatabase = true;

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

    public function testCompletingStaleCopiesFiresHookOnce(): void
    {
        $order = Order::create(['Status' => 'pending']);
        $order->write();
        // Two requests (webhook + customer return) each hold their own copy.
        $first = Order::get()->byID($order->ID);
        $second = Order::get()->byID($order->ID);

        $first->markAsCompleted();
        $second->markAsCompleted();

        $this->assertSame('completed', Order::get()->byID($order->ID)->Status);
        $this->assertSame(1, OrderHookSpyExtension::$completedCount);
    }

    public function testAccessTokenIsGeneratedAndUnique(): void
    {
        $a = Order::create();
        $a->write();
        $b = Order::create();
        $b->write();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $a->AccessToken);
        $this->assertNotSame($a->AccessToken, $b->AccessToken);
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
