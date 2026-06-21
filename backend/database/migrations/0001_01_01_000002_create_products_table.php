<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('specification')->nullable()->comment('规格');
            $table->string('category')->nullable()->comment('产品类别');
            $table->text('allergens')->nullable()->comment('过敏原信息,JSON数组');
            $table->text('ingredients')->nullable()->comment('配料清单');
            $table->tinyInteger('status')->default(1)->comment('1:在售 0:停用');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
