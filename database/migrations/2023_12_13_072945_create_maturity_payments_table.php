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
        Schema::create('maturity_payments', function (Blueprint $table) {
            $table->id();
            $table->string('uuid',100)->nullable();
            $table->integer('lc_id')->default(0)->nullable();
            $table->string('lc_num',100)->nullable();
            $table->decimal('lc_value',11,2)->default(0)->nullable();
            $table->integer('doc_acceptace_id')->default(0)->nullable();
            $table->date('payment_date')->nullable();
            $table->float('exchange_rate')->nullable();
            $table->decimal('amount',11,2)->nullable();
            $table->string('remarks')->nullable();
            $table->integer('trans_id')->default(0)->nullable();
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
        Schema::dropIfExists('maturity_payments');
    }
};
