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
        Schema::table('deposit_history', function (Blueprint $table) {
            // Change action_date column from date to datetime
            $table->datetime('action_date')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deposit_history', function (Blueprint $table) {
            // Revert action_date column back to date
            $table->date('action_date')->change();
        });
    }
};
