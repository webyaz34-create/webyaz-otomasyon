<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('WooCommerce')) return;

class Webyaz_Gift {

    public function __construct() {
        add_action('woocommerce_after_order_notes', [$this, 'gift_fields']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_gift_fields']);
        add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'display_in_admin']);
        add_action('woocommerce_email_after_order_table', [$this, 'display_in_email'], 15, 4);
    }

    public function gift_fields($checkout) {
        echo '<div class="webyaz-gift-section">';
        echo '<h3 class="webyaz-gift-title">Hediye Secenekleri</h3>';

        woocommerce_form_field('webyaz_is_gift', [
            'type' => 'checkbox',
            'class' => ['form-row-wide', 'webyaz-gift-check'],
            'label' => 'Bu siparis bir hediye',
        ], $checkout->get_value('webyaz_is_gift'));

        echo '<div class="webyaz-gift-details" id="webyazGiftDetails" style="display:none;">';

        woocommerce_form_field('webyaz_gift_wrap', [
            'type' => 'checkbox',
            'class' => ['form-row-wide'],
            'label' => 'Hediye paketi istiyorum',
        ], $checkout->get_value('webyaz_gift_wrap'));

        woocommerce_form_field('webyaz_gift_note', [
            'type' => 'textarea',
            'class' => ['form-row-wide'],
            'label' => 'Hediye notu',
            'placeholder' => 'Hediyenize eklemek istediginiz mesaj...',
            'custom_attributes' => ['rows' => 3, 'maxlength' => 300],
        ], $checkout->get_value('webyaz_gift_note'));

        woocommerce_form_field('webyaz_gift_hide_price', [
            'type' => 'checkbox',
            'class' => ['form-row-wide'],
            'label' => 'Faturada fiyat gosterilmesin',
        ], $checkout->get_value('webyaz_gift_hide_price'));

        echo '</div></div>';
    }

    public function save_gift_fields($order_id) {
        $is_gift = !empty($_POST['webyaz_is_gift']) ? 'yes' : 'no';
        update_post_meta($order_id, '_webyaz_is_gift', $is_gift);

        if ($is_gift === 'yes') {
            update_post_meta($order_id, '_webyaz_gift_wrap', !empty($_POST['webyaz_gift_wrap']) ? 'yes' : 'no');
            update_post_meta($order_id, '_webyaz_gift_note', sanitize_textarea_field($_POST['webyaz_gift_note'] ?? ''));
            update_post_meta($order_id, '_webyaz_gift_hide_price', !empty($_POST['webyaz_gift_hide_price']) ? 'yes' : 'no');
        }
    }

    public function display_in_admin($order) {
        $order_id = $order->get_id();
        if (get_post_meta($order_id, '_webyaz_is_gift', true) !== 'yes') return;

        echo '<div class="webyaz-admin-fields" style="border-left-color:var(--webyaz-secondary,#d26e4b);">';
        echo '<h3>Hediye Bilgileri</h3>';
        echo '<p><strong>Hediye Paketi:</strong> ' . (get_post_meta($order_id, '_webyaz_gift_wrap', true) === 'yes' ? 'Evet' : 'Hayir') . '</p>';
        $note = get_post_meta($order_id, '_webyaz_gift_note', true);
        if (!empty($note)) {
            echo '<p><strong>Hediye Notu:</strong> ' . esc_html($note) . '</p>';
        }
        echo '<p><strong>Fiyat Gizle:</strong> ' . (get_post_meta($order_id, '_webyaz_gift_hide_price', true) === 'yes' ? 'Evet' : 'Hayir') . '</p>';
        echo '</div>';
    }

    public function display_in_email($order, $sent_to_admin, $plain_text, $email) {
        if (!$sent_to_admin || $plain_text) return;
        $this->display_in_admin($order);
    }
}

new Webyaz_Gift();
