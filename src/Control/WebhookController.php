<?php

namespace Atwx\Checkout\Control;

use Atwx\Checkout\Payment\GatewayRegistry;
use Atwx\Checkout\Service\PaymentService;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Server-to-server payment status callbacks:
 *
 *   POST /checkout/webhook/$Gateway
 *
 * Dispatches to the matching gateway and reconciles the resolved payment.
 * Always answers 200 so the provider does not keep retrying on app-level issues.
 */
class WebhookController extends Controller
{
    private static array $allowed_actions = [
        'index',
    ];

    public function index(HTTPRequest $request): HTTPResponse
    {
        $code = $request->param('Gateway');
        if (!$code) {
            return HTTPResponse::create('', 400);
        }

        $gateway = GatewayRegistry::get($code);
        $payment = $gateway->handleWebhook($request);
        if ($payment) {
            PaymentService::singleton()->reconcile($payment, $gateway);
        }

        return HTTPResponse::create('', 200);
    }
}
