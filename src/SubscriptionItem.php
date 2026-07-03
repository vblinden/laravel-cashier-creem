<?php

namespace Laravel\Cashier\Creem;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $product_id
 * @property string|null $creem_id
 * @property string|null $price_id
 * @property string $status
 * @property int $quantity
 */
class SubscriptionItem extends Model
{
    protected $guarded = [];

    public function subscription()
    {
        return $this->belongsTo(Cashier::$subscriptionModel);
    }
}