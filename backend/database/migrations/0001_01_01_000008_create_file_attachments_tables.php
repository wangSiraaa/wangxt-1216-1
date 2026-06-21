<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('original_name')->comment('原始文件名');
            $table->string('stored_name')->comment('存储文件名');
            $table->string('file_path')->comment('文件存储路径');
            $table->unsignedBigInteger('file_size')->comment('文件大小(字节)');
            $table->string('mime_type')->nullable();
            $table->string('file_hash', 64)->comment('文件SHA256哈希,用于校验');
            $table->string('related_type')->nullable()->comment('关联类型: detection_abnormal, recall_task, customer_complaint, store_feedback');
            $table->unsignedBigInteger('related_id')->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->integer('download_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['related_type', 'related_id']);
            $table->index(['file_hash']);
        });

        Schema::create('file_download_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_attachment_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('integrity_verified')->default(false)->comment('文件完整性校验是否通过');
            $table->string('verified_hash', 64)->nullable()->comment('下载时校验的文件哈希');
            $table->timestamps();

            $table->index(['file_attachment_id']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_download_logs');
        Schema::dropIfExists('file_attachments');
    }
};
