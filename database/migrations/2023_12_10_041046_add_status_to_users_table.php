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
        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('role_id')->default(0);
            $table->tinyInteger('gender')->default(0);
            $table->date('dob')->nullable();
            $table->string('phone',30)->nullable();
            $table->string('address',200)->nullable();
            $table->tinyInteger('active_status')->default(1)->comment('0=deactive,1=active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
