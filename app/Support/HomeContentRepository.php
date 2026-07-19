<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HomeContentRepository
{
    public function get(): array
    {
        return array_merge($this->defaults(), $this->row());
    }

    protected function row(): array
    {
        if (! Schema::hasTable('home_content')) {
            return [];
        }

        $row = DB::table('home_content')->orderBy('id')->first();

        if (! $row) {
            return [];
        }

        return array_filter((array) $row, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    protected function defaults(): array
    {
        return [
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
        ];
    }
}
