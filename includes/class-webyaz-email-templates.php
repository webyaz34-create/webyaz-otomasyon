<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Email_Templates {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('woocommerce_email_styles', array($this, 'custom_styles'), 99);
        add_action('woocommerce_email_header', array($this, 'custom_header'), 10, 2);
        add_action('woocommerce_email_footer', array($this, 'custom_footer'));
        add_action('woocommerce_email_order_details', array($this, 'order_status_badge'), 5, 4);
        add_filter('woocommerce_mail_content', array($this, 'wrap_content'), 99);
    }

    public function register_settings() {
        register_setting('webyaz_email_tpl_group', 'webyaz_email_tpl');
    }

    private static function get_defaults() {
        return array(
            'active'           => '0',
            'logo_url'         => '',
            'header_bg'        => '#446084',
            'header_bg2'       => '#2d4a6f',
            'header_text'      => '#ffffff',
            'accent_color'     => '#d26e4b',
            'body_bg'          => '#f0f2f5',
            'body_text'        => '#333333',
            'footer_bg'        => '#1a1a2e',
            'footer_text_color'=> '#999999',
            'footer_text'      => '',
            'social_btn_bg'    => '#2a2a4e',
            'social_btn_text'  => '#ffffff',
            'social_instagram' => '',
            'social_facebook'  => '',
            'social_twitter'   => '',
            'social_youtube'   => '',
            'font_family'      => "'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
            'border_radius'    => '16',
            'show_product_img' => '1',
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_email_tpl', array()), self::get_defaults());
    }

    // === PREMIUM CSS INJECTION ===
    public function custom_styles($css) {
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return $css;
        $r = intval($opts['border_radius']);
        $hbg = esc_attr($opts['header_bg']);
        $hbg2 = esc_attr($opts['header_bg2']);
        $htxt = esc_attr($opts['header_text']);
        $accent = esc_attr($opts['accent_color']);
        $bbg = esc_attr($opts['body_bg']);
        $btxt = esc_attr($opts['body_text']);
        $fbg = esc_attr($opts['footer_bg']);
        $ftxt = esc_attr($opts['footer_text_color']);
        $font = $opts['font_family'];

        $css .= "
            /* === WEBYAZ PREMIUM EMAIL === */
            body, #wrapper { background-color: {$bbg} !important; }
            #template_container {
                border: none !important;
                border-radius: {$r}px !important;
                overflow: hidden !important;
                box-shadow: 0 8px 40px rgba(0,0,0,0.12) !important;
                background: #fff !important;
            }

            /* HEADER */
            #template_header_image { background: linear-gradient(135deg, {$hbg}, {$hbg2}) !important; padding: 0 !important; }
            #template_header {
                background: linear-gradient(135deg, {$hbg}, {$hbg2}) !important;
                border-bottom: none !important;
                border-radius: 0 !important;
                padding: 32px 48px !important;
            }
            #template_header h1 {
                color: {$htxt} !important;
                font-family: {$font} !important;
                font-weight: 700 !important;
                font-size: 24px !important;
                letter-spacing: -0.3px !important;
                text-shadow: 0 1px 2px rgba(0,0,0,0.1) !important;
            }

            /* BODY */
            #body_content {
                font-family: {$font} !important;
                color: {$btxt} !important;
                padding: 32px 48px !important;
            }
            #body_content p { font-size: 14px !important; line-height: 1.7 !important; color: {$btxt} !important; }
            #body_content table td, .td { font-family: {$font} !important; }
            #body_content a { color: {$accent} !important; font-weight: 600 !important; text-decoration: none !important; }
            #body_content a:hover { text-decoration: underline !important; }

            /* ORDER TABLE */
            .td { padding: 14px 12px !important; }
            #body_content table.td { border: none !important; border-radius: 12px !important; overflow: hidden !important; }
            #body_content table.td th {
                background: {$hbg} !important;
                color: {$htxt} !important;
                font-weight: 600 !important;
                font-size: 13px !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
                padding: 14px 16px !important;
                border: none !important;
            }
            #body_content table.td td {
                border-bottom: 1px solid #f0f0f0 !important;
                border-left: none !important;
                border-right: none !important;
                padding: 14px 16px !important;
                font-size: 14px !important;
                vertical-align: middle !important;
            }
            #body_content table.td tr:last-child td { border-bottom: none !important; }
            #body_content table.td tfoot td { border-top: 2px solid #eee !important; }
            #body_content table.td tfoot tr:last-child td {
                font-weight: 700 !important;
                font-size: 16px !important;
                color: {$accent} !important;
                border-top: 2px solid {$accent} !important;
            }

            /* ADDRESS BLOCKS */
            address { font-style: normal !important; line-height: 1.7 !important; }

            /* BADGE */
            .webyaz-email-badge {
                display: inline-block;
                padding: 8px 24px;
                border-radius: 50px;
                color: #fff;
                font-size: 13px;
                font-weight: 700;
                letter-spacing: 0.3px;
                text-transform: uppercase;
            }

            /* CREDIT FOOTER */
            #template_footer {
                background: {$fbg} !important;
                border-top: none !important;
                border-radius: 0 !important;
                padding: 28px 48px !important;
            }
            #template_footer #credit {
                color: {$ftxt} !important;
                font-family: {$font} !important;
                font-size: 12px !important;
            }
            #template_footer #credit a { color: {$accent} !important; }

            /* PRODUCT IMAGE */
            .webyaz-product-img {
                width: 54px;
                height: 54px;
                border-radius: 8px;
                object-fit: cover;
                margin-right: 14px;
                vertical-align: middle;
                border: 1px solid #eee;
            }

            /* GENERAL LINK BUTTONS */
            .webyaz-btn-link {
                display: inline-block;
                padding: 12px 28px;
                background: {$accent};
                color: #fff !important;
                text-decoration: none !important;
                border-radius: 8px;
                font-weight: 700;
                font-size: 14px;
                margin-top: 12px;
            }

            /* WC ITEM META */
            .wc-item-meta { font-size: 12px !important; color: #888 !important; }
            .wc-item-meta li { margin-bottom: 2px !important; }
        ";
        return $css;
    }

    // === PREMIUM HEADER ===
    public function custom_header($email_heading, $email = null) {
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return;

        if (!empty($opts['logo_url'])) {
            echo '<div style="text-align:center;padding:28px 48px 0;background:linear-gradient(135deg,' . esc_attr($opts['header_bg']) . ',' . esc_attr($opts['header_bg2']) . ');">';
            echo '<img src="' . esc_url($opts['logo_url']) . '" alt="Logo" style="max-height:50px;max-width:180px;">';
            echo '</div>';
        }
    }

    // === ORDER STATUS BADGE ===
    public function order_status_badge($order, $sent_to_admin, $plain_text, $email) {
        $opts = self::get_opts();
        if ($opts['active'] !== '1' || $plain_text) return;

        $status_colors = array(
            'pending'    => array('#ff9800', '⏳'),
            'processing' => array('#2196f3', '🔄'),
            'on-hold'    => array('#9e9e9e', '⏸'),
            'completed'  => array('#4caf50', '✅'),
            'cancelled'  => array('#f44336', '❌'),
            'refunded'   => array('#9c27b0', '↩️'),
            'failed'     => array('#d32f2f', '⛔'),
        );
        $status = $order->get_status();
        $info = isset($status_colors[$status]) ? $status_colors[$status] : array($opts['accent_color'], '📦');
        $label = wc_get_order_status_name($status);

        echo '<div style="text-align:center;margin:8px 0 24px;">';
        echo '<span class="webyaz-email-badge" style="background:' . esc_attr($info[0]) . ';box-shadow:0 4px 15px ' . esc_attr($info[0]) . '40;">';
        echo $info[1] . ' ' . esc_html($label);
        echo '</span>';
        echo '</div>';

        // Quick info bar
        echo '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;border-radius:12px;overflow:hidden;background:#f8f9ff;">';
        echo '<tr>';
        echo '<td style="padding:16px 20px;text-align:center;border-right:1px solid #eee;">';
        echo '<div style="font-size:11px;color:#999;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Sipariş No</div>';
        echo '<div style="font-size:16px;font-weight:700;color:' . esc_attr($opts['header_bg']) . ';">#' . $order->get_order_number() . '</div>';
        echo '</td>';
        echo '<td style="padding:16px 20px;text-align:center;border-right:1px solid #eee;">';
        echo '<div style="font-size:11px;color:#999;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Tarih</div>';
        echo '<div style="font-size:14px;font-weight:600;color:#333;">' . $order->get_date_created()->date_i18n('d.m.Y') . '</div>';
        echo '</td>';
        echo '<td style="padding:16px 20px;text-align:center;">';
        echo '<div style="font-size:11px;color:#999;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Toplam</div>';
        echo '<div style="font-size:16px;font-weight:700;color:' . esc_attr($opts['accent_color']) . ';">' . $order->get_formatted_order_total() . '</div>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
    }

    // === PRODUCT IMAGE INJECTION ===
    public function wrap_content($content) {
        $opts = self::get_opts();
        if ($opts['active'] !== '1' || $opts['show_product_img'] !== '1') return $content;

        // Inject product thumbnails into order items
        if (preg_match_all('/<td[^>]*class="[^"]*td[^"]*"[^>]*>(.*?)<\/td>/si', $content, $matches)) {
            // This is handled by WooCommerce default template - we just enhance styling
        }

        return $content;
    }

    // === PREMIUM FOOTER ===
    public function custom_footer() {
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return;

        $has_social = !empty($opts['social_instagram']) || !empty($opts['social_facebook']) || !empty($opts['social_twitter']) || !empty($opts['social_youtube']);

        if ($has_social || !empty($opts['footer_text'])) {
            $accent = esc_attr($opts['accent_color']);

            echo '<div style="text-align:center;padding:24px 48px;background:' . esc_attr($opts['footer_bg']) . ';font-family:' . esc_attr($opts['font_family']) . ';">';

            // Social media buttons
            if ($has_social) {
                echo '<div style="margin-bottom:16px;">';
                $socials = array(
                    'instagram' => array('social_instagram', 'https://instagram.com/', 'Instagram', '📷'),
                    'facebook'  => array('social_facebook', '', 'Facebook', '📘'),
                    'twitter'   => array('social_twitter', 'https://twitter.com/', 'Twitter', '🐦'),
                    'youtube'   => array('social_youtube', '', 'YouTube', '🎬'),
                );
                foreach ($socials as $key => $s) {
                    $val = $opts[$s[0]] ?? '';
                    if (empty($val)) continue;
                    $url = $s[1] ? $s[1] . ltrim($val, '@/') : $val;
                    $sbg = esc_attr($opts['social_btn_bg']);
                    $stxt = esc_attr($opts['social_btn_text']);
                    echo '<a href="' . esc_url($url) . '" style="display:inline-block;margin:0 6px;padding:8px 16px;background:' . $sbg . ';border-radius:8px;color:' . $stxt . ' !important;text-decoration:none;font-size:13px;font-weight:600;">';
                    echo $s[3] . ' ' . $s[2];
                    echo '</a>';
                }
                echo '</div>';
            }

            // Divider
            echo '<div style="width:60px;height:2px;background:' . $accent . ';margin:0 auto 14px;border-radius:2px;opacity:0.5;"></div>';

            // Footer text
            if (!empty($opts['footer_text'])) {
                echo '<p style="margin:0 0 8px;font-size:12px;color:' . esc_attr($opts['footer_text_color']) . ';line-height:1.6;">' . wp_kses_post($opts['footer_text']) . '</p>';
            }

            // Powered by
            echo '<p style="margin:0;font-size:11px;color:rgba(255,255,255,0.3);">Powered by Webyaz</p>';
            echo '</div>';
        }
    }

    // === ADMIN MENU ===
    public function add_submenu() {
        $hook = add_submenu_page('webyaz-dashboard', 'E-posta Şablonu', 'E-posta Şablonu', 'manage_options', 'webyaz-email-tpl', array($this, 'render_admin'));
        add_action('admin_print_scripts-' . $hook, function() { wp_enqueue_media(); });
    }

    // === ADMIN PAGE ===
    public function render_admin() {
        $opts = self::get_opts();
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>📧 E-posta Şablon Ayarları</h1><p>WooCommerce sipariş e-postalarını profesyonel tasarımla özelleştirin</p></div>
            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">✅ Ayarlar kaydedildi!</div><?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('webyaz_email_tpl_group'); ?>

                <style>
                    .wet-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; align-items:start; }
                    .wet-settings { display:flex; flex-direction:column; gap:16px; }
                    .wet-section { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:22px; }
                    .wet-section-title { margin:0 0 16px; font-size:15px; font-weight:700; color:#1a1a2e; display:flex; align-items:center; gap:8px; }
                    .wet-fields { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
                    .wet-field { }
                    .wet-field label { display:block; font-size:12px; font-weight:600; color:#555; margin-bottom:5px; }
                    .wet-field input, .wet-field select { width:100%; padding:9px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; box-sizing:border-box; }
                    .wet-field input[type=color] { height:40px; padding:3px; cursor:pointer; }
                    .wet-field input[type=url], .wet-field input[type=text] { font-size:13px; }
                    .wet-field-full { grid-column:1/-1; }
                    .wet-preview-wrap { position:sticky; top:40px; }
                    .wet-preview { border-radius:14px; overflow:hidden; box-shadow:0 8px 40px rgba(0,0,0,0.12); background:#fff; }
                    .wet-preview-label { font-size:13px; font-weight:700; color:#999; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:12px; text-align:center; }
                    @media (max-width:900px) { .wet-grid { grid-template-columns:1fr; } }
                </style>

                <div class="wet-grid">
                    <!-- LEFT: SETTINGS -->
                    <div class="wet-settings">

                        <!-- GENEL -->
                        <div class="wet-section">
                            <h3 class="wet-section-title">⚡ Genel Ayarlar</h3>
                            <div class="wet-fields">
                                <div class="wet-field">
                                    <label>Durum</label>
                                    <select name="webyaz_email_tpl[active]">
                                        <option value="0" <?php selected($opts['active'], '0'); ?>>⏸ Kapalı</option>
                                        <option value="1" <?php selected($opts['active'], '1'); ?>>✅ Aktif</option>
                                    </select>
                                </div>
                                <div class="wet-field">
                                    <label>Ürün Görseli</label>
                                    <select name="webyaz_email_tpl[show_product_img]">
                                        <option value="1" <?php selected($opts['show_product_img'], '1'); ?>>Göster</option>
                                        <option value="0" <?php selected($opts['show_product_img'], '0'); ?>>Gizle</option>
                                    </select>
                                </div>
                                <div class="wet-field wet-field-full">
                                    <label>🖼️ Logo URL</label>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <input type="url" id="wet-logo-url" name="webyaz_email_tpl[logo_url]" value="<?php echo esc_attr($opts['logo_url']); ?>" placeholder="https://siteniz.com/logo.png" style="flex:1;">
                                        <button type="button" id="wet-logo-select" class="button" style="white-space:nowrap;height:38px;display:flex;align-items:center;gap:5px;border-radius:8px;font-weight:600;">📂 Medya Seç</button>
                                    </div>
                                    <?php if (!empty($opts['logo_url'])): ?>
                                        <div id="wet-logo-preview" style="margin-top:8px;"><img src="<?php echo esc_url($opts['logo_url']); ?>" style="max-height:40px;max-width:200px;border-radius:6px;border:1px solid #e5e7eb;padding:4px;background:#fff;"></div>
                                    <?php else: ?>
                                        <div id="wet-logo-preview" style="margin-top:8px;"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="wet-field wet-field-full">
                                    <label>Köşe Yuvarlaklığı (px)</label>
                                    <input type="number" name="webyaz_email_tpl[border_radius]" value="<?php echo esc_attr($opts['border_radius']); ?>" min="0" max="30">
                                </div>
                            </div>
                        </div>

                        <!-- RENKLER -->
                        <div class="wet-section">
                            <h3 class="wet-section-title">🎨 Renk Ayarları</h3>
                            <div class="wet-fields">
                                <div class="wet-field">
                                    <label>Header Gradient 1</label>
                                    <input type="color" name="webyaz_email_tpl[header_bg]" value="<?php echo esc_attr($opts['header_bg']); ?>">
                                </div>
                                <div class="wet-field">
                                    <label>Header Gradient 2</label>
                                    <input type="color" name="webyaz_email_tpl[header_bg2]" value="<?php echo esc_attr($opts['header_bg2']); ?>">
                                </div>
                                <div class="wet-field">
                                    <label>Header Yazı</label>
                                    <input type="color" name="webyaz_email_tpl[header_text]" value="<?php echo esc_attr($opts['header_text']); ?>">
                                </div>
                                <div class="wet-field">
                                    <label>Vurgu Rengi</label>
                                    <input type="color" name="webyaz_email_tpl[accent_color]" value="<?php echo esc_attr($opts['accent_color']); ?>">
                                </div>
                                <div class="wet-field">
                                    <label>Gövde Arka Plan</label>
                                    <input type="color" name="webyaz_email_tpl[body_bg]" value="<?php echo esc_attr($opts['body_bg']); ?>">
                                </div>
                                <div class="wet-field">
                                    <label>Gövde Yazı</label>
                                    <input type="color" name="webyaz_email_tpl[body_text]" value="<?php echo esc_attr($opts['body_text']); ?>">
                                </div>
                                <div class="wet-field">
                                    <label>Footer Arka Plan</label>
                                    <input type="color" name="webyaz_email_tpl[footer_bg]" value="<?php echo esc_attr($opts['footer_bg']); ?>">
                                </div>
                                <div class="wet-field">
                                    <label>Footer Yazı</label>
                                    <input type="color" name="webyaz_email_tpl[footer_text_color]" value="<?php echo esc_attr($opts['footer_text_color']); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- FOOTER -->
                        <div class="wet-section">
                            <h3 class="wet-section-title">📱 Footer & Sosyal Medya</h3>
                            <div class="wet-fields">
                                <div class="wet-field wet-field-full">
                                    <label>Footer Metni</label>
                                    <input type="text" name="webyaz_email_tpl[footer_text]" value="<?php echo esc_attr($opts['footer_text']); ?>" placeholder="© 2026 Firma Adı. Tüm hakları saklıdır.">
                                </div>
                                <div class="wet-field">
                                    <label>📷 Instagram</label>
                                    <input type="text" name="webyaz_email_tpl[social_instagram]" value="<?php echo esc_attr($opts['social_instagram']); ?>" placeholder="@kullaniciadi">
                                </div>
                                <div class="wet-field">
                                    <label>📘 Facebook URL</label>
                                    <input type="url" name="webyaz_email_tpl[social_facebook]" value="<?php echo esc_attr($opts['social_facebook']); ?>" placeholder="https://facebook.com/sayfa">
                                </div>
                                <div class="wet-field">
                                    <label>🐦 Twitter</label>
                                    <input type="text" name="webyaz_email_tpl[social_twitter]" value="<?php echo esc_attr($opts['social_twitter']); ?>" placeholder="@kullaniciadi">
                                </div>
                                <div class="wet-field">
                                    <label>🎬 YouTube URL</label>
                                    <input type="url" name="webyaz_email_tpl[social_youtube]" value="<?php echo esc_attr($opts['social_youtube']); ?>" placeholder="https://youtube.com/@kanal">
                                </div>
                                <div class="wet-field">
                                    <label>🎨 Buton Arka Plan</label>
                                    <input type="color" name="webyaz_email_tpl[social_btn_bg]" value="<?php echo esc_attr($opts['social_btn_bg']); ?>">
                                </div>
                                <div class="wet-field">
                                    <label>🎨 Buton Yazı Rengi</label>
                                    <input type="color" name="webyaz_email_tpl[social_btn_text]" value="<?php echo esc_attr($opts['social_btn_text']); ?>">
                                </div>
                            </div>
                        </div>

                        <?php submit_button('💾 Kaydet', 'primary', 'submit', true, array('style' => 'padding:12px 32px;font-size:14px;border-radius:10px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;box-shadow:0 4px 12px rgba(79,70,229,0.25);')); ?>
                    </div>

                    <!-- RIGHT: PREVIEW -->
                    <div class="wet-preview-wrap">
                        <div class="wet-preview-label">📧 Canlı Önizleme</div>
                        <div class="wet-preview" id="pv-wrapper" style="background:<?php echo esc_attr($opts['body_bg']); ?>;padding:24px;">
                            <div id="pv-container" style="max-width:500px;margin:0 auto;border-radius:<?php echo esc_attr($opts['border_radius']); ?>px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,0.12);background:#fff;">

                                <!-- HEADER -->
                                <div id="pv-header" style="background:linear-gradient(135deg,<?php echo esc_attr($opts['header_bg']); ?>,<?php echo esc_attr($opts['header_bg2']); ?>);padding:28px 32px;text-align:center;">
                                    <div id="pv-logo-wrap" style="<?php echo empty($opts['logo_url']) ? 'display:none;' : ''; ?>margin-bottom:12px;">
                                        <img id="pv-logo-img" src="<?php echo esc_url($opts['logo_url']); ?>" style="max-height:40px;" alt="Logo">
                                    </div>
                                    <div id="pv-header-text" style="color:<?php echo esc_attr($opts['header_text']); ?>;font-size:22px;font-weight:700;font-family:<?php echo esc_attr($opts['font_family']); ?>;letter-spacing:-0.3px;">
                                        Siparişiniz Alındı!
                                    </div>
                                </div>

                                <!-- BODY -->
                                <div id="pv-body" style="padding:28px 32px;font-family:<?php echo esc_attr($opts['font_family']); ?>;color:<?php echo esc_attr($opts['body_text']); ?>;">

                                    <!-- STATUS BADGE -->
                                    <div style="text-align:center;margin-bottom:20px;">
                                        <span style="display:inline-block;padding:8px 24px;border-radius:50px;background:#2196f3;color:#fff;font-size:13px;font-weight:700;letter-spacing:0.3px;box-shadow:0 4px 15px rgba(33,150,243,0.3);">🔄 Hazırlanıyor</span>
                                    </div>

                                    <!-- QUICK INFO BAR -->
                                    <table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:20px;border-radius:12px;overflow:hidden;background:#f8f9ff;">
                                        <tr>
                                            <td style="padding:14px 16px;text-align:center;border-right:1px solid #eee;">
                                                <div style="font-size:10px;color:#999;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Sipariş No</div>
                                                <div id="pv-order-no" style="font-size:15px;font-weight:700;color:<?php echo esc_attr($opts['header_bg']); ?>;">#1234</div>
                                            </td>
                                            <td style="padding:14px 16px;text-align:center;border-right:1px solid #eee;">
                                                <div style="font-size:10px;color:#999;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Tarih</div>
                                                <div style="font-size:13px;font-weight:600;color:#333;">20.03.2026</div>
                                            </td>
                                            <td style="padding:14px 16px;text-align:center;">
                                                <div style="font-size:10px;color:#999;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Toplam</div>
                                                <div class="pv-accent-text" style="font-size:15px;font-weight:700;color:<?php echo esc_attr($opts['accent_color']); ?>;">1.499,90 ₺</div>
                                            </td>
                                        </tr>
                                    </table>

                                    <p id="pv-body-paragraph" style="font-size:14px;line-height:1.7;margin:0 0 20px;">Merhaba <strong>Ali</strong>, siparişinizi aldık ve hazırlıyoruz. Sipariş detaylarınız aşağıdadır:</p>

                                    <!-- PRODUCT TABLE -->
                                    <table width="100%" cellpadding="0" cellspacing="0" style="border-radius:10px;overflow:hidden;margin-bottom:20px;">
                                        <tr>
                                            <th class="pv-th" style="background:<?php echo esc_attr($opts['header_bg']); ?>;color:<?php echo esc_attr($opts['header_text']); ?>;padding:12px 14px;text-align:left;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Ürün</th>
                                            <th class="pv-th" style="background:<?php echo esc_attr($opts['header_bg']); ?>;color:<?php echo esc_attr($opts['header_text']); ?>;padding:12px 14px;text-align:center;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Adet</th>
                                            <th class="pv-th" style="background:<?php echo esc_attr($opts['header_bg']); ?>;color:<?php echo esc_attr($opts['header_text']); ?>;padding:12px 14px;text-align:right;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">Fiyat</th>
                                        </tr>
                                        <tr>
                                            <td style="padding:14px;border-bottom:1px solid #f0f0f0;font-size:14px;">
                                                <div style="display:flex;align-items:center;gap:10px;">
                                                    <div style="width:44px;height:44px;background:#f3f4f6;border-radius:8px;display:inline-block;vertical-align:middle;"></div>
                                                    <div style="display:inline-block;vertical-align:middle;">
                                                        <strong>Premium Ürün</strong>
                                                        <div style="font-size:11px;color:#888;margin-top:2px;">Renk: Siyah, Beden: M</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="padding:14px;border-bottom:1px solid #f0f0f0;text-align:center;font-size:14px;">1</td>
                                            <td class="pv-accent-text" style="padding:14px;border-bottom:1px solid #f0f0f0;text-align:right;font-size:14px;font-weight:600;color:<?php echo esc_attr($opts['accent_color']); ?>;">1.299,90 ₺</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:14px;font-size:14px;">
                                                <div style="display:flex;align-items:center;gap:10px;">
                                                    <div style="width:44px;height:44px;background:#f3f4f6;border-radius:8px;display:inline-block;vertical-align:middle;"></div>
                                                    <strong style="display:inline-block;vertical-align:middle;">Extra Aksesuar</strong>
                                                </div>
                                            </td>
                                            <td style="padding:14px;text-align:center;font-size:14px;">1</td>
                                            <td class="pv-accent-text" style="padding:14px;text-align:right;font-size:14px;font-weight:600;color:<?php echo esc_attr($opts['accent_color']); ?>;">200,00 ₺</td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" class="pv-accent-border-top" style="padding:14px;border-top:2px solid <?php echo esc_attr($opts['accent_color']); ?>;text-align:right;font-weight:700;font-size:15px;">TOPLAM</td>
                                            <td class="pv-accent-text pv-accent-border-top" style="padding:14px;border-top:2px solid <?php echo esc_attr($opts['accent_color']); ?>;text-align:right;font-weight:700;font-size:17px;color:<?php echo esc_attr($opts['accent_color']); ?>;">1.499,90 ₺</td>
                                        </tr>
                                    </table>
                                </div>

                                <!-- FOOTER -->
                                <div id="pv-footer" style="background:<?php echo esc_attr($opts['footer_bg']); ?>;padding:24px 32px;text-align:center;">
                                    <?php
                                    $has_social = !empty($opts['social_instagram']) || !empty($opts['social_facebook']) || !empty($opts['social_twitter']) || !empty($opts['social_youtube']);
                                    if ($has_social):
                                    ?>
                                        <div id="pv-social-wrap" style="margin-bottom:14px;">
                                            <?php if (!empty($opts['social_instagram'])): ?><span class="pv-social-btn" style="display:inline-block;margin:0 4px;padding:6px 12px;background:<?php echo esc_attr($opts['social_btn_bg']); ?>;border-radius:6px;color:<?php echo esc_attr($opts['social_btn_text']); ?>;font-size:12px;">📷 Instagram</span><?php endif; ?>
                                            <?php if (!empty($opts['social_facebook'])): ?><span class="pv-social-btn" style="display:inline-block;margin:0 4px;padding:6px 12px;background:<?php echo esc_attr($opts['social_btn_bg']); ?>;border-radius:6px;color:<?php echo esc_attr($opts['social_btn_text']); ?>;font-size:12px;">📘 Facebook</span><?php endif; ?>
                                            <?php if (!empty($opts['social_twitter'])): ?><span class="pv-social-btn" style="display:inline-block;margin:0 4px;padding:6px 12px;background:<?php echo esc_attr($opts['social_btn_bg']); ?>;border-radius:6px;color:<?php echo esc_attr($opts['social_btn_text']); ?>;font-size:12px;">🐦 Twitter</span><?php endif; ?>
                                            <?php if (!empty($opts['social_youtube'])): ?><span class="pv-social-btn" style="display:inline-block;margin:0 4px;padding:6px 12px;background:<?php echo esc_attr($opts['social_btn_bg']); ?>;border-radius:6px;color:<?php echo esc_attr($opts['social_btn_text']); ?>;font-size:12px;">🎬 YouTube</span><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div id="pv-footer-divider" style="width:50px;height:2px;background:<?php echo esc_attr($opts['accent_color']); ?>;margin:0 auto 12px;border-radius:2px;opacity:0.5;"></div>
                                    <p id="pv-footer-text" style="margin:0 0 6px;font-size:12px;color:<?php echo esc_attr($opts['footer_text_color']); ?>;">
                                        <?php echo !empty($opts['footer_text']) ? esc_html($opts['footer_text']) : '© 2026 Firma Adı. Tüm hakları saklıdır.'; ?>
                                    </p>
                                    <p style="margin:0;font-size:10px;color:rgba(255,255,255,0.25);">Powered by Webyaz</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <script>
            jQuery(function($){
                // === MEDIA LIBRARY ===
                var frame;
                $('#wet-logo-select').on('click', function(e){
                    e.preventDefault();
                    if (frame) { frame.open(); return; }
                    frame = wp.media({
                        title: 'Logo Görseli Seç',
                        button: { text: 'Logo Olarak Kullan' },
                        library: { type: 'image' },
                        multiple: false
                    });
                    frame.on('select', function(){
                        var att = frame.state().get('selection').first().toJSON();
                        var url = att.url;
                        $('#wet-logo-url').val(url).trigger('change');
                        $('#wet-logo-preview').html('<img src="'+url+'" style="max-height:40px;max-width:200px;border-radius:6px;border:1px solid #e5e7eb;padding:4px;background:#fff;">');
                    });
                    frame.open();
                });

                // === LIVE PREVIEW ===
                function updatePreview() {
                    var hbg  = $('input[name="webyaz_email_tpl[header_bg]"]').val();
                    var hbg2 = $('input[name="webyaz_email_tpl[header_bg2]"]').val();
                    var htxt = $('input[name="webyaz_email_tpl[header_text]"]').val();
                    var accent = $('input[name="webyaz_email_tpl[accent_color]"]').val();
                    var bbg  = $('input[name="webyaz_email_tpl[body_bg]"]').val();
                    var btxt = $('input[name="webyaz_email_tpl[body_text]"]').val();
                    var fbg  = $('input[name="webyaz_email_tpl[footer_bg]"]').val();
                    var ftxt = $('input[name="webyaz_email_tpl[footer_text_color]"]').val();
                    var radius = $('input[name="webyaz_email_tpl[border_radius]"]').val();
                    var logo = $('#wet-logo-url').val();
                    var footerText = $('input[name="webyaz_email_tpl[footer_text]"]').val();

                    // Body background
                    $('#pv-wrapper').css('background', bbg);

                    // Container border radius
                    $('#pv-container').css('border-radius', radius + 'px');

                    // Header gradient
                    $('#pv-header').css('background', 'linear-gradient(135deg,' + hbg + ',' + hbg2 + ')');

                    // Header text
                    $('#pv-header-text').css('color', htxt);

                    // Logo
                    if (logo) {
                        $('#pv-logo-img').attr('src', logo);
                        $('#pv-logo-wrap').show();
                    } else {
                        $('#pv-logo-wrap').hide();
                    }

                    // Body text
                    $('#pv-body').css('color', btxt);
                    $('#pv-body-paragraph').css('color', btxt);

                    // Order number color (header_bg)
                    $('#pv-order-no').css('color', hbg);

                    // Accent colored texts (prices, totals)
                    $('.pv-accent-text').css('color', accent);

                    // Accent border top
                    $('.pv-accent-border-top').css('border-top-color', accent);

                    // Table headers
                    $('.pv-th').css({'background': hbg, 'color': htxt});

                    // Footer
                    $('#pv-footer').css('background', fbg);
                    $('#pv-footer-text').css('color', ftxt);
                    $('#pv-footer-divider').css('background', accent);

                    // Social buttons
                    var sbg = $('input[name="webyaz_email_tpl[social_btn_bg]"]').val();
                    var stxt = $('input[name="webyaz_email_tpl[social_btn_text]"]').val();
                    $('.pv-social-btn').css({'background': sbg, 'color': stxt});

                    // Footer text content
                    if (footerText) {
                        $('#pv-footer-text').text(footerText);
                    } else {
                        $('#pv-footer-text').text('© 2026 Firma Adı. Tüm hakları saklıdır.');
                    }
                }

                // Listen to all color inputs
                $('input[type="color"]').on('input change', updatePreview);

                // Listen to border radius
                $('input[name="webyaz_email_tpl[border_radius]"]').on('input change', updatePreview);

                // Listen to logo URL
                $('#wet-logo-url').on('input change', updatePreview);

                // Listen to footer text
                $('input[name="webyaz_email_tpl[footer_text]"]').on('input change', updatePreview);
            });
            </script>
        </div>
        <?php
    }
    // =========================================
    // SHARED BRANDED EMAIL SENDER
    // =========================================
    public static function send_branded_email($to, $subject, $heading, $body_html, $icon = '📧', $badge_color = '#2196f3') {
        $opts = self::get_opts();
        $font  = esc_attr($opts['font_family']);
        $hbg   = esc_attr($opts['header_bg']);
        $hbg2  = esc_attr($opts['header_bg2']);
        $htxt  = esc_attr($opts['header_text']);
        $accent= esc_attr($opts['accent_color']);
        $bbg   = esc_attr($opts['body_bg']);
        $btxt  = esc_attr($opts['body_text']);
        $fbg   = esc_attr($opts['footer_bg']);
        $ftxt  = esc_attr($opts['footer_text_color']);
        $r     = intval($opts['border_radius']);
        $site  = get_bloginfo('name');

        $logo_html = '';
        if (!empty($opts['logo_url'])) {
            $logo_html = '<img src="' . esc_url($opts['logo_url']) . '" alt="' . esc_attr($site) . '" style="max-height:45px;max-width:160px;margin-bottom:10px;display:block;margin-left:auto;margin-right:auto;">';
        }

        // Social links
        $social_html = '';
        $socials = array(
            'instagram' => array('social_instagram', 'https://instagram.com/', '📷 Instagram'),
            'facebook'  => array('social_facebook', '', '📘 Facebook'),
            'twitter'   => array('social_twitter', 'https://twitter.com/', '🐦 Twitter'),
            'youtube'   => array('social_youtube', '', '🎬 YouTube'),
        );
        foreach ($socials as $s) {
            $val = $opts[$s[0]] ?? '';
            if (empty($val)) continue;
            $url = $s[1] ? $s[1] . ltrim($val, '@/') : $val;
            $sbg = esc_attr($opts['social_btn_bg']);
            $stxt = esc_attr($opts['social_btn_text']);
            $social_html .= '<a href="' . esc_url($url) . '" style="display:inline-block;margin:0 4px;padding:6px 14px;background:' . $sbg . ';border-radius:6px;color:' . $stxt . ';text-decoration:none;font-size:12px;font-weight:600;">' . $s[2] . '</a>';
        }

        $footer_text = !empty($opts['footer_text']) ? '<p style="margin:0 0 8px;font-size:12px;color:' . $ftxt . ';line-height:1.6;">' . wp_kses_post($opts['footer_text']) . '</p>' : '';

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
        <body style="margin:0;padding:0;background:' . $bbg . ';font-family:' . $font . ';">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:' . $bbg . ';padding:32px 16px;">
        <tr><td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;border-radius:' . $r . 'px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,0.1);background:#fff;">

        <!-- HEADER -->
        <tr><td style="background:linear-gradient(135deg,' . $hbg . ',' . $hbg2 . ');padding:32px 40px;text-align:center;">
            ' . $logo_html . '
            <h1 style="margin:0;color:' . $htxt . ';font-size:22px;font-weight:700;font-family:' . $font . ';letter-spacing:-0.3px;">' . esc_html($heading) . '</h1>
        </td></tr>

        <!-- ICON BADGE -->
        <tr><td style="text-align:center;padding:24px 40px 0;">
            <span style="display:inline-block;padding:10px 28px;border-radius:50px;background:' . esc_attr($badge_color) . ';color:#fff;font-size:14px;font-weight:700;box-shadow:0 4px 15px ' . esc_attr($badge_color) . '40;">' . $icon . '</span>
        </td></tr>

        <!-- BODY -->
        <tr><td style="padding:24px 40px 32px;font-family:' . $font . ';color:' . $btxt . ';font-size:14px;line-height:1.7;">
            ' . $body_html . '
        </td></tr>

        <!-- FOOTER -->
        <tr><td style="background:' . $fbg . ';padding:24px 40px;text-align:center;">
            ' . ($social_html ? '<div style="margin-bottom:14px;">' . $social_html . '</div>' : '') . '
            <div style="width:50px;height:2px;background:' . $accent . ';margin:0 auto 12px;border-radius:2px;opacity:0.5;"></div>
            ' . $footer_text . '
            <p style="margin:0;font-size:11px;color:rgba(255,255,255,0.3);">Powered by Webyaz</p>
        </td></tr>

        </table>
        </td></tr>
        </table>
        </body></html>';

        $headers = array('Content-Type: text/html; charset=UTF-8');
        return wp_mail($to, $subject, $html, $headers);
    }
}

new Webyaz_Email_Templates();
