<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Ticker {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_body_open', array($this, 'render_frontend'), 1);
        add_shortcode('webyaz_ticker', array($this, 'shortcode'));
    }

    public function register_settings() {
        register_setting('webyaz_ticker_group', 'webyaz_ticker');
    }

    private static function get_defaults() {
        return array(
            'active' => '0',
            'items' => "UCRETSIZ KARGO FIRSATI\nOZEL INDIRIMLER\nEN IYI URUNLER, EN IYI FIYATLAR\nHIZLI TESLIMAT\nGUVENLI ALISVERIS",
            'bg_color' => '#f5c518',
            'text_color' => '#1a1a1a',
            'font_size' => '14',
            'height' => '40',
            'speed' => '30',
            'direction' => 'left',
            'separator' => '★',
            'separator_color' => '#e53935',
            'font_weight' => '700',
            'position' => 'top',
            'letter_spacing' => '1',
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_ticker', array()), self::get_defaults());
    }

    private function render_ticker($opts = null) {
        if (!$opts) $opts = self::get_opts();
        $items = array_filter(array_map('trim', explode("\n", $opts['items'])));
        if (empty($items)) return '';

        $bg = esc_attr($opts['bg_color']);
        $tc = esc_attr($opts['text_color']);
        $fs = intval($opts['font_size']);
        $h = intval($opts['height']);
        $spd = intval($opts['speed']);
        $dir = $opts['direction'] === 'right' ? 'reverse' : 'normal';
        $sep = esc_html($opts['separator']);
        $sc = esc_attr($opts['separator_color']);
        $fw = esc_attr($opts['font_weight']);
        $ls = intval($opts['letter_spacing']);

        $content = '';
        foreach ($items as $i => $item) {
            if ($i > 0) $content .= '<span class="wyz-ticker-sep" style="color:' . $sc . ';margin:0 24px;font-size:' . ($fs + 2) . 'px;">' . $sep . '</span>';
            $content .= '<span class="wyz-ticker-item">' . esc_html(mb_strtoupper($item, 'UTF-8')) . '</span>';
        }
        $double = $content . '<span class="wyz-ticker-sep" style="color:' . $sc . ';margin:0 24px;font-size:' . ($fs + 2) . 'px;">' . $sep . '</span>' . $content;

        $uid = 'wyzTicker' . wp_rand(1000, 9999);

        return '<div class="wyz-ticker-wrap" style="background:' . $bg . ';height:' . $h . 'px;overflow:hidden;position:relative;width:100%;font-family:Roboto,Arial,sans-serif;">
            <div class="wyz-ticker-track" id="' . $uid . '" style="display:flex;align-items:center;height:100%;white-space:nowrap;color:' . $tc . ';font-size:' . $fs . 'px;font-weight:' . $fw . ';letter-spacing:' . $ls . 'px;animation:wyzTickerScroll ' . $spd . 's linear infinite;animation-direction:' . $dir . ';">
                ' . $double . '
            </div>
            <style>
            @keyframes wyzTickerScroll{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}
            .wyz-ticker-wrap:hover .wyz-ticker-track{animation-play-state:paused;}
            </style>
        </div>';
    }

    public function render_frontend() {
        if (is_admin()) return;
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return;
        if ($opts['position'] === 'shortcode') return;
        echo $this->render_ticker($opts);
    }

    public function shortcode() {
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return '';
        return $this->render_ticker($opts);
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Kayan Yazi', 'Kayan Yazi', 'manage_options', 'webyaz-ticker', array($this, 'render_admin'));
    }

    public function render_admin() {
        $opts = self::get_opts();
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Kayan Yazi Seridi</h1>
                <p>Sayfanin ustunde veya altinda kayan duyuru / promosyon seridi</p>
            </div>
            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">Kaydedildi!</div><?php endif; ?>

            <div style="margin-bottom:24px;padding:16px;background:#f8f9fa;border-radius:12px;border:1px solid #e0e0e0;">
                <h3 style="margin:0 0 8px;">Onizleme</h3>
                <?php echo $this->render_ticker($opts); ?>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('webyaz_ticker_group'); ?>
                <div class="webyaz-settings-section">
                    <h2>Genel Ayarlar</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Durum</label>
                            <select name="webyaz_ticker[active]">
                                <option value="0" <?php selected($opts['active'], '0'); ?>>Kapali</option>
                                <option value="1" <?php selected($opts['active'], '1'); ?>>Aktif</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Konum</label>
                            <select name="webyaz_ticker[position]">
                                <option value="top" <?php selected($opts['position'], 'top'); ?>>Sayfa Ustu</option>
                                <option value="shortcode" <?php selected($opts['position'], 'shortcode'); ?>>Shortcode [webyaz_ticker]</option>
                            </select>
                            <small>Shortcode secerseniz istediginiz yere <code>[webyaz_ticker]</code> yazarak ekleyin.</small>
                        </div>
                        <div class="webyaz-field">
                            <label>Yon</label>
                            <select name="webyaz_ticker[direction]">
                                <option value="left" <?php selected($opts['direction'], 'left'); ?>>Sola Kaydir ←</option>
                                <option value="right" <?php selected($opts['direction'], 'right'); ?>>Saga Kaydir →</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Hiz (saniye)</label>
                            <input type="number" name="webyaz_ticker[speed]" value="<?php echo esc_attr($opts['speed']); ?>" min="5" max="120" step="5">
                            <small>Dusuk = hizli, yuksek = yavas</small>
                        </div>
                    </div>
                </div>
                <div class="webyaz-settings-section">
                    <h2>Icerik</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field" style="grid-column:1/-1;">
                            <label>Yazilar (her satir bir oge)</label>
                            <textarea name="webyaz_ticker[items]" rows="6" style="width:100%;font-size:14px;"><?php echo esc_textarea($opts['items']); ?></textarea>
                        </div>
                        <div class="webyaz-field">
                            <label>Ayirici Simge</label>
                            <input type="text" name="webyaz_ticker[separator]" value="<?php echo esc_attr($opts['separator']); ?>" style="width:80px;text-align:center;font-size:18px;">
                            <small>★ ● ◆ ▸ | ♦ ✦</small>
                        </div>
                        <div class="webyaz-field">
                            <label>Ayirici Rengi</label>
                            <input type="color" name="webyaz_ticker[separator_color]" value="<?php echo esc_attr($opts['separator_color']); ?>">
                        </div>
                    </div>
                </div>
                <div class="webyaz-settings-section">
                    <h2>Tasarim</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Arka Plan Rengi</label>
                            <input type="color" name="webyaz_ticker[bg_color]" value="<?php echo esc_attr($opts['bg_color']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Yazi Rengi</label>
                            <input type="color" name="webyaz_ticker[text_color]" value="<?php echo esc_attr($opts['text_color']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Yazi Boyutu (px)</label>
                            <input type="number" name="webyaz_ticker[font_size]" value="<?php echo esc_attr($opts['font_size']); ?>" min="10" max="30">
                        </div>
                        <div class="webyaz-field">
                            <label>Yukseklik (px)</label>
                            <input type="number" name="webyaz_ticker[height]" value="<?php echo esc_attr($opts['height']); ?>" min="20" max="80" step="5">
                        </div>
                        <div class="webyaz-field">
                            <label>Yazi Kalinligi</label>
                            <select name="webyaz_ticker[font_weight]">
                                <option value="400" <?php selected($opts['font_weight'], '400'); ?>>Normal</option>
                                <option value="500" <?php selected($opts['font_weight'], '500'); ?>>Orta</option>
                                <option value="600" <?php selected($opts['font_weight'], '600'); ?>>Yari Kalin</option>
                                <option value="700" <?php selected($opts['font_weight'], '700'); ?>>Kalin</option>
                                <option value="800" <?php selected($opts['font_weight'], '800'); ?>>Cok Kalin</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Harf Araligi (px)</label>
                            <input type="number" name="webyaz_ticker[letter_spacing]" value="<?php echo esc_attr($opts['letter_spacing']); ?>" min="0" max="10">
                        </div>
                    </div>
                </div>
                <?php submit_button('Kaydet'); ?>
            </form>
        </div>
        <?php
    }
}

new Webyaz_Ticker();
