<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('WooCommerce')) return;

class Webyaz_Free_Shipping_Bar {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_footer', array($this, 'display_bar'));
        add_action('wp_ajax_webyaz_fsb_cart_total', array($this, 'get_cart_total'));
        add_action('wp_ajax_nopriv_webyaz_fsb_cart_total', array($this, 'get_cart_total'));
    }

    public function register_settings() {
        register_setting('webyaz_fsb_group', 'webyaz_free_shipping_bar');
    }

    private static function get_defaults() {
        return array(
            'min_amount'    => '500',
            'bar_bg'        => '#4caf50',
            'bar_text'      => '#ffffff',
            'position'      => 'bottom', // top veya bottom
            'message'       => 'Ucretsiz kargo icin {amount} daha alisveris yapin!',
            'success_msg'   => '🎉 Tebrikler! Ucretsiz kargo kazandiniz!',
            'show_on'       => 'all', // all, shop, product, cart
            'hide_mobile'   => '0',
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_free_shipping_bar', array()), self::get_defaults());
    }

    public function get_cart_total() {
        $total = WC()->cart ? WC()->cart->get_subtotal() : 0;
        wp_send_json_success(array('total' => $total));
    }

    public function display_bar() {
        $opts = self::get_opts();
        $min = floatval($opts['min_amount']);
        if ($min <= 0) return;

        // Sayfa filtresi
        $show = $opts['show_on'];
        if ($show === 'shop' && !is_shop() && !is_product_category()) return;
        if ($show === 'product' && !is_product()) return;
        if ($show === 'cart' && !is_cart()) return;

        // Mobil gizle
        $mobile_hide = ($opts['hide_mobile'] === '1') ? 'display:none !important;' : '';

        $cart_total = WC()->cart ? WC()->cart->get_subtotal() : 0;
        $remaining = max(0, $min - $cart_total);
        $percent = min(100, ($cart_total / $min) * 100);
        $is_free = ($remaining <= 0);

        $message = $is_free
            ? $opts['success_msg']
            : str_replace('{amount}', '<strong>' . wc_price($remaining) . '</strong>', $opts['message']);

        $pos_style = ($opts['position'] === 'top')
            ? 'top:0;'
            : 'bottom:0;';
        ?>
        <div id="webyazFSB" style="position:fixed;<?php echo $pos_style; ?>left:0;right:0;z-index:99999;background:<?php echo esc_attr($opts['bar_bg']); ?>;color:<?php echo esc_attr($opts['bar_text']); ?>;font-family:-apple-system,BlinkMacSystemFont,sans-serif;box-shadow:0 -2px 15px rgba(0,0,0,0.15);transition:all .4s ease;<?php echo $mobile_hide ? '@media(max-width:768px){' . $mobile_hide . '}' : ''; ?>">
            <div style="max-width:800px;margin:0 auto;padding:12px 20px;text-align:center;">
                <div style="font-size:14px;font-weight:600;margin-bottom:8px;" id="webyazFSBMsg">
                    <?php echo wp_kses_post($message); ?>
                </div>
                <div style="background:rgba(255,255,255,0.3);border-radius:20px;height:8px;overflow:hidden;max-width:500px;margin:0 auto;">
                    <div id="webyazFSBProgress" style="height:100%;background:<?php echo esc_attr($opts['bar_text']); ?>;border-radius:20px;transition:width .6s ease;width:<?php echo $percent; ?>%;"></div>
                </div>
            </div>
            <button onclick="this.parentElement.style.display='none';" style="position:absolute;top:8px;right:12px;background:none;border:none;color:<?php echo esc_attr($opts['bar_text']); ?>;font-size:18px;cursor:pointer;opacity:0.7;">&times;</button>
        </div>
        <?php if ($opts['hide_mobile'] === '1'): ?>
        <style>@media(max-width:768px){#webyazFSB{display:none !important;}}</style>
        <?php endif; ?>
        <script>
        (function(){
            // Sepet guncellendiginde bari guncelle
            jQuery(document.body).on('added_to_cart removed_from_cart updated_cart_totals', function(){
                jQuery.post(webyaz_ajax.ajax_url, {action:'webyaz_fsb_cart_total',nonce:webyaz_ajax.nonce}, function(r){
                    if(!r.success) return;
                    var total = parseFloat(r.data.total);
                    var min = <?php echo $min; ?>;
                    var remaining = Math.max(0, min - total);
                    var percent = Math.min(100, (total/min)*100);
                    var bar = document.getElementById('webyazFSBProgress');
                    var msg = document.getElementById('webyazFSBMsg');
                    if(bar) bar.style.width = percent+'%';
                    if(msg) {
                        if(remaining <= 0) {
                            msg.innerHTML = '<?php echo esc_js($opts['success_msg']); ?>';
                        } else {
                            var priceHtml = '<strong>'+remaining.toFixed(2).replace('.', ',')+'&nbsp;₺</strong>';
                            msg.innerHTML = '<?php echo esc_js(str_replace('{amount}', "'+priceHtml+'", $opts['message'])); ?>';
                        }
                    }
                });
            });
        })();
        </script>
        <?php
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Kargo Bari', 'Kargo Bari', 'manage_options', 'webyaz-free-shipping-bar', array($this, 'render_admin'));
    }

    public function render_admin() {
        $opts = self::get_opts();
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>🚚 Ücretsiz Kargo Barı</h1><p>Sepet tutarına göre ücretsiz kargo progress barı göster</p></div>
            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">Ayarlar kaydedildi!</div><?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('webyaz_fsb_group'); ?>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Genel Ayarlar</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Ücretsiz Kargo Minimum Tutar (TL)</label>
                            <input type="number" step="1" name="webyaz_free_shipping_bar[min_amount]" value="<?php echo esc_attr($opts['min_amount']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Konum</label>
                            <select name="webyaz_free_shipping_bar[position]">
                                <option value="bottom" <?php selected($opts['position'], 'bottom'); ?>>Alt (Sabit)</option>
                                <option value="top" <?php selected($opts['position'], 'top'); ?>>Üst (Sabit)</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Gösterim Yeri</label>
                            <select name="webyaz_free_shipping_bar[show_on]">
                                <option value="all" <?php selected($opts['show_on'], 'all'); ?>>Tüm Sayfalar</option>
                                <option value="shop" <?php selected($opts['show_on'], 'shop'); ?>>Sadece Mağaza</option>
                                <option value="product" <?php selected($opts['show_on'], 'product'); ?>>Sadece Ürün Sayfası</option>
                                <option value="cart" <?php selected($opts['show_on'], 'cart'); ?>>Sadece Sepet</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Mobilde Gizle</label>
                            <select name="webyaz_free_shipping_bar[hide_mobile]">
                                <option value="0" <?php selected($opts['hide_mobile'], '0'); ?>>Hayır</option>
                                <option value="1" <?php selected($opts['hide_mobile'], '1'); ?>>Evet</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Tasarım</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Bar Arka Plan Rengi</label>
                            <input type="color" name="webyaz_free_shipping_bar[bar_bg]" value="<?php echo esc_attr($opts['bar_bg']); ?>" style="width:60px;height:40px;padding:2px;cursor:pointer;">
                        </div>
                        <div class="webyaz-field">
                            <label>Yazı Rengi</label>
                            <input type="color" name="webyaz_free_shipping_bar[bar_text]" value="<?php echo esc_attr($opts['bar_text']); ?>" style="width:60px;height:40px;padding:2px;cursor:pointer;">
                        </div>
                    </div>
                </div>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Mesajlar</h2>
                    <p style="color:#666;font-size:12px;margin:-5px 0 12px;">Mesajda <code>{amount}</code> kalan tutarı gösterir.</p>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>İlerleme Mesajı</label>
                            <input type="text" name="webyaz_free_shipping_bar[message]" value="<?php echo esc_attr($opts['message']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Başarı Mesajı</label>
                            <input type="text" name="webyaz_free_shipping_bar[success_msg]" value="<?php echo esc_attr($opts['success_msg']); ?>">
                        </div>
                    </div>
                </div>
                <?php submit_button('Kaydet'); ?>
            </form>
        </div>
        <?php
    }
}

new Webyaz_Free_Shipping_Bar();
