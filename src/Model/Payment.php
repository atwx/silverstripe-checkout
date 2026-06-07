<?php

namespace Atwx\Checkout\Model;

use SilverStripe\ORM\DataObject;

/**
 * A single payment transaction. An Order can have several Payments
 * (failed attempts plus the successful one).
 *
 * @property string $GatewayCode
 * @property string $GatewayPaymentID
 * @property string $Status
 * @property float  $Amount
 * @property string $Currency
 * @property string $RawResponse
 * @method Order Order()
 */
class Payment extends DataObject
{
    private static string $table_name = 'Atwx_Checkout_Payment';

    private static array $db = [
        'GatewayCode' => 'Varchar(32)',
        'GatewayPaymentID' => 'Varchar(128)',
        'Status' => "Enum('pending,authorized,paid,failed,cancelled,refunded','pending')",
        'Amount' => 'Currency',
        'Currency' => 'Varchar(3)',
        'RawResponse' => 'Text',
        'InitiatedAt' => 'Datetime',
        'CompletedAt' => 'Datetime',
    ];

    private static array $has_one = [
        'Order' => Order::class,
    ];

    private static string $default_sort = 'Created DESC';

    private static array $summary_fields = [
        'Created' => 'Date',
        'GatewayCode' => 'Gateway',
        'GatewayPaymentID' => 'Gateway ID',
        'Status' => 'Status',
        'Amount' => 'Amount',
    ];

    private static array $indexes = [
        'GatewayPaymentID' => true,
    ];

    /** Short human-readable description handed to the provider. */
    public function getPaymentDescription(): string
    {
        $order = $this->Order();
        if ($order && $order->exists()) {
            return $order->OrderNumber ?: ('Order #' . $order->ID);
        }
        return 'Payment #' . $this->ID;
    }
}
