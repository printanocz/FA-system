<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // SHA-256 archivního PDF (hex), plní se při uložení archivu.
            $table->string('pdf_sha256', 64)->nullable()->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('pdf_sha256');
        });
    }
};
