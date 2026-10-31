<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profile', function (Blueprint $table) {
            $table->id();
            // Singleton: unikátní sloupec s pevnou hodnotou dovolí v tabulce jediný řádek.
            $table->boolean('singleton')->default(true)->unique();
            $table->string('name');
            $table->string('ico', 20)->nullable();
            $table->string('dic', 20)->nullable();
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('country', 2)->default('CZ');
            // Údaj o zápisu, např. „Fyzická osoba zapsaná v živnostenském rejstříku“.
            $table->string('registration_note')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('web')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->boolean('vat_payer')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profile');
    }
};
