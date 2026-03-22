<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('WooCommerce')) return;

class Webyaz_Product_Tabs {

    public function __construct() {
        add_filter('woocommerce_product_tabs', [$this, 'add_tabs']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_product', [$this, 'save_meta']);
    }

    public function add_tabs($tabs) {
        $tabs['webyaz_teslimat'] = [
            'title' => 'Teslimat Bilgileri',
            'priority' => 35,
            'callback' => [$this, 'teslimat_tab_content'],
        ];

        $tabs['webyaz_iade'] = [
            'title' => 'İade ve Değişim',
            'priority' => 40,
            'callback' => [$this, 'iade_tab_content'],
        ];

        return $tabs;
    }

    public function teslimat_tab_content() {
        global $product;
        $content = get_post_meta($product->get_id(), '_webyaz_teslimat', true);
        if (empty($content)) {
            $content = get_option('webyaz_default_teslimat', $this->default_teslimat());
        }
        echo '<div class="webyaz-tab-content webyaz-teslimat">';
        echo wp_kses_post(wpautop($content));
        echo '</div>';
    }

    public function iade_tab_content() {
        global $product;
        $content = get_post_meta($product->get_id(), '_webyaz_iade', true);
        if (empty($content)) {
            $content = get_option('webyaz_default_iade', $this->default_iade());
        }
        echo '<div class="webyaz-tab-content webyaz-iade">';
        echo wp_kses_post(wpautop($content));
        echo '</div>';
    }

    public function add_meta_boxes() {
        add_meta_box('webyaz_teslimat_box', 'Teslimat Bilgileri (Webyaz)', [$this, 'teslimat_meta_box'], 'product', 'normal', 'default');
        add_meta_box('webyaz_iade_box', 'İade ve Değişim (Webyaz)', [$this, 'iade_meta_box'], 'product', 'normal', 'default');
    }

    public function teslimat_meta_box($post) {
        wp_nonce_field('webyaz_tabs_nonce', 'webyaz_tabs_nonce');
        $val = get_post_meta($post->ID, '_webyaz_teslimat', true);
        wp_editor($val, 'webyaz_teslimat_editor', ['textarea_name' => 'webyaz_teslimat', 'textarea_rows' => 8]);
        echo '<p class="description">Boş bırakılırsa genel varsayılan içerik gösterilir.</p>';
    }

    public function iade_meta_box($post) {
        $val = get_post_meta($post->ID, '_webyaz_iade', true);
        wp_editor($val, 'webyaz_iade_editor', ['textarea_name' => 'webyaz_iade', 'textarea_rows' => 8]);
        echo '<p class="description">Boş bırakılırsa genel varsayılan içerik gösterilir.</p>';
    }

    public function save_meta($post_id) {
        if (!isset($_POST['webyaz_tabs_nonce']) || !wp_verify_nonce($_POST['webyaz_tabs_nonce'], 'webyaz_tabs_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        if (isset($_POST['webyaz_teslimat'])) {
            update_post_meta($post_id, '_webyaz_teslimat', wp_kses_post($_POST['webyaz_teslimat']));
        }
        if (isset($_POST['webyaz_iade'])) {
            update_post_meta($post_id, '_webyaz_iade', wp_kses_post($_POST['webyaz_iade']));
        }
    }

    private function default_teslimat() {
        $site = get_bloginfo('name');
        return "<h4>Teslimat Bilgileri</h4>
<ul>
<li><strong>Kargo Firması:</strong> Anlaşmalı kargo firmaları ile gönderim yapılmaktadır.</li>
<li><strong>Teslimat Süresi:</strong> Siparişiniz onaylandıktan sonra 1-3 iş günü içinde kargoya verilir.</li>
<li><strong>Kargo Takibi:</strong> Kargoya verildikten sonra takip numarası e-posta ile iletilir.</li>
<li><strong>Teslimat Adresi:</strong> Sipariş sırasında belirttiğiniz adrese teslim edilir.</li>
</ul>
<p>{$site} olarak siparişlerinizi en hızlı şekilde ulaştırmak için çalışıyoruz.</p>";
    }

    private function default_iade() {
        $site = get_bloginfo('name');
        return "<h4>İade ve Değişim Koşulları</h4>
<ul>
<li>Ürünü teslim aldığınız tarihten itibaren <strong>14 gün</strong> içinde iade veya değişim talebinde bulunabilirsiniz.</li>
<li>İade edilecek ürünler kullanılmamış, orijinal ambalajında ve faturası ile birlikte gönderilmelidir.</li>
<li>Kişiye özel üretilen ürünlerde iade kabul edilmemektedir.</li>
<li>İade kargo ücreti alıcıya aittir (hatalı/kusurlu ürün hariç).</li>
<li>İade onaylandıktan sonra ödemeniz <strong>5-10 iş günü</strong> içinde iade edilir.</li>
</ul>
<p>Detaylı bilgi için bizimle iletişime geçebilirsiniz.</p>";
    }
}

new Webyaz_Product_Tabs();
