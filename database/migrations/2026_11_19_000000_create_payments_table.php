<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('paypal_order_id')->unique();
            $table->string('capture_id')->nullable()->index();
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3);
            $table->string('status', 30)->default('CREATED')->index();
            $table->string('payer_email')->nullable();
            $table->json('paypal_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
