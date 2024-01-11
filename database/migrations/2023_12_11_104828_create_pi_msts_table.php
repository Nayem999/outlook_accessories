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
        Schema::create('pi_msts', function (Blueprint $table) {
            $table->id();
            $table->string('uuid',100)->nullable();
            $table->string('pi_no',100)->nullable();
            $table->integer('company_id')->default(0);
            $table->integer('buyer_id')->default(0);
            $table->integer('bank_id')->default(0);
            $table->date('pi_date')->nullable();
            $table->date('pi_validity_date')->nullable();
            $table->date('last_shipment_date')->nullable();
            $table->string('remarks',200)->nullable();
            $table->float('pi_value')->default(0)->nullable();
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
        Schema::dropIfExists('pi_msts');
    }
};
