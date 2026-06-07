<?php

namespace Atwx\Checkout\Tests\Functional;

use Atwx\Checkout\Model\Order;
use Atwx\Checkout\Model\Payment;
use Atwx\Checkout\Payment\GatewayRegistry;
use Atwx\Checkout\Payment\Mock\MockGateway;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;

class WebhookControllerTest extends FunctionalTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::modify()->set(GatewayRegistry::class, 'gateways', [
            'mock' => ['class' => MockGateway::class, 'result' => 'paid'],
        ]);
    }

    public function testWebhookResolvesPaymentReconcilesAndCompletesOrder(): void
    {
        $order = Order::create(['Status' => 'pending', 'TotalAmount' => 10, 'Currency' => 'EUR']);
        $order->write();
        $payment = Payment::create([
            'OrderID' => $order->ID,
            'GatewayCode' => 'mock',
            'GatewayPaymentID' => 'pay_123',
            'Status' => 'pending',
            'Amount' => 10,
        ]);
        $payment->write();

        $response = $this->post('checkout/webhook/mock', ['id' => 'pay_123']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('paid', Payment::get()->byID($payment->ID)->Status);
        $this->assertSame('completed', Order::get()->byID($order->ID)->Status);
    }

    public function testWebhookWithoutIdStillReturns200(): void
    {
        $response = $this->post('checkout/webhook/mock', []);
        $this->assertSame(200, $response->getStatusCode());
    }
}
