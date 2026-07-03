<?php

namespace Laravel\Cashier\Creem\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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

    public function test_it_rejects_disallowed_billable_types_from_metadata(): void
    {
        $billable = Cashier::findBillableFromMetadata([
            'billable_id' => '1',
            'billable_type' => \stdClass::class,
        ]);

        $this->assertNull($billable);
    }

    public function test_it_allows_the_default_auth_provider_model(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->timestamps();
        });

        config([
            'cashier.billable_model' => null,
            'auth.defaults.guard' => 'web',
            'auth.guards.web' => ['driver' => 'session', 'provider' => 'members'],
            'auth.providers.members' => ['driver' => 'eloquent', 'model' => Member::class],
        ]);

        $member = Member::create(['name' => 'Member', 'email' => 'member@example.com']);

        $billable = Cashier::findBillableFromMetadata([
            'billable_id' => (string) $member->id,
            'billable_type' => Member::class,
        ]);

        $this->assertTrue($member->is($billable));
    }
}