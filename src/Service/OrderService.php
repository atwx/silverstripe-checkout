<?php

namespace Atwx\Checkout\Service;

use Atwx\Checkout\Model\Order;
use Atwx\Checkout\Model\OrderItem;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;

/**
 * Creates orders from purchasables and assigns human-readable order numbers.
 */
class OrderService
{
    use Injectable;
    use Configurable;

    private static string $order_number_format = 'ORD-{year}-{seq:05}';
    private static string $currency = 'EUR';

    /**
     * Create an order directly from a set of purchasables (each carrying
     * PurchasableExtension), without going through a Cart.
     *
     * @param array<DataObject>     $purchasables
     * @param array<string, mixed>  $customerData  Email, Name (+ optional MemberID)
     * @param array<int, int>       $quantities    optional, keyed by purchasable index
     */
    public function createDirect(array $purchasables, array $customerData, array $quantities = []): Order
    {
        $order = Order::create();
        $order->CustomerEmail = $customerData['Email'] ?? '';
        $order->CustomerName = $customerData['Name'] ?? '';
        if (!empty($customerData['MemberID'])) {
            $order->MemberID = (int) $customerData['MemberID'];
        }
        $order->Currency = self::config()->get('currency');
        $order->Status = 'pending';
        $order->PlacedAt = DBDatetime::now()->Rfc2822();
        $order->OrderNumber = $this->generateOrderNumber();
        $order->write();

        $total = 0.0;
        foreach (array_values($purchasables) as $i => $purchasable) {
            $qty = max(1, (int) ($quantities[$i] ?? 1));
            $item = OrderItem::create();
            $item->OrderID = $order->ID;
            $item->Quantity = $qty;
            $item->PriceSnapshot = (float) $purchasable->Price;
            $item->TitleSnapshot = $purchasable->hasMethod('getPurchasableTitle')
                ? $purchasable->getPurchasableTitle()
                : (string) $purchasable->getTitle();
            $item->PurchasableID = $purchasable->ID;
            $item->PurchasableClass = $purchasable->ClassName;
            $item->write();
            $total += $item->getSubtotal();
        }

        $order->TotalAmount = $total;
        $order->write();

        return $order;
    }

    public function generateOrderNumber(): string
    {
        $year = (int) DBDatetime::now()->Year();
        $seq = Order::get()
            ->filter(['Created:GreaterThanOrEqual' => $year . '-01-01 00:00:00'])
            ->count() + 1;

        $format = self::config()->get('order_number_format');
        return preg_replace_callback('/\{(\w+)(?::(\d+))?\}/', function ($m) use ($year, $seq) {
            return match ($m[1]) {
                'year' => (string) $year,
                'seq' => isset($m[2]) ? str_pad((string) $seq, (int) $m[2], '0', STR_PAD_LEFT) : (string) $seq,
                default => $m[0],
            };
        }, $format);
    }
}
