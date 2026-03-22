<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Floating_Contact {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_footer', array($this, 'render_frontend'));
    }

    public function register_settings() {
        register_setting('webyaz_floating_contact_group', 'webyaz_floating_contact');
    }

    private static function get_defaults() {
        return array(
            'active' => '0',
            'phone' => '',
            'whatsapp' => '',
            'email' => '',
            'instagram' => '',
            'facebook' => '',
            'youtube' => '',
            'tiktok' => '',
            'linkedin' => '',
            'x' => '',
            'bottom_offset' => '20',
            'right_offset' => '20',
            'position' => 'right',
            'mobile_bottom_offset' => '70',
            'mobile_right_offset' => '15',
            'mobile_position' => 'right',
            'use_brand_colors' => '1',
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_floating_contact', array()), self::get_defaults());
    }

    private function get_channels() {
        return array(
            'phone' => array(
                'label' => 'Telefon',
                'color' => '#34b7f1',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M6.62 10.79a15.05 15.05 0 006.59 6.59l2.2-2.2a1 1 0 011.01-.24 11.36 11.36 0 003.58.57 1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1 11.36 11.36 0 00.57 3.58 1 1 0 01-.25 1.02l-2.2 2.19z"/></svg>',
                'url_prefix' => 'tel:',
            ),
            'whatsapp' => array(
                'label' => 'WhatsApp',
                'color' => '#25D366',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347zM12.05 21.785h-.01a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.981.998-3.648-.235-.374A9.86 9.86 0 012.15 12.01C2.15 6.558 6.587 2.122 12.05 2.122a9.82 9.82 0 016.965 2.885 9.82 9.82 0 012.885 6.965c-.003 5.462-4.44 9.898-9.9 9.898l-.05-.005zM12.05.072C5.463.072.097 5.45.097 12.01a11.81 11.81 0 001.588 5.945L.057 24l6.184-1.621a11.85 11.85 0 005.66 1.441h.1c6.587 0 11.95-5.378 11.95-11.94A11.87 11.87 0 0012.05.072z"/></svg>',
                'url_prefix' => 'https://wa.me/',
            ),
            'email' => array(
                'label' => 'E-posta',
                'color' => '#EA4335',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
                'url_prefix' => 'mailto:',
            ),
            'instagram' => array(
                'label' => 'Instagram',
                'color' => '#E4405F',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>',
                'url_prefix' => 'https://instagram.com/',
            ),
            'facebook' => array(
                'label' => 'Facebook',
                'color' => '#1877F2',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
                'url_prefix' => 'https://facebook.com/',
            ),
            'youtube' => array(
                'label' => 'YouTube',
                'color' => '#FF0000',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
                'url_prefix' => 'https://youtube.com/',
            ),
            'tiktok' => array(
                'label' => 'TikTok',
                'color' => '#000000',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>',
                'url_prefix' => 'https://tiktok.com/@',
            ),
            'linkedin' => array(
                'label' => 'LinkedIn',
                'color' => '#0A66C2',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
                'url_prefix' => 'https://linkedin.com/in/',
            ),
            'x' => array(
                'label' => 'X (Twitter)',
                'color' => '#000000',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
                'url_prefix' => 'https://x.com/',
            ),
        );
    }

    public function render_frontend() {
        if (is_admin()) return;
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return;

        $channels = $this->get_channels();
        $bottom = intval($opts['bottom_offset']);
        $right = intval($opts['right_offset']);
        $pos = $opts['position'];
        $mb = intval($opts['mobile_bottom_offset']);
        $mr = intval($opts['mobile_right_offset']);
        $mpos = $opts['mobile_position'];
        $use_brand = $opts['use_brand_colors'] === '1';

        $primary = '#333';
        $secondary = '#666';
        if (function_exists('get_theme_mod')) {
            $p = get_theme_mod('color_primary', '');
            $s = get_theme_mod('color_secondary', '');
            if ($p) $primary = $p;
            if ($s) $secondary = $s;
        }

        $active_channels = array();
        foreach ($channels as $key => $ch) {
            if (!empty($opts[$key])) {
                $active_channels[$key] = $ch;
            }
        }
        if (empty($active_channels)) return;

        $side_css = ($pos === 'left') ? 'left:' . $right . 'px;right:auto;align-items:flex-start;' : 'right:' . $right . 'px;left:auto;align-items:flex-end;';
        $m_side_css = ($mpos === 'left') ? 'left:' . $mr . 'px!important;right:auto!important;align-items:flex-start!important;' : 'right:' . $mr . 'px!important;left:auto!important;align-items:flex-end!important;';
        ?>
        <style>
        .wyz-float-wrap{position:fixed;bottom:<?php echo $bottom; ?>px;<?php echo $side_css; ?>z-index:99999;display:flex;flex-direction:column;gap:0;font-family:'Roboto',sans-serif;}
        .wyz-float-toggle{width:56px;height:56px;border-radius:50%;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(0,0,0,0.2);transition:transform .3s,box-shadow .3s;background:<?php echo $use_brand ? esc_attr($primary) : '#333'; ?>;color:#fff;}
        .wyz-float-toggle:hover{transform:scale(1.1);box-shadow:0 6px 24px rgba(0,0,0,0.3);}
        .wyz-float-toggle svg{transition:transform .3s;}
        .wyz-float-wrap.open .wyz-float-toggle svg{transform:rotate(45deg);}
        .wyz-float-items{display:flex;flex-direction:column;align-items:flex-end;gap:10px;margin-bottom:12px;opacity:0;visibility:hidden;transform:translateY(20px);transition:all .3s ease;pointer-events:none;}
        .wyz-float-wrap.open .wyz-float-items{opacity:1;visibility:visible;transform:translateY(0);pointer-events:auto;}
        .wyz-float-item{display:flex;align-items:center;gap:10px;text-decoration:none !important;}
        .wyz-float-item-label{background:#fff;color:#333;padding:6px 14px;border-radius:20px;font-size:13px;font-weight:500;box-shadow:0 2px 8px rgba(0,0,0,0.12);white-space:nowrap;opacity:0;transform:translateX(10px);transition:all .25s ease;}
        .wyz-float-item:hover .wyz-float-item-label{opacity:1;transform:translateX(0);}
        .wyz-float-item-icon{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 3px 12px rgba(0,0,0,0.15);transition:transform .2s;}
        .wyz-float-item:hover .wyz-float-item-icon{transform:scale(1.12);}
        <?php
        foreach ($active_channels as $key => $ch) {
            $bg = $use_brand ? $ch['color'] : $primary;
            echo '.wyz-float-icon-' . $key . '{background:' . esc_attr($bg) . ';}';
        }
        ?>
        @media(max-width:768px){
            .wyz-float-wrap{bottom:<?php echo $mb; ?>px!important;<?php echo $m_side_css; ?>}
            .wyz-float-item-label{display:none;}
            .wyz-float-toggle{width:50px;height:50px;}
            .wyz-float-item-icon{width:42px;height:42px;}
        }
        </style>
        <div class="wyz-float-wrap" id="wyzFloatWrap">
            <div class="wyz-float-items">
                <?php foreach ($active_channels as $key => $ch):
                    $val = $opts[$key];
                    $url = $ch['url_prefix'] . $val;
                    $target = in_array($key, array('phone','email')) ? '' : ' target="_blank" rel="noopener"';
                ?>
                <a href="<?php echo esc_url($url); ?>" class="wyz-float-item"<?php echo $target; ?>>
                    <span class="wyz-float-item-label"><?php echo esc_html($ch['label']); ?></span>
                    <span class="wyz-float-item-icon wyz-float-icon-<?php echo $key; ?>"><?php echo $ch['icon']; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <button class="wyz-float-toggle" onclick="document.getElementById('wyzFloatWrap').classList.toggle('open')" aria-label="Iletisim">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="26" height="26"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/><path d="M7 9h2v2H7zm4 0h2v2h-2zm4 0h2v2h-2z"/></svg>
            </button>
        </div>
        <?php
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Iletisim Butonlari', 'Iletisim Butonlari', 'manage_options', 'webyaz-floating-contact', array($this, 'render_admin'));
    }

    public function render_admin() {
        $opts = self::get_opts();
        $channels = $this->get_channels();
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>Yuzen Iletisim Butonlari</h1><p>Sag alt kosede yuzen sosyal medya & iletisim butonlari</p></div>
            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">Kaydedildi!</div><?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('webyaz_floating_contact_group'); ?>
                <div class="webyaz-settings-section">
                    <h2>Genel Ayarlar</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Durum</label>
                            <select name="webyaz_floating_contact[active]">
                                <option value="0" <?php selected($opts['active'], '0'); ?>>Kapali</option>
                                <option value="1" <?php selected($opts['active'], '1'); ?>>Aktif</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Marka Renklerini Kullan</label>
                            <select name="webyaz_floating_contact[use_brand_colors]">
                                <option value="1" <?php selected($opts['use_brand_colors'], '1'); ?>>Evet (WhatsApp yesil, Facebook mavi vb.)</option>
                                <option value="0" <?php selected($opts['use_brand_colors'], '0'); ?>>Hayir (Tema renklerini kullan)</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Alt Mesafe (px)</label>
                            <input type="number" name="webyaz_floating_contact[bottom_offset]" value="<?php echo esc_attr($opts['bottom_offset']); ?>" min="0" max="300" step="5">
                        </div>
                        <div class="webyaz-field">
                            <label>Yan Mesafe (px)</label>
                            <input type="number" name="webyaz_floating_contact[right_offset]" value="<?php echo esc_attr($opts['right_offset']); ?>" min="0" max="300" step="5">
                        </div>
                        <div class="webyaz-field">
                            <label>Konum (Masaustu)</label>
                            <select name="webyaz_floating_contact[position]">
                                <option value="right" <?php selected($opts['position'], 'right'); ?>>Sag</option>
                                <option value="left" <?php selected($opts['position'], 'left'); ?>>Sol</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="webyaz-settings-section">
                    <h2>Mobil Ayarlar</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Mobil Alt Mesafe (px)</label>
                            <input type="number" name="webyaz_floating_contact[mobile_bottom_offset]" value="<?php echo esc_attr($opts['mobile_bottom_offset']); ?>" min="0" max="300" step="5">
                            <small>Alt menu varsa yukari kaydirin (ornegin 80-120)</small>
                        </div>
                        <div class="webyaz-field">
                            <label>Mobil Yan Mesafe (px)</label>
                            <input type="number" name="webyaz_floating_contact[mobile_right_offset]" value="<?php echo esc_attr($opts['mobile_right_offset']); ?>" min="0" max="300" step="5">
                        </div>
                        <div class="webyaz-field">
                            <label>Mobil Konum</label>
                            <select name="webyaz_floating_contact[mobile_position]">
                                <option value="right" <?php selected($opts['mobile_position'], 'right'); ?>>Sag</option>
                                <option value="left" <?php selected($opts['mobile_position'], 'left'); ?>>Sol</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="webyaz-settings-section">
                    <h2>Kanallar</h2>
                    <p style="margin-bottom:12px;color:#666;">Bos birakilan kanallar gorunmez.</p>
                    <div class="webyaz-settings-grid">
                        <?php foreach ($channels as $key => $ch): ?>
                        <div class="webyaz-field">
                            <label style="display:flex;align-items:center;gap:8px;">
                                <span style="display:inline-flex;width:24px;height:24px;border-radius:50%;background:<?php echo esc_attr($ch['color']); ?>;color:#fff;align-items:center;justify-content:center;"><?php echo $ch['icon']; ?></span>
                                <?php echo esc_html($ch['label']); ?>
                            </label>
                            <input type="text" name="webyaz_floating_contact[<?php echo $key; ?>]" value="<?php echo esc_attr($opts[$key]); ?>" placeholder="<?php
                                if ($key === 'phone') echo '05xx xxx xxxx';
                                elseif ($key === 'whatsapp') echo '905xxxxxxxxx';
                                elseif ($key === 'email') echo 'info@siteadi.com';
                                else echo 'kullanici_adi';
                            ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php submit_button('Kaydet'); ?>
            </form>
        </div>
        <?php
    }
}

new Webyaz_Floating_Contact();
