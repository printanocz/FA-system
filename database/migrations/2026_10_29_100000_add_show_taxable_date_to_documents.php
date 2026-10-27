<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Řídí jen zobrazení DUZP na PDF a v detailu; datum (taxable_date) se vystavením doplní vždy.
            $table->boolean('show_taxable_date')->default(false)->after('taxable_date');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('show_taxable_date');
        });
    }
};
