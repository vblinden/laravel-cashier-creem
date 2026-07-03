<?php

namespace Laravel\Cashier\Creem;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'billed_at' => 'datetime',
        'period_start_at' => 'datetime',
        'period_end_at' => 'datetime',
    ];

    public function billable()
    {
        return $this->morphTo();
    }
}