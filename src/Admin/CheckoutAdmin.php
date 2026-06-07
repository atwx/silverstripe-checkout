<?php

namespace Atwx\Checkout\Admin;

use Atwx\Checkout\Model\Order;
use Atwx\Checkout\Model\Payment;
use SilverStripe\Admin\ModelAdmin;

/**
 * CMS section listing orders and payments.
 */
class CheckoutAdmin extends ModelAdmin
{
    private static array $managed_models = [
        Order::class,
        Payment::class,
    ];

    private static string $url_segment = 'checkout';

    private static string $menu_title = 'Bestellungen';

    private static string $menu_icon_class = 'font-icon-list';
}
