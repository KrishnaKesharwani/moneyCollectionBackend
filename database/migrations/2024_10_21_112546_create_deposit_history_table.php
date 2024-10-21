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
        Schema::create('deposit_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deposit_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->enum('action_type', ['credit', 'debit'])->nullable();
            $table->date('action_date')->nullable();
            $table->integer('receiver_member_id')->default(0);
            $table->foreign('deposit_id')->references('id')->on('customer_deposits')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_history');
    }
};
