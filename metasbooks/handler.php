<?php

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

class Handler
{
    public static function define($datas)
    {
        $actions = [
            'update_apikey' => 'updateApikey',
            'reset_stocks' => 'resetStocks',
            'update_create' => 'updateCreate'
        ];

        $action = $actions[$datas['case']] ?? null;

        if ($action && method_exists(__CLASS__, $action)) {
            return self::$action($datas);
        }

        return 'undefined';
    }

    private static function resetStocks()
    {
        global $wpdb;

        $postmeta = $wpdb->prefix . 'postmeta';

        $queries = [
            "UPDATE $postmeta SET meta_value = 'outofstock' WHERE meta_value = 'instock' AND meta_key = '_stock_status'",
            "UPDATE $postmeta SET meta_value = '0' WHERE meta_key = '_stock'"
        ];

        foreach ($queries as $query) {
            dbDelta($query);
        }

        return 'stocks_reseted';
    }

    private static function updateCreate($datas)
    {
        global $wpdb;

        $postTypes = ['product', 'product_variation'];
        $sku = esc_sql($datas['ean']);

        $query = $wpdb->prepare(
            "SELECT post.post_type
            FROM {$wpdb->posts} AS post
            LEFT JOIN {$wpdb->postmeta} AS meta ON post.ID = meta.post_id
            WHERE post.post_type IN ('" . implode("','", $postTypes) . "')
            AND meta.meta_key = '_sku'
            AND meta.meta_value = %s",
            $sku
        );

        $result = $wpdb->get_var($query);

        return $result ? self::updateStock($datas) : self::createBook($datas);
    }

    private static function createBook($datas)
    {
        global $wpdb;

        $metasbooks = $wpdb->prefix . 'metasbooks';
        $apikey = $wpdb->get_var("SELECT apikey FROM $metasbooks WHERE id = 1");

        require_once plugin_dir_path(__FILE__) . '/class/Datas_metasbooks.php';
        $output = Datas_metasbooks::create($apikey, $datas['ean'], $datas['stock']);

        return $output ? 'created' : $datas['ean'];
    }

    private static function updateStock($datas)
    {
        if (empty($datas['stock'])) {
            return 'stock_update_failed';
        }

        global $wpdb;

        $sku = esc_sql($datas['ean']);
        $productId = $wpdb->get_var(
            $wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s", $sku)
        );

        if (!$productId) {
            return 'product_not_found';
        }

        $product = wc_get_product($productId);

        if (!$product || !$product->is_type('simple')) {
            return 'product_not_found_or_not_simple';
        }

        $product->set_stock_quantity((int)$datas['stock']);
        $product->set_stock_status('instock');
        $product->save();

        return 'updated';
    }

    private static function updateApikey($datas)
    {
        $response = file_get_contents('https://metasbooks.fr/api/check_account.php?apikey=' . $datas['apikey']);
        $isValid = json_decode($response);

        if (isset($isValid->err_code) && (int)$isValid->err_code === 4) {
            return 'inv_key';
        }

        if ((int)$isValid->is_active === 0) {
            return 'not_activ';
        }

        if ((int)$isValid->is_active === 1) {
            global $wpdb;

            $metasbooks = $wpdb->prefix . 'metasbooks';
            $existingKey = $wpdb->get_var("SELECT apikey FROM $metasbooks WHERE id = 1");

            $sql = $existingKey
                ? $wpdb->prepare("UPDATE $metasbooks SET apikey = %s WHERE id = 1", $datas['apikey'])
                : $wpdb->prepare("INSERT INTO $metasbooks (apikey) VALUES (%s)", $datas['apikey']);

            dbDelta($sql);

            return 'success';
        }
    }
}
