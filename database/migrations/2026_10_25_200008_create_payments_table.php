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
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('CZK');
            $table->date('paid_at');
            $table->string('method', 20)->default('bank_transfer');
            // Vazba na bank_transactions přijde s T-24 (tabulka zatím neexistuje).
            $table->unsignedBigInteger('bank_transaction_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
