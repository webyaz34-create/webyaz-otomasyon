<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('WooCommerce')) return;

class Webyaz_Stock_Alert {

    public function __construct() {
        add_action('woocommerce_single_product_summary', [$this, 'show_alert_form'], 31);
        add_action('wp_ajax_webyaz_stock_alert', [$this, 'handle_ajax']);
        add_action('wp_ajax_nopriv_webyaz_stock_alert', [$this, 'handle_ajax']);
        add_action('woocommerce_product_set_stock_status', [$this, 'check_stock_change'], 10, 3);
    }

    public function show_alert_form() {
        global $product;
        if (!$product || $product->is_in_stock()) return;
        ?>
        <div class="webyaz-stock-alert">
            <p class="webyaz-stock-alert-title">Bu urun su an stokta yok</p>
            <div class="webyaz-stock-alert-form" id="webyazStockForm" data-product="<?php echo esc_attr($product->get_id()); ?>">
                <input type="email" id="webyazStockEmail" placeholder="E-posta adresiniz" required>
                <button type="button" id="webyazStockBtn" class="webyaz-stock-alert-btn">Stok Gelince Haber Ver</button>
            </div>
            <div class="webyaz-stock-alert-msg" id="webyazStockMsg" style="display:none;"></div>
        </div>
        <?php
    }

    public function handle_ajax() {
        check_ajax_referer('webyaz_nonce', 'nonce');
        $email = sanitize_email($_POST['email'] ?? '');
        $product_id = absint($_POST['product_id'] ?? 0);

        if (!is_email($email) || !$product_id) {
            wp_send_json_error('Gecerli bir e-posta giriniz.');
        }

        $alerts = get_post_meta($product_id, '_webyaz_stock_alerts', true);
        if (!is_array($alerts)) $alerts = [];

        if (in_array($email, $alerts)) {
            wp_send_json_success('Bu e-posta zaten kayitli. Stok gelince bildirim alacaksiniz.');
            return;
        }

        $alerts[] = $email;
        update_post_meta($product_id, '_webyaz_stock_alerts', $alerts);
        wp_send_json_success('Kaydedildi! Urun stoga girdiginde size e-posta gonderecegiz.');
    }

    public function check_stock_change($product_id, $stock_status, $product) {
        if ($stock_status !== 'instock') return;

        $alerts = get_post_meta($product_id, '_webyaz_stock_alerts', true);
        if (empty($alerts) || !is_array($alerts)) return;

        $product_name = $product->get_name();
        $product_url = get_permalink($product_id);
        $site_name = get_bloginfo('name');

        foreach ($alerts as $email) {
            $subject = "{$site_name} - {$product_name} stoga girdi!";
            $message = "Merhaba,<br><br>";
            $message .= "Takip ettiginiz <strong>{$product_name}</strong> urunu stoga girmistir.<br><br>";
            $message .= "<a href='{$product_url}' style='background:#d26e4b;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;'>Urunu Goruntule</a><br><br>";
            $message .= "Iyi alisverisler,<br>{$site_name}";

            wp_mail($email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
        }

        delete_post_meta($product_id, '_webyaz_stock_alerts');
    }
}

new Webyaz_Stock_Alert();
