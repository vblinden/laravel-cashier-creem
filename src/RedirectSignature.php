<?php

namespace Laravel\Cashier\Creem;

class RedirectSignature
{
    public static function verify(array $params, ?string $apiKey = null): bool
    {
        $signature = $params['signature'] ?? null;

        if (! $signature) {
            return false;
        }

        unset($params['signature']);

        $parts = [];

        foreach ($params as $key => $value) {
            if ($value === null || $value === '' || $value === 'null') {
                continue;
            }

            $parts[] = "{$key}={$value}";
        }

        $parts[] = 'salt='.($apiKey ?? config('cashier.api_key'));

        $expected = hash('sha256', implode('|', $parts));

        return hash_equals($expected, $signature);
    }
}