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
        Schema::create('deposit_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deposit_id');
            $table->decimal('request_amount', 10, 2)->default(0);
            $table->text('reason')->nullable();
            $table->integer('requested_by')->default(0);
            $table->enum('status', ['pending', 'approved'])->default('pending');
            $table->timestamp('request_date')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreign('deposit_id')->references('id')->on('customer_deposits')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_requests');
    }
};
