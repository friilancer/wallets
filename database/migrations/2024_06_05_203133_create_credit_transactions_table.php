<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
CREATE TABLE transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    wallet_id INTEGER NOT NULL,
    transaction_type TEXT NOT NULL CHECK(transaction_type IN ('credit', 'debit')),
    amount REAL NOT NULL CHECK(amount > 0),
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    description TEXT,
    FOREIGN KEY (wallet_id) REFERENCES wallets(id)
);
*/
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('transaction_reference', 50)->unique()->default('TRN-' . Str::uuid());
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
