<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pix_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_id')->constrained('movements')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('pix_id')->nullable()->index();
            $table->string('transaction_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('PENDING')->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pix_payments');
    }
};
