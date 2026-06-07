<?php

namespace Atwx\Checkout\Tests\Fixture;

use Atwx\Checkout\Extension\PurchasableExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * A minimal app-style purchasable used by the test suite. The PurchasableExtension
 * is applied via each test's $required_extensions, so only Title lives here.
 *
 * @mixin PurchasableExtension
 */
class TestPurchasable extends DataObject implements TestOnly
{
    private static $table_name = 'Atwx_Checkout_TestPurchasable';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];
}
