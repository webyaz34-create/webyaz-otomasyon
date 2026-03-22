<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Bulk_Discount {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('woocommerce_before_calculate_totals', array($this, 'apply_discounts'), 20);
        add_action('woocommerce_single_product_summary', array($this, 'display_rules'), 35);
        add_filter('woocommerce_package_rates', array($this, 'free_shipping'), 100);
    }

    public function register_settings() {
        register_setting('webyaz_bulk_discount_group', 'webyaz_bulk_discount');
    }

    private static function get_defaults() {
        return array(
            'active' => '0',
            'rules' => array(),
            'free_shipping_active' => '0',
            'free_shipping_min' => '500',
            'free_shipping_text' => 'ustu kargo bedava!',
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_bulk_discount', array()), self::get_defaults());
    }

    public function apply_discounts($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        $opts = self::get_opts();
        if ($opts['active'] !== '1' || empty($opts['rules'])) return;

        $total_qty = 0;
        foreach ($cart->get_cart() as $item) {
            $total_qty += $item['quantity'];
        }

        $discount = 0;
        foreach ($opts['rules'] as $rule) {
            if ($total_qty >= intval($rule['min_qty'])) {
                $discount = floatval($rule['discount']);
            }
        }

        if ($discount > 0) {
            foreach ($cart->get_cart() as $item) {
                $price = floatval($item['data']->get_price());
                $item['data']->set_price($price * (1 - $discount / 100));
            }
        }
    }

    public function free_shipping($rates) {
        $opts = self::get_opts();
        if ($opts['free_shipping_active'] !== '1') return $rates;
        $min = floatval($opts['free_shipping_min']);
        $total = WC()->cart ? WC()->cart->get_subtotal() : 0;
        if ($total >= $min) {
            foreach ($rates as $id => $rate) {
                if ($rate->method_id !== 'free_shipping') {
                    $rates[$id]->cost = 0;
                    $rates[$id]->label = 'Ucretsiz Kargo';
                }
            }
        }
        return $rates;
    }

    public function display_rules() {
        $opts = self::get_opts();
        if ($opts['active'] !== '1' || empty($opts['rules'])) return;
        echo '<div class="webyaz-bulk-rules">';
        echo '<span class="webyaz-bulk-title">Toplu Alis Indirimi</span>';
        echo '<div class="webyaz-bulk-items">';
        foreach ($opts['rules'] as $rule) {
            echo '<div class="webyaz-bulk-item"><strong>' . intval($rule['min_qty']) . '+ urun</strong> &#8594; <span>%' . esc_html($rule['discount']) . ' indirim</span></div>';
        }
        echo '</div>';
        if ($opts['free_shipping_active'] === '1') {
            echo '<div class="webyaz-bulk-shipping">' . esc_html($opts['free_shipping_min']) . ' TL ' . esc_html($opts['free_shipping_text']) . '</div>';
        }
        echo '</div>';
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Toplu Indirim', 'Toplu Indirim', 'manage_options', 'webyaz-bulk-discount', array($this, 'render_admin'));
    }

    public function render_admin() {
        $opts = self::get_opts();
        if (!is_array($opts['rules'])) $opts['rules'] = array();
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>Toplu Indirim Kurallari</h1><p>Coklu urun alimlarinda otomatik indirim uygula</p></div>
            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">Ayarlar kaydedildi!</div><?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('webyaz_bulk_discount_group'); ?>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Genel</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Toplu Indirimi Aktif Et</label>
                            <select name="webyaz_bulk_discount[active]">
                                <option value="0" <?php selected($opts['active'], '0'); ?>>Kapali</option>
                                <option value="1" <?php selected($opts['active'], '1'); ?>>Aktif</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Indirim Kurallari</h2>
                    <div id="webyazBulkRules">
                        <?php foreach ($opts['rules'] as $i => $rule): ?>
                        <div class="webyaz-bulk-rule-row" style="display:flex;gap:10px;align-items:center;margin-bottom:8px;">
                            <input type="number" name="webyaz_bulk_discount[rules][<?php echo $i; ?>][min_qty]" value="<?php echo esc_attr($rule['min_qty']); ?>" placeholder="Min adet" style="width:100px;">
                            <span>adet alinda</span>
                            <input type="number" step="0.1" name="webyaz_bulk_discount[rules][<?php echo $i; ?>][discount]" value="<?php echo esc_attr($rule['discount']); ?>" placeholder="% indirim" style="width:100px;">
                            <span>% indirim</span>
                            <button type="button" onclick="this.parentElement.remove();" class="button" style="color:#d32f2f;">&times;</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="webyazAddBulkRule" class="button">+ Kural Ekle</button>
                </div>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Ucretsiz Kargo</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Ucretsiz Kargo Aktif</label>
                            <select name="webyaz_bulk_discount[free_shipping_active]">
                                <option value="0" <?php selected($opts['free_shipping_active'], '0'); ?>>Kapali</option>
                                <option value="1" <?php selected($opts['free_shipping_active'], '1'); ?>>Aktif</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Minimum Tutar (TL)</label>
                            <input type="number" name="webyaz_bulk_discount[free_shipping_min]" value="<?php echo esc_attr($opts['free_shipping_min']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Gosterim Metni</label>
                            <input type="text" name="webyaz_bulk_discount[free_shipping_text]" value="<?php echo esc_attr($opts['free_shipping_text']); ?>">
                        </div>
                    </div>
                </div>
                <?php submit_button('Kaydet'); ?>
            </form>
        </div>
        <script>
        var ruleIdx = <?php echo count($opts['rules']); ?>;
        document.getElementById('webyazAddBulkRule').addEventListener('click', function(){
            var html = '<div class="webyaz-bulk-rule-row" style="display:flex;gap:10px;align-items:center;margin-bottom:8px;">';
            html += '<input type="number" name="webyaz_bulk_discount[rules]['+ruleIdx+'][min_qty]" placeholder="Min adet" style="width:100px;">';
            html += '<span>adet alinda</span>';
            html += '<input type="number" step="0.1" name="webyaz_bulk_discount[rules]['+ruleIdx+'][discount]" placeholder="% indirim" style="width:100px;">';
            html += '<span>% indirim</span>';
            html += '<button type="button" onclick="this.parentElement.remove();" class="button" style="color:#d32f2f;">&times;</button></div>';
            document.getElementById('webyazBulkRules').insertAdjacentHTML('beforeend', html);
            ruleIdx++;
        });
        </script>
        <?php
    }
}

new Webyaz_Bulk_Discount();
