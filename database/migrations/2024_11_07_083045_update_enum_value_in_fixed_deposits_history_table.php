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
        Schema::table('fixed_deposits_history', function (Blueprint $table) {
            $table->string('debit_type')->change();
        });

        // Step 2: Re-define the column as enum with the updated values and default
        Schema::table('fixed_deposits_history', function (Blueprint $table) {
            $table->enum('debit_type', ['monthly', 'money back'])->default('monthly')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fixed_deposits_history', function (Blueprint $table) {
            $table->enum('debit_type', ['monthly', 'money_back'])->default('monthly')->change();
        });

    }
};
