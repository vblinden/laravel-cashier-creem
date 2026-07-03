<?php

namespace Laravel\Cashier\Creem\Tests;

use Illuminate\Support\Facades\Http;
use Laravel\Cashier\Creem\Customer;

class CustomerPortalTest extends TestCase
{
    public function test_it_returns_a_customer_portal_url(): void
    {
        Http::fake([
            'https://test-api.creem.io/v1/customers/billing' => Http::response([
                'customer_portal_link' => 'https://creem.io/portal/test',
            ], 200),
        ]);

        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);

        Customer::create([
            'billable_id' => $user->id,
            'billable_type' => $user->getMorphClass(),
            'creem_id' => 'cust_portal',
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $this->assertSame(
            'https://creem.io/portal/test',
            $user->fresh()->customerPortalUrl()
        );
    }
}
