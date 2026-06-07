<?php

namespace Atwx\Checkout\Control;

use Atwx\Checkout\Model\Order;
use Atwx\Checkout\Payment\GatewayRegistry;
use Atwx\Checkout\Service\PaymentService;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Customer-facing checkout endpoints:
 *
 *   GET /checkout/return/$OrderID    return from the gateway; reconciles + routes
 *   GET /checkout/thanks/$OrderID    success page
 *   GET /checkout/cancelled/$OrderID cancellation page
 */
class CheckoutController extends Controller
{
    private static array $allowed_actions = [
        'handleReturn',
        'thanks',
        'cancelled',
    ];

    private static array $url_handlers = [
        'return/$OrderID' => 'handleReturn',
    ];

    /**
     * The customer has returned from the gateway. Reconcile the order's most
     * recent payment, then route to the thanks or cancelled page. The webhook is
     * the authoritative signal — this is purely for UX.
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
        }

        // Prefer an app-supplied return target so the app can render its own
        // (themed) confirmation page; fall back to the module's bare templates.
        if ($order->isCompleted()) {
            return $this->redirect($order->SuccessUrl ?: $this->Link('thanks/' . $order->ID));
        }
        return $this->redirect($order->CancelUrl ?: $this->Link('cancelled/' . $order->ID));
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
        $id = (int) $request->param('OrderID');
        if (!$id) {
            $id = (int) $request->param('ID');
        }
        return $id ? Order::get()->byID($id) : null;
    }
}
