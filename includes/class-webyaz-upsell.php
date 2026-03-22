<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Upsell {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_footer', array($this, 'popup_html'));
        add_action('wp_ajax_webyaz_upsell_products', array($this, 'ajax_products'));
        add_action('wp_ajax_nopriv_webyaz_upsell_products', array($this, 'ajax_products'));
    }

    public function register_settings() {
        register_setting('webyaz_upsell_group', 'webyaz_upsell');
    }

    private static function get_defaults() {
        return array('active' => '0', 'discount' => '20', 'title' => 'Bunu da sepetine ekle!', 'count' => '3');
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_upsell', array()), self::get_defaults());
    }

    public function ajax_products() {
        check_ajax_referer('webyaz_nonce', 'nonce');
        $product_id = intval($_POST['product_id'] ?? 0);
        if (!$product_id) { wp_send_json_error(); return; }

        $opts = self::get_opts();
        $product = wc_get_product($product_id);
        if (!$product) { wp_send_json_error(); return; }

        $upsell_ids = $product->get_upsell_ids();
        if (empty($upsell_ids)) {
            $upsell_ids = wc_get_related_product_ids($product_id, intval($opts['count']));
        }
        $upsell_ids = array_slice($upsell_ids, 0, intval($opts['count']));

        $discount = floatval($opts['discount']);
        $items = array();
        foreach ($upsell_ids as $uid) {
            $up = wc_get_product($uid);
            if (!$up || !$up->is_purchasable()) continue;
            $price = floatval($up->get_price());
            $items[] = array(
                'id' => $uid,
                'name' => $up->get_name(),
                'image' => wp_get_attachment_image_url($up->get_image_id(), 'thumbnail'),
                'price' => wc_price($price),
                'sale_price' => wc_price($price * (1 - $discount / 100)),
                'permalink' => $up->get_permalink(),
            );
        }
        wp_send_json_success($items);
    }

    public function popup_html() {
        if (!function_exists('is_product') || !is_product()) return;
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return;
        ?>
        <div id="webyazUpsellPopup" class="webyaz-upsell-overlay" style="display:none;">
            <div class="webyaz-upsell-popup">
                <button class="webyaz-upsell-close" onclick="document.getElementById('webyazUpsellPopup').style.display='none';">&times;</button>
                <h3 class="webyaz-upsell-title"><?php echo esc_html($opts['title']); ?></h3>
                <p class="webyaz-upsell-discount">%<?php echo esc_html($opts['discount']); ?> indirimli!</p>
                <div id="webyazUpsellItems" class="webyaz-upsell-items"></div>
            </div>
        </div>
        <?php
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Upsell Popup', 'Upsell Popup', 'manage_options', 'webyaz-upsell', array($this, 'render_admin'));
    }

    public function render_admin() {
        $opts = self::get_opts();
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>Upsell Popup Ayarlari</h1><p>Sepete eklendiginde onerilen urunleri goster</p></div>
            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">Kaydedildi!</div><?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('webyaz_upsell_group'); ?>
                <div class="webyaz-settings-section">
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Aktif</label>
                            <select name="webyaz_upsell[active]">
                                <option value="0" <?php selected($opts['active'], '0'); ?>>Kapali</option>
                                <option value="1" <?php selected($opts['active'], '1'); ?>>Aktif</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Indirim Orani (%)</label>
                            <input type="number" name="webyaz_upsell[discount]" value="<?php echo esc_attr($opts['discount']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Popup Basligi</label>
                            <input type="text" name="webyaz_upsell[title]" value="<?php echo esc_attr($opts['title']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Gosterilecek Urun Sayisi</label>
                            <input type="number" name="webyaz_upsell[count]" value="<?php echo esc_attr($opts['count']); ?>" min="1" max="6">
                        </div>
                    </div>
                </div>
                <?php submit_button('Kaydet'); ?>
            </form>
        </div>
        <?php
    }
}

new Webyaz_Upsell();
