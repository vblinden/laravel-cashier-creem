<?php

namespace Laravel\Cashier\Creem\Tests;

use Carbon\Carbon;
use Laravel\Cashier\Creem\Subscription;

class SubscriptionPausedAtTest extends TestCase
{
    public function test_it_preserves_paused_at_across_repeated_syncs(): void
    {
        $subscription = Subscription::create([
            'billable_id' => 1,
            'billable_type' => User::class,
            'type' => 'default',
            'creem_id' => 'sub_paused',
            'status' => Subscription::STATUS_PAUSED,
            'paused_at' => Carbon::parse('2024-01-01 12:00:00'),
        ]);

        $subscription->syncFromCreem([
            'id' => 'sub_paused',
            'status' => Subscription::STATUS_PAUSED,
        ]);

        $this->assertTrue($subscription->fresh()->paused_at->equalTo(Carbon::parse('2024-01-01 12:00:00')));
    }

    public function test_it_uses_creem_paused_at_when_provided(): void
    {
        $subscription = Subscription::create([
            'billable_id' => 1,
            'billable_type' => User::class,
            'type' => 'default',
            'creem_id' => 'sub_paused',
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $subscription->syncFromCreem([
            'id' => 'sub_paused',
            'status' => Subscription::STATUS_PAUSED,
            'paused_at' => '2024-06-15T10:30:00.000Z',
        ]);

        $this->assertTrue($subscription->fresh()->paused_at->equalTo(Carbon::parse('2024-06-15T10:30:00.000Z')));
    }
}
