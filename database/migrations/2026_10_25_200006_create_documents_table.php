<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            // Číslo se přidělí až při vystavení, koncept ho nemá.
            $table->string('number', 50)->nullable();
            $table->foreignId('series_id')->nullable()->constrained('number_series')->restrictOnDelete();
            // Rok a pořadí čísla v řadě: určují „poslední doklad v řadě“.
            $table->unsignedSmallInteger('number_year')->nullable();
            $table->unsignedInteger('number_seq')->nullable();
            $table->string('status', 20)->default('draft');

            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->json('buyer_snapshot')->nullable();
            $table->json('supplier_snapshot')->nullable();

            $table->date('issue_date')->nullable();
            $table->date('taxable_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('valid_until')->nullable();

            $table->string('currency', 3)->default('CZK');
            $table->decimal('exchange_rate', 15, 6)->nullable();
            $table->string('language', 2)->default('cs');

            $table->string('payment_method', 20)->default('bank_transfer');
            // Účet vybraný na konceptu; při vystavení se uloží snapshot.
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->json('bank_account_snapshot')->nullable();
            $table->string('vs', 20)->nullable();
            $table->string('ks', 10)->nullable();
            $table->string('ss', 20)->nullable();
            $table->string('order_number', 50)->nullable();

            $table->text('text_before')->nullable();
            $table->text('text_after')->nullable();
            $table->text('note_internal')->nullable();

            $table->decimal('discount_percent', 5, 2)->default(0);
            // Zaokrouhlení na celé jednotky měny: příznak a vypočtená částka (+/-).
            $table->boolean('round_total')->default(false);
            $table->decimal('rounding', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('vat_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('paid_total', 15, 2)->default(0);
            $table->date('paid_at')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedSmallInteger('reminders_count')->default(0);
            $table->timestamp('last_reminder_at')->nullable();
            $table->foreignId('related_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('public_token', 64)->nullable()->unique();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->unique(['series_id', 'number']);
            $table->index(['type', 'status']);
            $table->index('due_date');
            $table->index('issue_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
