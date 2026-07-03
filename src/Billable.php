<?php

namespace Laravel\Cashier\Creem;

use Laravel\Cashier\Creem\Concerns\ManagesCustomer;
use Laravel\Cashier\Creem\Concerns\ManagesSubscriptions;
use Laravel\Cashier\Creem\Concerns\ManagesTransactions;
use Laravel\Cashier\Creem\Concerns\PerformsCharges;

trait Billable
{
    use ManagesCustomer;
    use ManagesSubscriptions;
    use ManagesTransactions;
    use PerformsCharges;
}