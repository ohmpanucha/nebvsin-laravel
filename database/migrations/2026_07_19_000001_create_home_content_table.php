<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('home_content')) {
            return;
        }

        Schema::create('home_content', function (Blueprint $table) {
            $table->id();

            // Hero
            $table->string('hero_eyebrow', 255)->default('');
            $table->text('hero_subtitle')->nullable();
            $table->string('hero_cta_primary_label', 100)->default('');
            $table->string('hero_cta_secondary_label', 100)->default('');
            $table->string('hero_image', 512)->nullable();

            // Featured core
            $table->string('feature_eyebrow', 255)->default('');
            $table->string('feature_title', 255)->default('');
            $table->text('feature_note')->nullable();
            $table->string('feature_kicker', 255)->default('');
            $table->string('feature_heading_line1', 255)->default('');
            $table->string('feature_heading_line2', 255)->default('');
            $table->text('feature_copy')->nullable();
            $table->string('feature_cta_label', 100)->default('');
            $table->string('feature_image', 512)->nullable();

            // Signature
            $table->string('signature_kicker', 255)->default('');
            $table->string('signature_heading_line1', 255)->default('');
            $table->string('signature_heading_line2', 255)->default('');
            $table->string('signature_limited_line1', 255)->default('');
            $table->string('signature_limited_line2', 255)->default('');
            $table->string('signature_limited_line3', 255)->default('');
            $table->text('signature_copy')->nullable();
            $table->string('signature_cta_label', 100)->default('');
            $table->string('signature_image', 512)->nullable();

            // Manifesto
            $table->string('manifesto_eyebrow', 255)->default('');
            $table->string('manifesto_line1', 255)->default('');
            $table->string('manifesto_line2_prefix', 255)->default('');
            $table->string('manifesto_highlight', 255)->default('');

            $table->timestamps();
        });

        // Single content row, seeded with the text currently hardcoded in
        // resources/views/storefront/home.blade.php so nothing changes
        // visually until an admin edits it.
        DB::table('home_content')->insert([
            'hero_eyebrow' => 'NEW COLLECTION / 3 LEVELS OF EXPRESSION',
            'hero_subtitle' => "Dark luxury streetwear built in three levels — from everyday essentials, to the core identity of the brand, to limited signature pieces made to be collected.",
            'hero_cta_primary_label' => 'Shop the Collection',
            'hero_cta_secondary_label' => 'Explore Signature',
            'hero_image' => null,

            'feature_eyebrow' => '02 / CORE',
            'feature_title' => 'FEATURED CORE',
            'feature_note' => 'Core is where NEBVSIN speaks first — every graphic starts as a feeling before it becomes a t-shirt.',
            'feature_kicker' => '02 / CORE COLLECTION',
            'feature_heading_line1' => 'SHADOW',
            'feature_heading_line2' => 'IN MY TEETH',
            'feature_copy' => 'A daily statement piece built around visual tension and inner conflict. Clean surface, aggressive detail — the quietest piece from far away, the loudest up close.',
            'feature_cta_label' => 'Discover the Piece',
            'feature_image' => null,

            'signature_kicker' => '03 / SIGNATURE',
            'signature_heading_line1' => 'SPLIT',
            'signature_heading_line2' => 'MIND',
            'signature_limited_line1' => 'LIMITED EDITION',
            'signature_limited_line2' => '60 PIECES WORLDWIDE',
            'signature_limited_line3' => 'NO RESTOCK',
            'signature_copy' => 'Signature exists in another world from Essential and Core — red accent, edition numbering, and a collectible experience built to be kept, not just worn.',
            'signature_cta_label' => 'View Signature Piece',
            'signature_image' => null,

            'manifesto_eyebrow' => 'NEBVSIN MANIFESTO',
            'manifesto_line1' => "WE DON'T SELL CLOTHES.",
            'manifesto_line2_prefix' => 'WE SELL ',
            'manifesto_highlight' => 'CONFLICT.',

            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('home_content');
    }
};
