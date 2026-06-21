<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detection_abnormals', function (Blueprint $table) {
            $table->id();
            $table->string('abnormal_no')->unique()->comment('异常单号');
            $table->string('abnormal_type')->comment('allergen:过敏原异常, microbe:微生物检测异常, physical:物理指标, chemical:化学指标, other:其他');
            $table->unsignedBigInteger('batch_id')->nullable()->comment('关联批次ID');
            $table->string('batch_no')->nullable()->comment('批次号');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name')->nullable();
            $table->string('detection_item')->comment('检测项目(如:沙门氏菌,花生过敏原)');
            $table->string('detection_value')->nullable()->comment('检测值');
            $table->string('standard_value')->nullable()->comment('标准值/阈值');
            $table->text('description')->comment('异常描述');
            $table->string('detection_report_no')->nullable()->comment('检测报告编号');
            $table->date('detection_date')->nullable()->comment('检测日期');
            $table->string('status')->default('pending')->comment('pending:待确认, confirmed:已确认, rejected:已驳回, resolved:已处理');
            $table->dateTime('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->text('confirm_remark')->nullable();
            $table->unsignedBigInteger('reported_by')->comment('登记人ID');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
            $table->index(['batch_id']);
            $table->index(['abnormal_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detection_abnormals');
    }
};
