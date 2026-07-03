<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id');
            $table->string('creem_id')->nullable();
            $table->string('product_id');
            $table->string('price_id')->nullable();
            $table->string('status');
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->unique(['subscription_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
    }
};