<?php

namespace Atwx\Checkout\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * The seam between the module and an app's "products". Attach to any app
 * DataObject to make it purchasable — the module never defines a Product class.
 *
 *   App\VideoProduct:
 *     extensions:
 *       - Atwx\Checkout\Extension\PurchasableExtension
 *
 * @property float $Price
 * @property bool  $IsActive
 * @extends Extension<DataObject&static>
 */
class PurchasableExtension extends Extension
{
    private static array $db = [
        'Price' => 'Currency',
        'IsActive' => 'Boolean(1)',
    ];

    /** Localised, currency-formatted price. */
    public function PriceFormatted(): string
    {
        return $this->getOwner()->dbObject('Price')->Nice();
    }

    /**
     * Whether the current member may purchase this item. Defaults to IsActive;
     * apps can veto via the updateCanPurchase extension hook:
     *
     *   public function updateCanPurchase(bool &$can, ?Member $member): void
     *
     * (The hook is named updateCanPurchase, not canPurchase, because PHP method
     * names are case-insensitive and canPurchase would collide with this method.)
     */
    public function CanPurchase(?Member $member = null): bool
    {
        $can = (bool) $this->getOwner()->IsActive;
        $this->getOwner()->extend('updateCanPurchase', $can, $member);
        return $can;
    }

    /** Title used for order line item snapshots. */
    public function getPurchasableTitle(): string
    {
        return (string) $this->getOwner()->getTitle();
    }
}
