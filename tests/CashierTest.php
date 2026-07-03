<?php

namespace Laravel\Cashier\Creem\Tests;

use Laravel\Cashier\Creem\Cashier;
use Laravel\Cashier\Creem\RedirectSignature;

class CashierTest extends TestCase
{
    public function test_it_detects_sandbox_from_test_api_key(): void
    {
        config(['cashier.api_key' => 'creem_test_abc123', 'cashier.sandbox' => null]);

        $this->assertTrue(Cashier::usesSandbox());
        $this->assertSame('https://test-api.creem.io/v1', Cashier::apiUrl());
    }

    public function test_it_uses_production_api_for_live_keys(): void
    {
        config(['cashier.api_key' => 'creem_live_abc123', 'cashier.sandbox' => false]);

        $this->assertFalse(Cashier::usesSandbox());
        $this->assertSame('https://api.creem.io/v1', Cashier::apiUrl());
    }

    public function test_it_finds_billable_from_metadata(): void
    {
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);

        $billable = Cashier::findBillableFromMetadata([
            'billable_id' => (string) $user->id,
            'billable_type' => User::class,
        ]);

        $this->assertTrue($user->is($billable));
    }
}