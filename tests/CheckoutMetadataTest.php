<?php

namespace Laravel\Cashier\Creem\Tests;

class CheckoutMetadataTest extends TestCase
{
    public function test_custom_metadata_cannot_override_billable_identifiers(): void
    {
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);

        $otherUser = User::create(['name' => 'Other User', 'email' => 'other@example.com']);

        $checkout = $user
            ->subscribe('prod_monthly')
            ->metadata([
                'plan' => 'pro',
                'billable_id' => (string) $otherUser->id,
                'billable_type' => User::class,
            ]);

        $metadata = $checkout->getPayload()['metadata'];

        $this->assertSame('pro', $metadata['plan']);
        $this->assertSame((string) $user->id, $metadata['billable_id']);
        $this->assertSame($user->getMorphClass(), $metadata['billable_type']);
        $this->assertSame('default', $metadata['subscription_type']);
    }
}
