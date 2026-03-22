<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Cookie {

    public function __construct() {
        add_action('wp_footer', array($this, 'render_banner'));
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Cerez Bildirimi', 'Cerez Bildirimi', 'manage_options', 'webyaz-cookie', array($this, 'render_admin'));
    }

    public function register_settings() {
        register_setting('webyaz_cookie_group', 'webyaz_cookie');
    }

    private static function get_defaults() {
        return array(
            'enabled' => '1',
            'text' => 'Bu web sitesi deneyiminizi iyilestirmek icin cerezleri kullanmaktadir. Sitemizi kullanmaya devam ederek cerez politikamizi kabul etmis olursunuz.',
            'button_text' => 'Kabul Et',
            'link_text' => 'Detayli Bilgi',
            'link_url' => '',
            'position' => 'bottom',
            'bg_color' => '#1a1a2e',
            'text_color' => '#cccccc',
            'btn_color' => '',
            'expire_days' => '365',
        );
    }

    public function render_banner() {
        if (is_admin()) return;
        $opts = wp_parse_args(get_option('webyaz_cookie', array()), self::get_defaults());
        if ($opts['enabled'] !== '1') return;

        $btn_color = !empty($opts['btn_color']) ? $opts['btn_color'] : '';
        $link_url = !empty($opts['link_url']) ? $opts['link_url'] : '';
        if (empty($link_url)) {
            $kvkk = get_page_by_path('kvkk-aydinlatma-metni');
            if ($kvkk) $link_url = get_permalink($kvkk->ID);
        }
        $pos = $opts['position'] === 'top' ? 'top:0;' : 'bottom:0;';
        $expire = intval($opts['expire_days']);
        ?>
        <div class="webyaz-cookie-banner" id="webyazCookieBanner" style="display:none;position:fixed;left:0;right:0;<?php echo $pos; ?>z-index:999998;background:<?php echo esc_attr($opts['bg_color']); ?>;color:<?php echo esc_attr($opts['text_color']); ?>;padding:16px 20px;font-family:'Roboto',sans-serif;font-size:14px;line-height:1.6;box-shadow:0 -2px 20px rgba(0,0,0,0.15);">
            <div style="max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;">
                <div style="flex:1;min-width:250px;">
                    <span><?php echo esc_html($opts['text']); ?></span>
                    <?php if ($link_url): ?>
                        <a href="<?php echo esc_url($link_url); ?>" style="color:<?php echo esc_attr($opts['text_color']); ?>;text-decoration:underline;margin-left:6px;"><?php echo esc_html($opts['link_text']); ?></a>
                    <?php endif; ?>
                </div>
                <div style="display:flex;gap:10px;align-items:center;">
                    <button id="webyazCookieAccept" style="background:<?php echo esc_attr($btn_color ? $btn_color : 'var(--webyaz-secondary, #d26e4b)'); ?>;color:#fff;border:none;border-radius:8px;padding:12px 28px;font-size:14px;font-weight:700;cursor:pointer;font-family:'Roboto',sans-serif;white-space:nowrap;transition:opacity 0.2s;"><?php echo esc_html($opts['button_text']); ?></button>
                </div>
            </div>
        </div>
        <script>
        (function(){
            if (document.cookie.indexOf('webyaz_cookie_ok=1') !== -1) return;
            var b = document.getElementById('webyazCookieBanner');
            if (b) b.style.display = 'block';
            document.getElementById('webyazCookieAccept').addEventListener('click', function(){
                document.cookie = 'webyaz_cookie_ok=1;path=/;max-age=<?php echo ($expire * 86400); ?>';
                b.style.display = 'none';
            });
        })();
        </script>
        <?php
    }

    public function render_admin() {
        $opts = wp_parse_args(get_option('webyaz_cookie', array()), self::get_defaults());
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Cerez Bildirimi</h1>
                <p>KVKK / GDPR uyumlu cerez kabul banner'i</p>
            </div>
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="webyaz-notice success">Ayarlar kaydedildi!</div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('webyaz_cookie_group'); ?>
                <div class="webyaz-settings-section">
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Aktif</label>
                            <select name="webyaz_cookie[enabled]">
                                <option value="1" <?php selected($opts['enabled'], '1'); ?>>Acik</option>
                                <option value="0" <?php selected($opts['enabled'], '0'); ?>>Kapali</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Konum</label>
                            <select name="webyaz_cookie[position]">
                                <option value="bottom" <?php selected($opts['position'], 'bottom'); ?>>Alt</option>
                                <option value="top" <?php selected($opts['position'], 'top'); ?>>Ust</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Gecerlilik (gun)</label>
                            <input type="number" name="webyaz_cookie[expire_days]" value="<?php echo esc_attr($opts['expire_days']); ?>" min="1" max="365">
                        </div>
                        <div class="webyaz-field" style="grid-column:1/-1;">
                            <label>Bildirim Metni</label>
                            <textarea name="webyaz_cookie[text]" rows="2"><?php echo esc_textarea($opts['text']); ?></textarea>
                        </div>
                        <div class="webyaz-field">
                            <label>Buton Yazisi</label>
                            <input type="text" name="webyaz_cookie[button_text]" value="<?php echo esc_attr($opts['button_text']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Detay Link Yazisi</label>
                            <input type="text" name="webyaz_cookie[link_text]" value="<?php echo esc_attr($opts['link_text']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Detay Link URL (bos = KVKK sayfasi)</label>
                            <input type="url" name="webyaz_cookie[link_url]" value="<?php echo esc_attr($opts['link_url']); ?>">
                        </div>
                    </div>
                    <h2 class="webyaz-section-title" style="margin-top:20px;">Renkler</h2>
                    <div class="webyaz-settings-grid" style="grid-template-columns:repeat(3,1fr);">
                        <div class="webyaz-field">
                            <label>Arka Plan</label>
                            <input type="color" name="webyaz_cookie[bg_color]" value="<?php echo esc_attr($opts['bg_color']); ?>" style="width:50px;height:36px;border:1px solid #ddd;border-radius:6px;cursor:pointer;">
                        </div>
                        <div class="webyaz-field">
                            <label>Yazi Rengi</label>
                            <input type="color" name="webyaz_cookie[text_color]" value="<?php echo esc_attr($opts['text_color']); ?>" style="width:50px;height:36px;border:1px solid #ddd;border-radius:6px;cursor:pointer;">
                        </div>
                        <div class="webyaz-field">
                            <label>Buton Rengi (bos = tema)</label>
                            <input type="color" name="webyaz_cookie[btn_color]" value="<?php echo esc_attr($opts['btn_color'] ? $opts['btn_color'] : '#d26e4b'); ?>" style="width:50px;height:36px;border:1px solid #ddd;border-radius:6px;cursor:pointer;">
                        </div>
                    </div>
                </div>
                <div style="margin-top:20px;"><?php submit_button('Kaydet', 'primary', 'submit', false); ?></div>
            </form>
        </div>
        <?php
    }
}

new Webyaz_Cookie();
