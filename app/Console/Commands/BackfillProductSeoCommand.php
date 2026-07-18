<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class BackfillProductSeoCommand extends Command
{
    protected $signature = 'storefront:backfill-product-seo';

    protected $description = 'Backfill slug, alt text, and SEO fields for legacy products';

    public function handle(): int
    {
        try {
            if (! Schema::hasTable('products')) {
                $this->error('The products table does not exist yet.');

                return self::FAILURE;
            }

            $products = Product::query()->orderBy('id')->get();
        } catch (Throwable $exception) {
            $this->error('Unable to connect to the configured database.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        if ($products->isEmpty()) {
            $this->warn('No products found to backfill.');

            return self::SUCCESS;
        }

        $updated = 0;

        foreach ($products as $product) {
            $slug = trim((string) $product->slug);
            $alt = trim((string) $product->alt);
            $metaTitle = trim((string) $product->meta_title);
            $metaDescription = trim((string) $product->meta_description);
            $ogImage = trim((string) $product->og_image);
            $description = trim((string) $product->description);
            $image = trim((string) $product->image);
            $name = trim((string) $product->name);

            if ($slug === '') {
                $product->slug = $this->uniqueSlug($name, (int) $product->id);
            }

            if ($alt === '') {
                $product->alt = $this->buildAlt($name);
            }

            if ($metaTitle === '') {
                $product->meta_title = $this->buildMetaTitle($name);
            }

            if ($metaDescription === '') {
                $product->meta_description = $this->buildMetaDescription($name, $description);
            }

            if ($ogImage === '' && $image !== '') {
                $product->og_image = $image;
            }

            if ($product->isDirty()) {
                $product->save();
                $updated++;
            }
        }

        $this->info('Backfill complete. Updated '.$updated.' product records.');

        return self::SUCCESS;
    }

    protected function uniqueSlug(string $name, int $id): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'product';
        }

        $candidate = $base.'-'.$id;
        $suffix = 1;

        while (
            Product::query()
                ->where('id', '!=', $id)
                ->where('slug', $candidate)
                ->exists()
        ) {
            $candidate = $base.'-'.$id.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected function buildAlt(string $name): string
    {
        $cleanName = trim($name) !== '' ? trim($name) : 'Product';

        return $cleanName.' by NEBVSIN streetwear';
    }

    protected function buildMetaTitle(string $name): string
    {
        $cleanName = trim($name) !== '' ? trim($name) : 'Product';

        return $cleanName.' | NEBVSIN';
    }

    protected function buildMetaDescription(string $name, string $description): string
    {
        $cleanDescription = trim($description);

        if ($cleanDescription !== '') {
            return Str::limit($cleanDescription, 155, '');
        }

        $cleanName = trim($name) !== '' ? trim($name) : 'This product';

        return Str::limit($cleanName.' from NEBVSIN limited drop streetwear collection.', 155, '');
    }
}
