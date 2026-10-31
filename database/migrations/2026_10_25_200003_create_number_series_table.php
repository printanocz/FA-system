<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_series', function (Blueprint $table) {
            $table->id();
            $table->string('doc_type', 20);
            $table->string('prefix', 20)->default('');
            // Tokeny: {YYYY}, {YY}, {N...} (počet N = minimální počet číslic). Výchozí {YYYY}{NNN} → 2026061.
            $table->string('format', 50)->default('{YYYY}{NNN}');
            $table->unsignedInteger('next_number')->default(1);
            $table->boolean('reset_yearly')->default(true);
            // Rok posledního přidělení; podle něj se pozná změna roku (reset čítače).
            $table->unsignedSmallInteger('last_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['doc_type', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_series');
    }
};
