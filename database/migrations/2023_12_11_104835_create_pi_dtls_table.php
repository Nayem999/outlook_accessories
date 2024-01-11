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
        Schema::create('pi_dtls', function (Blueprint $table) {
            $table->id();
            $table->integer('pi_id')->default(0)->nullable();
            $table->integer('wo_id')->default(0)->nullable();
            $table->integer('wo_dtls_id')->default(0)->nullable();
            $table->integer('order_id')->default(0)->nullable();
            $table->integer('order_dtls_id')->default(0)->nullable();
            $table->integer('qnty')->default(0)->nullable();
            $table->float('price')->default(0)->nullable();
            $table->float('amount')->default(0)->nullable();
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
        Schema::dropIfExists('pi_dtls');
    }
};
