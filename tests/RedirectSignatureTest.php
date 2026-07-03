<?php

namespace Laravel\Cashier\Creem\Tests;

use Laravel\Cashier\Creem\RedirectSignature;

class RedirectSignatureTest extends TestCase
{
    public function test_it_verifies_redirect_signatures(): void
    {
        $apiKey = 'creem_test_example';

        $params = [
            'checkout_id' => 'ch_test',
            'order_id' => 'ord_test',
            'customer_id' => 'cust_test',
            'subscription_id' => 'sub_test',
            'product_id' => 'prod_test',
        ];

        $parts = [];

        foreach ($params as $key => $value) {
            $parts[] = "{$key}={$value}";
        }

        $parts[] = "salt={$apiKey}";

        $params['signature'] = hash('sha256', implode('|', $parts));

        $this->assertTrue(RedirectSignature::verify($params, $apiKey));
    }
}