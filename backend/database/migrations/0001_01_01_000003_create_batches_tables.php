<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_no')->unique()->comment('批次号');
            $table->string('batch_type')->comment('raw_material:原料批次, semi_finished:半成品批次, finished:成品批次');
            $table->unsignedBigInteger('product_id')->nullable()->comment('产品ID,原料批次可为空');
            $table->string('product_name')->comment('产品/原料名称');
            $table->string('product_code')->nullable()->comment('产品/原料编码');
            $table->decimal('quantity', 15, 2)->default(0)->comment('数量');
            $table->string('unit')->nullable()->comment('单位');
            $table->date('production_date')->nullable()->comment('生产日期');
            $table->date('expiry_date')->nullable()->comment('保质期/到期日');
            $table->string('supplier_name')->nullable()->comment('供应商名称(原料批次)');
            $table->string('supplier_batch_no')->nullable()->comment('供应商批次号');
            $table->text('remark')->nullable();
            $table->tinyInteger('is_locked')->default(0)->comment('0:正常 1:已锁定(涉及召回/异常)');
            $table->string('lock_reason')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->tinyInteger('status')->default(1)->comment('1:在库/有效 0:已出库/失效');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['batch_no', 'batch_type']);
            $table->index(['product_id']);
            $table->index(['is_locked']);
        });

        Schema::create('batch_lineages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_batch_id')->comment('父批次ID(原料/半成品)');
            $table->unsignedBigInteger('child_batch_id')->comment('子批次ID(半成品/成品)');
            $table->decimal('usage_quantity', 15, 2)->default(0)->comment('使用数量');
            $table->string('usage_unit')->nullable();
            $table->timestamps();

            $table->index(['parent_batch_id']);
            $table->index(['child_batch_id']);
            $table->unique(['parent_batch_id', 'child_batch_id']);
        });

        Schema::create('batch_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id')->comment('批次ID');
            $table->unsignedBigInteger('store_id')->comment('配送门店ID');
            $table->decimal('delivery_quantity', 15, 2)->default(0)->comment('配送数量');
            $table->string('delivery_unit')->nullable();
            $table->date('delivery_date')->comment('配送日期');
            $table->string('delivery_no')->nullable()->comment('配送单号');
            $table->string('status')->default('delivered')->comment('delivered:已配送, returned:已退回');
            $table->decimal('returned_quantity', 15, 2)->default(0);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['batch_id']);
            $table->index(['store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_deliveries');
        Schema::dropIfExists('batch_lineages');
        Schema::dropIfExists('batches');
    }
};
