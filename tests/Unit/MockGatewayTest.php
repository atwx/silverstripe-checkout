<?php

namespace Atwx\Checkout\Tests\Unit;

use Atwx\Checkout\Model\Order;
use Atwx\Checkout\Model\Payment;
use Atwx\Checkout\Payment\Mock\MockGateway;
use Atwx\Checkout\Payment\PaymentStatus;
use SilverStripe\Dev\SapphireTest;

class MockGatewayTest extends SapphireTest
{
    public function testInitiateRedirectsToReturnUrlAndStoresGatewayId(): void
    {
        $order = Order::create(['TotalAmount' => 10, 'Currency' => 'EUR']);
        $order->write();
        $payment = Payment::create(['OrderID' => $order->ID, 'Amount' => 10]);
        $payment->write();

        $result = (new MockGateway(['result' => 'paid']))
            ->initiate($payment, 'https://return.test', 'https://hook.test');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('https://return.test', $result->redirectUrl);
        $this->assertNotEmpty(Payment::get()->byID($payment->ID)->GatewayPaymentID);
    }

    public function testFetchStatusReflectsConfiguredResult(): void
    {
        $payment = Payment::create();
        $payment->write();

        $this->assertSame(PaymentStatus::Paid, (new MockGateway(['result' => 'paid']))->fetchStatus($payment));
        $this->assertSame(PaymentStatus::Failed, (new MockGateway(['result' => 'failed']))->fetchStatus($payment));
    }
}
