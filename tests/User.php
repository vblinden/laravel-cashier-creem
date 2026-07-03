<?php

namespace Laravel\Cashier\Creem\Tests;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Cashier\Creem\Billable;

class User extends Authenticatable
{
    use Billable;

    protected $guarded = [];
}