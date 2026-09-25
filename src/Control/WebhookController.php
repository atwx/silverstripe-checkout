<?php

namespace Atwx\Checkout\Control;

use Atwx\Checkout\Payment\GatewayRegistry;
use Atwx\Checkout\Service\PaymentService;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use Throwable;

/**
 * Server-to-server payment status callbacks:
 *
 *   POST /checkout/webhook/$Gateway
 *
 * Dispatches to the matching gateway and reconciles the resolved payment.
 * Answers 200 when handled (or the payment is unknown, so the provider stops
 * retrying), 404 for an unknown gateway and 500 when reconciling failed
 * (e.g. provider API unreachable), so the provider retries later.
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

        try {
            $gateway = GatewayRegistry::get($code);
        } catch (InvalidArgumentException $e) {
            return HTTPResponse::create('', 404);
        }

        try {
            $payment = $gateway->handleWebhook($request);
            if ($payment) {
                PaymentService::singleton()->reconcile($payment, $gateway);
            }
        } catch (Throwable $e) {
            Injector::inst()->get(LoggerInterface::class)->error(
                'Checkout webhook failed: ' . $e->getMessage(),
                ['gateway' => $code, 'exception' => $e]
            );
            return HTTPResponse::create('', 500);
        }

        return HTTPResponse::create('', 200);
    }
}
