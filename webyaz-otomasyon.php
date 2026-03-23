<?php

/**
 * Plugin Name: Webyaz Otomasyon
 * Description: Flatsome tema uyumlu e-ticaret eklentisi - Checkout, SEO, siparis bildirimleri, urun karsilastirma, favoriler, guvenlik ve daha fazlasi.
 * Version: 4.5.0
 * Author: Webyaz
 * Text Domain: webyaz-otomasyon
 * Requires at least: 5.0
 * Requires PHP: 7.0
 */

if (!defined('ABSPATH')) exit;

define('WEBYAZ_VERSION', '4.5.0');
define('WEBYAZ_PATH', plugin_dir_path(__FILE__));
define('WEBYAZ_URL', plugin_dir_url(__FILE__));

register_activation_hook(__FILE__, 'webyaz_activate');
function webyaz_activate()
{
    // Ilk kurulum - guvenli modda basla
    if (get_option('webyaz_setup_complete') === false) {
        add_option('webyaz_setup_complete', '0');
    }
    require_once WEBYAZ_PATH . 'includes/class-webyaz-colors.php';
    require_once WEBYAZ_PATH . 'includes/class-webyaz-settings.php';
    flush_rewrite_rules();
}


add_action('plugins_loaded', 'webyaz_init');
function webyaz_init()
{
    // Dashboard icin gerekli temel dosyalar - her zaman yukle
    require_once WEBYAZ_PATH . 'includes/class-webyaz-colors.php';
    require_once WEBYAZ_PATH . 'includes/class-webyaz-settings.php';

    // GUVENLI MOD: Kurulum tamamlanmadiysa sadece dashboard goster
    // Not: Mevcut sitelerde option yoksa '1' kabul et (geri uyumluluk)
    if (get_option('webyaz_setup_complete', '1') !== '1') {
        return;
    }

    // Temel moduller - kurulum tamamlandiktan sonra yukle (dashboard icin gerekli)
    require_once WEBYAZ_PATH . 'includes/class-webyaz-pages.php';
    require_once WEBYAZ_PATH . 'includes/class-webyaz-dashboard-stats.php';
    require_once WEBYAZ_PATH . 'includes/class-webyaz-backup-notice.php';
    require_once WEBYAZ_PATH . 'includes/class-webyaz-cache-notice.php';
    require_once WEBYAZ_PATH . 'includes/class-webyaz-role-manager.php';
    require_once WEBYAZ_PATH . 'includes/class-webyaz-help.php';

    // Toggle'a bagli moduller - sadece aktifse yukle
    $toggle_map = array(
        'webyaz_mod_backup'           => 'class-webyaz-backup.php',
        'webyaz_mod_checkout'         => 'class-webyaz-checkout.php',
        'webyaz_mod_whatsapp'         => 'class-webyaz-whatsapp.php',
        'webyaz_mod_product_tabs'     => 'class-webyaz-product-tabs.php',
        'webyaz_mod_antibot'          => 'class-webyaz-antibot.php',
        'webyaz_mod_seo'              => 'class-webyaz-seo.php',
        'webyaz_mod_cross_sell'       => 'class-webyaz-cross-sell.php',
        'webyaz_mod_popup'            => 'class-webyaz-popup.php',
        'webyaz_mod_compare'          => 'class-webyaz-compare.php',
        'webyaz_mod_wishlist'         => 'class-webyaz-wishlist.php',
        'webyaz_mod_brute_force'      => 'class-webyaz-brute-force.php',
        'webyaz_mod_mobile_bar'       => 'class-webyaz-mobile-bar.php',
        'webyaz_mod_auto_tags'        => 'class-webyaz-auto-tags.php',
        'webyaz_mod_footer'           => 'class-webyaz-footer.php',
        'webyaz_mod_cookie'           => 'class-webyaz-cookie.php',
        'webyaz_mod_recently_viewed'  => 'class-webyaz-recently-viewed.php',
        'webyaz_mod_qa'               => 'class-webyaz-qa.php',
        'webyaz_mod_attributes'       => 'class-webyaz-attributes.php',
        'webyaz_mod_countdown'        => 'class-webyaz-countdown.php',
        'webyaz_mod_bulk_discount'    => 'class-webyaz-bulk-discount.php',
        'webyaz_mod_upsell'           => 'class-webyaz-upsell.php',
        'webyaz_mod_live_support'     => 'class-webyaz-live-support.php',
        'webyaz_mod_stock_counter'    => 'class-webyaz-stock-counter.php',
        'webyaz_mod_cart_reminder'    => 'class-webyaz-cart-reminder.php',
        'webyaz_mod_maintenance'      => 'class-webyaz-maintenance.php',
        'webyaz_mod_webp'             => 'class-webyaz-webp.php',
        'webyaz_mod_order_whatsapp'   => 'class-webyaz-order-whatsapp.php',
        'webyaz_mod_bulk_webp'        => 'class-webyaz-bulk-webp.php',
        'webyaz_mod_live_search'      => 'class-webyaz-live-search.php',
        'webyaz_mod_badges'           => 'class-webyaz-badges.php',
        'webyaz_mod_shop_turkish'     => 'class-webyaz-shop-turkish.php',
        'webyaz_mod_story_menu'       => 'class-webyaz-story-menu.php',
        'webyaz_mod_floating_contact' => 'class-webyaz-floating-contact.php',
        'webyaz_mod_cost_price'       => 'class-webyaz-cost-price.php',
        'webyaz_mod_ticker'           => 'class-webyaz-ticker.php',
        'webyaz_mod_xml'              => 'class-webyaz-xml-manager.php',
        'webyaz_mod_marketplace'      => 'class-webyaz-marketplace.php',
        'webyaz_mod_bulk_edit'        => 'class-webyaz-bulk-edit.php',
        'webyaz_mod_analytics'        => 'class-webyaz-analytics.php',
        'webyaz_mod_sms'              => 'class-webyaz-sms.php',
        'webyaz_mod_invoice'          => 'class-webyaz-invoice.php',
        'webyaz_mod_order_note'       => 'class-webyaz-order-note.php',
        'webyaz_mod_product_video'    => 'class-webyaz-product-video.php',
        'webyaz_mod_product_desc'     => 'class-webyaz-product-desc.php',
        'webyaz_mod_social_proof'     => 'class-webyaz-social-proof.php',
        'webyaz_mod_partner'          => 'class-webyaz-partner.php',
        'webyaz_mod_b2b'              => 'class-webyaz-b2b.php',
        'webyaz_mod_membership'       => 'class-webyaz-membership.php',
        'webyaz_mod_email_templates'  => 'class-webyaz-email-templates.php',
        'webyaz_mod_duplicate_post'   => 'class-webyaz-duplicate-post.php',
        'webyaz_mod_big_upload'       => 'class-webyaz-big-upload.php',
        'webyaz_mod_order_status'     => 'class-webyaz-order-status.php',
        'webyaz_mod_order_track'      => 'class-webyaz-order-track.php',
        'webyaz_mod_quick_product'    => 'class-webyaz-quick-product.php',
        'webyaz_mod_bulk_product'     => 'class-webyaz-bulk-product.php',
        'webyaz_mod_cargo'            => 'class-webyaz-cargo.php',
        'webyaz_mod_stock_alert'      => 'class-webyaz-stock-alert.php',
        'webyaz_mod_gift'             => 'class-webyaz-gift.php',
        'webyaz_mod_photo_reviews'    => 'class-webyaz-photo-reviews.php',
        'webyaz_mod_size_guide'       => 'class-webyaz-size-guide.php',
        'webyaz_mod_previously_bought' => 'class-webyaz-previously-bought.php',
        'webyaz_mod_custom_css'       => 'class-webyaz-custom-css.php',
        'webyaz_mod_product_style'    => 'class-webyaz-product-style.php',
        'webyaz_mod_extra_services'   => 'class-webyaz-extra-services.php',
        'webyaz_mod_security_shield'  => 'class-webyaz-security-shield.php',
        'webyaz_mod_free_shipping_bar' => 'class-webyaz-free-shipping-bar.php',
        'webyaz_mod_auto_discount'    => 'class-webyaz-auto-discount.php',
        'webyaz_mod_returns'          => 'class-webyaz-returns.php',
        'webyaz_mod_loyalty'          => 'class-webyaz-loyalty.php',
        'webyaz_mod_referral'         => 'class-webyaz-referral.php',
        'webyaz_mod_coupon_manager'   => 'class-webyaz-coupon-manager.php',
        'webyaz_mod_branded_pages'    => 'class-webyaz-branded-pages.php',
        'webyaz_mod_preorder'         => 'class-webyaz-preorder.php',
        'webyaz_mod_delivery_date'    => 'class-webyaz-delivery-date.php',
        'webyaz_mod_compatible'       => 'class-webyaz-compatible.php',
        'webyaz_mod_customer_ledger'  => 'class-webyaz-customer-ledger.php',
        'webyaz_mod_updater'          => 'class-webyaz-updater.php',
        'webyaz_mod_support'          => 'class-webyaz-support.php',
    );

    foreach ($toggle_map as $option_key => $file) {
        if (get_option($option_key, '0') === '1') {
            require_once WEBYAZ_PATH . 'includes/' . $file;
        }
    }

    // Yukleme limiti artir (256MB)
    @ini_set('upload_max_filesize', '256M');
    @ini_set('post_max_size', '256M');
    @ini_set('max_execution_time', '300');
    @ini_set('max_input_time', '300');
    add_filter('upload_size_limit', function () {
        return 256 * 1024 * 1024;
    });

    add_action('wp_enqueue_scripts', 'webyaz_enqueue_assets');

    // Alt menuleri harf sirasina gore sirala (en son calis)
    add_action('admin_menu', 'webyaz_sort_submenus', 999);
}

function webyaz_sort_submenus() {
    global $submenu;
    if (!isset($submenu['webyaz-dashboard'])) return;

    // Ustte sabit kalacak sayfa slug'lari
    $pinned_slugs = array('webyaz-dashboard', 'webyaz-settings');
    $pinned = array();
    $rest = array();

    foreach ($submenu['webyaz-dashboard'] as $item) {
        if (in_array($item[2], $pinned_slugs)) {
            $pinned[] = $item;
        } else {
            $rest[] = $item;
        }
    }

    // Geri kalanlari basliga gore alfabetik sirala
    usort($rest, function($a, $b) {
        return strcasecmp($a[0], $b[0]);
    });

    // Kontrol Paneli + Ayarlar ustte, gerisi alfabetik
    $submenu['webyaz-dashboard'] = array_merge($pinned, $rest);
}

function webyaz_enqueue_assets()
{
    $colors = Webyaz_Colors::get_theme_colors();

    wp_enqueue_style('webyaz-google-fonts', 'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap', array(), null);
    wp_enqueue_style('webyaz-style', WEBYAZ_URL . 'assets/css/webyaz.css', array('webyaz-google-fonts'), WEBYAZ_VERSION);
    wp_add_inline_style('webyaz-style', ':root{--webyaz-primary:' . $colors['primary'] . ';--webyaz-secondary:' . $colors['secondary'] . ';}');
    wp_enqueue_script('webyaz-script', WEBYAZ_URL . 'assets/js/webyaz.js', array('jquery'), WEBYAZ_VERSION, true);
    wp_localize_script('webyaz-script', 'webyaz_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('webyaz_nonce'),
    ));
}
