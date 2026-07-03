<?php

namespace Laravel\Cashier\Creem;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Laravel\Cashier\Creem\Exceptions\CreemException;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use NumberFormatter;

class Cashier
{
    const VERSION = '1.0.0';

    protected static $formatCurrencyUsing;

    public static $registersRoutes = true;

    public static $deactivatePastDue = true;

    public static $customerModel = Customer::class;

    public static $subscriptionModel = Subscription::class;

    public static $subscriptionItemModel = SubscriptionItem::class;

    public static $transactionModel = Transaction::class;

    public static function findBillable($customerId): ?Model
    {
        return (new static::$customerModel)->where('creem_id', $customerId)->first()?->billable;
    }

    public static function findBillableFromMetadata(array $metadata): ?Model
    {
        if (! empty($metadata['billable_id']) && ! empty($metadata['billable_type'])) {
            $model = $metadata['billable_type'];

            if (static::isAllowedBillableType($model)) {
                return $model::find($metadata['billable_id']);
            }
        }

        foreach (['referenceId', 'reference_id', 'internal_customer_id', 'userId', 'user_id'] as $key) {
            if (! empty($metadata[$key]) && ($billable = static::findBillableByReference($metadata[$key]))) {
                return $billable;
            }
        }

        return null;
    }

    protected static function isAllowedBillableType(string $model): bool
    {
        if (! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            return false;
        }

        return in_array($model, static::allowedBillableTypes(), true);
    }

    protected static function allowedBillableTypes(): array
    {
        $types = [];

        if ($billableModel = config('cashier.billable_model')) {
            $types[] = $billableModel;
        }

        foreach (config('auth.providers', []) as $provider) {
            if (! empty($provider['model'])) {
                $types[] = $provider['model'];
            }
        }

        return array_values(array_unique($types));
    }

    protected static function defaultBillableModel(): ?string
    {
        if ($model = config('cashier.billable_model')) {
            return $model;
        }

        $defaultGuard = config('auth.defaults.guard');
        $provider = config("auth.guards.{$defaultGuard}.provider");

        if (is_string($provider) && ($model = config("auth.providers.{$provider}.model"))) {
            return $model;
        }

        foreach (config('auth.providers', []) as $providerConfig) {
            if (! empty($providerConfig['model'])) {
                return $providerConfig['model'];
            }
        }

        return null;
    }

    protected static function findBillableByReference(string $reference): ?Model
    {
        $customerModel = static::$customerModel;

        if ($customer = $customerModel::find($reference)) {
            return $customer->billable;
        }

        $billableModel = static::defaultBillableModel();

        if ($billableModel && class_exists($billableModel)) {
            return $billableModel::find($reference);
        }

        return null;
    }

    public static function webhookUrl(): string
    {
        return config('cashier.webhook') ?? route('cashier.webhook');
    }

    public static function apiUrl(): string
    {
        if (static::usesSandbox()) {
            return 'https://test-api.creem.io/v1';
        }

        return 'https://api.creem.io/v1';
    }

    public static function usesSandbox(): bool
    {
        $sandbox = config('cashier.sandbox');

        if (! is_null($sandbox)) {
            return (bool) $sandbox;
        }

        $apiKey = config('cashier.api_key');

        return is_string($apiKey) && str_starts_with($apiKey, 'creem_test_');
    }

    public static function api(string $method, string $uri, ?array $payload = null): array
    {
        if (empty(config('cashier.api_key'))) {
            throw new Exception('Creem API key not set.');
        }

        $request = Http::withHeaders([
            'x-api-key' => config('cashier.api_key'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->withUserAgent('Laravel\Cashier\Creem/'.static::VERSION);

        $url = static::apiUrl().'/'.ltrim($uri, '/');

        $response = match (strtolower($method)) {
            'get' => $request->get($url, $payload ?? []),
            'post' => $request->post($url, $payload ?? []),
            'patch' => $request->patch($url, $payload ?? []),
            'put' => $request->put($url, $payload ?? []),
            'delete' => $request->delete($url, $payload ?? []),
            default => throw new Exception("Unsupported HTTP method [{$method}]."),
        };

        if ($response->failed()) {
            $body = $response->json() ?? [];

            $message = $body['message']
                ?? $body['error']
                ?? (isset($body['statusCode']) ? json_encode($body) : null)
                ?? $response->body();

            throw (new CreemException($message))->setResponse($body);
        }

        return $response->json() ?? [];
    }

    public static function formatAmount(int $amount, ?string $currency = null): string
    {
        if (static::$formatCurrencyUsing) {
            return call_user_func(static::$formatCurrencyUsing, $amount, $currency);
        }

        $money = new Money($amount, new Currency(strtoupper($currency ?? config('cashier.currency', 'USD'))));

        $formatter = new IntlMoneyFormatter(
            new NumberFormatter(config('cashier.currency_locale', 'en'), NumberFormatter::CURRENCY),
            new ISOCurrencies
        );

        return $formatter->format($money);
    }

    public static function formatAmountUsing(callable $callback): void
    {
        static::$formatCurrencyUsing = $callback;
    }

    public static function ignoreRoutes(): void
    {
        static::$registersRoutes = false;
    }

    public static function keepPastDueSubscriptionsActive(): void
    {
        static::$deactivatePastDue = false;
    }

    public static function useCustomerModel(string $model): void
    {
        static::$customerModel = $model;
    }

    public static function useSubscriptionModel(string $model): void
    {
        static::$subscriptionModel = $model;
    }

    public static function useSubscriptionItemModel(string $model): void
    {
        static::$subscriptionItemModel = $model;
    }

    public static function useTransactionModel(string $model): void
    {
        static::$transactionModel = $model;
    }
}