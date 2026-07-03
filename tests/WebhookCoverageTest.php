<?php

namespace Laravel\Cashier\Creem\Tests;

use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Creem\Events\CheckoutCompleted;
use Laravel\Cashier\Creem\Events\SubscriptionCanceled;
use Laravel\Cashier\Creem\Subscription;

class WebhookCoverageTest extends TestCase
{
    protected function signPayload(array $payload): array
    {
        return [
            'creem-signature' => hash_hmac('sha256', json_encode($payload), config('cashier.webhook_secret')),
        ];
    }

    public function test_it_handles_checkout_completed_webhook(): void
    {
        Event::fake([CheckoutCompleted::class]);

        $user = User::create(['name' => 'Test User', 'email' => 'customer@example.com']);

        $payload = [
            'eventType' => 'checkout.completed',
            'object' => [
                'customer' => [
                    'id' => 'cust_checkout',
                    'email' => 'customer@example.com',
                    'name' => 'Test User',
                ],
                'subscription' => [
                    'id' => 'sub_checkout',
                    'status' => 'active',
                    'product' => ['id' => 'prod_monthly'],
                    'metadata' => [
                        'subscription_type' => 'default',
                        'billable_id' => (string) $user->id,
                        'billable_type' => User::class,
                    ],
                ],
            ],
        ];

        $this->postJson(route('cashier.webhook'), $payload, $this->signPayload($payload))
            ->assertOk()
            ->assertSee('Webhook Handled');

        $this->assertDatabaseHas('subscriptions', [
            'creem_id' => 'sub_checkout',
            'billable_id' => $user->id,
        ]);

        Event::assertDispatched(CheckoutCompleted::class);
    }

    public function test_it_handles_subscription_canceled_webhook(): void
    {
        Event::fake([SubscriptionCanceled::class]);

        $user = User::create(['name' => 'Test User', 'email' => 'customer@example.com']);

        Subscription::create([
            'billable_id' => $user->id,
            'billable_type' => $user->getMorphClass(),
            'type' => 'default',
            'creem_id' => 'sub_cancel',
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $payload = [
            'eventType' => 'subscription.canceled',
            'object' => [
                'id' => 'sub_cancel',
                'status' => 'canceled',
                'canceled_at' => '2024-10-12T11:58:38.000Z',
                'customer' => [
                    'id' => 'cust_cancel',
                    'email' => 'customer@example.com',
                    'name' => 'Test User',
                ],
                'metadata' => [
                    'billable_id' => (string) $user->id,
                    'billable_type' => User::class,
                ],
            ],
        ];

        $this->postJson(route('cashier.webhook'), $payload, $this->signPayload($payload))
            ->assertOk()
            ->assertSee('Webhook Handled');

        $this->assertDatabaseHas('subscriptions', [
            'creem_id' => 'sub_cancel',
            'status' => Subscription::STATUS_CANCELED,
        ]);

        Event::assertDispatched(SubscriptionCanceled::class);
    }

    public function test_it_acknowledges_unknown_webhook_events(): void
    {
        $payload = [
            'eventType' => 'product.updated',
            'object' => [],
        ];

        $this->postJson(route('cashier.webhook'), $payload, $this->signPayload($payload))
            ->assertOk()
            ->assertSee('Webhook Received');
    }

    public function test_it_deduplicates_transactions_on_retry(): void
    {
        $user = User::create(['name' => 'Test User', 'email' => 'customer@example.com']);

        $payload = [
            'eventType' => 'subscription.paid',
            'object' => [
                'id' => 'sub_retry',
                'status' => 'active',
                'product' => ['id' => 'prod_monthly'],
                'customer' => [
                    'id' => 'cust_retry',
                    'email' => 'customer@example.com',
                    'name' => 'Test User',
                ],
                'metadata' => [
                    'billable_id' => (string) $user->id,
                    'billable_type' => User::class,
                ],
                'last_transaction' => [
                    'id' => 'tran_retry',
                    'amount_paid' => 1000,
                    'currency' => 'USD',
                    'status' => 'paid',
                ],
            ],
        ];

        $headers = $this->signPayload($payload);

        $this->postJson(route('cashier.webhook'), $payload, $headers)->assertOk();
        $this->postJson(route('cashier.webhook'), $payload, $headers)->assertOk();

        $this->assertSame(1, $user->transactions()->where('creem_id', 'tran_retry')->count());
    }
}
