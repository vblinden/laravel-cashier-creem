<?php

namespace Laravel\Cashier\Creem\Concerns;

use Laravel\Cashier\Creem\Checkout;
use Laravel\Cashier\Creem\Subscription;

trait PerformsCharges
{
    public function checkout(string $productId, int $units = 1): Checkout
    {
        $payload = [
            'product_id' => $productId,
            'units' => $units,
            'metadata' => $this->checkoutMetadata(),
        ];

        if ($email = $this->creemEmail()) {
            $payload['customer'] = ['email' => $email];
        }

        if ($customer = $this->customer) {
            $payload['customer']['id'] = $customer->creem_id;
        }

        return Checkout::create($payload);
    }

    public function subscribe(string $productId, string $type = Subscription::DEFAULT_TYPE, int $units = 1): Checkout
    {
        return $this->checkout($productId, $units)->metadata([
            'subscription_type' => $type,
        ]);
    }

    public function charge(string $productId, int $units = 1): Checkout
    {
        return $this->checkout($productId, $units);
    }
}