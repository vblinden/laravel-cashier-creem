<?php

namespace Laravel\Cashier\Creem\Tests;

use Illuminate\Support\Facades\Http;
use Laravel\Cashier\Creem\Checkout;

class CheckoutBuilderTest extends TestCase
{
    public function test_it_builds_checkout_payload_with_billable_metadata(): void
    {
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);

        $payload = $user
            ->subscribe('prod_monthly', 'team', 2)
            ->successUrl('https://example.com/success')
            ->discountCode('LAUNCH50')
            ->metadata(['campaign' => 'spring'])
            ->getPayload();

        $this->assertSame('prod_monthly', $payload['product_id']);
        $this->assertSame(2, $payload['units']);
        $this->assertSame('https://example.com/success', $payload['success_url']);
        $this->assertSame('LAUNCH50', $payload['discount_code']);
        $this->assertSame((string) $user->id, $payload['metadata']['billable_id']);
        $this->assertSame($user->getMorphClass(), $payload['metadata']['billable_type']);
        $this->assertSame('team', $payload['metadata']['subscription_type']);
        $this->assertSame('spring', $payload['metadata']['campaign']);
        $this->assertSame('test@example.com', $payload['customer']['email']);
    }

    public function test_it_creates_a_checkout_session_via_the_api(): void
    {
        Http::fake([
            'https://test-api.creem.io/v1/checkouts' => Http::response([
                'checkout_url' => 'https://creem.io/checkout/ch_test',
            ], 200),
        ]);

        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);

        $url = $user->checkout('prod_one_time')->url();

        $this->assertSame('https://creem.io/checkout/ch_test', $url);
    }
}
