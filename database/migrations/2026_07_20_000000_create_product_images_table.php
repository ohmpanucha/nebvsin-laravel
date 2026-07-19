<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateProductImagesTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('product_images')) {
            Schema::create('product_images', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('product_id');
                $table->string('image_path', 512);
                $table->string('alt', 512)->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->index(['product_id', 'sort_order'], 'product_images_product_sort_idx');
                $table->foreign('product_id')
                    ->references('id')
                    ->on('products')
                    ->onDelete('cascade');
            });
        }

        if (Schema::hasTable('products')) {
            DB::table('products')
                ->whereNotNull('image')
                ->where('image', '<>', '')
                ->orderBy('id')
                ->get(['id', 'image', 'alt'])
                ->each(function ($product) {
                    $exists = DB::table('product_images')
                        ->where('product_id', $product->id)
                        ->where('image_path', $product->image)
                        ->exists();

                    if (! $exists) {
                        DB::table('product_images')->insert([
                            'product_id' => $product->id,
                            'image_path' => $product->image,
                            'alt' => $product->alt,
                            'sort_order' => 0,
                            'is_primary' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });
        }
    }

    public function down()
    {
        Schema::dropIfExists('product_images');
    }
}
