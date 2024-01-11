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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid',100)->nullable();
            $table->tinyInteger('trans_type_id')->default(0)->comment('1=Income,2=Expenses');
            $table->tinyInteger('party_type_id')->default(0)->comment('1=Company,2=Buyer,3=Supplier,4=Employee,5=Others');
            $table->integer('party_id')->default(0);
            $table->integer('trans_purpose_id')->default(0);
            $table->tinyInteger('trans_method_id')->default(0)->comment('1=Cash,2=Bank Check,3=TT,4=Bank Deposit,5=LC Payment Receive');
            $table->integer('bank_id')->default(0)->nullable();
            $table->string('check_number',100)->nullable();
            $table->integer('transfer_method_id')->default(0)->nullable();
            $table->float('amount',8,2)->default(0);
            $table->date('date')->nullable();
            $table->string('note',200)->nullable();
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
        Schema::dropIfExists('transactions');
    }
};
