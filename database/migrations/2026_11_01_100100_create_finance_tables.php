<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Club ledger mirroring 02 Finance Record, receipts, payees and fund withdrawals. Money in sen. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_opening_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->unique()->constrained()->cascadeOnDelete();
            $table->bigInteger('amount_sen');
            $table->date('as_of');
            $table->string('note', 500)->nullable();
            $table->foreignId('set_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date')->index();
            $table->string('description');
            $table->unsignedBigInteger('income_sen')->default(0);
            $table->unsignedBigInteger('expense_sen')->default(0);
            $table->nullableMorphs('source');
            $table->foreignId('receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Bank details are encrypted at rest (text columns hold ciphertext).
        Schema::create('payees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('name');
            $table->string('email')->nullable();
            $table->text('contact_no')->nullable();
            $table->string('bank_name');
            $table->text('account_holder');
            $table->text('account_number');
            $table->timestamps();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('meeting_id')->nullable()->after('event_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', fn (Blueprint $table) => $table->dropColumn('meeting_id'));
        foreach (['payees', 'ledger_entries', 'receipts', 'club_opening_balances'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
