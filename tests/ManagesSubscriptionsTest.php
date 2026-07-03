<?php

namespace Laravel\Cashier\Creem\Tests;

use Laravel\Cashier\Creem\Subscription;

class ManagesSubscriptionsTest extends TestCase
{
    public function test_subscription_lookup_queries_the_database_without_eager_loading(): void
    {
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);

        Subscription::create([
            'billable_id' => $user->id,
            'billable_type' => $user->getMorphClass(),
            'type' => 'default',
            'creem_id' => 'sub_default',
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $freshUser = User::find($user->id);

        $this->assertFalse($freshUser->relationLoaded('subscriptions'));
        $this->assertTrue($freshUser->subscribed('default'));
        $this->assertFalse($freshUser->subscribed('team'));
    }
}
