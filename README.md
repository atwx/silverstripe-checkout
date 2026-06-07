# atwx/silverstripe-checkout

Product-agnostic cart, order and payment module for Silverstripe 6.

The module owns the path **from purchasable → order → payment → paid order**. It does *not*
define what a "product" is, how a gateway works internally, or how goods are delivered after
payment — that stays in your application, wired up through extensions and hooks.

> Status: early MVP. See [Implemented vs. planned](#implemented-vs-planned).

## Requirements

- PHP 8.3+
- Silverstripe 6
- `mollie/mollie-api-php` (pulled in automatically; only needed if you use the Mollie gateway)

## Installation

```bash
composer require atwx/silverstripe-checkout
vendor/bin/sake dev/build flush=1
```

## Core concepts

| Piece | Class | Role |
|-------|-------|------|
| Purchasable | `Atwx\Checkout\Extension\PurchasableExtension` | Attach to any app DataObject to give it `Price` + `IsActive`. The module has no `Product` class. |
| Order / OrderItem | `Atwx\Checkout\Model\Order`, `OrderItem` | What was bought, by whom, at what price. Items snapshot title + price. |
| Payment | `Atwx\Checkout\Model\Payment` | One transaction. An order can have several (retries are first-class). |
| Gateway | `Atwx\Checkout\Payment\PaymentGateway` | Interface. Concrete gateways are swappable and registered in YAML. |
| Services | `OrderService`, `PaymentService` | Create orders, start + reconcile payments. |

## Making something purchasable

```yaml
# app/_config/checkout.yml
App\VideoProduct:
  extensions:
    - Atwx\Checkout\Extension\PurchasableExtension
```

`PurchasableExtension` adds `Price` (Currency) and `IsActive` (Boolean), plus
`PriceFormatted()` and `CanPurchase($member)`. Veto a purchase from the app via the
`canPurchase` extension hook.

## Creating an order and starting payment

```php
use Atwx\Checkout\Payment\GatewayRegistry;
use Atwx\Checkout\Service\OrderService;
use Atwx\Checkout\Service\PaymentService;
use SilverStripe\Control\Director;

$order = OrderService::create()->createDirect(
    [$purchasableA, $purchasableB],          // any DataObjects with PurchasableExtension
    ['Email' => $email, 'Name' => $name]
);
$order->SuccessUrl = Director::absoluteURL('/danke');   // optional, see "Return handling"
$order->CancelUrl  = Director::absoluteURL('/checkout'); // optional
$order->write();

$gateway = GatewayRegistry::getDefault();    // or GatewayRegistry::get('mollie')
$result  = PaymentService::singleton()->begin($order, $gateway, Director::absoluteURL('checkout/return/' . $order->ID));

if ($result->isRedirect()) {
    return $controller->redirect($result->redirectUrl);  // hosted gateway checkout
}
```

## Routes

| Route | Controller | Purpose |
|-------|------------|---------|
| `GET checkout/return/$OrderID` | `CheckoutController` | Customer returns from gateway; reconciles, then redirects (see below). |
| `GET checkout/thanks/$OrderID` | `CheckoutController` | Bare success page (fallback). |
| `GET checkout/cancelled/$OrderID` | `CheckoutController` | Bare cancellation page (fallback). |
| `POST checkout/webhook/$Gateway` | `WebhookController` | Server-to-server status callback; the authoritative signal. |

### Return handling (configurable)

After the customer returns, the module reconciles the latest payment and then redirects:

- completed → `Order.SuccessUrl` if set, otherwise the module's `thanks` template
- not completed → `Order.CancelUrl` if set, otherwise the module's `cancelled` template

Set `SuccessUrl` / `CancelUrl` on the order to send customers to your own themed pages.
The `thanks` / `cancelled` templates are also overridable through the normal theme chain:
`themes/<theme>/templates/Atwx/Checkout/Control/CheckoutController_thanks.ss`.

## Hooks

The high-level order hooks are the main integration point. Add a `DataExtension` to `Order`:

```php
class MyOrderExtension extends SilverStripe\Core\Extension
{
    public function onOrderCompleted(): void   { /* deliver goods, send mail, … */ }
    public function onOrderCancelled(): void    {}
    public function onOrderRefunded(): void     {}
}
```

`onOrderCompleted` fires **exactly once**, on the transition into `completed` — safe against
webhook retries. Lower-level payment hooks also exist: `onPaymentStatusChange`,
`onPaymentSucceeded`, `onPaymentFailed`, `onPaymentRefunded`.

## Gateways & configuration

Gateways are registered by code in YAML. Each entry needs a `class`; the remaining keys are
passed to the gateway constructor as config. The backtick `` `ENV` `` syntax reads environment
variables.

```yaml
Atwx\Checkout\Payment\GatewayRegistry:
  default: 'mollie'
  gateways:
    mollie:
      class: Atwx\Checkout\Payment\Mollie\MollieGateway
      api_key: '`MOLLIE_API_KEY`'
    mock:
      class: Atwx\Checkout\Payment\Mock\MockGateway
      result: 'paid'        # 'paid' | 'failed' — for local dev / tests
```

Set `MOLLIE_API_KEY` (test_… or live_…) in `.env`. The **Mollie gateway is the default but is
not hard-wired**: it is just one `PaymentGateway` implementation. Swap the `default`, register
additional gateways, or build your own implementation of the interface. The `MockGateway`
ships for development and testing and skips any external call.

Order number format:

```yaml
Atwx\Checkout\Service\OrderService:
  order_number_format: 'ORD-{year}-{seq:05}'
  currency: 'EUR'
```

## CMS

`Atwx\Checkout\Admin\CheckoutAdmin` adds a "Bestellungen" section listing orders and payments.

## Running the tests

The suite (`tests/`) is a regression net for the current behaviour — order creation and
snapshotting, the idempotent reconcile/order-completion flow, gateway registry + backtick env
resolution, the purchasable extension, and the checkout return/webhook routing. Run it from the
project root with PHPUnit ^11.3 installed:

```bash
vendor/bin/phpunit -c vendor/atwx/silverstripe-checkout/phpunit.xml.dist
```

Notes:
- Tests need a database user allowed to create `ss_tmpdb_*` databases. On DDEV the default `db`
  user lacks this; grant it once:
  `ddev mysql -uroot -proot -e "GRANT ALL PRIVILEGES ON \`ss\_tmpdb%\`.* TO 'db'@'%'; FLUSH PRIVILEGES;"`
- If you have just run `dev/build`, clear the cache first so the test class manifest is rebuilt:
  `rm -rf /tmp/silverstripe-cache-*` (or the path printed by your install).

## Implemented vs. planned

**Implemented (MVP):** Order / OrderItem / Payment models, `PurchasableExtension`, gateway
interface + registry, Mollie + Mock gateways, `OrderService` (direct orders) + `PaymentService`
(begin + idempotent reconcile), checkout return + webhook controllers, per-order return URLs,
order/payment hooks, `CheckoutAdmin`, minimal overridable templates, PHPUnit regression suite.

**Not yet implemented:** Cart / CartItem + `CartService`, Invoice gateway, refund flows from the
CMS, abandoned-cart hooks, the `ContentController` cart extension + cart JSON endpoints, the
`/checkout/start` self-serve checkout form. Stripe / PayPal / coupons are
intended as separate add-on packages. See `_concept/silverstripe-checkout.md` for the full design.
```
