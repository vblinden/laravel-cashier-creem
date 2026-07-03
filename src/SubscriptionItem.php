<?php

namespace Laravel\Cashier\Creem;

use Illuminate\Database\Eloquent\Model;

class SubscriptionItem extends Model
{
    protected $guarded = [];

    public function subscription()
    {
        return $this->belongsTo(Cashier::$subscriptionModel);
    }
}