<?php

use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Creem\Http\Controllers\WebhookController;

Route::post('/webhook', WebhookController::class)->name('webhook');