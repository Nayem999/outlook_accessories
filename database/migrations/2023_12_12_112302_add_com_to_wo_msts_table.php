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
        Schema::table('wo_msts', function (Blueprint $table) {
            $table->integer('company_id')->default(0)->after('id');
            $table->integer('buyer_id')->default(0)->after('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wo_msts', function (Blueprint $table) {
            //
        });
    }
};
