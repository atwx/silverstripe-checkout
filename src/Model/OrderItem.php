<?php

namespace Atwx\Checkout\Model;

use SilverStripe\ORM\DataObject;

/**
 * Frozen snapshot of a purchasable at the time of purchase. The Purchasable
 * relation is polymorphic and may point at any app DataObject (or be cleared
 * later) — Title and Price are snapshotted so orders stay historically correct.
 *
 * @property int    $Quantity
 * @property float  $PriceSnapshot
 * @property string $TitleSnapshot
 */
class OrderItem extends DataObject
{
    private static string $table_name = 'Atwx_Checkout_OrderItem';

    private static array $db = [
        'Quantity' => 'Int',
        'PriceSnapshot' => 'Currency',
        'TitleSnapshot' => 'Varchar(255)',
    ];

    private static array $has_one = [
        'Order' => Order::class,
        'Purchasable' => DataObject::class, // polymorphic
    ];

    private static array $defaults = [
        'Quantity' => 1,
    ];

    private static array $summary_fields = [
        'TitleSnapshot' => 'Item',
        'Quantity' => 'Qty',
        'PriceSnapshot' => 'Price',
    ];

    public function getTitle(): string
    {
        return $this->TitleSnapshot;
    }

    /** Line total = quantity × unit price. */
    public function getSubtotal(): float
    {
        return (float) $this->PriceSnapshot * (int) $this->Quantity;
    }
}
