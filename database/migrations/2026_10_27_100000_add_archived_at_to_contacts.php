<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Kontakt s doklady se nemaže, jen archivuje: doklady na něj dál odkazují.
            $table->timestamp('archived_at')->nullable()->after('ares_synced_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
