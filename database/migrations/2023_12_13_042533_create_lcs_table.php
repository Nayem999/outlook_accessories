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
        Schema::create('lcs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid',100)->nullable();
            $table->string('lc_no',100)->nullable();
            $table->integer('company_id')->default(0)->nullable();
            $table->integer('buyer_id')->default(0)->nullable();
            $table->string('contract_no',100)->nullable();
            $table->date('contract_date')->nullable();
            $table->string('letter_of_credit_no',50)->nullable();
            $table->date('letter_of_credit_date')->nullable();
            $table->date('lc_issue_date')->nullable();
            $table->date('lc_expiry_date')->nullable();
            $table->integer('currency_id')->default(0)->nullable();
            $table->decimal('lc_value',11,2)->default(0)->nullable();
            $table->integer('opening_bank_id')->default(0)->nullable();
            $table->integer('advising_bank_id')->default(0)->nullable();
            $table->string('amendment_no',10)->nullable();
            $table->date('amendment_date')->nullable();
            $table->tinyInteger('pay_term_id')->default(1)->comment('1=At Sight,2=Usance');
            $table->string('tenor',10)->nullable();
            $table->string('tolerance',10)->nullable();
            $table->string('port_of_loading',100)->nullable();
            $table->string('port_of_discharge',100)->nullable();
            $table->date('last_shipment_date')->nullable();
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
        Schema::dropIfExists('lcs');
    }
};
