<?php

namespace Laravel\Cashier\Creem\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Laravel\Cashier\Creem\Transaction;

class TransactionRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Model $billable,
        public Transaction $transaction,
        public array $payload
    ) {
    }
}