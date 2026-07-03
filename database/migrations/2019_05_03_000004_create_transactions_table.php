<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->morphs('billable');
            $table->string('creem_id')->unique();
            $table->string('creem_subscription_id')->nullable()->index();
            $table->string('order_id')->nullable();
            $table->string('status');
            $table->string('total');
            $table->string('tax')->default('0');
            $table->string('currency', 3);
            $table->timestamp('billed_at');
            $table->timestamp('period_start_at')->nullable();
            $table->timestamp('period_end_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};