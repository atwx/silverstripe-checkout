<?php

namespace Atwx\Checkout\Tests\Unit;

use Atwx\Checkout\Tests\Fixture\TestPurchasable;
use SilverStripe\Dev\SapphireTest;

class PurchasableExtensionTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        TestPurchasable::class,
    ];

    protected static $required_extensions = [
        TestPurchasable::class => [\Atwx\Checkout\Extension\PurchasableExtension::class],
    ];

    public function testPriceFormattedRendersCurrency(): void
    {
        $p = TestPurchasable::create(['Title' => 'X', 'Price' => 99.90]);
        $this->assertStringContainsString('99', $p->PriceFormatted());
    }

    public function testCanPurchaseFollowsIsActive(): void
    {
        $active = TestPurchasable::create(['Title' => 'A', 'Price' => 10, 'IsActive' => 1]);
        $inactive = TestPurchasable::create(['Title' => 'B', 'Price' => 10, 'IsActive' => 0]);

        $this->assertTrue($active->CanPurchase());
        $this->assertFalse($inactive->CanPurchase());
    }

    public function testPurchasableTitleUsesTitle(): void
    {
        $p = TestPurchasable::create(['Title' => 'Mein Titel', 'Price' => 1]);
        $this->assertSame('Mein Titel', $p->getPurchasableTitle());
    }
}
