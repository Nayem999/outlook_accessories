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
        Schema::create('goods_rcv_msts', function (Blueprint $table) {
            $table->id();
            $table->string('uuid',100)->nullable();
            $table->string('goods_rcv_no',100)->nullable();
            $table->integer('supplier_id')->default(0);
            $table->integer('wo_id')->default(0);
            $table->date('rcv_date')->nullable();
            $table->string('challan_no',100)->nullable();
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
        Schema::dropIfExists('goods_rcv_msts');
    }
};
