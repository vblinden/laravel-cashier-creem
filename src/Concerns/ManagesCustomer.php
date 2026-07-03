<?php

namespace Laravel\Cashier\Creem\Concerns;

use Illuminate\Http\RedirectResponse;
use Laravel\Cashier\Creem\Cashier;
use Laravel\Cashier\Creem\Customer;
use LogicException;

trait ManagesCustomer
{
    public function createAsCustomer(array $options = []): Customer
    {
        if ($customer = $this->customer) {
            return $customer;
        }

        if (! array_key_exists('name', $options) && $name = $this->creemName()) {
            $options['name'] = $name;
        }

        if (! array_key_exists('email', $options) && $email = $this->creemEmail()) {
            $options['email'] = $email;
        }

        if (! isset($options['email'])) {
            throw new LogicException('Unable to create Creem customer without an email.');
        }

        $trialEndsAt = $options['trial_ends_at'] ?? null;

        unset($options['trial_ends_at']);

        try {
            $response = Cashier::api('GET', 'customers', ['email' => $options['email']]);
        } catch (\Throwable) {
            $response = null;
        }

        if (empty($response['id'])) {
            throw new LogicException('Creem customers are created during checkout. Use checkout() to start a payment session.');
        }

        if (Cashier::$customerModel::where('creem_id', $response['id'])->exists()) {
            throw new LogicException("The Creem customer [{$response['id']}] already exists in the database.");
        }

        $customer = $this->customer()->make();
        $customer->creem_id = $response['id'];
        $customer->name = $response['name'] ?? $options['name'] ?? '';
        $customer->email = $response['email'];
        $customer->trial_ends_at = $trialEndsAt;
        $customer->save();

        $this->refresh();

        return $customer;
    }

    public function customer()
    {
        return $this->morphOne(Cashier::$customerModel, 'billable');
    }

    public function syncAsCustomer(array $customerData): Customer
    {
        $customer = $this->customer()->updateOrCreate(
            ['billable_id' => $this->getKey(), 'billable_type' => $this->getMorphClass()],
            [
                'creem_id' => $customerData['id'],
                'name' => $customerData['name'] ?? $this->creemName() ?? '',
                'email' => $customerData['email'],
            ]
        );

        $this->refresh();

        return $customer;
    }

    public function redirectToCustomerPortal(): RedirectResponse
    {
        return redirect()->away($this->customerPortalUrl());
    }

    public function customerPortalUrl(): string
    {
        $customer = $this->customer;

        if (! $customer) {
            throw new LogicException('Unable to generate customer portal URL without a Creem customer.');
        }

        $response = Cashier::api('POST', 'customers/billing', [
            'customer_id' => $customer->creem_id,
        ]);

        return $response['customer_portal_link'] ?? $response['customerPortalLink']
            ?? throw new LogicException('Creem did not return a customer portal link.');
    }

    public function creemName(): ?string
    {
        return $this->name ?? null;
    }

    public function creemEmail(): ?string
    {
        return $this->email ?? null;
    }

    protected function checkoutMetadata(): array
    {
        return [
            'billable_id' => (string) $this->getKey(),
            'billable_type' => $this->getMorphClass(),
        ];
    }
}