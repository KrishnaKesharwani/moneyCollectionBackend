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
        Schema::table('member_finance_history', function (Blueprint $table) {
            $table->text('details')->nullable()->after('amount_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_finance_history', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
