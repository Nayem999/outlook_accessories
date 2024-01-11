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
        Schema::table('quotation_dtls', function (Blueprint $table) {
            $table->dropColumn('file_image');

            $table->integer('quotation_type')->default(0)->after('quotation_id');
            $table->integer('order_inquire_dtls_id')->default(0)->after('quotation_type');
            $table->float('price')->default(0)->nullable()->after('qnty');
            $table->float('amount')->default(0)->nullable()->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_dtls', function (Blueprint $table) {
            //
        });
    }
};
