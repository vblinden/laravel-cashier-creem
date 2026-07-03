<?php

namespace Laravel\Cashier\Creem;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

class Subscription extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_TRIALING = 'trialing';
    const STATUS_PAST_DUE = 'past_due';
    const STATUS_PAUSED = 'paused';
    const STATUS_CANCELED = 'canceled';
    const STATUS_SCHEDULED_CANCEL = 'scheduled_cancel';
    const STATUS_UNPAID = 'unpaid';

    const DEFAULT_TYPE = 'default';

    protected $guarded = [];

    protected $with = ['items'];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'paused_at' => 'datetime',
        'ends_at' => 'datetime',
        'current_period_start_at' => 'datetime',
        'current_period_end_at' => 'datetime',
    ];

    public function billable()
    {
        return $this->morphTo();
    }

    public function items()
    {
        return $this->hasMany(Cashier::$subscriptionItemModel);
    }

    public function transactions()
    {
        return $this->hasMany(Cashier::$transactionModel, 'creem_subscription_id', 'creem_id')
            ->orderByDesc('created_at');
    }

    public function findItemOrFail(string $productId)
    {
        return $this->items()->where('product_id', $productId)->firstOrFail();
    }

    protected function singleItemOrFail(?string $productId = null)
    {
        if ($this->items()->count() > 1 && is_null($productId)) {
            throw new InvalidArgumentException(
                'Please provide a product ID when retrieving an item of a subscription with multiple products.'
            );
        }

        return $productId ? $this->findItemOrFail($productId) : $this->items()->firstOrFail();
    }

    public function hasMultipleProducts(): bool
    {
        return $this->items->count() > 1;
    }

    public function hasSingleProduct(): bool
    {
        return ! $this->hasMultipleProducts();
    }

    public function hasProduct(string $productId): bool
    {
        return $this->items->contains(fn (SubscriptionItem $item) => $item->product_id === $productId);
    }

    public function valid(): bool
    {
        return $this->onTrial()
            || $this->active()
            || $this->onGracePeriod()
            || $this->scheduledForCancellation()
            || (! Cashier::$deactivatePastDue && $this->pastDue());
    }

    public function scopeValid($query)
    {
        $query->where(function ($query) {
            $query->where('status', self::STATUS_TRIALING)
                ->orWhere('status', self::STATUS_ACTIVE)
                ->orWhere('status', self::STATUS_SCHEDULED_CANCEL)
                ->orWhere(function ($query) {
                    $query->whereNotNull('ends_at')->where('ends_at', '>', Carbon::now());
                });

            if (! Cashier::$deactivatePastDue) {
                $query->orWhere('status', self::STATUS_PAST_DUE);
            }
        });
    }

    public function onTrial(): bool
    {
        return $this->status === self::STATUS_TRIALING;
    }

    public function scopeOnTrial($query)
    {
        $query->where('status', self::STATUS_TRIALING);
    }

    public function hasExpiredTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    public function active(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeActive($query)
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    public function recurring(): bool
    {
        return $this->active() && ! $this->onGracePeriod() && ! $this->scheduledForCancellation();
    }

    public function pastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    public function scopePastDue($query)
    {
        $query->where('status', self::STATUS_PAST_DUE);
    }

    public function paused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function canceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }

    public function scheduledForCancellation(): bool
    {
        return $this->status === self::STATUS_SCHEDULED_CANCEL;
    }

    public function onGracePeriod(): bool
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }

    public function scopeOnGracePeriod($query)
    {
        $query->whereNotNull('ends_at')->where('ends_at', '>', Carbon::now());
    }

    public function swap(string $productId, array $options = []): self
    {
        $response = Cashier::api('POST', "subscriptions/{$this->creem_id}/upgrade", array_merge([
            'product_id' => $productId,
            'update_behavior' => 'proration-charge-immediately',
        ], $options));

        return $this->syncFromCreem($response);
    }

    public function updateQuantity(int $quantity, ?string $productId = null, array $options = []): self
    {
        if ($quantity < 1) {
            throw new LogicException('Quantities of zero are not allowed.');
        }

        $item = $this->singleItemOrFail($productId);

        $response = Cashier::api('POST', "subscriptions/{$this->creem_id}", array_merge([
            'items' => [[
                'id' => $item->creem_id,
                'units' => $quantity,
            ]],
            'update_behavior' => 'proration-charge-immediately',
        ], $options));

        return $this->syncFromCreem($response);
    }

    public function incrementQuantity(int $count = 1, ?string $productId = null): self
    {
        $item = $this->singleItemOrFail($productId);

        return $this->updateQuantity($item->quantity + $count, $productId);
    }

    public function decrementQuantity(int $count = 1, ?string $productId = null): self
    {
        $item = $this->singleItemOrFail($productId);

        return $this->updateQuantity(max(1, $item->quantity - $count), $productId);
    }

    public function cancel(bool $cancelNow = false): self
    {
        $response = Cashier::api('POST', "subscriptions/{$this->creem_id}/cancel", [
            'mode' => $cancelNow ? 'immediate' : 'scheduled',
        ]);

        return $this->syncFromCreem($response);
    }

    public function cancelNow(): self
    {
        return $this->cancel(true);
    }

    public function resume(): self
    {
        if (! in_array($this->status, [self::STATUS_PAUSED, self::STATUS_SCHEDULED_CANCEL], true)) {
            throw new LogicException('Cannot resume a subscription that is not paused or scheduled for cancellation.');
        }

        $response = Cashier::api('POST', "subscriptions/{$this->creem_id}/resume");

        return $this->syncFromCreem($response);
    }

    public function asCreemSubscription(): array
    {
        return Cashier::api('GET', "subscriptions/{$this->creem_id}");
    }

    public function syncFromCreem(array $data): self
    {
        $this->forceFill([
            'status' => $data['status'],
            'trial_ends_at' => $data['status'] === self::STATUS_TRIALING && isset($data['current_period_end_date'])
                ? Carbon::parse($data['current_period_end_date'])
                : null,
            'ends_at' => isset($data['canceled_at'])
                ? Carbon::parse($data['canceled_at'])
                : ($data['status'] === self::STATUS_SCHEDULED_CANCEL && isset($data['current_period_end_date'])
                    ? Carbon::parse($data['current_period_end_date'])
                    : null),
            'paused_at' => static::resolvePausedAt($data, $this),
            'current_period_start_at' => isset($data['current_period_start_date'])
                ? Carbon::parse($data['current_period_start_date'])
                : null,
            'current_period_end_at' => isset($data['current_period_end_date'])
                ? Carbon::parse($data['current_period_end_date'])
                : null,
        ])->save();

        $this->syncSubscriptionItems($data);

        return $this;
    }

    public static function resolvePausedAt(array $data, ?self $existing = null): ?Carbon
    {
        if (($data['status'] ?? null) !== self::STATUS_PAUSED) {
            return null;
        }

        if (isset($data['paused_at'])) {
            return Carbon::parse($data['paused_at']);
        }

        return $existing?->paused_at ?? now();
    }

    public function syncSubscriptionItems(array $data): void
    {
        $items = $data['items'] ?? [];
        $productIds = [];

        if (! empty($items)) {
            foreach ($items as $item) {
                $productIds[] = $item['product_id'];

                $this->items()->updateOrCreate(
                    ['product_id' => $item['product_id']],
                    [
                        'creem_id' => $item['id'] ?? null,
                        'price_id' => $item['price_id'] ?? null,
                        'status' => $data['status'],
                        'quantity' => $item['units'] ?? 1,
                    ]
                );
            }
        } else {
            $product = is_array($data['product'] ?? null) ? $data['product'] : null;
            $productId = is_string($data['product'] ?? null) ? $data['product'] : ($product['id'] ?? null);

            if ($productId) {
                $productIds[] = $productId;

                $this->items()->updateOrCreate(
                    ['product_id' => $productId],
                    [
                        'creem_id' => null,
                        'price_id' => null,
                        'status' => $data['status'],
                        'quantity' => 1,
                    ]
                );
            }
        }

        if (! empty($productIds)) {
            $this->items()->whereNotIn('product_id', $productIds)->delete();
        }
    }
}