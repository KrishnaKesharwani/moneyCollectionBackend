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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('owner_name');
            $table->string('mobile')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('aadhar_no')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('advance_amount', 15, 2)->default(0);
            $table->enum('status', ['active', 'inactive']);
            $table->string('main_logo')->nullable();
            $table->string('sidebar_logo')->nullable();
            $table->string('favicon_icon')->nullable();
            $table->string('owner_image')->nullable();
            $table->text('address')->nullable();
            $table->text('details')->nullable();
            $table->timestamps();
            // Add soft deletes
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
