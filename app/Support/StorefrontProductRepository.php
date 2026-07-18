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
                return Product::query()
                    ->orderBy('sort_order')
                    ->orderBy('id')
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
                            'gallery' => $attributes['gallery'] ?? ($attributes['images'] ?? null),
                            'image_2' => $attributes['image_2'] ?? null,
                            'image_3' => $attributes['image_3'] ?? null,
                            'image_4' => $attributes['image_4'] ?? null,
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

    protected function normalizeProduct(array $product): array
    {
        $id = (int) ($product['id'] ?? 0);
        $name = trim((string) ($product['name'] ?? 'Untitled product'));
        $description = trim((string) ($product['description'] ?? ''));
        $slug = trim((string) ($product['slug'] ?? ''));
        $normalizedSlug = $slug !== '' ? $slug : $this->makeSlug($name, $id);
        $priceThb = (int) ($product['price_thb'] ?? 0);
        $metaTitle = trim((string) ($product['meta_title'] ?? ''));
        $metaDescription = trim((string) ($product['meta_description'] ?? ''));
        $image = trim((string) ($product['image'] ?? ''));
        $ogImage = trim((string) ($product['og_image'] ?? ''));
        $gallery = $this->normalizeGallery($product, $image, $name);

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
            'sort_order' => (int) ($product['sort_order'] ?? 0),
            'limited_qty' => (int) ($product['limited_qty'] ?? 0),
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
