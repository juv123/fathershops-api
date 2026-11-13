<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FatherStockService
{
    // how long we keep data in cache (minutes)
    private const CACHE_MINUTES = 10;

    public function getCategories(): ?array
    {
        return Cache::remember('categories', now()->addMinutes(self::CACHE_MINUTES), function () {
            $data = $this->fetchJson('/category/hot-category.json');

            if (!$data || empty($data['response'])) {
                return null;
            }

            return collect($data['response'])->map(fn ($c) => [
                'id'        => (int) $c['category_id'],
                'parent_id' => (int) $c['parent_id'],
                'name'      => $c['name'],
                'image'     => $c['image'] ?? null,
            ])->values()->all();
        });
    }

    public function getCategoryProducts(int $categoryId): ?array
    {
        return Cache::remember("category.$categoryId", now()->addMinutes(self::CACHE_MINUTES), function () use ($categoryId) {
            $data = $this->fetchJson("/category/{$categoryId}.json");

            // no "products" key means the category was not found
            if (!$data || !isset($data['data']['products'])) {
                return null;
            }

            // the same product can appear many times, so keep only one of each
            return collect($data['data']['products'])
                ->unique('product_id')
                ->map(fn ($p) => [
                    'id'       => (int) $p['product_id'],
                    'name'     => $this->getName($p['descriptions'] ?? []),
                    'image'    => $p['image'] ?? null,
                    'price'    => round((float) ($p['calculated_price'] ?? 0), 2),
                    'quantity' => (int) ($p['quantity'] ?? 0),
                ])
                ->values()
                ->all();
        });
    }

    public function getProduct(int $productId): ?array
    {
        return Cache::remember("product.$productId", now()->addMinutes(self::CACHE_MINUTES), function () use ($productId) {
            $p = $this->fetchJson("/cache/{$productId}-f1.json");

            if (!$p || empty($p['product_id'])) {
                return null;
            }

            $description = $p['descriptions']['1'] ?? reset($p['descriptions']) ?: [];

            return [
                'id'          => (int) $p['product_id'],
                'name'        => $this->getName($p['descriptions'] ?? []),
                'image'       => $p['image'] ?? null,
                'images'      => collect($p['images'] ?? [])->pluck('image')->filter()->values()->all(),
                'price'       => round((float) ($p['calculated_price'] ?? 0), 2),
                'quantity'    => (int) ($p['quantity'] ?? 0),
                'sku'         => $p['sku'] ?? null,
                'description' => $this->toPlainText($description['description'] ?? ''),
                'options'     => $this->getOptions($p['options'] ?? []),
            ];
        });
    }

    // Calls the external API. Returns null if anything goes wrong.
    private function fetchJson(string $path): ?array
    {
        try {
            $response = Http::baseUrl(config('services.fatherstock.url'))
                ->timeout(10)
                ->acceptJson()
                ->get($path);
        } catch (ConnectionException $e) {
            Log::warning('FatherStock API not reachable: ' . $e->getMessage());
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    // descriptions look like {"1": {...english...}, "2": {...arabic...}}
    private function getName(array $descriptions): string
    {
        $first = $descriptions['1'] ?? reset($descriptions) ?: [];

        return $first['name'] ?? 'Unnamed product';
    }

    private function getOptions(array $options): array
    {
        return collect($options)->map(fn ($option) => [
            'name'   => $option['names']['1']['name'] ?? 'Option',
            'values' => collect($option['product_option_value'] ?? [])->map(fn ($v) => [
                'name'  => $v['names']['1']['name'] ?? '',
                'image' => $v['image'] ?? null,
            ])->values()->all(),
        ])->values()->all();
    }

    // The description from the API is raw HTML with scripts and inline styles.
    // We remove everything and send plain text, so the frontend stays safe.
    private function toPlainText(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $text));
    }
}