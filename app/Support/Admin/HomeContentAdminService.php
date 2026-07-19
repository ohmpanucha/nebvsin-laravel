<?php

namespace App\Support\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HomeContentAdminService
{
    protected const FIELDS = [
        'hero_eyebrow', 'hero_subtitle', 'hero_cta_primary_label', 'hero_cta_secondary_label', 'hero_image',
        'feature_eyebrow', 'feature_title', 'feature_note', 'feature_kicker',
        'feature_heading_line1', 'feature_heading_line2', 'feature_copy', 'feature_cta_label', 'feature_image',
        'signature_kicker', 'signature_heading_line1', 'signature_heading_line2',
        'signature_limited_line1', 'signature_limited_line2', 'signature_limited_line3',
        'signature_copy', 'signature_cta_label', 'signature_image',
        'manifesto_eyebrow', 'manifesto_line1', 'manifesto_line2_prefix', 'manifesto_highlight',
    ];

    public function summary(): array
    {
        $row = $this->get();

        return [
            ['label' => 'Home content', 'value' => $row ? 'Configured' : 'Not set up'],
            ['label' => 'Last updated', 'value' => $row['updated_at'] ?? '—'],
        ];
    }

    public function get(): array
    {
        if (! Schema::hasTable('home_content')) {
            return [];
        }

        $row = DB::table('home_content')->orderBy('id')->first();

        return $row ? (array) $row : [];
    }

    public function update(array $input): void
    {
        if (! Schema::hasTable('home_content')) {
            throw new \RuntimeException('home_content table is missing. Run migrations first.');
        }

        $payload = $this->normalizePayload($input);

        if (! $payload) {
            return;
        }

        $payload['updated_at'] = now();
        $existing = DB::table('home_content')->orderBy('id')->first();

        if ($existing) {
            DB::table('home_content')->where('id', $existing->id)->update($payload);
        } else {
            $payload['created_at'] = now();
            DB::table('home_content')->insert($payload);
        }
    }

    protected function normalizePayload(array $input): array
    {
        $payload = [];

        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $value = trim((string) $input[$field]);

            if (str_ends_with($field, '_image')) {
                $payload[$field] = $value === '' ? null : $value;
                continue;
            }

            $payload[$field] = $value;
        }

        return $payload;
    }
}
