<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Live_Support {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_footer', array($this, 'render_button'));
    }

    public function register_settings() {
        register_setting('webyaz_live_support_group', 'webyaz_live_support');
    }

    private static function get_defaults() {
        return array(
            'active' => '0',
            'whatsapp' => '',
            'telegram' => '',
            'instagram' => '',
            'messenger' => '',
            'email' => '',
            'position' => 'right',
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_live_support', array()), self::get_defaults());
    }

    public function render_button() {
        if (is_admin()) return;
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return;

        $channels = array();
        if (!empty($opts['whatsapp'])) $channels[] = array('name' => 'WhatsApp', 'url' => 'https://wa.me/' . preg_replace('/[^0-9]/', '', $opts['whatsapp']), 'color' => '#25d366', 'icon' => 'M12 2C6.48 2 2 6.48 2 12c0 1.77.46 3.43 1.27 4.88L2 22l5.23-1.25C8.62 21.54 10.26 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2z');
        if (!empty($opts['telegram'])) $channels[] = array('name' => 'Telegram', 'url' => 'https://t.me/' . ltrim($opts['telegram'], '@'), 'color' => '#0088cc', 'icon' => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8l-1.57 7.4c-.12.54-.43.67-.87.42l-2.4-1.77-1.16 1.12c-.13.13-.24.24-.49.24l.17-2.44 4.44-4.01c.19-.17-.04-.27-.3-.1l-5.5 3.46-2.37-.74c-.51-.16-.52-.51.11-.76l9.26-3.57c.43-.16.8.1.66.75z');
        if (!empty($opts['instagram'])) $channels[] = array('name' => 'Instagram', 'url' => 'https://instagram.com/' . ltrim($opts['instagram'], '@'), 'color' => '#e4405f', 'icon' => 'M12 2.16c2.67 0 2.99.01 4.04.06 2.71.12 3.96 1.4 4.08 4.08.05 1.05.06 1.37.06 4.04s-.01 2.99-.06 4.04c-.12 2.68-1.37 3.96-4.08 4.08-1.05.05-1.37.06-4.04.06s-2.99-.01-4.04-.06c-2.71-.12-3.96-1.4-4.08-4.08C3.83 13.33 3.82 13.01 3.82 12s.01-2.99.06-4.04c.12-2.68 1.37-3.96 4.08-4.08C8.01 3.83 8.33 3.82 12 3.82');
        if (!empty($opts['messenger'])) $channels[] = array('name' => 'Messenger', 'url' => 'https://m.me/' . $opts['messenger'], 'color' => '#0084ff', 'icon' => 'M12 2C6.36 2 2 6.13 2 11.7c0 2.91 1.2 5.42 3.15 7.15V22l3.04-1.67c.81.22 1.67.34 2.56.34 5.64 0 10-4.13 10-9.7S17.64 2 12 2z');
        if (!empty($opts['email'])) $channels[] = array('name' => 'E-posta', 'url' => 'mailto:' . $opts['email'], 'color' => '#ea4335', 'icon' => 'M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z');

        if (empty($channels)) return;
        $pos = $opts['position'] === 'left' ? 'left:20px;' : 'right:20px;';
        ?>
        <div class="webyaz-support-fab" style="<?php echo $pos; ?>" id="webyazSupportFab">
            <div class="webyaz-support-channels" id="webyazSupportChannels" style="display:none;">
                <?php foreach ($channels as $ch): ?>
                <a href="<?php echo esc_url($ch['url']); ?>" target="_blank" rel="noopener" class="webyaz-support-channel" style="background:<?php echo $ch['color']; ?>;" title="<?php echo esc_attr($ch['name']); ?>">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="#fff"><path d="<?php echo $ch['icon']; ?>"/></svg>
                    <span><?php echo esc_html($ch['name']); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <button class="webyaz-support-btn" onclick="var c=document.getElementById('webyazSupportChannels');c.style.display=c.style.display==='none'?'flex':'none';">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="#fff"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
            </button>
        </div>
        <?php
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Canli Destek', 'Canli Destek', 'manage_options', 'webyaz-live-support', array($this, 'render_admin'));
    }

    public function render_admin() {
        $opts = self::get_opts();
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>Canli Destek Ayarlari</h1><p>Coklu kanal destek butonu</p></div>
            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">Kaydedildi!</div><?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('webyaz_live_support_group'); ?>
                <div class="webyaz-settings-section">
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field"><label>Aktif</label><select name="webyaz_live_support[active]"><option value="0" <?php selected($opts['active'], '0'); ?>>Kapali</option><option value="1" <?php selected($opts['active'], '1'); ?>>Aktif</option></select></div>
                        <div class="webyaz-field"><label>Konum</label><select name="webyaz_live_support[position]"><option value="right" <?php selected($opts['position'], 'right'); ?>>Sag</option><option value="left" <?php selected($opts['position'], 'left'); ?>>Sol</option></select></div>
                        <div class="webyaz-field"><label>WhatsApp Numarasi</label><input type="text" name="webyaz_live_support[whatsapp]" value="<?php echo esc_attr($opts['whatsapp']); ?>" placeholder="905xxxxxxxxx"></div>
                        <div class="webyaz-field"><label>Telegram Kullanici Adi</label><input type="text" name="webyaz_live_support[telegram]" value="<?php echo esc_attr($opts['telegram']); ?>" placeholder="@kullaniciadi"></div>
                        <div class="webyaz-field"><label>Instagram Kullanici Adi</label><input type="text" name="webyaz_live_support[instagram]" value="<?php echo esc_attr($opts['instagram']); ?>" placeholder="@kullaniciadi"></div>
                        <div class="webyaz-field"><label>Messenger ID</label><input type="text" name="webyaz_live_support[messenger]" value="<?php echo esc_attr($opts['messenger']); ?>" placeholder="sayfa-adi"></div>
                        <div class="webyaz-field"><label>E-posta</label><input type="email" name="webyaz_live_support[email]" value="<?php echo esc_attr($opts['email']); ?>" placeholder="destek@site.com"></div>
                    </div>
                </div>
                <?php submit_button('Kaydet'); ?>
            </form>
        </div>
        <?php
    }
}

new Webyaz_Live_Support();
