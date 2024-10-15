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
        Schema::create('customer_loans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('customer_id');
            $table->decimal('loan_amount', 10, 2)->default(0);
            $table->decimal('installment_amount', 10, 2)->default(0);
            $table->integer('no_of_days')->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('assigned_member_id')->default(0);
            $table->text('details')->nullable();
            $table->integer('created_by')->default(0);
            $table->enum('status', ['active', 'inactive']);
            $table->enum('loan_status',['pending','approved','cancelled','completed','other','paid']);
            $table->text('loan_status_message')->nullable();
            $table->integer('loan_status_changed_by');
            $table->datetime('loan_status_change_date')->nullable();
            $table->softDeletes();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_loans');
    }
};
