<?php

namespace Laravel\Cashier\Creem;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

class Checkout
{
    protected ?array $response = null;

    public function __construct(protected array $payload = [])
    {
    }

    public static function create(array $payload = []): self
    {
        return new static($payload);
    }

    public function product(string $productId): self
    {
        $this->payload['product_id'] = $productId;

        return $this;
    }

    public function units(int $units): self
    {
        $this->payload['units'] = $units;

        return $this;
    }

    public function requestId(string $requestId): self
    {
        $this->payload['request_id'] = $requestId;

        return $this;
    }

    public function successUrl(string $url): self
    {
        $this->payload['success_url'] = $url;

        return $this;
    }

    public function discountCode(string $code): self
    {
        $this->payload['discount_code'] = $code;

        return $this;
    }

    public function customer(array $customer): self
    {
        $this->payload['customer'] = array_merge($this->payload['customer'] ?? [], $customer);

        return $this;
    }

    public function metadata(array $metadata): self
    {
        $this->payload['metadata'] = array_merge($this->payload['metadata'] ?? [], $metadata);

        return $this;
    }

    public function customPrice(int $amount): self
    {
        $this->payload['custom_price'] = $amount;

        return $this;
    }

    public function createSession(): array
    {
        if (! isset($this->payload['success_url']) && $default = config('cashier.success_url')) {
            $this->payload['success_url'] = $default;
        }

        $this->response = Cashier::api('POST', 'checkouts', $this->payload);

        return $this->response;
    }

    public function url(): string
    {
        return $this->createSession()['checkout_url'];
    }

    public function redirect(): RedirectResponse
    {
        return redirect()->away($this->url());
    }

    public function toArray(): array
    {
        return $this->response ?? $this->payload;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getResponse(): ?array
    {
        return $this->response;
    }
}