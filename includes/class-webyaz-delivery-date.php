<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Delivery_Date {

    public function __construct() {
        add_action('woocommerce_product_options_shipping', array($this, 'add_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_fields'));
        add_action('woocommerce_single_product_summary', array($this, 'show_estimate'), 35);
    }

    // Admin: Urun duzenleme alanlari (Kargo sekmesinde)
    public function add_fields() {
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;color:#1565c0;">📅 Tahmini Teslimat Ayarlari</h4>';

        woocommerce_wp_text_input(array(
            'id'          => '_webyaz_delivery_min',
            'label'       => 'Min Gun',
            'type'        => 'number',
            'placeholder' => '2',
            'description' => 'Minimum teslimat suresi (gun)',
            'custom_attributes' => array('min' => '0', 'step' => '1'),
        ));

        woocommerce_wp_text_input(array(
            'id'          => '_webyaz_delivery_max',
            'label'       => 'Max Gun',
            'type'        => 'number',
            'placeholder' => '5',
            'description' => 'Maksimum teslimat suresi (gun)',
            'custom_attributes' => array('min' => '0', 'step' => '1'),
        ));

        woocommerce_wp_text_input(array(
            'id'          => '_webyaz_delivery_label',
            'label'       => 'Ozel Metin',
            'placeholder' => '',
            'description' => 'Bos birakilirsa otomatik hesaplanir',
        ));

        echo '</div>';
    }

    // Kaydet
    public function save_fields($post_id) {
        if (isset($_POST['_webyaz_delivery_min'])) {
            update_post_meta($post_id, '_webyaz_delivery_min', absint($_POST['_webyaz_delivery_min']));
        }
        if (isset($_POST['_webyaz_delivery_max'])) {
            update_post_meta($post_id, '_webyaz_delivery_max', absint($_POST['_webyaz_delivery_max']));
        }
        if (isset($_POST['_webyaz_delivery_label'])) {
            update_post_meta($post_id, '_webyaz_delivery_label', sanitize_text_field($_POST['_webyaz_delivery_label']));
        }
    }

    // Frontend: Tahmini teslimat goster
    public function show_estimate() {
        global $product;
        if (!$product) return;

        $min = get_post_meta($product->get_id(), '_webyaz_delivery_min', true);
        $max = get_post_meta($product->get_id(), '_webyaz_delivery_max', true);
        $label = get_post_meta($product->get_id(), '_webyaz_delivery_label', true);

        // Ozel metin varsa direkt goster
        if ($label) {
            $text = $label;
        } elseif ($min && $max) {
            $date_min = date_i18n('d M', strtotime('+' . intval($min) . ' days'));
            $date_max = date_i18n('d M', strtotime('+' . intval($max) . ' days'));
            $text = $date_min . ' - ' . $date_max . ' arasi teslim';
        } elseif ($min) {
            $date_min = date_i18n('d M', strtotime('+' . intval($min) . ' days'));
            $text = $date_min . ' tarihinden itibaren teslim';
        } else {
            return; // Hicbir deger yoksa gosterme
        }

        echo '<div style="display:flex;align-items:center;gap:8px;background:linear-gradient(135deg,#e3f2fd,#bbdefb);padding:10px 14px;border-radius:8px;margin:10px 0;font-size:13px;border-left:4px solid #1565c0;">';
        echo '<span style="font-size:18px;">🚚</span>';
        echo '<div>';
        echo '<div style="font-weight:700;color:#1565c0;font-size:12px;">Tahmini Teslimat</div>';
        echo '<div style="color:#333;">' . esc_html($text) . '</div>';
        echo '</div>';
        echo '</div>';
    }
}

new Webyaz_Delivery_Date();
