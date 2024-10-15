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
        Schema::create('loan_status_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->enum('loan_status',['pending','approved','cancelled','completed','other','paid']);
            $table->text('loan_status_message')->nullable();
            $table->integer('loan_status_changed_by');
            $table->datetime('loan_status_change_date')->nullable();
            $table->foreign('loan_id')->references('id')->on('customer_loans')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_status_history');
    }
};
