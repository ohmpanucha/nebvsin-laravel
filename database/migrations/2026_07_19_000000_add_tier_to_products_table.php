<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'tier')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('tier', 20)->default('core')->after('price_thb');
            });
        }

        // One-time backfill for existing rows created before tier was an explicit field.
        DB::table('products')->where('price_thb', '>=', 750)->update(['tier' => 'signature']);
        DB::table('products')->where('price_thb', '<=', 400)->where('price_thb', '>', 0)->update(['tier' => 'essential']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'tier')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('tier');
            });
        }
    }
};
