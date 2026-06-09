<?php
/**
 * Adapter san pham global cho TGS Doc Tracker.
 *
 * Plugin nay chi doc catalog qua TGS_Global_Product_Source cua tgs_shop_management.
 * Cac cot local_product_* trong ledger/discrepancy la cot giao dich legacy, khong phai bang catalog local.
 *
 * @package tgs_doc_tracker
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TGS_Doc_Tracker_Global_Products
{
    private static $source_ready = null;

    public static function ensure_source(): bool
    {
        if (self::$source_ready !== null) {
            return (bool) self::$source_ready;
        }

        global $wpdb;

        if (!defined('TGS_TABLE_GLOBAL_PRODUCT_NAME')) {
            define('TGS_TABLE_GLOBAL_PRODUCT_NAME', $wpdb->base_prefix . 'global_product_name');
        }

        if (!class_exists('TGS_Global_Product_Source')) {
            $source_file = WP_PLUGIN_DIR . '/tgs_shop_management/functions/class-tgs-global-product-source.php';
            if (is_readable($source_file)) {
                require_once $source_file;
            }
        }

        self::$source_ready = class_exists('TGS_Global_Product_Source');
        return (bool) self::$source_ready;
    }

    public static function products_by_ids(array $ids): array
    {
        if (!self::ensure_source()) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }

        $result = TGS_Global_Product_Source::query_products([
            'ids' => $ids,
            'per_page' => count($ids),
            'parent_only' => false,
            'status_filter' => 'all',
            'require_sku' => false,
            'with_local_aliases' => false,
        ]);

        $map = [];
        foreach ((array) ($result['items'] ?? []) as $product) {
            $product = (array) $product;
            $id = (int) ($product['global_product_name_id'] ?? 0);
            if ($id > 0) {
                $map[$id] = $product;
            }
        }

        return $map;
    }

    public static function products_by_skus(array $skus): array
    {
        if (!self::ensure_source()) {
            return [];
        }

        $skus = array_values(array_unique(array_filter(array_map(static function ($sku) {
            return trim((string) $sku);
        }, $skus), static function ($sku) {
            return $sku !== '';
        })));

        if (!$skus) {
            return [];
        }

        $result = TGS_Global_Product_Source::query_products([
            'skus' => $skus,
            'per_page' => count($skus),
            'parent_only' => false,
            'status_filter' => 'all',
            'require_sku' => true,
            'with_local_aliases' => false,
        ]);

        $map = [];
        foreach ((array) ($result['items'] ?? []) as $product) {
            $product = (array) $product;
            $sku = self::sku($product);
            if ($sku === '') {
                continue;
            }
            $map[$sku] = $product;
            $map[strtoupper($sku)] = $product;
        }

        return $map;
    }

    public static function sku(array $product): string
    {
        return trim((string) ($product['global_product_sku'] ?? $product['sku'] ?? ''));
    }

    public static function name(array $product, string $fallback = ''): string
    {
        $name = trim((string) ($product['global_product_name'] ?? $product['product_name'] ?? $product['name'] ?? ''));
        return $name !== '' ? $name : $fallback;
    }
}
