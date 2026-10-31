<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('type', 16); // invoice | reminder
            $table->string('to_address');
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->string('subject');
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 16); // sent | failed
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_log');
    }
};
