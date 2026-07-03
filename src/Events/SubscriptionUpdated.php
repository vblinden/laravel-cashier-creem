<?php

namespace Laravel\Cashier\Creem\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Laravel\Cashier\Creem\Subscription;

class SubscriptionUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Subscription $subscription, public array $payload)
    {
    }
}