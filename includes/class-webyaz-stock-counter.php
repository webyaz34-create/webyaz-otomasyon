<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Stock_Counter {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('woocommerce_single_product_summary', array($this, 'display_counter'), 26);
        add_action('woocommerce_after_shop_loop_item_title', array($this, 'display_counter_loop'), 9);
    }

    public function register_settings() {
        register_setting('webyaz_stock_counter_group', 'webyaz_stock_counter');
    }

    private static function get_defaults() {
        return array(
            'active' => '0',
            'threshold' => '10',
            'message' => 'Son {stock} urun kaldi!',
            'urgency_color' => '#d32f2f',
            'show_bar' => '1',
            'bar_max' => '20',
            'show_in_loop' => '1',
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_stock_counter', array()), self::get_defaults());
    }

    public function display_counter() {
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return;

        global $product;
        if (!$product || !$product->managing_stock()) return;
        $stock = $product->get_stock_quantity();
        if ($stock === null || $stock <= 0 || $stock > intval($opts['threshold'])) return;

        $msg = str_replace('{stock}', $stock, $opts['message']);
        $bar_max = max(1, intval($opts['bar_max']));
        $percent = min(100, round(($stock / $bar_max) * 100));
        $color = esc_attr($opts['urgency_color']);

        echo '<div class="webyaz-stock-counter">';
        echo '<div class="webyaz-sc-icon">';
        if ($stock <= 3) {
            echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="' . $color . '"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>';
        } else {
            echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="' . $color . '"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>';
        }
        echo '</div>';
        echo '<span class="webyaz-sc-text" style="color:' . $color . ';">' . esc_html($msg) . '</span>';

        if ($opts['show_bar'] === '1') {
            echo '<div class="webyaz-sc-bar">';
            echo '<div class="webyaz-sc-bar-fill" style="width:' . $percent . '%;background:' . $color . ';"></div>';
            echo '</div>';
        }
        echo '</div>';
    }

    public function display_counter_loop() {
        $opts = self::get_opts();
        if ($opts['active'] !== '1' || $opts['show_in_loop'] !== '1') return;

        global $product;
        if (!$product || !$product->managing_stock()) return;
        $stock = $product->get_stock_quantity();
        if ($stock === null || $stock <= 0 || $stock > intval($opts['threshold'])) return;

        $color = esc_attr($opts['urgency_color']);
        echo '<div class="webyaz-sc-loop" style="color:' . $color . ';">';
        echo '<svg width="12" height="12" viewBox="0 0 24 24" fill="' . $color . '" style="vertical-align:middle;margin-right:3px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>';
        echo 'Son ' . intval($stock) . ' urun!';
        echo '</div>';
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Stok Sayaci', 'Stok Sayaci', 'manage_options', 'webyaz-stock-counter', array($this, 'render_admin'));
    }

    public function render_admin() {
        $opts = self::get_opts();
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>Stok Sayaci Ayarlari</h1><p>Dusuk stoklu urunlerde aciliyet uyarisi goster</p></div>
            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">Kaydedildi!</div><?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('webyaz_stock_counter_group'); ?>
                <div class="webyaz-settings-section">
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field"><label>Aktif</label><select name="webyaz_stock_counter[active]"><option value="0" <?php selected($opts['active'], '0'); ?>>Kapali</option><option value="1" <?php selected($opts['active'], '1'); ?>>Aktif</option></select></div>
                        <div class="webyaz-field"><label>Esik Degeri (bu sayinin altinda goster)</label><input type="number" name="webyaz_stock_counter[threshold]" value="<?php echo esc_attr($opts['threshold']); ?>" min="1" max="100"></div>
                        <div class="webyaz-field"><label>Mesaj ({stock} = stok sayisi)</label><input type="text" name="webyaz_stock_counter[message]" value="<?php echo esc_attr($opts['message']); ?>"></div>
                        <div class="webyaz-field"><label>Uyari Rengi</label><input type="color" name="webyaz_stock_counter[urgency_color]" value="<?php echo esc_attr($opts['urgency_color']); ?>"></div>
                        <div class="webyaz-field"><label>Stok Cubugu Goster</label><select name="webyaz_stock_counter[show_bar]"><option value="1" <?php selected($opts['show_bar'], '1'); ?>>Evet</option><option value="0" <?php selected($opts['show_bar'], '0'); ?>>Hayir</option></select></div>
                        <div class="webyaz-field"><label>Cubuk Maksimum Degeri</label><input type="number" name="webyaz_stock_counter[bar_max]" value="<?php echo esc_attr($opts['bar_max']); ?>" min="1"></div>
                        <div class="webyaz-field"><label>Urun Listesinde Goster</label><select name="webyaz_stock_counter[show_in_loop]"><option value="1" <?php selected($opts['show_in_loop'], '1'); ?>>Evet</option><option value="0" <?php selected($opts['show_in_loop'], '0'); ?>>Hayir</option></select></div>
                    </div>
                </div>
                <?php submit_button('Kaydet'); ?>
            </form>
        </div>
        <?php
    }
}

new Webyaz_Stock_Counter();
