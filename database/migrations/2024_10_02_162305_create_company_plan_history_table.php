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
        Schema::create('company_plan_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('pay_date')->nullable();
            $table->foreign('plan_id')->references('id')->on('company_plans')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_plan_history');
    }
};
