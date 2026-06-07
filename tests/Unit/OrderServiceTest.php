<?php

namespace Atwx\Checkout\Tests\Unit;

use Atwx\Checkout\Service\OrderService;
use Atwx\Checkout\Tests\Fixture\TestPurchasable;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

class OrderServiceTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        TestPurchasable::class,
    ];

    protected static $required_extensions = [
        TestPurchasable::class => [\Atwx\Checkout\Extension\PurchasableExtension::class],
    ];

    public function testCreateDirectSnapshotsItemsAndTotal(): void
    {
        $a = TestPurchasable::create(['Title' => 'Paket A', 'Price' => 100]);
        $a->write();
        $b = TestPurchasable::create(['Title' => 'Zusatz B', 'Price' => 45]);
        $b->write();

        $order = OrderService::create()->createDirect(
            [$a, $b],
            ['Email' => 'kunde@example.com', 'Name' => 'Kunde']
        );

        $this->assertSame('pending', $order->Status);
        $this->assertSame('kunde@example.com', $order->CustomerEmail);
        $this->assertSame('Kunde', $order->CustomerName);
        $this->assertEqualsWithDelta(145.0, (float) $order->TotalAmount, 0.001);
        $this->assertCount(2, $order->Items());
        $this->assertMatchesRegularExpression('/^ORD-\d{4}-\d{5}$/', $order->OrderNumber);

        $itemA = $order->Items()->filter('TitleSnapshot', 'Paket A')->first();
        $this->assertNotNull($itemA);
        $this->assertEqualsWithDelta(100.0, (float) $itemA->PriceSnapshot, 0.001);
        // Polymorphic snapshot link is preserved.
        $this->assertSame(TestPurchasable::class, $itemA->PurchasableClass);
        $this->assertSame($a->ID, (int) $itemA->PurchasableID);
    }

    public function testSnapshotIsFrozenAgainstLaterPriceChange(): void
    {
        $p = TestPurchasable::create(['Title' => 'X', 'Price' => 50]);
        $p->write();

        $order = OrderService::create()->createDirect([$p], ['Email' => 'e@e.test', 'Name' => 'n']);

        // Changing the source price must not affect the historical order.
        $p->Price = 999;
        $p->write();

        $this->assertEqualsWithDelta(50.0, (float) $order->Items()->first()->PriceSnapshot, 0.001);
        $this->assertEqualsWithDelta(50.0, (float) $order->TotalAmount, 0.001);
    }

    public function testQuantitiesMultiplyIntoTotal(): void
    {
        $p = TestPurchasable::create(['Title' => 'X', 'Price' => 50]);
        $p->write();

        $order = OrderService::create()->createDirect([$p], ['Email' => 'e', 'Name' => 'n'], [0 => 3]);

        $this->assertSame(3, (int) $order->Items()->first()->Quantity);
        $this->assertEqualsWithDelta(150.0, (float) $order->TotalAmount, 0.001);
    }

    public function testOrderNumberFormatIsConfigurable(): void
    {
        Config::modify()->set(OrderService::class, 'order_number_format', 'X-{seq:03}');
        $this->assertMatchesRegularExpression('/^X-\d{3}$/', OrderService::create()->generateOrderNumber());
    }
}
