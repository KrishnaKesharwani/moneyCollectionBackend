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
        Schema::create('loan_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->datetime('receive_date')->nullable();
            $table->integer('receiver_member_id')->default(0);
            $table->foreign('loan_id')->references('id')->on('customer_loans')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_history');
    }
};
