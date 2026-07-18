<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SyncProductsTableForStorefront extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->integer('id')->primary();
                $table->string('name');
                $table->integer('price_thb')->default(0);
                $table->string('image', 512);
                $table->string('alt', 512)->nullable();
                $table->text('description')->nullable();
                $table->integer('sort_order')->default(0);
                $table->integer('limited_qty')->default(40);
                $table->boolean('is_public')->default(true);
                $table->boolean('coming_soon')->default(false);
                $table->string('slug')->nullable()->unique();
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->string('og_image', 512)->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
            });

            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'description')) {
                $table->text('description')->nullable()->after('alt');
            }

            if (! Schema::hasColumn('products', 'limited_qty')) {
                $table->integer('limited_qty')->default(40)->after('sort_order');
            }

            if (! Schema::hasColumn('products', 'is_public')) {
                $table->boolean('is_public')->default(true)->after('limited_qty');
            }

            if (! Schema::hasColumn('products', 'coming_soon')) {
                $table->boolean('coming_soon')->default(false)->after('is_public');
            }

            if (! Schema::hasColumn('products', 'slug')) {
                $table->string('slug')->nullable()->unique()->after('coming_soon');
            }

            if (! Schema::hasColumn('products', 'meta_title')) {
                $table->string('meta_title')->nullable()->after('slug');
            }

            if (! Schema::hasColumn('products', 'meta_description')) {
                $table->text('meta_description')->nullable()->after('meta_title');
            }

            if (! Schema::hasColumn('products', 'og_image')) {
                $table->string('og_image', 512)->nullable()->after('meta_description');
            }
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'og_image')) {
                $table->dropColumn('og_image');
            }

            if (Schema::hasColumn('products', 'meta_description')) {
                $table->dropColumn('meta_description');
            }

            if (Schema::hasColumn('products', 'meta_title')) {
                $table->dropColumn('meta_title');
            }

            if (Schema::hasColumn('products', 'slug')) {
                $table->dropUnique(['slug']);
                $table->dropColumn('slug');
            }
        });
    }
}
