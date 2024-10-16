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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->default(0);
            $table->unsignedBigInteger('company_id');
            $table->string('customer_no');
            $table->string('name');
            $table->string('mobile')->nullable();
            $table->string('email')->unique();
            $table->date('join_date');
            $table->string('aadhar_no')->nullable();
            $table->string('image')->nullable();
            $table->text('address')->nullable();
            $table->integer('created_by')->default(0);
            $table->enum('status', ['active', 'inactive']);
            $table->integer('loan_count')->default(100);
            $table->timestamps();
            // Add soft deletes
            $table->softDeletes();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
