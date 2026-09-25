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
    protected $usesDatabase = true;

    // Assert on the redirect itself; do not follow it to the (non-existent) targets.
    protected $autoFollowRedirection = false;

    protected function setUp(): void
    {
        parent::setUp();
        Config::modify()->set(GatewayRegistry::class, 'gateways', [
            'mock' => ['class' => MockGateway::class, 'result' => 'paid'],
            'mockfail' => ['class' => MockGateway::class, 'result' => 'failed'],
            'mockpending' => ['class' => MockGateway::class, 'result' => 'pending'],
            'mockopen' => ['class' => MockGateway::class, 'result' => 'open'],
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

        $response = $this->get('checkout/return/' . $order->AccessToken);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/danke-seite', (string) $response->getHeader('Location'));
        $this->assertSame('completed', Order::get()->byID($order->ID)->Status);
    }

    public function testReturnRedirectsToCancelUrlWhenNotPaid(): void
    {
        $order = $this->orderWithPayment('mockfail', '/danke-seite', '/abbruch');

        $response = $this->get('checkout/return/' . $order->AccessToken);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/abbruch', (string) $response->getHeader('Location'));
        $this->assertSame('pending', Order::get()->byID($order->ID)->Status);
    }

    public function testReturnWithPendingPaymentGoesToSuccessUrl(): void
    {
        $order = $this->orderWithPayment('mockpending', '/danke-seite', '/abbruch');

        $response = $this->get('checkout/return/' . $order->AccessToken);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/danke-seite', (string) $response->getHeader('Location'));
        $this->assertSame('pending', Order::get()->byID($order->ID)->Status);
    }

    public function testReturnWithOpenPaymentGoesToCancelUrl(): void
    {
        $order = $this->orderWithPayment('mockopen', '/danke-seite', '/abbruch');

        $response = $this->get('checkout/return/' . $order->AccessToken);

        $this->assertStringContainsString('/abbruch', (string) $response->getHeader('Location'));
    }

    public function testReturnForUnknownOrderIsNotFound(): void
    {
        $response = $this->get('checkout/return/' . str_repeat('a', 40));
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testOrdersCannotBeAccessedBySequentialId(): void
    {
        $order = $this->orderWithPayment('mock', '', '');

        $this->assertNotEmpty($order->AccessToken);
        $this->assertSame(404, $this->get('checkout/thanks/' . $order->ID)->getStatusCode());
        $this->assertSame(404, $this->get('checkout/return/' . $order->ID)->getStatusCode());
        $this->assertSame(200, $this->get('checkout/thanks/' . $order->AccessToken)->getStatusCode());
    }
}
