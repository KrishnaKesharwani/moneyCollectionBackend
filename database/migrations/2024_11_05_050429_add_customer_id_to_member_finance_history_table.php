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
            $table->integer('customer_id')->default(0)->after('amount_by_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_finance_history', function (Blueprint $table) {
            $table->dropColumn('customer_id');
        });
    }
};
