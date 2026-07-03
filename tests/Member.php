<?php

namespace Laravel\Cashier\Creem\Tests;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Cashier\Creem\Billable;

class Member extends Authenticatable
{
    use Billable;

    protected $table = 'members';

    protected $guarded = [];
}
