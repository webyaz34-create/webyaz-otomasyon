<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Cart_Reminder {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_footer', array($this, 'exit_intent_popup'));
        add_action('wp_footer', array($this, 'tab_reminder'));
        add_action('wp_ajax_webyaz_apply_coupon', array($this, 'ajax_apply_coupon'));
        add_action('wp_ajax_nopriv_webyaz_apply_coupon', array($this, 'ajax_apply_coupon'));
    }

    public function register_settings() {
        register_setting('webyaz_cart_reminder_group', 'webyaz_cart_reminder');
    }

    public function ajax_apply_coupon() {
        check_ajax_referer('webyaz_coupon', 'nonce');
        $coupon = isset($_POST['coupon']) ? sanitize_text_field($_POST['coupon']) : '';
        if ($coupon && WC()->cart && !WC()->cart->has_discount($coupon)) {
            WC()->cart->apply_coupon($coupon);
        }
        wp_send_json_success();
    }

    private static function get_defaults() {
        return array(
            'active' => '0',
            'exit_popup' => '1',
            'exit_title' => 'Biraz bekle!',
            'exit_message' => 'Sepetinde urunler var. Simdi tamamla, firsati kacirma!',
            'exit_btn' => 'Sepete Don',
            'exit_discount' => '',
            'tab_reminder' => '1',
            'tab_text' => 'Sepetini unutma!',
            'delay_seconds' => '30',
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_cart_reminder', array()), self::get_defaults());
    }

    public function exit_intent_popup() {
        if (is_admin() || is_checkout()) return;
        $opts = self::get_opts();
        if ($opts['active'] !== '1' || $opts['exit_popup'] !== '1') return;
        $cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : '#';
        ?>
        <div id="webyazExitPopup" class="webyaz-exit-overlay" style="display:none;" onclick="if(event.target===this){this.style.display='none';sessionStorage.setItem('webyaz_exit_shown','1');}">
            <div class="webyaz-exit-popup">
                <button onclick="this.parentElement.parentElement.style.display='none';sessionStorage.setItem('webyaz_exit_shown','1');" class="webyaz-exit-close">&times;</button>
                <div class="webyaz-exit-icon">&#128722;</div>
                <h3 class="webyaz-exit-title"><?php echo esc_html($opts['exit_title']); ?></h3>
                <p class="webyaz-exit-msg"><?php echo esc_html($opts['exit_message']); ?></p>
                <?php if (!empty($opts['exit_discount'])): ?>
                <div class="webyaz-exit-coupon">Kupon Kodu: <strong><?php echo esc_html($opts['exit_discount']); ?></strong></div>
                <button type="button" class="webyaz-exit-btn" id="webyazApplyCoupon" style="margin-bottom:8px;background:#2e7d32;border:none;cursor:pointer;">Kuponu Uygula & Sepete Git</button>
                <?php endif; ?>
                <a href="<?php echo esc_url($cart_url); ?>" class="webyaz-exit-btn"><?php echo esc_html($opts['exit_btn']); ?></a>
            </div>
        </div>
        <script>
        (function(){
            if(sessionStorage.getItem('webyaz_exit_shown')) return;
            var shown=false;
            document.addEventListener('mouseout',function(e){
                if(shown) return;
                if(e.clientY<5 && e.relatedTarget==null){
                    var cart=document.cookie.indexOf('woocommerce_items_in_cart=0')===-1 && document.cookie.indexOf('woocommerce_items_in_cart')!==-1;
                    if(cart){
                        document.getElementById('webyazExitPopup').style.display='flex';
                        shown=true;
                    }
                }
            });
        })();
        <?php if (!empty($opts['exit_discount'])): ?>
        document.getElementById('webyazApplyCoupon').addEventListener('click', function(){
            var btn = this;
            btn.textContent = 'Uygulaniyor...';
            btn.disabled = true;
            var coupon = '<?php echo esc_js($opts["exit_discount"]); ?>';
            var cartUrl = '<?php echo esc_js($cart_url); ?>';
            fetch('<?php echo esc_js(admin_url("admin-ajax.php")); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=webyaz_apply_coupon&coupon=' + encodeURIComponent(coupon) + '&nonce=<?php echo wp_create_nonce("webyaz_coupon"); ?>'
            }).then(function(r){ return r.json(); }).then(function(){
                window.location.href = cartUrl;
            }).catch(function(){
                window.location.href = cartUrl;
            });
        });
        <?php endif; ?>
        </script>
        <?php
    }

    public function tab_reminder() {
        if (is_admin()) return;
        $opts = self::get_opts();
        if ($opts['active'] !== '1' || $opts['tab_reminder'] !== '1') return;
        $text = esc_js($opts['tab_text']);
        ?>
        <script>
        (function(){
            var origTitle=document.title;
            var reminderText='<?php echo $text; ?>';
            var interval;
            document.addEventListener('visibilitychange',function(){
                if(document.hidden){
                    var cart=document.cookie.indexOf('woocommerce_items_in_cart=0')===-1 && document.cookie.indexOf('woocommerce_items_in_cart')!==-1;
                    if(cart){
                        var toggle=true;
                        interval=setInterval(function(){
                            document.title=toggle?reminderText:origTitle;
                            toggle=!toggle;
                        },1500);
                    }
                } else {
                    clearInterval(interval);
                    document.title=origTitle;
                }
            });
        })();
        </script>
        <?php
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Sepet Hatirlatma', 'Sepet Hatirlatma', 'manage_options', 'webyaz-cart-reminder', array($this, 'render_admin'));
    }

    public function render_admin() {
        $opts = self::get_opts();
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>Sepet Hatirlatma</h1><p>Terk edilen sepetler icin popup ve sekme uyarisi</p></div>
            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">Kaydedildi!</div><?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('webyaz_cart_reminder_group'); ?>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Genel</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field"><label>Aktif</label><select name="webyaz_cart_reminder[active]"><option value="0" <?php selected($opts['active'], '0'); ?>>Kapali</option><option value="1" <?php selected($opts['active'], '1'); ?>>Aktif</option></select></div>
                    </div>
                </div>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Cikis Popup (Exit Intent)</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:12px;">Kullanici sayfadan cikmayi denediginde sepeti doluysa popup gosterir</p>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field"><label>Cikis Popup</label><select name="webyaz_cart_reminder[exit_popup]"><option value="1" <?php selected($opts['exit_popup'], '1'); ?>>Aktif</option><option value="0" <?php selected($opts['exit_popup'], '0'); ?>>Kapali</option></select></div>
                        <div class="webyaz-field"><label>Baslik</label><input type="text" name="webyaz_cart_reminder[exit_title]" value="<?php echo esc_attr($opts['exit_title']); ?>"></div>
                        <div class="webyaz-field"><label>Mesaj</label><textarea name="webyaz_cart_reminder[exit_message]" rows="2"><?php echo esc_textarea($opts['exit_message']); ?></textarea></div>
                        <div class="webyaz-field"><label>Buton Metni</label><input type="text" name="webyaz_cart_reminder[exit_btn]" value="<?php echo esc_attr($opts['exit_btn']); ?>"></div>
                        <div class="webyaz-field"><label>Indirim Kuponu (opsiyonel)</label><input type="text" name="webyaz_cart_reminder[exit_discount]" value="<?php echo esc_attr($opts['exit_discount']); ?>" placeholder="SEPET10"></div>
                    </div>
                </div>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Sekme Hatirlatma</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:12px;">Kullanici baska sekmeye gectiginde tarayici sekmesinde mesaj gosterir</p>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field"><label>Sekme Hatirlatma</label><select name="webyaz_cart_reminder[tab_reminder]"><option value="1" <?php selected($opts['tab_reminder'], '1'); ?>>Aktif</option><option value="0" <?php selected($opts['tab_reminder'], '0'); ?>>Kapali</option></select></div>
                        <div class="webyaz-field"><label>Sekme Metni</label><input type="text" name="webyaz_cart_reminder[tab_text]" value="<?php echo esc_attr($opts['tab_text']); ?>"></div>
                    </div>
                </div>
                <?php submit_button('Kaydet'); ?>
            </form>
        </div>
        <?php
    }
}

new Webyaz_Cart_Reminder();
