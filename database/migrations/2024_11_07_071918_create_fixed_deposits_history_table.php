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
        Schema::create('fixed_deposits_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fixed_deposit_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->enum('action_type', ['debit'])->nullable();
            $table->date('action_date')->nullable();
            $table->enum('debit_type', ['monthly', 'money_back'])->default('monthly');
            $table->foreign('fixed_deposit_id')->references('id')->on('fixed_deposits')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_deposits_history');
    }
};
