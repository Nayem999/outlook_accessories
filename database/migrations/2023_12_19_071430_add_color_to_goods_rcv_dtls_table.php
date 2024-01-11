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
        Schema::table('goods_rcv_dtls', function (Blueprint $table) {
            $table->integer('product_id')->default(0)->after('wo_dtls_id');
            $table->string('style',100)->nullable()->after('product_id');
            $table->integer('size_id')->default(0)->after('style');
            $table->integer('color_id')->default(0)->after('size_id');
            $table->integer('unit_id')->default(0)->after('color_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_rcv_dtls', function (Blueprint $table) {
            //
        });
    }
};
