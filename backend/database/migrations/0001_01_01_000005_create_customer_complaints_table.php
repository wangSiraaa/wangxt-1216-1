<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_complaints', function (Blueprint $table) {
            $table->id();
            $table->string('complaint_no')->unique()->comment('投诉单号');
            $table->string('complaint_type')->comment('allergy:过敏反应, food_poisoning:食物中毒, foreign_matter:异物, quality:品质问题, packaging:包装问题, other:其他');
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->unsignedBigInteger('store_id')->nullable()->comment('投诉门店');
            $table->string('product_name')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('batch_no')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->date('purchase_date')->nullable();
            $table->dateTime('occurrence_time')->nullable();
            $table->text('description')->comment('投诉描述');
            $table->text('symptoms')->nullable()->comment('症状描述');
            $table->string('severity')->default('general')->comment('mild:轻微, general:一般, serious:严重, critical:危重');
            $table->text('handling_process')->nullable()->comment('处理过程');
            $table->string('status')->default('pending')->comment('pending:待处理, processing:处理中, resolved:已解决, closed:已关闭');
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution')->nullable()->comment('解决方案');
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
            $table->index(['batch_id']);
            $table->index(['store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_complaints');
    }
};
