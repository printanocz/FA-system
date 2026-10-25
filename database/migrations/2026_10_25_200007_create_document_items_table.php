<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('qty', 15, 4)->default(1);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->foreignId('price_item_id')->nullable()->constrained('price_items')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_items');
    }
};
