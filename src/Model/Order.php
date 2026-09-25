<?php

namespace Atwx\Checkout\Model;

use Atwx\Checkout\Service\OrderService;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
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
 * @property string $AccessToken
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
        // Unguessable key for customer-facing URLs (return, thanks, cancelled),
        // so orders cannot be enumerated via their sequential ID.
        'AccessToken' => 'Varchar(64)',
    ];

    private static array $indexes = [
        'AccessToken' => ['type' => 'unique', 'columns' => ['AccessToken']],
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

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->AccessToken) {
            $this->AccessToken = bin2hex(random_bytes(20));
        }
    }

    public function getTitle(): string
    {
        return $this->OrderNumber ?: ('Order #' . $this->ID);
    }

    public function TotalFormatted(): string
    {
        return OrderService::formatAmount((float) $this->TotalAmount, $this->Currency ?: null);
    }

    public function isCompleted(): bool
    {
        return $this->Status === 'completed';
    }

    /**
     * Mark this order completed. Idempotent: the onOrderCompleted hook fires
     * exactly once, on the transition into 'completed' — also when the webhook
     * and the customer's return reconcile concurrently. The transition is claimed
     * with a conditional UPDATE, so only one request wins.
     */
    public function markAsCompleted(): void
    {
        if ($this->Status === 'completed' || !$this->isInDB()) {
            return;
        }
        $now = DBDatetime::now()->Rfc2822();
        $table = DataObject::getSchema()->tableName(self::class);
        DB::prepared_query(
            "UPDATE \"{$table}\" SET \"Status\" = 'completed', \"CompletedAt\" = ?, \"LastEdited\" = ?"
            . " WHERE \"ID\" = ? AND \"Status\" <> 'completed'",
            [$now, $now, $this->ID]
        );
        $claimed = DB::affected_rows() > 0;

        // Keep the in-memory record in sync; a later write() stores the same values.
        $this->Status = 'completed';
        $this->CompletedAt = $now;

        if ($claimed) {
            $this->extend('onOrderCompleted');
        }
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
