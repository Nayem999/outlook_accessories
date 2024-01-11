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
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->integer('role_id')->default(0);
            $table->integer('user_id')->default(0);
            $table->string('module',100);
            $table->tinyInteger('view')->default(0)->nullable();
            $table->tinyInteger('edit')->default(0)->nullable();
            $table->tinyInteger('delete')->default(0)->nullable();
            $table->integer('created_by')->default(0)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
    }
};
