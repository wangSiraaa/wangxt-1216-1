<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_feedbacks', function (Blueprint $table) {
            $table->text('unshelved_reason')->nullable()->comment('未下架原因/补充说明')->after('remark');
        });
    }

    public function down(): void
    {
        Schema::table('store_feedbacks', function (Blueprint $table) {
            $table->dropColumn('unshelved_reason');
        });
    }
};
