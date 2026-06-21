<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recall_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('recall_no')->unique()->comment('召回任务编号');
            $table->string('title')->comment('召回标题');
            $table->text('description')->comment('召回描述');
            $table->string('recall_level')->default('level3')->comment('level1:一级(严重), level2:二级(较重), level3:三级(一般)');
            $table->string('recall_reason_type')->comment('allergen:过敏原, microbe:微生物, quality:品质, complaint:客诉, other:其他');
            $table->unsignedBigInteger('detection_abnormal_id')->nullable()->comment('关联检测异常ID');
            $table->unsignedBigInteger('customer_complaint_id')->nullable()->comment('关联客诉ID');
            $table->string('status')->default('draft')->comment('draft:草稿, pending:待发布, published:已发布, cancelled:已取消, completed:已完成');
            $table->dateTime('published_at')->nullable()->comment('公告发布时间');
            $table->unsignedBigInteger('published_by')->nullable();
            $table->text('announcement_content')->nullable()->comment('公告内容');
            $table->dateTime('expected_completion_date')->nullable()->comment('预期完成日期');
            $table->dateTime('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->text('summary')->nullable()->comment('召回总结');
            $table->unsignedBigInteger('created_by')->comment('创建人');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
            $table->index(['recall_level']);
        });

        Schema::create('recall_task_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recall_task_id');
            $table->unsignedBigInteger('batch_id');
            $table->string('batch_no');
            $table->string('batch_type');
            $table->string('product_name');
            $table->decimal('total_quantity', 15, 2)->default(0)->comment('涉及总数量');
            $table->decimal('delivered_quantity', 15, 2)->default(0)->comment('已配送数量');
            $table->timestamps();

            $table->index(['recall_task_id']);
            $table->index(['batch_id']);
            $table->unique(['recall_task_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recall_task_batches');
        Schema::dropIfExists('recall_tasks');
    }
};
