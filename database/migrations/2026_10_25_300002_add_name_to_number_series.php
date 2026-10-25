<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('number_series', function (Blueprint $table) {
            // Název řady pro Nastavení („Faktury vydané“). Povinný; stávající řady se doplní níže.
            $table->string('name', 100)->default('')->after('id');
        });

        $names = [
            'invoice' => 'Faktury vydané',
            'proforma' => 'Zálohové faktury',
            'credit_note' => 'Dobropisy',
            'quote' => 'Cenové nabídky',
        ];

        foreach ($names as $type => $name) {
            DB::table('number_series')->where('doc_type', $type)->where('name', '')->update(['name' => $name]);
        }
    }

    public function down(): void
    {
        Schema::table('number_series', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
