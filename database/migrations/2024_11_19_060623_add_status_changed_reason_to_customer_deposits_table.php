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
        Schema::table('customer_deposits', function (Blueprint $table) {
            $table->string('status_changed_reason')->nullable();
            $table->integer('status_changed_by')->default(0);
            $table->timestamp('status_changed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_deposits', function (Blueprint $table) {
            $table->dropColumn('status_changed_reason');
            $table->dropColumn('status_changed_by');
            $table->dropColumn('status_changed_at');
        });
    }
};
