<?php

namespace Laravel\Cashier\Creem\Tests;

use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Creem\Events\SubscriptionPaid;
use Laravel\Cashier\Creem\Events\WebhookHandled;
use Laravel\Cashier\Creem\Events\WebhookReceived;
use Laravel\Cashier\Creem\Subscription;

class WebhookTest extends TestCase
{
    public function test_it_handles_subscription_paid_webhook(): void
    {
        Event::fake([WebhookReceived::class, WebhookHandled::class, SubscriptionPaid::class]);

        $user = User::create(['name' => 'Test User', 'email' => 'customer@example.com']);

        $payload = [
            'id' => 'evt_test',
            'eventType' => 'subscription.paid',
            'object' => [
                'id' => 'sub_test123',
                'object' => 'subscription',
                'status' => 'active',
                'product' => [
                    'id' => 'prod_monthly',
                    'name' => 'Monthly',
                    'price' => 1000,
                    'currency' => 'USD',
                ],
                'customer' => [
                    'id' => 'cust_test123',
                    'email' => 'customer@example.com',
                    'name' => 'Test User',
                ],
                'metadata' => [
                    'subscription_type' => 'default',
                    'billable_id' => (string) $user->id,
                    'billable_type' => User::class,
                ],
                'current_period_start_date' => '2024-10-12T11:58:38.000Z',
                'current_period_end_date' => '2024-11-12T11:58:38.000Z',
                'last_transaction' => [
                    'id' => 'tran_test123',
                    'amount' => 1000,
                    'amount_paid' => 1210,
                    'tax_amount' => 210,
                    'currency' => 'USD',
                    'status' => 'paid',
                    'created_at' => 1728734327109,
                ],
            ],
        ];

        $signature = hash_hmac('sha256', json_encode($payload), config('cashier.webhook_secret'));

        $this->postJson(route('cashier.webhook'), $payload, [
            'creem-signature' => $signature,
        ])->assertOk()->assertSee('Webhook Handled');

        $this->assertDatabaseHas('customers', [
            'billable_id' => $user->id,
            'creem_id' => 'cust_test123',
            'email' => 'customer@example.com',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'billable_id' => $user->id,
            'creem_id' => 'sub_test123',
            'status' => Subscription::STATUS_ACTIVE,
            'type' => 'default',
        ]);

        $this->assertDatabaseHas('transactions', [
            'billable_id' => $user->id,
            'creem_id' => 'tran_test123',
            'status' => 'paid',
        ]);

        Event::assertDispatched(SubscriptionPaid::class);
    }
}