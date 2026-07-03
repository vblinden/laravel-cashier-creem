<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Creem API Key
    |--------------------------------------------------------------------------
    |
    | Your Creem API key from the Developers section of your dashboard. Keys
    | prefixed with creem_test_ use the sandbox environment automatically.
    |
    */

    'api_key' => env('CREEM_API_KEY'),

    'webhook_secret' => env('CREEM_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Cashier Path
    |--------------------------------------------------------------------------
    |
    | This is the base URI path where Cashier's webhook route will be available.
    |
    */

    'path' => env('CASHIER_PATH', 'creem'),

    /*
    |--------------------------------------------------------------------------
    | Cashier Webhook
    |--------------------------------------------------------------------------
    |
    | Optionally override the webhook URL used when registering webhooks.
    |
    */

    'webhook' => env('CASHIER_WEBHOOK'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */

    'currency' => env('CASHIER_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Currency Locale
    |--------------------------------------------------------------------------
    */

    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Creem Sandbox
    |--------------------------------------------------------------------------
    |
    | Force the test API environment. When null, sandbox mode is detected from
    | the creem_test_ API key prefix.
    |
    */

    'sandbox' => env('CREEM_SANDBOX'),

    /*
    |--------------------------------------------------------------------------
    | Default Success URL
    |--------------------------------------------------------------------------
    |
    | The default URL customers are redirected to after completing checkout.
    |
    */

    'success_url' => env('CREEM_SUCCESS_URL'),

];