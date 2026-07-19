<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class StorefrontProductRepository
{
    public function allPublicProducts(): Collection
    {
        return $this->loadProducts()
            ->filter(function (array $product) {
                return $product['is_public'] === true;
            })
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    public function findPublicProductById(int $id): ?array
    {
        return $this->allPublicProducts()
            ->first(function (array $product) use ($id) {
                return $product['id'] === $id;
            });
    }

    public function findPublicProductBySlug(string $slug): ?array
    {
        return $this->allPublicProducts()
            ->first(function (array $product) use ($slug) {
                return $product['slug'] === $slug;
            });
    }

    public function assetUrl(?string $path): string
    {
        $value = trim((string) $path);

        if ($value === '') {
            return '';
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        $baseUrl = rtrim((string) config('storefront.asset_base_url'), '/');

        if ($baseUrl === '') {
            return $value;
        }

        return $baseUrl.'/'.ltrim($value, '/');
    }

    public function defaultOgImage(): string
    {
        $firstProduct = $this->allPublicProducts()->first();

        return $firstProduct ? $firstProduct['og_image'] : $this->assetUrl('/uploads/products/DROP01.png');
    }

    protected function loadProducts(): Collection
    {
        try {
            if (Schema::hasTable('products')) {
                $query = Product::query()
                    ->orderBy('sort_order')
                    ->orderBy('id');

                if (Schema::hasTable('product_images')) {
                    $query->with('images');
                }

                return $query
                    ->get()
                    ->map(function (Product $product) {
                        $attributes = $product->getAttributes();
                        return $this->normalizeProduct([
                            'id' => (int) $product->id,
                            'name' => $product->name,
                            'price_thb' => (int) $product->price_thb,
                            'image' => $product->image,
                            'alt' => $product->alt,
                            'description' => $product->description,
                            'sort_order' => (int) $product->sort_order,
                            'limited_qty' => (int) $product->limited_qty,
                            'is_public' => (bool) $product->is_public,
                            'coming_soon' => (bool) $product->coming_soon,
                            'slug' => $product->slug,
                            'meta_title' => $product->meta_title,
                            'meta_description' => $product->meta_description,
                            'og_image' => $product->og_image,
                            'gallery' => $this->productImagesGallery($product, $attributes),
                            'image_2' => $attributes['image_2'] ?? null,
                            'image_3' => $attributes['image_3'] ?? null,
                            'image_4' => $attributes['image_4'] ?? null,
                            'tier' => $attributes['tier'] ?? null,
                            'short_description' => $attributes['short_description'] ?? null,
                            'story' => $attributes['story'] ?? null,
                            'colors' => $attributes['colors'] ?? null,
                            'sizes' => $attributes['sizes'] ?? null,
                            'is_limited' => $attributes['is_limited'] ?? null,
                            'edition_total' => $attributes['edition_total'] ?? null,
                            'edition_number' => $attributes['edition_number'] ?? null,
                            'packaging' => $attributes['packaging'] ?? null,
                            'status' => $attributes['status'] ?? null,
                        ]);
                    });
            }
        } catch (Throwable $exception) {
            // Fall back to local JSON while the Laravel database layer is still being wired.
        }

        return collect($this->fallbackProducts())
            ->map(function (array $product, int $index) {
                return $this->normalizeProduct([
                    'id' => $index + 1,
                    'name' => $product['name'] ?? 'Untitled product',
                    'price_thb' => $this->parsePriceThb($product['price'] ?? null),
                    'image' => $product['image'] ?? '',
                    'alt' => $product['alt'] ?? null,
                    'description' => $product['description'] ?? null,
                    'sort_order' => $index,
                    'limited_qty' => 40,
                    'is_public' => true,
                    'coming_soon' => false,
                    'slug' => null,
                    'meta_title' => null,
                    'meta_description' => null,
                    'og_image' => null,
                    'gallery' => $product['gallery'] ?? ($product['images'] ?? null),
                    'image_2' => $product['image_2'] ?? null,
                    'image_3' => $product['image_3'] ?? null,
                    'image_4' => $product['image_4'] ?? null,
                    'tier' => $product['tier'] ?? null,
                    'short_description' => $product['shortDescription'] ?? ($product['short_description'] ?? null),
                    'story' => $product['story'] ?? null,
                    'colors' => $product['colors'] ?? null,
                    'sizes' => $product['sizes'] ?? null,
                    'is_limited' => $product['isLimited'] ?? ($product['is_limited'] ?? null),
                    'edition_total' => $product['editionTotal'] ?? ($product['edition_total'] ?? null),
                    'edition_number' => $product['editionNumber'] ?? ($product['edition_number'] ?? null),
                    'packaging' => $product['packaging'] ?? null,
                    'status' => $product['status'] ?? null,
                ]);
            });
    }

    protected function fallbackProducts(): array
    {
        $path = (string) config('storefront.fallback_products_path');

        if ($path === '' || ! File::exists($path)) {
            return [];
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function productImagesGallery(Product $product, array $attributes): array
    {
        if ($product->relationLoaded('images')) {
            $images = $product->images
                ->map(function ($image) {
                    return [
                        'path' => $image->image_path,
                        'alt' => $image->alt,
                    ];
                })
                ->all();

            if ($images) {
                return $images;
            }
        }

        return $attributes['gallery'] ?? ($attributes['images'] ?? []);
    }

    protected function normalizeProduct(array $product): array
    {
        $id = (int) ($product['id'] ?? 0);
        $name = trim((string) ($product['name'] ?? 'Untitled product'));
        $description = trim((string) ($product['description'] ?? ''));
        $slug = trim((string) ($product['slug'] ?? ''));
        $normalizedSlug = $slug !== '' ? $slug : $this->makeSlug($name, $id);
        $priceThb = (int) ($product['price_thb'] ?? 0);
        $tierKey = ProductTierConfig::normalizeKey($product['tier'] ?? null);
        $tier = ProductTierConfig::get($tierKey);
        $metaTitle = trim((string) ($product['meta_title'] ?? ''));
        $metaDescription = trim((string) ($product['meta_description'] ?? ''));
        $image = trim((string) ($product['image'] ?? ''));
        $ogImage = trim((string) ($product['og_image'] ?? ''));
        $gallery = $this->normalizeGallery($product, $image, $name);
        $limitedQty = (int) ($product['limited_qty'] ?? 0);
        $editionTotal = (int) ($product['edition_total'] ?? 0);
        $editionNumber = (int) ($product['edition_number'] ?? 0);
        $isLimited = array_key_exists('is_limited', $product)
            ? (bool) $product['is_limited']
            : ($tierKey === 'signature' || $limitedQty > 0);
        $status = $this->normalizeStatus($product['status'] ?? null, (bool) ($product['coming_soon'] ?? false));

        return [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'price_thb' => $priceThb,
            'price_label' => number_format($priceThb).' THB',
            'image' => $image,
            'image_url' => $this->assetUrl($image),
            'gallery' => $gallery,
            'alt' => trim((string) ($product['alt'] ?? '')) ?: $name.' by NEBVSIN streetwear',
            'short_description' => trim((string) ($product['short_description'] ?? '')),
            'story' => trim((string) ($product['story'] ?? '')),
            'colors' => $this->normalizeStringList($product['colors'] ?? null),
            'sizes' => $this->normalizeStringList($product['sizes'] ?? null),
            'tier' => $tierKey,
            'tier_key' => $tierKey,
            'tier_number' => $tier['number'],
            'tier_label' => $tier['label'],
            'tier_tagline' => $tier['tagline'],
            'tier_summary' => $tier['summary'],
            'sort_order' => (int) ($product['sort_order'] ?? 0),
            'limited_qty' => $limitedQty,
            'is_limited' => $isLimited,
            'edition_total' => $editionTotal > 0 ? $editionTotal : ($tierKey === 'signature' ? $limitedQty : 0),
            'edition_number' => $editionNumber,
            'edition_label' => $editionNumber > 0 && ($editionTotal > 0 || $limitedQty > 0)
                ? str_pad((string) $editionNumber, 2, '0', STR_PAD_LEFT).' / '.($editionTotal > 0 ? $editionTotal : $limitedQty)
                : null,
            'packaging' => trim((string) ($product['packaging'] ?? '')) ?: ($tierKey === 'signature' ? 'premium' : 'standard'),
            'status' => $status,
            'is_public' => (bool) ($product['is_public'] ?? true),
            'coming_soon' => (bool) ($product['coming_soon'] ?? false),
            'slug' => $normalizedSlug,
            'meta_title' => $metaTitle !== '' ? $metaTitle : $name.' | NEBVSIN',
            'meta_description' => $metaDescription !== '' ? $metaDescription : Str::limit($description, 155, ''),
            'og_image' => $this->assetUrl($ogImage !== '' ? $ogImage : $image),
        ];
    }

    protected function normalizeGallery(array $product, string $primaryImage, string $name): array
    {
        $rawGallery = [];

        if (isset($product['gallery']) && is_string($product['gallery'])) {
            $decoded = json_decode($product['gallery'], true);
            if (is_array($decoded)) {
                $rawGallery = array_merge($rawGallery, $decoded);
            }
        } elseif (isset($product['gallery']) && is_array($product['gallery'])) {
            $rawGallery = array_merge($rawGallery, $product['gallery']);
        }

        if (isset($product['images']) && is_array($product['images'])) {
            $rawGallery = array_merge($rawGallery, $product['images']);
        }

        foreach (['image_2', 'image_3', 'image_4'] as $field) {
            $value = trim((string) ($product[$field] ?? ''));
            if ($value !== '') {
                $rawGallery[] = ['path' => $value];
            }
        }

        array_unshift($rawGallery, ['path' => $primaryImage, 'alt' => trim((string) ($product['alt'] ?? ''))]);

        $normalizedGallery = [];
        $seen = [];

        foreach ($rawGallery as $index => $item) {
            $normalized = $this->normalizeGalleryItem($item, $name, $index);

            if ($normalized === null || isset($seen[$normalized['path']])) {
                continue;
            }

            $seen[$normalized['path']] = true;
            $normalizedGallery[] = $normalized;
        }

        return $normalizedGallery;
    }

    protected function normalizeGalleryItem($item, string $name, int $index): ?array
    {
        if (is_string($item)) {
            $path = trim($item);
            $alt = '';
        } elseif (is_array($item)) {
            $path = trim((string) ($item['path'] ?? $item['url'] ?? $item['image'] ?? ''));
            $alt = trim((string) ($item['alt'] ?? ''));
        } else {
            return null;
        }

        if ($path === '') {
            return null;
        }

        return [
            'path' => $path,
            'url' => $this->assetUrl($path),
            'alt' => $alt !== '' ? $alt : $name.' by NEBVSIN image '.($index + 1),
        ];
    }

    protected function normalizeStringList($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = array_filter(array_map('trim', explode(',', $value)));
            }
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($item) => strtoupper(trim((string) $item)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeStatus($status, bool $comingSoon): string
    {
        if ($comingSoon) {
            return 'coming_soon';
        }

        $value = strtolower(trim((string) $status));

        return in_array($value, ['available', 'sold_out', 'coming_soon'], true) ? $value : 'available';
    }

    protected function parsePriceThb(?string $priceLabel): int
    {
        $digits = preg_replace('/\D+/', '', (string) $priceLabel);

        return $digits !== '' ? (int) $digits : 0;
    }

    protected function makeSlug(string $name, int $id): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'product';
        }

        return $base.'-'.$id;
    }
}
