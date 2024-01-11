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
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn('gender');
            $table->dropColumn('dob');

            $table->tinyInteger('account_type')->nullable()->default(0)->comment('1=Payable,2=Receiveable')->after('address');
            $table->string('contact_person_name',100)->nullable()->after('address');
            $table->string('contact_person_email',100)->nullable()->after('contact_person_name');
            $table->string('contact_person_phone',30)->nullable()->after('contact_person_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            //
        });
    }
};
