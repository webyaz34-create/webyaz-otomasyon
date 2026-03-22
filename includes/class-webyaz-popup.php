<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Popup {

    public function __construct() {
        add_action('wp_footer', array($this, 'render_popup'));
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_submenu() {
        add_submenu_page(
            'webyaz-dashboard',
            'Popup Ayarlari',
            'Popup',
            'manage_options',
            'webyaz-popup',
            array($this, 'render_admin')
        );
    }

    public function register_settings() {
        register_setting('webyaz_popup_group', 'webyaz_popup');
    }

    private static function get_defaults() {
        return array(
            'enabled' => '0',
            'title' => 'Hosgeldiniz!',
            'text' => 'Ilk siparislerinizde %10 indirim kazanin!',
            'coupon' => '',
            'button_text' => 'Alisverise Basla',
            'button_url' => '',
            'delay' => '3',
            'show_once' => '1',
        );
    }

    public static function get($key) {
        $opts = wp_parse_args(get_option('webyaz_popup', array()), self::get_defaults());
        return isset($opts[$key]) ? $opts[$key] : '';
    }

    public function render_popup() {
        if (is_admin()) return;
        $opts = wp_parse_args(get_option('webyaz_popup', array()), self::get_defaults());
        if ($opts['enabled'] !== '1') return;

        $btn_url = $opts['button_url'];
        if (empty($btn_url) && function_exists('wc_get_page_permalink')) {
            $btn_url = wc_get_page_permalink('shop');
        }
        if (empty($btn_url)) $btn_url = home_url('/');
        ?>
        <div class="webyaz-popup-overlay" id="webyazPopupOverlay" style="display:none;">
            <div class="webyaz-popup-box" id="webyazPopupBox">
                <button class="webyaz-popup-close" id="webyazPopupClose">&times;</button>
                <div class="webyaz-popup-content">
                    <h3><?php echo esc_html($opts['title']); ?></h3>
                    <p><?php echo esc_html($opts['text']); ?></p>
                    <?php if (!empty($opts['coupon'])): ?>
                        <div class="webyaz-popup-coupon">
                            <span id="webyazCouponCode"><?php echo esc_html(strtoupper($opts['coupon'])); ?></span>
                            <button class="webyaz-popup-copy" onclick="navigator.clipboard.writeText('<?php echo esc_js($opts['coupon']); ?>');this.textContent='Kopyalandi!';">Kopyala</button>
                        </div>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($btn_url); ?>" class="webyaz-popup-btn"><?php echo esc_html($opts['button_text']); ?></a>
                </div>
            </div>
        </div>
        <script>
        (function(){
            var delay = <?php echo intval($opts['delay']); ?> * 1000;
            var showOnce = <?php echo $opts['show_once'] === '1' ? 'true' : 'false'; ?>;
            if (showOnce && document.cookie.indexOf('webyaz_popup_shown=1') !== -1) return;
            setTimeout(function(){
                document.getElementById('webyazPopupOverlay').style.display = 'flex';
                if (showOnce) document.cookie = 'webyaz_popup_shown=1;path=/;max-age=86400';
            }, delay);
            document.getElementById('webyazPopupClose').addEventListener('click', function(){
                document.getElementById('webyazPopupOverlay').style.display = 'none';
            });
            document.getElementById('webyazPopupOverlay').addEventListener('click', function(e){
                if (e.target === this) this.style.display = 'none';
            });
        })();
        </script>
        <?php
    }

    public function render_admin() {
        $opts = wp_parse_args(get_option('webyaz_popup', array()), self::get_defaults());
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Popup / Indirim Ayarlari</h1>
                <p>Ziyaretcilere gosterilecek karsilama popup ayarlari</p>
            </div>
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="webyaz-notice success">Ayarlar kaydedildi!</div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('webyaz_popup_group'); ?>
                <div class="webyaz-settings-section">
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Popup Aktif</label>
                            <select name="webyaz_popup[enabled]">
                                <option value="0" <?php selected($opts['enabled'], '0'); ?>>Kapali</option>
                                <option value="1" <?php selected($opts['enabled'], '1'); ?>>Acik</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Gosterim</label>
                            <select name="webyaz_popup[show_once]">
                                <option value="1" <?php selected($opts['show_once'], '1'); ?>>Gunde 1 kez</option>
                                <option value="0" <?php selected($opts['show_once'], '0'); ?>>Her ziyarette</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Baslik</label>
                            <input type="text" name="webyaz_popup[title]" value="<?php echo esc_attr($opts['title']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Aciklama</label>
                            <input type="text" name="webyaz_popup[text]" value="<?php echo esc_attr($opts['text']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Kupon Kodu (opsiyonel)</label>
                            <input type="text" name="webyaz_popup[coupon]" value="<?php echo esc_attr($opts['coupon']); ?>" placeholder="HOSGELDIN10">
                        </div>
                        <div class="webyaz-field">
                            <label>Gecikme (saniye)</label>
                            <input type="number" name="webyaz_popup[delay]" value="<?php echo esc_attr($opts['delay']); ?>" min="0" max="60">
                        </div>
                        <div class="webyaz-field">
                            <label>Buton Yazisi</label>
                            <input type="text" name="webyaz_popup[button_text]" value="<?php echo esc_attr($opts['button_text']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Buton Linki (bos = magaza)</label>
                            <input type="url" name="webyaz_popup[button_url]" value="<?php echo esc_attr($opts['button_url']); ?>">
                        </div>
                    </div>
                </div>
                <div style="margin-top:20px;">
                    <?php submit_button('Kaydet', 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>
        <?php
    }
}

new Webyaz_Popup();
