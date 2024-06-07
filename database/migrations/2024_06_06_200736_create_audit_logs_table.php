<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('credit_transaction_id')->nullable();
            $table->unsignedBigInteger('debit_transaction_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->text('description')->nullable();
            $table->enum('status', ['success', 'failure', 'pending'])->default('pending');
            $table->text('error')->nullable();
            $table->boolean('anomaly')->default(false);
            $table->string('anomaly_type')->nullable();
            $table->timestamps();
            
            $table->foreign('credit_transaction_id')->references('id')->on('credit_transactions')->onDelete('set null');
            $table->foreign('debit_transaction_id')->references('id')->on('debit_transactions')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
