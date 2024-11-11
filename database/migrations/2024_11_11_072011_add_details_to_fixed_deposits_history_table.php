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
            $table->text('details')->nullable()->after('debit_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fixed_deposits_history', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};