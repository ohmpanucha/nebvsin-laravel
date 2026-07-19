<?php

namespace App\Support;

class ProductTierConfig
{
    public const DEFAULT = 'core';

    protected const TIERS = [
        'essential' => [
            'number' => '01',
            'label' => 'ESSENTIAL',
            'starting_price' => 350,
            'tagline' => 'MINIMAL. EVERYDAY. ALWAYS YOU.',
            'summary' => 'Minimal everyday pieces built around fabric, fit, and quiet detail.',
            'short' => 'Minimal. Everyday. Always you.',
        ],
        'core' => [
            'number' => '02',
            'label' => 'CORE',
            'starting_price' => 550,
            'tagline' => 'THE STORY. THE ART. THE NEBVSIN CORE.',
            'summary' => 'Graphic artwork and storytelling from the main NEBVSIN design language.',
            'short' => 'The story. The art. The NEBVSIN core.',
        ],
        'signature' => [
            'number' => '03',
            'label' => 'SIGNATURE',
            'starting_price' => 750,
            'tagline' => 'THE ARTWORK. THE SIGNATURE. THE NEBVSIN.',
            'summary' => 'Limited collectible artwork with premium packaging and no-restock energy.',
            'short' => 'The artwork. The signature. The NEBVSIN.',
        ],
    ];

    public static function all(): array
    {
        return self::TIERS;
    }

    public static function keys(): array
    {
        return array_keys(self::TIERS);
    }

    public static function get(?string $tier): array
    {
        $key = self::normalizeKey($tier);

        return array_merge(['key' => $key], self::TIERS[$key]);
    }

    public static function normalizeKey(?string $tier): string
    {
        $key = strtolower(trim((string) $tier));

        return isset(self::TIERS[$key]) ? $key : self::DEFAULT;
    }

    public static function inferFromPrice(int $priceThb): string
    {
        if ($priceThb >= self::TIERS['signature']['starting_price']) {
            return 'signature';
        }

        if ($priceThb <= 400 && $priceThb > 0) {
            return 'essential';
        }

        return 'core';
    }
}
