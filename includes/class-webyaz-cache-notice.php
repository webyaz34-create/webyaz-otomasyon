<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Cache_Notice {

    public function __construct() {
        add_action('admin_notices', array($this, 'show_notice'));
    }

    public function show_notice() {
        if (!current_user_can('manage_options')) return;
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'webyaz') === false) return;

        $cache_plugins = array(
            'w3-total-cache/w3-total-cache.php',
            'wp-super-cache/wp-cache.php',
            'litespeed-cache/litespeed-cache.php',
            'wp-fastest-cache/wpFastestCache.php',
            'autoptimize/autoptimize.php',
            'wp-optimize/wp-optimize.php',
            'sg-cachepress/sg-cachepress.php',
        );
        $has_cache = false;
        foreach ($cache_plugins as $p) {
            if (is_plugin_active($p)) { $has_cache = true; break; }
        }

        if (!$has_cache) {
            echo '<div class="notice notice-info" style="border-left-color:#2196f3;padding:12px;font-family:Roboto,sans-serif;">';
            echo '<strong style="color:#1565c0;">Performans Onerisi:</strong> Sitenizde aktif bir onbellek eklentisi bulunamadi. ';
            echo 'Site hizinizi artirmak icin LiteSpeed Cache, WP Super Cache veya W3 Total Cache yukleyin.';
            echo '</div>';
        }
    }
}

new Webyaz_Cache_Notice();
