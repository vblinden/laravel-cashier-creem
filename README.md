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

## Integrating in a Laravel App

### CSRF exemption

Creem sends webhooks as `POST` requests without a CSRF token. Exclude the webhook route in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: [
        'creem/webhook',
    ]);
})
```

If you change `CASHIER_PATH`, exclude `{your-path}/webhook` instead.

### Billable metadata

Every checkout automatically includes metadata so webhooks can resolve the local user:

```php
[
    'billable_id' => (string) $user->getKey(),
    'billable_type' => $user->getMorphClass(),
]
```

When starting a subscription, `subscribe()` also adds `subscription_type` (defaults to `default`). This maps to the `type` column on your local `subscriptions` table.

You can add your own metadata on top:

```php
return $user
    ->subscribe('prod_YOUR_PRODUCT_ID', 'default')
    ->metadata(['plan' => 'pro'])
    ->redirect();
```

Set `billable_model` in `config/cashier.php` if you need a fallback lookup path:

```php
'billable_model' => App\Models\User::class,
```

### Granting access

Creem fires multiple events around checkout. Use them for different purposes:

| Event | When to use |
| --- | --- |
| `checkout.completed` | Sync customer/subscription records after first checkout |
| `subscription.active` | Sync only — Creem creates the subscription object |
| `subscription.paid` | **Grant access** — Creem's recommended event for activation |
| `subscription.canceled` | Revoke access |

```php
use Laravel\Cashier\Creem\Events\SubscriptionPaid;
use Laravel\Cashier\Creem\Events\SubscriptionCanceled;

Event::listen(SubscriptionPaid::class, function (SubscriptionPaid $event) {
    // Recommended: grant access here
    $event->billable->update(['plan' => 'pro']);
});

Event::listen(SubscriptionCanceled::class, function (SubscriptionCanceled $event) {
    // Revoke access
});
```

For most apps, `$user->subscribed('default')` is enough after webhooks have synced — you do not need a separate `plan` column unless you want one.

### Local development

**Webhooks:** Creem must reach your app over HTTPS. Use a tunnel (e.g. ngrok) and register the public URL in the Creem dashboard:

```
https://your-ngrok-url.ngrok.io/creem/webhook
```

Without webhooks, checkout redirects still work, but subscriptions will not sync to your database until Creem can deliver events.

**Test cards** (sandbox only):

| Card | Result |
| --- | --- |
| `4111 1111 1111 1111` | Successful payment |
| `4507 9900 0000 0028` | Card declined |
| `4507 9900 0000 0010` | Insufficient funds |

Use any future expiry date, any CVV, and any billing address.

**Sandbox detection:** API keys prefixed with `creem_test_` automatically use `https://test-api.creem.io/v1`.

### Routes and controller

Register checkout and portal routes inside your authenticated middleware group:

```php
// routes/web.php
use App\Http\Controllers\BillingCheckoutController;

Route::middleware(['auth'])->group(function () {
    Route::get('billing/checkout/{plan}', [BillingCheckoutController::class, 'checkout'])
        ->name('billing.checkout');

    Route::get('billing/portal', [BillingCheckoutController::class, 'portal'])
        ->name('billing.portal');
});
```

Example controller:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingCheckoutController extends Controller
{
    public function checkout(Request $request, string $plan): RedirectResponse
    {
        $productId = config("billing.plans.{$plan}.creem_product");

        abort_unless(filled($productId), 503, 'Billing is not configured for this plan.');

        $user = $request->user();

        if ($user->subscribed('default')) {
            return $user->redirectToCustomerPortal();
        }

        return $user
            ->subscribe($productId)
            ->successUrl(route('billing.index', ['checkout' => 'success']))
            ->redirect();
    }

    public function portal(Request $request): RedirectResponse
    {
        return $request->user()->redirectToCustomerPortal();
    }
}
```

In Blade or Livewire views, link to checkout **without** `wire:navigate`. External redirects to Creem require a full page navigation — using `wire:navigate` causes CORS errors.

```blade
<flux:button :href="route('billing.checkout', 'pro')">
    Upgrade to Pro
</flux:button>
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

Cashier automatically verifies webhook signatures and syncs customers, subscriptions, and transactions.

Dispatched events:

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