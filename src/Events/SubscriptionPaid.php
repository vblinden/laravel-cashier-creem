<?php

namespace Laravel\Cashier\Creem\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Laravel\Cashier\Creem\Subscription;

class SubscriptionPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Model $billable,
        public Subscription $subscription,
        public array $payload
    ) {
    }
}