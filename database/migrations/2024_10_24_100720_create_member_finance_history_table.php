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
        Schema::create('member_finance_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_finance_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->enum('amount_by', ['advance','loan','deposit']);
            $table->integer('amount_by_id')->default(0);
            $table->enum('amount_type',['credit','debit']);
            $table->date('amount_date');
            $table->foreign('member_finance_id')->references('id')->on('member_finance')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_finance_history');
    }
};
