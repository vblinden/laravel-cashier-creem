<?php

namespace Laravel\Cashier\Creem\Tests;

class WebhookSignatureTest extends TestCase
{
    public function test_it_rejects_webhooks_when_secret_is_not_configured(): void
    {
        config(['cashier.webhook_secret' => null]);

        $this->postJson(route('cashier.webhook'), [
            'eventType' => 'subscription.paid',
            'object' => [],
        ])->assertForbidden();
    }

    public function test_it_rejects_webhooks_with_invalid_signature(): void
    {
        $this->postJson(route('cashier.webhook'), [
            'eventType' => 'subscription.paid',
            'object' => [],
        ], [
            'creem-signature' => 'invalid',
        ])->assertForbidden();
    }

    public function test_it_rejects_webhooks_without_signature_header(): void
    {
        $this->postJson(route('cashier.webhook'), [
            'eventType' => 'subscription.paid',
            'object' => [],
        ])->assertForbidden();
    }
}
