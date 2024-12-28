<?php
/*
Plugin Name: MetasBooks
Plugin URI: https://metasbooks.fr
Description: Collecte de métadonnées de livres depuis la plateforme MetasBooks.fr, génération automatique de fiches produits et synchronisation des stocks.
Author: Jérôme Bonfiglio
Version: 1.0
Author URI: https://metasbooks.fr
*/

// Activation and Deactivation Hooks
function metasbooks_activation()
{
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    global $wpdb;
    $table_name = $wpdb->prefix . 'metasbooks';

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT NOT NULL AUTO_INCREMENT,
        apikey TEXT DEFAULT NULL,
        sync INT DEFAULT 0,
        librairie_com INT DEFAULT 0,
        netlib INT DEFAULT 0,
        PRIMARY KEY (id)
    )";
    maybe_create_table($table_name, $sql);
}

function metasbooks_deactivation()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'metasbooks';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

register_activation_hook(__FILE__, 'metasbooks_activation');
register_deactivation_hook(__FILE__, 'metasbooks_deactivation');

// Enqueue Admin Scripts and Styles
function metasbooks_enqueue_admin_assets()
{
    wp_register_style('metasbooks_admin_css', plugin_dir_url(__FILE__) . 'style.css');
    wp_enqueue_style('metasbooks_admin_css');

    wp_enqueue_script('metasbooks_admin_js', plugin_dir_url(__FILE__) . 'script.js', ['jquery'], null, true);
    wp_localize_script('metasbooks_admin_js', 'metasbooksAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('metasbooks_nonce')
    ]);
}
add_action('admin_enqueue_scripts', 'metasbooks_enqueue_admin_assets');

// Admin Menu Setup
function metasbooks_admin_menu()
{
    add_menu_page('MetasBooks', 'MetasBooks', 'manage_options', 'metasbooks', 'metasbooks_admin_page', 'dashicons-book', 6);
    add_submenu_page('metasbooks', 'Synchronisation Auto', 'Synchronisation Auto', 'manage_options', 'metasbooks-sync-auto', 'metasbooks_sync_auto_page');
    add_submenu_page('metasbooks', 'LaLibrairie.com', 'LaLibrairie.com', 'manage_options', 'metasbooks-lalibrairie', 'metasbooks_lalibrairie_page');
}
add_action('admin_menu', 'metasbooks_admin_menu');

// Admin Page Handlers
function metasbooks_admin_page()
{
    $output = "<div class='metasbooks_container'><h1>MetasBooks</h1>";

    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        $output .= "<div class='error'>WooCommerce doit être installé pour utiliser MetasBooks &#10060;</div>";
    } else {
        $output .= "<div class='success'>WooCommerce est installé &#10003;</div>";
    }

    global $wpdb;
    $apikey = $wpdb->get_var("SELECT apikey FROM {$wpdb->prefix}metasbooks WHERE id = 1");

    if (!$apikey) {
        $output .= "<div class='error'>Clé API manquante. Obtenez-en une sur <a href='https://metasbooks.fr' target='_blank'>metasbooks.fr</a>.</div>";
    } else {
        $api_response = file_get_contents("https://metasbooks.fr/api/check_account.php?apikey=$apikey");
        $api_data = json_decode($api_response);

        if (isset($api_data->err_code) && $api_data->err_code == 4) {
            $output .= "<div class='error'>Clé API invalide &#10060;</div>";
        } elseif ($api_data->is_active == 1) {
            $output .= "<div class='success'>Compte API activé &#10003;</div>";
        } else {
            $output .= "<div class='error'>Compte non activé. Contactez <a href='mailto:support@metasbooks.fr'>support@metasbooks.fr</a>.</div>";
        }

        $credits = $api_data->credits ?? 0;
        if ($credits <= 50) {
            $output .= ($credits == 0)
                ? "<div class='error'>Aucun crédit restant. Approvisionnez votre compte sur <a href='https://metasbooks.fr'>metasbooks.fr</a>.</div>"
                : "<div class='warning'>Crédits faibles: $credits restants. Approvisionnez sur <a href='https://metasbooks.fr'>metasbooks.fr</a>.</div>";
        } else {
            $output .= "<div class='success'>Crédits restants: $credits &#10003;</div>";
        }
    }

    $output .= "</div>";
    echo $output;
}

function metasbooks_sync_auto_page()
{
    require_once plugin_dir_path(__FILE__) . 'syncro_auto.php';
    echo Syncro_Auto::generate();
}

function metasbooks_lalibrairie_page()
{
    require_once plugin_dir_path(__FILE__) . 'librairie_com.php';
    echo Librairie_Com::generate();
}

// AJAX Handler
function metasbooks_ajax_handler()
{
    check_ajax_referer('metasbooks_nonce');
    require_once plugin_dir_path(__FILE__) . 'handler.php';
    echo Handler::define($_POST);
    wp_die();
}
add_action('wp_ajax_metasbooks', 'metasbooks_ajax_handler');
add_action('wp_ajax_nopriv_metasbooks', 'metasbooks_ajax_handler');
