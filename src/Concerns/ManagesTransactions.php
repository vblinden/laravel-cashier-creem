<?php

namespace Laravel\Cashier\Creem\Concerns;

use Laravel\Cashier\Creem\Cashier;

trait ManagesTransactions
{
    public function transactions()
    {
        return $this->morphMany(Cashier::$transactionModel, 'billable')->orderByDesc('created_at');
    }
}