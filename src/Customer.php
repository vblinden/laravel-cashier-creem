<?php

namespace Laravel\Cashier\Creem;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $creem_id
 * @property string $email
 * @property string $name
 * @property Carbon|null $trial_ends_at
 */
class Customer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    public function billable()
    {
        return $this->morphTo();
    }

    public function onGenericTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function hasExpiredGenericTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }
}