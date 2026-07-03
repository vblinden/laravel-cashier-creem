<?php

namespace Laravel\Cashier\Creem\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Laravel\Cashier\Creem\Cashier;
use Laravel\Cashier\Creem\Events\CheckoutCompleted;
use Laravel\Cashier\Creem\Events\SubscriptionCanceled;
use Laravel\Cashier\Creem\Events\SubscriptionCreated;
use Laravel\Cashier\Creem\Events\SubscriptionPaid;
use Laravel\Cashier\Creem\Events\SubscriptionUpdated;
use Laravel\Cashier\Creem\Events\TransactionRecorded;
use Laravel\Cashier\Creem\Events\WebhookHandled;
use Laravel\Cashier\Creem\Events\WebhookReceived;
use Laravel\Cashier\Creem\Http\Middleware\VerifyWebhookSignature;
use Laravel\Cashier\Creem\Subscription;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    public function __construct()
    {
        if (config('cashier.webhook_secret')) {
            $this->middleware(VerifyWebhookSignature::class);
        }
    }

    public function __invoke(Request $request): Response
    {
        $payload = $request->all();

        $eventType = $payload['eventType'] ?? $payload['event_type'] ?? null;

        if (! $eventType) {
            return new Response('Webhook Received', 200);
        }

        $method = 'handle'.Str::studly(Str::replace('.', ' ', $eventType));

        WebhookReceived::dispatch($payload);

        if (method_exists($this, $method)) {
            $this->{$method}($payload);

            WebhookHandled::dispatch($payload);

            return new Response('Webhook Handled', 200);
        }

        return new Response('Webhook Received', 200);
    }

    protected function handleCheckoutCompleted(array $payload): void
    {
        $data = $payload['object'] ?? [];

        $metadata = array_merge(
            $data['metadata'] ?? [],
            $data['subscription']['metadata'] ?? []
        );

        $billable = $this->resolveBillable($data, $metadata);

        if ($billable && isset($data['customer'])) {
            $this->ensureCustomer($billable, $data['customer']);
        }

        if (isset($data['subscription']) && is_array($data['subscription'])) {
            $this->syncSubscription($billable, $data['subscription'], $metadata);
        }

        if ($billable && isset($data['subscription']['last_transaction'])) {
            $this->recordTransaction($billable, ['subscription' => $data['subscription']]);
        }

        CheckoutCompleted::dispatch($billable, $payload);
    }

    protected function handleSubscriptionActive(array $payload): void
    {
        $this->handleSubscriptionLifecycle($payload, SubscriptionCreated::class);
    }

    protected function handleSubscriptionPaid(array $payload): void
    {
        $data = $payload['object'] ?? [];
        $metadata = $data['metadata'] ?? [];

        $billable = $this->resolveBillable($data, $metadata);

        if (! $billable) {
            return;
        }

        $this->ensureCustomer($billable, $data['customer'] ?? []);

        $subscription = $this->syncSubscription($billable, $data, $metadata);

        if ($subscription && isset($data['last_transaction'])) {
            $this->recordTransaction($billable, [
                'subscription' => $data,
                'order' => ['transaction' => $data['last_transaction']],
            ], $subscription);
        }

        SubscriptionPaid::dispatch($billable, $subscription, $payload);
    }

    protected function handleSubscriptionUpdate(array $payload): void
    {
        $this->handleSubscriptionLifecycle($payload, SubscriptionUpdated::class);
    }

    protected function handleSubscriptionCanceled(array $payload): void
    {
        $this->handleSubscriptionLifecycle($payload, SubscriptionCanceled::class);
    }

    protected function handleSubscriptionScheduledCancel(array $payload): void
    {
        $this->handleSubscriptionLifecycle($payload, SubscriptionUpdated::class);
    }

    protected function handleSubscriptionPastDue(array $payload): void
    {
        $this->handleSubscriptionLifecycle($payload, SubscriptionUpdated::class);
    }

    protected function handleSubscriptionExpired(array $payload): void
    {
        $this->handleSubscriptionLifecycle($payload, SubscriptionUpdated::class);
    }

    protected function handleSubscriptionPaused(array $payload): void
    {
        $this->handleSubscriptionLifecycle($payload, SubscriptionUpdated::class);
    }

    protected function handleSubscriptionTrialing(array $payload): void
    {
        $this->handleSubscriptionLifecycle($payload, SubscriptionUpdated::class);
    }

    protected function handleRefundCreated(array $payload): void
    {
        $data = $payload['object'] ?? [];
        $transaction = $data['transaction'] ?? null;

        if (! $transaction || ! isset($transaction['id'])) {
            return;
        }

        if ($record = Cashier::$transactionModel::firstWhere('creem_id', $transaction['id'])) {
            $record->update(['status' => $transaction['status'] ?? 'refunded']);
        }
    }

    protected function handleSubscriptionLifecycle(array $payload, string $event): void
    {
        $data = $payload['object'] ?? [];
        $metadata = $data['metadata'] ?? [];

        $billable = $this->resolveBillable($data, $metadata);

        if (! $billable) {
            return;
        }

        $this->ensureCustomer($billable, $data['customer'] ?? []);

        $subscription = $this->syncSubscription($billable, $data, $metadata);

        if (! $subscription) {
            return;
        }

        event(match ($event) {
            SubscriptionCreated::class => new SubscriptionCreated($billable, $subscription, $payload),
            SubscriptionCanceled::class => new SubscriptionCanceled($subscription, $payload),
            default => new SubscriptionUpdated($subscription, $payload),
        });
    }

    protected function syncSubscription(?Model $billable, array $data, array $metadata = []): ?Subscription
    {
        if (! $billable || ! isset($data['id'])) {
            return null;
        }

        $type = $metadata['subscription_type'] ?? Subscription::DEFAULT_TYPE;

        $subscription = Cashier::$subscriptionModel::updateOrCreate(
            ['creem_id' => $data['id']],
            [
                'billable_id' => $billable->getKey(),
                'billable_type' => $billable->getMorphClass(),
                'type' => $type,
                'status' => $data['status'],
                'trial_ends_at' => $data['status'] === Subscription::STATUS_TRIALING && isset($data['current_period_end_date'])
                    ? Carbon::parse($data['current_period_end_date'])
                    : null,
                'ends_at' => isset($data['canceled_at'])
                    ? Carbon::parse($data['canceled_at'])
                    : ($data['status'] === Subscription::STATUS_SCHEDULED_CANCEL && isset($data['current_period_end_date'])
                        ? Carbon::parse($data['current_period_end_date'])
                        : null),
                'paused_at' => $data['status'] === Subscription::STATUS_PAUSED ? now() : null,
                'current_period_start_at' => isset($data['current_period_start_date'])
                    ? Carbon::parse($data['current_period_start_date'])
                    : null,
                'current_period_end_at' => isset($data['current_period_end_date'])
                    ? Carbon::parse($data['current_period_end_date'])
                    : null,
            ]
        );

        $subscription->syncSubscriptionItems($data);

        if ($billable->customer) {
            $billable->customer->update(['trial_ends_at' => null]);
        }

        return $subscription;
    }

    protected function ensureCustomer(Model $billable, array $customerData): void
    {
        if (! isset($customerData['id'], $customerData['email'])) {
            return;
        }

        if (method_exists($billable, 'syncAsCustomer')) {
            $billable->syncAsCustomer($customerData);
        }
    }

    protected function recordTransaction(Model $billable, array $data, ?Subscription $subscription = null): void
    {
        $transaction = $data['order']['transaction'] ?? $data['last_transaction'] ?? null;

        if (! $transaction || ! isset($transaction['id'])) {
            return;
        }

        if ($this->transactionExists($transaction['id'])) {
            return;
        }

        $subscriptionId = is_array($data['subscription'] ?? null)
            ? ($data['subscription']['id'] ?? null)
            : ($subscription?->creem_id);

        $record = $billable->transactions()->create([
            'creem_id' => $transaction['id'],
            'creem_subscription_id' => $subscriptionId,
            'order_id' => $transaction['order'] ?? null,
            'status' => $transaction['status'],
            'total' => (string) ($transaction['amount_paid'] ?? $transaction['amount'] ?? 0),
            'tax' => (string) ($transaction['tax_amount'] ?? 0),
            'currency' => $transaction['currency'] ?? config('cashier.currency', 'USD'),
            'billed_at' => isset($transaction['created_at'])
                ? Carbon::createFromTimestampMs($transaction['created_at'])
                : now(),
            'period_start_at' => isset($transaction['period_start'])
                ? Carbon::createFromTimestampMs($transaction['period_start'])
                : null,
            'period_end_at' => isset($transaction['period_end'])
                ? Carbon::createFromTimestampMs($transaction['period_end'])
                : null,
        ]);

        TransactionRecorded::dispatch($billable, $record, $data);
    }

    protected function resolveBillable(array $data, array $metadata = []): ?Model
    {
        if ($billable = Cashier::findBillableFromMetadata($metadata)) {
            return $billable;
        }

        $customer = $data['customer'] ?? null;
        $customerId = is_array($customer) ? ($customer['id'] ?? null) : $customer;

        if ($customerId && ($billable = Cashier::findBillable($customerId))) {
            return $billable;
        }

        if (is_array($customer) && isset($customer['email'])) {
            $customerModel = Cashier::$customerModel;

            if ($record = $customerModel::where('email', $customer['email'])->first()) {
                return $record->billable;
            }
        }

        return null;
    }

    protected function transactionExists(string $transactionId): bool
    {
        return Cashier::$transactionModel::where('creem_id', $transactionId)->exists();
    }
}