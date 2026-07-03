<?php

namespace Laravel\Cashier\Creem\Tests;

use Illuminate\Support\Facades\Http;
use Laravel\Cashier\Creem\Cashier;
use Laravel\Cashier\Creem\Exceptions\CreemException;

class CashierApiTest extends TestCase
{
    public function test_it_can_call_the_creem_api(): void
    {
        Http::fake([
            'https://test-api.creem.io/v1/checkouts' => Http::response(['checkout_url' => 'https://creem.io/checkout/test'], 200),
        ]);

        $response = Cashier::api('POST', 'checkouts', ['product_id' => 'prod_test']);

        $this->assertSame('https://creem.io/checkout/test', $response['checkout_url']);
    }

    public function test_it_throws_creem_exception_on_failed_api_response(): void
    {
        Http::fake([
            'https://test-api.creem.io/v1/checkouts' => Http::response(['message' => 'Invalid product'], 422),
        ]);

        $this->expectException(CreemException::class);
        $this->expectExceptionMessage('Invalid product');

        Cashier::api('POST', 'checkouts', ['product_id' => 'prod_invalid']);
    }
}
