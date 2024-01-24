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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('uuid',100)->nullable();
            $table->integer('party_type_id')->default(0);
            $table->integer('party_id')->default(0);
            $table->integer('purpose_id')->default(0);
            $table->date('service_date')->nullable();
            $table->double('amount',11,2)->default(0)->nullable();
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
        Schema::dropIfExists('services');
    }
};
