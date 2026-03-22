<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Preorder {

    public function __construct() {
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_fields'));
        add_filter('woocommerce_product_single_add_to_cart_text', array($this, 'button_text'), 10, 2);
        add_filter('woocommerce_product_add_to_cart_text', array($this, 'button_text'), 10, 2);
        add_action('woocommerce_single_product_summary', array($this, 'show_preorder_notice'), 25);
        add_filter('woocommerce_is_purchasable', array($this, 'make_purchasable'), 10, 2);
        add_filter('woocommerce_product_is_in_stock', array($this, 'force_in_stock'), 10, 2);
    }

    // Admin: Urun duzenleme alanlari
    public function add_fields() {
        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;color:#7b1fa2;">🗓️ On Siparis Ayarlari</h4>';

        woocommerce_wp_checkbox(array(
            'id'          => '_webyaz_preorder',
            'label'       => 'On Siparis Aktif',
            'description' => 'Bu urunu on siparise ac',
        ));

        woocommerce_wp_text_input(array(
            'id'          => '_webyaz_preorder_date',
            'label'       => 'Tahmini Stok Tarihi',
            'type'        => 'date',
            'description' => 'Urun ne zaman stoga gelecek',
        ));

        woocommerce_wp_text_input(array(
            'id'          => '_webyaz_preorder_text',
            'label'       => 'Buton Metni',
            'placeholder' => 'On Siparis Ver',
            'description' => 'Sepete Ekle yerine gosterilecek metin',
        ));

        woocommerce_wp_text_input(array(
            'id'          => '_webyaz_preorder_note',
            'label'       => 'Bilgi Notu',
            'placeholder' => 'Bu urun on siparise aciktir.',
            'description' => 'Urun sayfasinda gosterilecek not',
        ));

        echo '</div>';
    }

    // Kaydet
    public function save_fields($post_id) {
        update_post_meta($post_id, '_webyaz_preorder', isset($_POST['_webyaz_preorder']) ? 'yes' : 'no');
        if (isset($_POST['_webyaz_preorder_date'])) {
            update_post_meta($post_id, '_webyaz_preorder_date', sanitize_text_field($_POST['_webyaz_preorder_date']));
        }
        if (isset($_POST['_webyaz_preorder_text'])) {
            update_post_meta($post_id, '_webyaz_preorder_text', sanitize_text_field($_POST['_webyaz_preorder_text']));
        }
        if (isset($_POST['_webyaz_preorder_note'])) {
            update_post_meta($post_id, '_webyaz_preorder_note', sanitize_text_field($_POST['_webyaz_preorder_note']));
        }
    }

    // On siparis aktifse buton metnini degistir
    public function button_text($text, $product) {
        if (get_post_meta($product->get_id(), '_webyaz_preorder', true) === 'yes') {
            $custom = get_post_meta($product->get_id(), '_webyaz_preorder_text', true);
            return $custom ?: 'On Siparis Ver';
        }
        return $text;
    }

    // Urun sayfasinda on siparis bildirimi
    public function show_preorder_notice() {
        global $product;
        if (!$product || get_post_meta($product->get_id(), '_webyaz_preorder', true) !== 'yes') return;

        $date = get_post_meta($product->get_id(), '_webyaz_preorder_date', true);
        $note = get_post_meta($product->get_id(), '_webyaz_preorder_note', true);

        echo '<div style="background:linear-gradient(135deg,#fff3e0,#ffe0b2);border-left:4px solid #e65100;padding:12px 16px;border-radius:8px;margin:12px 0;font-size:13px;">';
        echo '<div style="font-weight:700;color:#e65100;margin-bottom:4px;">🗓️ On Siparis</div>';
        if ($note) {
            echo '<div style="color:#333;">' . esc_html($note) . '</div>';
        }
        if ($date) {
            $formatted = date_i18n('d F Y', strtotime($date));
            echo '<div style="color:#666;margin-top:4px;">Tahmini stok tarihi: <strong>' . esc_html($formatted) . '</strong></div>';
        }
        echo '</div>';
    }

    // On siparisteki urunleri satin alinabilir yap
    public function make_purchasable($purchasable, $product) {
        if (get_post_meta($product->get_id(), '_webyaz_preorder', true) === 'yes') {
            return true;
        }
        return $purchasable;
    }

    // On siparisteki urunleri stokta goster
    public function force_in_stock($in_stock, $product) {
        if (get_post_meta($product->get_id(), '_webyaz_preorder', true) === 'yes') {
            return true;
        }
        return $in_stock;
    }
}

new Webyaz_Preorder();
