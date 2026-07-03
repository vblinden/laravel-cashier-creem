# Laravel Cashier Creem

Cashier Creem provides an expressive, fluent interface to [Creem](https://creem.io)'s subscription billing services. It mirrors the developer experience of [Laravel Cashier Paddle](https://github.com/laravel/cashier-paddle) while integrating with Creem's Merchant of Record API.

## Installation

```bash
composer require vblinden/laravel-cashier-creem
```

Publish the configuration and migrations:

```bash
php artisan vendor:publish --tag="cashier-config"
php artisan vendor:publish --tag="cashier-migrations"
php artisan migrate
```

Add your Creem credentials to `.env`:

```env
CREEM_API_KEY=creem_test_...
CREEM_WEBHOOK_SECRET=whsec_...
CREEM_SUCCESS_URL="${APP_URL}/billing/success"
CASHIER_PATH=creem
```

Register the webhook endpoint in your [Creem dashboard](https://creem.io/dashboard/developers):

```
https://your-app.test/creem/webhook
```

## Billable Model

Add the `Billable` trait to your user model:

```php
use Laravel\Cashier\Creem\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

## Subscriptions

Create a checkout session and redirect the customer to Creem:

```php
return $user->subscribe('prod_YOUR_PRODUCT_ID')->redirect();
```

Check subscription status:

```php
if ($user->subscribed('default')) {
    // ...
}

if ($user->subscribedToProduct('prod_YOUR_PRODUCT_ID')) {
    // ...
}
```

Manage an existing subscription:

```php
$user->subscription('default')->cancel();
$user->subscription('default')->cancelNow();
$user->subscription('default')->resume();
$user->subscription('default')->swap('prod_PREMIUM_PLAN');
$user->subscription('default')->updateQuantity(5);
```

## One-time Payments

```php
return $user->checkout('prod_ONE_TIME_PRODUCT')->redirect();
```

Customize checkout options:

```php
return $user->checkout('prod_YOUR_PRODUCT_ID')
    ->successUrl(route('billing.success'))
    ->discountCode('LAUNCH50')
    ->units(3)
    ->metadata(['campaign' => 'spring-sale'])
    ->redirect();
```

## Customer Portal

Let customers manage billing through Creem's customer portal:

```php
return $user->redirectToCustomerPortal();
```

## Webhooks

Cashier automatically verifies webhook signatures and syncs:

- Customers
- Subscriptions
- Transactions

Use `subscription.paid` to grant access (Creem's recommended event for activation):

```php
use Laravel\Cashier\Creem\Events\SubscriptionPaid;

Event::listen(SubscriptionPaid::class, function (SubscriptionPaid $event) {
    $event->billable->update(['plan' => 'pro']);
});
```

Other dispatched events:

- `CheckoutCompleted`
- `SubscriptionCreated`
- `SubscriptionUpdated`
- `SubscriptionCanceled`
- `SubscriptionPaid`
- `TransactionRecorded`
- `WebhookReceived`
- `WebhookHandled`

## Verifying Success Redirects

After checkout, Creem redirects to your success URL with signed query parameters. Verify them with:

```php
use Laravel\Cashier\Creem\RedirectSignature;

if (! RedirectSignature::verify($request->query())) {
    abort(401);
}
```

## Configuration

| Variable | Description |
| --- | --- |
| `CREEM_API_KEY` | Your Creem API key |
| `CREEM_WEBHOOK_SECRET` | Webhook signing secret |
| `CREEM_SANDBOX` | Force sandbox mode (`true`/`false`) |
| `CREEM_SUCCESS_URL` | Default checkout success URL |
| `CASHIER_PATH` | Route prefix for webhooks (default: `creem`) |
| `CASHIER_CURRENCY` | Default currency (default: `USD`) |

Test API keys prefixed with `creem_test_` automatically use the sandbox API.

## Custom Models

```php
use Laravel\Cashier\Creem\Cashier;

Cashier::useCustomerModel(Customer::class);
Cashier::useSubscriptionModel(Subscription::class);
```

## License

MIT