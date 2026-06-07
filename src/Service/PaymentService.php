<?php

namespace Atwx\Checkout\Service;

use Atwx\Checkout\Model\Order;
use Atwx\Checkout\Model\Payment;
use Atwx\Checkout\Payment\GatewayResult;
use Atwx\Checkout\Payment\PaymentGateway;
use Atwx\Checkout\Payment\PaymentStatus;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\FieldType\DBDatetime;

/**
 * Creates payments for orders and reconciles their status with the gateway.
 */
class PaymentService
{
    use Injectable;

    /**
     * Create a fresh pending Payment for an order and start it with the gateway.
     * The webhook URL is derived from the gateway code; the return URL is supplied
     * by the caller (it is app-specific).
     */
    public function begin(Order $order, PaymentGateway $gateway, string $returnUrl): GatewayResult
    {
        $payment = $this->createPayment($order, $gateway);
        $webhookUrl = Director::absoluteURL('checkout/webhook/' . $gateway->getCode());
        return $gateway->initiate($payment, $returnUrl, $webhookUrl);
    }

    public function createPayment(Order $order, PaymentGateway $gateway): Payment
    {
        $payment = Payment::create();
        $payment->OrderID = $order->ID;
        $payment->GatewayCode = $gateway->getCode();
        $payment->Amount = (float) $order->TotalAmount;
        $payment->Currency = $order->Currency ?: 'EUR';
        $payment->Status = PaymentStatus::Pending->value;
        $payment->InitiatedAt = DBDatetime::now()->Rfc2822();
        $payment->write();
        return $payment;
    }

    /**
     * Fetch the current status from the gateway, persist it, and propagate to the
     * order. Idempotent: onOrderCompleted fires exactly once even across webhook
     * retries, because the status transition guards prevent re-firing.
     */
    public function reconcile(Payment $payment, PaymentGateway $gateway): void
    {
        $newStatus = $gateway->fetchStatus($payment);
        $oldStatus = $payment->Status;

        if ($oldStatus === $newStatus->value) {
            // Already in this state — persist any refreshed RawResponse and stop.
            if ($payment->isChanged('RawResponse')) {
                $payment->write();
            }
            return;
        }

        $newStatusValue = $newStatus->value;
        $payment->Status = $newStatusValue;
        if ($newStatus->isFinal()) {
            $payment->CompletedAt = DBDatetime::now()->Rfc2822();
        }
        $payment->write();

        // extend() passes arguments by reference, so hand it variables (not the
        // readonly enum property) to avoid "cannot indirectly modify" errors.
        $payment->extend('onPaymentStatusChange', $oldStatus, $newStatusValue);

        switch ($newStatus) {
            case PaymentStatus::Paid:
                $payment->extend('onPaymentSucceeded');
                $payment->Order()->markAsCompleted();
                break;
            case PaymentStatus::Failed:
            case PaymentStatus::Cancelled:
                // A single failed/cancelled payment does not cancel the order —
                // retries are allowed (the order may receive another Payment).
                $payment->extend('onPaymentFailed');
                break;
            case PaymentStatus::Refunded:
                $payment->extend('onPaymentRefunded');
                $payment->Order()->markAsRefunded();
                break;
            default:
                break;
        }
    }
}
