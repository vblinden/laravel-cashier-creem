<?php

namespace Laravel\Cashier\Creem\Concerns;

use Laravel\Cashier\Creem\Cashier;
use Laravel\Cashier\Creem\Subscription;

trait ManagesSubscriptions
{
    public function subscriptions()
    {
        return $this->morphMany(Cashier::$subscriptionModel, 'billable')->orderByDesc('created_at');
    }

    public function subscription($type = 'default')
    {
        return $this->subscriptions()->where('type', $type)->first();
    }

    public function subscribed($type = 'default', $product = null): bool
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        return $product ? $subscription->hasProduct($product) : true;
    }

    public function subscribedToProduct($product, $type = 'default'): bool
    {
        return $this->subscribed($type, $product);
    }

    public function onTrial($type = 'default', $product = null): bool
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->onTrial()) {
            return false;
        }

        return $product ? $subscription->hasProduct($product) : true;
    }

    public function hasExpiredTrial($type = 'default', $product = null): bool
    {
        if (func_num_args() === 0 && $this->hasExpiredGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->hasExpiredTrial()) {
            return false;
        }

        return $product ? $subscription->hasProduct($product) : true;
    }

    public function onGenericTrial(): bool
    {
        if (is_null($this->customer)) {
            return false;
        }

        return $this->customer->onGenericTrial();
    }

    public function hasExpiredGenericTrial(): bool
    {
        if (is_null($this->customer)) {
            return false;
        }

        return $this->customer->hasExpiredGenericTrial();
    }

    public function trialEndsAt($type = 'default')
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return $this->customer->trial_ends_at;
        }

        if ($subscription = $this->subscription($type)) {
            return $subscription->trial_ends_at;
        }

        return null;
    }

    public function onProduct($product): bool
    {
        return $this->subscriptions()->get()->contains(function (Subscription $subscription) use ($product) {
            return $subscription->valid() && $subscription->hasProduct($product);
        });
    }
}