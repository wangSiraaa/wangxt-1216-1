<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recall_task_id')->comment('召回任务ID');
            $table->unsignedBigInteger('store_id')->comment('门店ID');
            $table->string('store_name')->nullable();
            $table->string('store_code')->nullable();
            $table->decimal('received_quantity', 15, 2)->default(0)->comment('应下架数量(配送数量)');
            $table->decimal('off_shelf_quantity', 15, 2)->default(0)->comment('实际下架数量');
            $table->decimal('remaining_quantity', 15, 2)->default(0)->comment('剩余未下架数量');
            $table->decimal('returned_quantity', 15, 2)->default(0)->comment('退回总部数量');
            $table->decimal('destroyed_quantity', 15, 2)->default(0)->comment('门店销毁数量');
            $table->decimal('sold_quantity', 15, 2)->default(0)->comment('已售出数量');
            $table->string('status')->default('pending')->comment('pending:待反馈, submitted:已反馈, overdue:逾期未报, confirmed:总部已确认');
            $table->text('remark')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->boolean('is_overdue')->default(false)->comment('是否逾期未报');
            $table->boolean('is_missing')->default(false)->comment('是否漏报(总部看板标红)');
            $table->timestamps();

            $table->index(['recall_task_id']);
            $table->index(['store_id']);
            $table->index(['status']);
            $table->index(['is_missing']);
            $table->unique(['recall_task_id', 'store_id']);
        });

        Schema::create('store_feedback_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_feedback_id');
            $table->unsignedBigInteger('batch_id');
            $table->string('batch_no');
            $table->string('product_name');
            $table->decimal('received_quantity', 15, 2)->default(0);
            $table->decimal('off_shelf_quantity', 15, 2)->default(0);
            $table->decimal('returned_quantity', 15, 2)->default(0);
            $table->decimal('destroyed_quantity', 15, 2)->default(0);
            $table->decimal('sold_quantity', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['store_feedback_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_feedback_items');
        Schema::dropIfExists('store_feedbacks');
    }
};
