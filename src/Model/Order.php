<?php

namespace Atwx\Checkout\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Member;

/**
 * What was bought, by whom, at what price, in which state.
 * An Order has many Payments because retries are first-class.
 *
 * @property string $OrderNumber
 * @property string $CustomerEmail
 * @property string $CustomerName
 * @property string $Status
 * @property float  $TotalAmount
 * @property string $Currency
 */
class Order extends DataObject
{
    private static string $table_name = 'Atwx_Checkout_Order';

    private static array $db = [
        'OrderNumber' => 'Varchar(32)',
        'CustomerEmail' => 'Varchar(255)',
        'CustomerName' => 'Varchar(255)',
        'Status' => "Enum('pending,completed,cancelled,refunded','pending')",
        'TotalAmount' => 'Currency',
        'Currency' => 'Varchar(3)',
        'PlacedAt' => 'Datetime',
        'CompletedAt' => 'Datetime',
        // Optional app-supplied return targets; the checkout return handler
        // redirects here instead of the module's own thanks/cancelled templates.
        'SuccessUrl' => 'Varchar(255)',
        'CancelUrl' => 'Varchar(255)',
    ];

    private static array $has_one = [
        'Member' => Member::class,
    ];

    private static array $has_many = [
        'Items' => OrderItem::class,
        'Payments' => Payment::class,
    ];

    private static string $default_sort = 'Created DESC';

    private static array $summary_fields = [
        'OrderNumber' => 'Order',
        'Created' => 'Date',
        'CustomerName' => 'Name',
        'CustomerEmail' => 'E-Mail',
        'Status' => 'Status',
        'TotalAmount' => 'Total',
    ];

    private static array $searchable_fields = [
        'OrderNumber',
        'CustomerEmail',
        'Status',
    ];

    public function getTitle(): string
    {
        return $this->OrderNumber ?: ('Order #' . $this->ID);
    }

    public function TotalFormatted(): string
    {
        return $this->dbObject('TotalAmount')->Nice();
    }

    public function isCompleted(): bool
    {
        return $this->Status === 'completed';
    }

    /**
     * Mark this order completed. Idempotent: the onOrderCompleted hook fires
     * exactly once, on the transition into 'completed'.
     */
    public function markAsCompleted(): void
    {
        if ($this->Status === 'completed') {
            return;
        }
        $this->Status = 'completed';
        $this->CompletedAt = DBDatetime::now()->Rfc2822();
        $this->write();
        $this->extend('onOrderCompleted');
    }

    public function markAsCancelled(): void
    {
        if (in_array($this->Status, ['cancelled', 'completed', 'refunded'], true)) {
            return;
        }
        $this->Status = 'cancelled';
        $this->write();
        $this->extend('onOrderCancelled');
    }

    public function markAsRefunded(): void
    {
        if ($this->Status === 'refunded') {
            return;
        }
        $this->Status = 'refunded';
        $this->write();
        $this->extend('onOrderRefunded');
    }
}
