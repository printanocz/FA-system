<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_items', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('note')->index();
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('price_items', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('archived_at');
        });
    }
};
