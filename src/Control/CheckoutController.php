<?php

namespace Atwx\Checkout\Control;

use Atwx\Checkout\Model\Order;
use Atwx\Checkout\Payment\GatewayRegistry;
use Atwx\Checkout\Payment\PaymentStatus;
use Atwx\Checkout\Service\PaymentService;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Customer-facing checkout endpoints, keyed by the order's AccessToken
 * (never by the sequential ID, so orders cannot be enumerated):
 *
 *   GET /checkout/return/$Token    return from the gateway; reconciles + routes
 *   GET /checkout/thanks/$Token    success page (also shown while payment is pending)
 *   GET /checkout/cancelled/$Token cancellation page
 */
class CheckoutController extends Controller
{
    private static array $allowed_actions = [
        'handleReturn',
        'thanks',
        'cancelled',
    ];

    private static array $url_handlers = [
        'return/$Token!' => 'handleReturn',
        'thanks/$Token!' => 'thanks',
        'cancelled/$Token!' => 'cancelled',
    ];

    /**
     * The customer has returned from the gateway. Reconcile the order's most
     * recent payment, then route to the thanks or cancelled page. The webhook is
     * the authoritative signal — this is purely for UX.
     *
     * A payment that is still pending (e.g. bank transfer, slow provider)
     * is routed to the success target, which is expected to show a "being
     * confirmed" state; open (abandoned), failed and cancelled payments go to
     * the cancel target.
     */
    public function handleReturn(HTTPRequest $request): HTTPResponse
    {
        $order = $this->getOrder($request);
        if (!$order) {
            return $this->httpError(404);
        }

        $payment = $order->Payments()->sort('Created DESC')->first();
        if ($payment && $payment->GatewayCode) {
            $gateway = GatewayRegistry::get($payment->GatewayCode);
            PaymentService::singleton()->reconcile($payment, $gateway);
            $order = Order::get()->byID($order->ID) ?: $order;
            $payment = $order->Payments()->sort('Created DESC')->first();
        }

        $status = $payment ? PaymentStatus::tryFrom((string) $payment->Status) : null;
        // Pending (being processed) is shown as "being confirmed" on the success
        // target; open (not paid, can be resumed) goes to the cancel target.
        if ($order->isCompleted() || ($status && !$status->isFinal() && !$status->isOpen())) {
            return $this->redirect($order->SuccessUrl ?: $this->Link('thanks/' . $order->AccessToken));
        }
        return $this->redirect($order->CancelUrl ?: $this->Link('cancelled/' . $order->AccessToken));
    }

    public function thanks(HTTPRequest $request): HTTPResponse
    {
        return $this->renderOrder($request, 'thanks');
    }

    public function cancelled(HTTPRequest $request): HTTPResponse
    {
        return $this->renderOrder($request, 'cancelled');
    }

    public function Link($action = null)
    {
        return Controller::join_links('checkout', $action);
    }

    private function renderOrder(HTTPRequest $request, string $template): HTTPResponse
    {
        $order = $this->getOrder($request);
        if (!$order) {
            return $this->httpError(404);
        }
        $html = $this->customise(['Order' => $order])
            ->renderWith(['Atwx/Checkout/Control/CheckoutController_' . $template]);
        return HTTPResponse::create($html);
    }

    private function getOrder(HTTPRequest $request): ?Order
    {
        $token = (string) $request->param('Token');
        if (!preg_match('/^[a-f0-9]{32,64}$/', $token)) {
            return null;
        }
        return Order::get()->filter('AccessToken', $token)->first();
    }
}
