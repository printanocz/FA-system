<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('company');
            $table->string('name');
            $table->string('ico', 20)->nullable()->index();
            $table->string('dic', 20)->nullable();
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('country', 2)->default('CZ');
            $table->string('email')->nullable();
            // Další adresy pro kopii e-mailu: pole řetězců.
            $table->json('email_cc')->nullable();
            $table->string('phone', 50)->nullable();
            $table->unsignedSmallInteger('default_due_days')->nullable();
            $table->decimal('default_discount_percent', 5, 2)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('ares_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
