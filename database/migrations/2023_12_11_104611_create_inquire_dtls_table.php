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
        Schema::create('inquire_dtls', function (Blueprint $table) {
            $table->id();
            $table->integer('inquire_id')->default(0);
            $table->integer('product_id')->default(0);
            $table->string('style',100)->nullable();
            $table->integer('size_id')->nullable()->default(0);
            $table->integer('color_id')->nullable()->default(0);
            $table->integer('unit_id')->nullable()->default(0);
            $table->integer('qnty')->default(0);
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
        Schema::dropIfExists('inquire_dtls');
    }
};
