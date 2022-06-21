<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateDhlsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dhls', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->comment('快递公司名称');
            $table->string('abbreviation', 80)->comment('快递公司英文缩写');
            $table->tinyInteger('is_default')->default(0)->comment('是否默认 0 普通 1默认');
            $table->tinyInteger('state')->default(0)->comment('状态0显示1隐藏');
            $table->integer('sort')->default(5)->comment('排序');
            $table->softDeletes();
            $table->timestamps();
            $table->unique('id');
        });
        DB::statement("ALTER TABLE `dhls` COMMENT='快递公司'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dhls');
    }
}
