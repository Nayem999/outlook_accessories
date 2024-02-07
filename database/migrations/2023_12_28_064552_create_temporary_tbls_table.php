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
        Schema::create('temporary_tbls', function (Blueprint $table) {
            $table->date('date')->nullable();
            $table->string('trans_type',100)->nullable();
            $table->integer('dr_amount')->default(0)->nullable();
            $table->integer('cr_amount')->default(0)->nullable();
            $table->integer('entry_form')->default(0)->nullable();
            $table->integer('ext_key')->default(0)->nullable();
            $table->string('ext_val',100)->nullable();
            $table->string('ext_val2',100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temporary_tbls');
    }
};
