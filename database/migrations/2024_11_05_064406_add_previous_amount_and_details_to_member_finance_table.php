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
        Schema::table('member_finance', function (Blueprint $table) {
            $table->decimal('previous_balance', 15, 2)->default(0)->after('remaining_amount');
            $table->string('details')->nullable()->after('previous_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_finance', function (Blueprint $table) {
            $table->dropColumn('previous_balance');
            $table->dropColumn('details');
        });
    }
};
