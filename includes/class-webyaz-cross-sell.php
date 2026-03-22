<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('WooCommerce')) return;

class Webyaz_Cross_Sell {

    public function __construct() {
        add_action('woocommerce_after_single_product_summary', array($this, 'show_bought_together'), 15);
        add_action('woocommerce_new_order', array($this, 'track_purchase'), 10, 1);
    }

    public function track_purchase($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $product_ids = array();
        foreach ($order->get_items() as $item) {
            $product_ids[] = $item->get_product_id();
        }

        if (count($product_ids) < 2) return;

        foreach ($product_ids as $pid) {
            $related = get_post_meta($pid, '_webyaz_bought_together', true);
            if (!is_array($related)) $related = array();
            foreach ($product_ids as $other_pid) {
                if ($other_pid === $pid) continue;
                if (!isset($related[$other_pid])) {
                    $related[$other_pid] = 0;
                }
                $related[$other_pid]++;
            }
            update_post_meta($pid, '_webyaz_bought_together', $related);
        }
    }

    public function show_bought_together() {
        global $product;
        if (!$product) return;

        $related = get_post_meta($product->get_id(), '_webyaz_bought_together', true);

        if (empty($related) || !is_array($related)) {
            $related_ids = wc_get_related_products($product->get_id(), 4);
        } else {
            arsort($related);
            $related_ids = array_slice(array_keys($related), 0, 4);
        }

        if (empty($related_ids)) return;

        $products = array_filter(array_map('wc_get_product', $related_ids));
        if (empty($products)) return;

        echo '<div class="webyaz-bought-together">';
        echo '<h3 class="webyaz-bt-title">Birlikte Satin Alinanlar</h3>';
        echo '<div class="webyaz-bt-grid">';

        foreach ($products as $p) {
            if (!$p || !$p->is_visible()) continue;
            $img = wp_get_attachment_image_src(get_post_thumbnail_id($p->get_id()), 'woocommerce_thumbnail');
            $img_url = $img ? $img[0] : wc_placeholder_img_src();
            echo '<a href="' . esc_url(get_permalink($p->get_id())) . '" class="webyaz-bt-item">';
            echo '<img src="' . esc_url($img_url) . '" alt="' . esc_attr($p->get_name()) . '">';
            echo '<div class="webyaz-bt-info">';
            echo '<div class="webyaz-bt-name">' . esc_html($p->get_name()) . '</div>';
            echo '<div class="webyaz-bt-price">' . $p->get_price_html() . '</div>';
            echo '</div>';
            echo '</a>';
        }

        echo '</div></div>';
    }
}

new Webyaz_Cross_Sell();
