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
        Schema::table('deposit_member_history', function (Blueprint $table) {
            Schema::rename('deposite_member_history', 'deposit_member_history');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deposit_member_history', function (Blueprint $table) {
            Schema::rename('deposite_member_history', 'deposit_member_history');
        });
    }
};
