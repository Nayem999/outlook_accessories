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
        Schema::create('doc_acpt_dtls', function (Blueprint $table) {
            $table->id();
            $table->integer('doc_acpt_id')->default(0)->nullable();
            $table->integer('doc_id')->default(0)->nullable();
            $table->tinyInteger('doc_where_id')->default(0)->nullable();
            $table->string('original',20)->default(0)->nullable();
            $table->string('file_image',200)->nullable();
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
        Schema::dropIfExists('doc_acpt_dtls');
    }
};
