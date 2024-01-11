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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name',100)->nullable();
            $table->string('company_email',100)->nullable();
            $table->string('logo',200)->nullable();
            $table->string('signature',200)->nullable();
            $table->string('office_add',200)->nullable();
            $table->string('office_phone',30)->nullable();
            $table->string('head_office_add',200)->nullable();
            $table->string('head_office_phone',30)->nullable();
            $table->string('tin_number',50)->nullable();
            $table->string('bin_number',50)->nullable();
            $table->tinyInteger('active_status')->default(1)->comment('1=active,2=deactive');
            $table->integer('created_by')->default(0);
            $table->integer('updated_by')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
