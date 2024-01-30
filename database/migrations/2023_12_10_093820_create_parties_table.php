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
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->string('uuid',100)->nullable();
            $table->string('name',100);
            $table->tinyInteger('party_type_id')->default(0)->comment('1=Company,2=Buyer,3=Supplier,4=Employee,5=Other');;
            $table->string('email',100)->nullable();
            $table->tinyInteger('gender')->default(0);
            $table->date('dob')->nullable();
            $table->string('phone',30)->nullable();
            $table->string('address',200)->nullable();
            $table->string('bin_no',100)->nullable();
            $table->string('irc',100)->nullable();
            $table->string('tin',100)->nullable();
            $table->integer('opening_balance')->nullable();
            $table->integer('trans_id')->default(0);
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
        Schema::dropIfExists('parties');
    }
};
