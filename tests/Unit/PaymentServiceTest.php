<?php

namespace Atwx\Checkout\Tests\Unit;

use Atwx\Checkout\Model\Order;
use Atwx\Checkout\Model\Payment;
use Atwx\Checkout\Payment\Mock\MockGateway;
use Atwx\Checkout\Service\PaymentService;
use Atwx\Checkout\Tests\Fixture\OrderHookSpyExtension;
use SilverStripe\Dev\SapphireTest;

class PaymentServiceTest extends SapphireTest
{
    protected static $required_extensions = [
        Order::class => [OrderHookSpyExtension::class],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        OrderHookSpyExtension::reset();
    }

    /** @return array{0: Order, 1: Payment} */
    private function makeOrderWithPayment(): array
    {
        $order = Order::create(['Status' => 'pending', 'TotalAmount' => 100, 'Currency' => 'EUR']);
        $order->write();
        $payment = Payment::create([
            'OrderID' => $order->ID,
            'GatewayCode' => 'mock',
            'Status' => 'pending',
            'Amount' => 100,
        ]);
        $payment->write();
        return [$order, $payment];
    }

    public function testReconcilePaidCompletesOrderExactlyOnce(): void
    {
        [$order, $payment] = $this->makeOrderWithPayment();
        $gateway = new MockGateway(['result' => 'paid']);

        PaymentService::singleton()->reconcile($payment, $gateway);
        PaymentService::singleton()->reconcile($payment, $gateway); // webhook retry

        $this->assertSame('paid', Payment::get()->byID($payment->ID)->Status);
        $this->assertSame('completed', Order::get()->byID($order->ID)->Status);
        $this->assertSame(1, OrderHookSpyExtension::$completedCount);
    }

    public function testReconcileFailedKeepsOrderPendingForRetry(): void
    {
        [$order, $payment] = $this->makeOrderWithPayment();

        PaymentService::singleton()->reconcile($payment, new MockGateway(['result' => 'failed']));

        $this->assertSame('failed', Payment::get()->byID($payment->ID)->Status);
        $this->assertSame('pending', Order::get()->byID($order->ID)->Status);
        $this->assertSame(0, OrderHookSpyExtension::$completedCount);
    }

    public function testReconcileRefundedRefundsOrder(): void
    {
        [$order, $payment] = $this->makeOrderWithPayment();

        PaymentService::singleton()->reconcile($payment, new MockGateway(['result' => 'paid']));
        PaymentService::singleton()->reconcile($payment, new MockGateway(['result' => 'refunded']));

        $this->assertSame('refunded', Payment::get()->byID($payment->ID)->Status);
        $this->assertSame('refunded', Order::get()->byID($order->ID)->Status);
    }
}
