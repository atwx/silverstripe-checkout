<?php

namespace Atwx\Checkout\Tests\Functional;

use Atwx\Checkout\Model\Order;
use Atwx\Checkout\Model\Payment;
use Atwx\Checkout\Payment\GatewayRegistry;
use Atwx\Checkout\Payment\Mock\MockGateway;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;

class CheckoutControllerTest extends FunctionalTest
{
    // Assert on the redirect itself; do not follow it to the (non-existent) targets.
    protected $autoFollowRedirection = false;

    protected function setUp(): void
    {
        parent::setUp();
        Config::modify()->set(GatewayRegistry::class, 'gateways', [
            'mock' => ['class' => MockGateway::class, 'result' => 'paid'],
            'mockfail' => ['class' => MockGateway::class, 'result' => 'failed'],
        ]);
    }

    private function orderWithPayment(string $gatewayCode, string $success, string $cancel): Order
    {
        $order = Order::create([
            'Status' => 'pending',
            'TotalAmount' => 10,
            'Currency' => 'EUR',
            'SuccessUrl' => $success,
            'CancelUrl' => $cancel,
        ]);
        $order->write();
        Payment::create([
            'OrderID' => $order->ID,
            'GatewayCode' => $gatewayCode,
            'Status' => 'pending',
            'Amount' => 10,
        ])->write();
        return $order;
    }

    public function testReturnReconcilesAndRedirectsToSuccessUrlWhenPaid(): void
    {
        $order = $this->orderWithPayment('mock', '/danke-seite', '/abbruch');

        $response = $this->get('checkout/return/' . $order->ID);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/danke-seite', (string) $response->getHeader('Location'));
        $this->assertSame('completed', Order::get()->byID($order->ID)->Status);
    }

    public function testReturnRedirectsToCancelUrlWhenNotPaid(): void
    {
        $order = $this->orderWithPayment('mockfail', '/danke-seite', '/abbruch');

        $response = $this->get('checkout/return/' . $order->ID);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/abbruch', (string) $response->getHeader('Location'));
        $this->assertSame('pending', Order::get()->byID($order->ID)->Status);
    }

    public function testReturnForUnknownOrderIsNotFound(): void
    {
        $response = $this->get('checkout/return/999999');
        $this->assertSame(404, $response->getStatusCode());
    }
}
