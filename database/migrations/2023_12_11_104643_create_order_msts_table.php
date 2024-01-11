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
        Schema::create('order_msts', function (Blueprint $table) {
            $table->id();
            $table->string('uuid',100)->nullable();
            $table->string('order_no',100)->nullable();
            $table->integer('company_id')->default(0);
            $table->integer('buyer_id')->default(0);
            $table->integer('inquire_id')->default(0)->nullable();
            $table->date('order_date')->nullable();
            $table->date('delivery_req_date')->nullable();
            $table->string('merchandiser_name',100)->nullable();
            $table->string('merchandiser_phone',30)->nullable();
            $table->string('order_person',100)->nullable();
            $table->string('attntion',100)->nullable();
            $table->year('season_year')->nullable();
            $table->tinyInteger('season')->default(0)->nullable()->comment('1=Summer,2=Rain,3=Winter');
            $table->string('file_image',200)->nullable();
            $table->string('remarks',200)->nullable();
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
        Schema::dropIfExists('order_msts');
    }
};
