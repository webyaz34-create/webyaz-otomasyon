<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Maintenance {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('template_redirect', array($this, 'check_maintenance'));
    }

    public function register_settings() {
        register_setting('webyaz_maintenance_group', 'webyaz_maintenance', array(
            'sanitize_callback' => array(__CLASS__, 'sanitize_options'),
        ));
    }

    public static function sanitize_options($input) {
        if (!is_array($input)) return $input;
        return $input;
    }

    private static function get_defaults() {
        return array(
            'active'         => '0',
            'site_name'      => get_bloginfo('name'),
            'message'        => 'Size daha iyi hizmet verebilmek için altyapımızı yeniliyoruz. En kısa sürede yeni yüzümüzle yayında olacağız. Bu süreçte bize ulaşabilirsiniz.',
            'logo'           => '',
            'bg_color'       => '#FDFBF7',
            'phone'          => '',
            'email'          => '',
            'address'        => '',
            'whatsapp'       => '',
            'whatsapp_text'  => 'WhatsApp\'tan Ulaşın',
            'facebook'       => '',
            'instagram'      => '',
            'twitter'        => '',
            'youtube'        => '',
            'linkedin'       => '',
            'tiktok'         => '',
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_maintenance', array()), self::get_defaults());
    }

    // =========================================
    // FRONTEND: Bakım Sayfası
    // =========================================
    public function check_maintenance() {
        if (is_admin() || wp_doing_ajax()) return;
        if (current_user_can('manage_options')) return;
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return;

        // Tema renklerini al
        $colors = Webyaz_Colors::get_theme_colors();
        $accent = $colors['primary'];
        $bg_color = !empty($opts['bg_color']) ? $opts['bg_color'] : '#FDFBF7';

        status_header(503);
        header('Retry-After: 3600');
        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($opts['site_name']); ?> | Bakım Sayfası</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
            <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
            <style>
                html, body {
                    margin: 0; padding: 0; width: 100%; height: 100%;
                    background-color: <?php echo esc_attr($bg_color); ?>;
                }
                .tam-ekran-ortala {
                    display: flex; justify-content: center; align-items: center;
                    min-height: 100vh; width: 100%; padding: 20px; box-sizing: border-box;
                    font-family: 'Open Sans', Arial, sans-serif; color: #444;
                }
                .bakim-karti {
                    background-color: #ffffff; width: 100%; max-width: 650px;
                    padding: 50px 30px; border: 3px solid <?php echo esc_attr($accent); ?>;
                    border-radius: 40px; box-shadow: 0 15px 35px <?php echo esc_attr($accent); ?>1a;
                    text-align: center; box-sizing: border-box;
                }
                .logo-kapsayici {
                    margin-bottom: 24px; display: inline-flex; align-items: center; justify-content: center;
                    background: <?php echo esc_attr($accent); ?>08; border: 2px solid <?php echo esc_attr($accent); ?>25;
                    border-radius: 50%; width: 130px; height: 130px; padding: 0; overflow: hidden;
                    box-shadow: 0 4px 16px <?php echo esc_attr($accent); ?>12;
                }
                .logo-kapsayici img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
                .logo-kapsayici .logo-ikon { color: <?php echo esc_attr($accent); ?>; font-size: 50px; }
                .slogan { color: <?php echo esc_attr($accent); ?>; font-size: 32px; font-weight: 700; margin: 0 0 15px 0; line-height: 1.3; }
                .aciklama-metni { font-size: 16px; color: #666; margin: 0 auto 35px auto; line-height: 1.6; max-width: 90%; }
                .ayirici { border: none; border-top: 1px solid <?php echo esc_attr($accent); ?>33; margin: 0 auto 35px auto; width: 70%; }

                .iletisim-kapsayici { display: flex; justify-content: center; margin-bottom: 30px; }
                .iletisim-listesi { list-style: none; padding: 0; margin: 0; text-align: left; display: inline-block; }
                .iletisim-listesi li { font-size: 16px; color: #444; margin-bottom: 20px; display: flex; align-items: center; line-height: 1.5; font-weight: 600; }
                .iletisim-listesi li i { color: <?php echo esc_attr($accent); ?>; width: 30px; font-size: 24px; text-align: center; margin-right: 15px; flex-shrink: 0; }
                .iletisim-listesi a { color: #444; text-decoration: none; transition: color 0.3s ease; }
                .iletisim-listesi a:hover { color: <?php echo esc_attr($accent); ?>; }

                .sosyal-medya { display: flex; justify-content: center; gap: 14px; margin-bottom: 30px; flex-wrap: wrap; }
                .sosyal-medya a {
                    display: flex; align-items: center; justify-content: center;
                    width: 44px; height: 44px; border-radius: 50%;
                    background: <?php echo esc_attr($accent); ?>15; color: <?php echo esc_attr($accent); ?>;
                    font-size: 20px; text-decoration: none; transition: all 0.3s ease;
                }
                .sosyal-medya a:hover {
                    background: <?php echo esc_attr($accent); ?>; color: #fff;
                    transform: translateY(-2px); box-shadow: 0 5px 15px <?php echo esc_attr($accent); ?>40;
                }

                .buton-grubu { display: flex; flex-direction: column; gap: 15px; align-items: center; }
                .btn-whatsapp {
                    display: inline-flex; align-items: center; justify-content: center;
                    background-color: #25D366; color: #ffffff; font-weight: 600; font-size: 17px;
                    text-decoration: none; padding: 16px 35px; border-radius: 40px; transition: all 0.3s ease;
                    box-shadow: 0 5px 15px rgba(37, 211, 102, 0.2); width: 100%; max-width: 400px; box-sizing: border-box;
                }
                .btn-whatsapp:hover { background-color: #1ebe57; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(37, 211, 102, 0.4); color: #ffffff; }
                .btn-whatsapp i { margin-right: 10px; font-size: 22px; }

                @media (max-width: 600px) {
                    .bakim-karti { padding: 40px 20px; border-radius: 30px; }
                    .slogan { font-size: 26px; }
                    .aciklama-metni { font-size: 14px; max-width: 100%; }
                    .iletisim-listesi li { font-size: 15px; }
                }
            </style>
        </head>
        <body>
            <div class="tam-ekran-ortala">
                <div class="bakim-karti">

                    <div class="logo-kapsayici">
                        <?php if (!empty($opts['logo'])): ?>
                            <img src="<?php echo esc_url($opts['logo']); ?>" alt="<?php echo esc_attr($opts['site_name']); ?>">
                        <?php else: ?>
                            <i class="fa-solid fa-heart-pulse logo-ikon"></i>
                        <?php endif; ?>
                    </div>

                    <h1 class="slogan"><?php echo esc_html($opts['site_name']); ?></h1>
                    <p class="aciklama-metni"><?php echo esc_html($opts['message']); ?></p>

                    <?php
                    $has_contact = !empty($opts['phone']) || !empty($opts['email']) || !empty($opts['address']);
                    $has_social = !empty($opts['facebook']) || !empty($opts['instagram']) || !empty($opts['twitter']) || !empty($opts['youtube']) || !empty($opts['linkedin']) || !empty($opts['tiktok']);
                    $has_whatsapp = !empty($opts['whatsapp']);

                    if ($has_contact || $has_social || $has_whatsapp):
                    ?>
                    <hr class="ayirici">

                    <?php if ($has_contact): ?>
                    <div class="iletisim-kapsayici">
                        <ul class="iletisim-listesi">
                            <?php if (!empty($opts['phone'])): ?>
                            <li><i class="fa-solid fa-phone-volume"></i><a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $opts['phone'])); ?>"><?php echo esc_html($opts['phone']); ?></a></li>
                            <?php endif; ?>
                            <?php if (!empty($opts['email'])): ?>
                            <li><i class="fa-regular fa-envelope"></i><a href="mailto:<?php echo esc_attr($opts['email']); ?>"><?php echo esc_html($opts['email']); ?></a></li>
                            <?php endif; ?>
                            <?php if (!empty($opts['address'])): ?>
                            <li><i class="fa-solid fa-location-dot"></i><span><?php echo esc_html($opts['address']); ?></span></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if ($has_social): ?>
                    <div class="sosyal-medya">
                        <?php if (!empty($opts['facebook'])): ?><a href="<?php echo esc_url($opts['facebook']); ?>" target="_blank" title="Facebook"><i class="fa-brands fa-facebook-f"></i></a><?php endif; ?>
                        <?php if (!empty($opts['instagram'])): ?><a href="<?php echo esc_url($opts['instagram']); ?>" target="_blank" title="Instagram"><i class="fa-brands fa-instagram"></i></a><?php endif; ?>
                        <?php if (!empty($opts['twitter'])): ?><a href="<?php echo esc_url($opts['twitter']); ?>" target="_blank" title="X / Twitter"><i class="fa-brands fa-x-twitter"></i></a><?php endif; ?>
                        <?php if (!empty($opts['youtube'])): ?><a href="<?php echo esc_url($opts['youtube']); ?>" target="_blank" title="YouTube"><i class="fa-brands fa-youtube"></i></a><?php endif; ?>
                        <?php if (!empty($opts['linkedin'])): ?><a href="<?php echo esc_url($opts['linkedin']); ?>" target="_blank" title="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a><?php endif; ?>
                        <?php if (!empty($opts['tiktok'])): ?><a href="<?php echo esc_url($opts['tiktok']); ?>" target="_blank" title="TikTok"><i class="fa-brands fa-tiktok"></i></a><?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($has_whatsapp): ?>
                    <div class="buton-grubu">
                        <a href="https://wa.me/<?php echo esc_attr(preg_replace('/[^0-9]/', '', $opts['whatsapp'])); ?>" target="_blank" class="btn-whatsapp">
                            <i class="fa-brands fa-whatsapp"></i> <?php echo esc_html($opts['whatsapp_text']); ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php endif; // has_contact || has_social || has_whatsapp ?>

                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    // =========================================
    // ADMIN
    // =========================================
    public function add_submenu() {
        $hook = add_submenu_page('webyaz-dashboard', 'Bakım Modu', 'Bakım Modu', 'manage_options', 'webyaz-maintenance', array($this, 'render_admin'));
        add_action('load-' . $hook, function(){ wp_enqueue_media(); });
    }

    public function render_admin() {
        $opts = self::get_opts();
        $colors = Webyaz_Colors::get_theme_colors();
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>🛠️ Bakım Modu</h1><p>Tek tıkla siteyi bakıma alın — profesyonel bakım sayfası ile ziyaretçilerinizi bilgilendirin</p></div>
            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">✅ Ayarlar kaydedildi!</div><?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('webyaz_maintenance_group'); ?>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

                    <!-- SOL KOLON: Ayarlar -->
                    <div>
                        <!-- Genel Ayarlar -->
                        <div class="webyaz-settings-section" style="margin-bottom:20px;">
                            <h3 style="margin:0 0 16px 0;font-size:16px;color:#333;">⚙️ Genel Ayarlar</h3>
                            <div class="webyaz-settings-grid">
                                <div class="webyaz-field">
                                    <label>Bakım Modu</label>
                                    <select name="webyaz_maintenance[active]">
                                        <option value="0" <?php selected($opts['active'], '0'); ?>>Kapalı</option>
                                        <option value="1" <?php selected($opts['active'], '1'); ?>>Aktif (Site bakımda)</option>
                                    </select>
                                    <?php if ($opts['active'] === '1'): ?>
                                    <small style="color:#d32f2f;font-weight:700;display:block;margin-top:6px;">⚠️ DİKKAT: Site şu anda bakımda! Ziyaretçiler siteye erişemiyor.</small>
                                    <?php endif; ?>
                                </div>
                                <div class="webyaz-field">
                                    <label>Site / Firma Adı</label>
                                    <input type="text" name="webyaz_maintenance[site_name]" value="<?php echo esc_attr($opts['site_name']); ?>" placeholder="Örn: May Healthcare">
                                </div>
                                <div class="webyaz-field">
                                    <label>Açıklama Metni</label>
                                    <textarea name="webyaz_maintenance[message]" rows="3" placeholder="Ziyaretçilere gösterilecek mesaj..."><?php echo esc_textarea($opts['message']); ?></textarea>
                                </div>
                                <div class="webyaz-field">
                                    <label>Logo</label>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <input type="text" id="webyaz_maint_logo" name="webyaz_maintenance[logo]" value="<?php echo esc_attr($opts['logo']); ?>" style="flex:1;" placeholder="Logo URL veya medyadan seçin">
                                        <button type="button" class="button" id="webyaz_maint_logo_btn">📷 Medyadan Seç</button>
                                    </div>
                                    <?php if (!empty($opts['logo'])): ?>
                                    <img src="<?php echo esc_url($opts['logo']); ?>" style="max-width:150px;margin-top:8px;border-radius:6px;border:1px solid #eee;padding:4px;">
                                    <?php endif; ?>
                                </div>
                                <div class="webyaz-field">
                                    <label>Arka Plan Rengi</label>
                                    <input type="color" name="webyaz_maintenance[bg_color]" value="<?php echo esc_attr($opts['bg_color']); ?>">
                                    <small>Varsayılan: krem (#FDFBF7). Kartın kenarlık ve vurgu rengi otomatik olarak sitenin ana renginden alınır <strong style="color:<?php echo esc_attr($colors['primary']); ?>;">(<?php echo esc_html($colors['primary']); ?>)</strong></small>
                                </div>
                            </div>
                        </div>

                        <!-- İletişim Bilgileri -->
                        <div class="webyaz-settings-section" style="margin-bottom:20px;">
                            <h3 style="margin:0 0 16px 0;font-size:16px;color:#333;">📞 İletişim Bilgileri</h3>
                            <div class="webyaz-settings-grid">
                                <div class="webyaz-field">
                                    <label>Telefon</label>
                                    <input type="text" name="webyaz_maintenance[phone]" value="<?php echo esc_attr($opts['phone']); ?>" placeholder="Örn: 0 531 780 53 32">
                                </div>
                                <div class="webyaz-field">
                                    <label>E-posta</label>
                                    <input type="email" name="webyaz_maintenance[email]" value="<?php echo esc_attr($opts['email']); ?>" placeholder="Örn: info@example.com">
                                </div>
                                <div class="webyaz-field">
                                    <label>Adres</label>
                                    <input type="text" name="webyaz_maintenance[address]" value="<?php echo esc_attr($opts['address']); ?>" placeholder="Örn: İstanbul, Türkiye">
                                </div>
                                <div class="webyaz-field">
                                    <label>WhatsApp Numarası</label>
                                    <input type="text" name="webyaz_maintenance[whatsapp]" value="<?php echo esc_attr($opts['whatsapp']); ?>" placeholder="Örn: 905317805332 (ülke kodu ile)">
                                    <small>Başında + olmadan, ülke kodu ile yazın. Örn: 905317805332</small>
                                </div>
                                <div class="webyaz-field">
                                    <label>WhatsApp Buton Metni</label>
                                    <input type="text" name="webyaz_maintenance[whatsapp_text]" value="<?php echo esc_attr($opts['whatsapp_text']); ?>" placeholder="WhatsApp'tan Ulaşın">
                                </div>
                            </div>
                        </div>

                        <!-- Sosyal Medya -->
                        <div class="webyaz-settings-section" style="margin-bottom:20px;">
                            <h3 style="margin:0 0 16px 0;font-size:16px;color:#333;">🌐 Sosyal Medya</h3>
                            <div class="webyaz-settings-grid">
                                <div class="webyaz-field"><label><i class="dashicons dashicons-facebook-alt" style="color:#1877f2;"></i> Facebook</label><input type="url" name="webyaz_maintenance[facebook]" value="<?php echo esc_attr($opts['facebook']); ?>" placeholder="https://facebook.com/..."></div>
                                <div class="webyaz-field"><label><i class="dashicons dashicons-instagram" style="color:#e4405f;"></i> Instagram</label><input type="url" name="webyaz_maintenance[instagram]" value="<?php echo esc_attr($opts['instagram']); ?>" placeholder="https://instagram.com/..."></div>
                                <div class="webyaz-field"><label>𝕏 Twitter / X</label><input type="url" name="webyaz_maintenance[twitter]" value="<?php echo esc_attr($opts['twitter']); ?>" placeholder="https://x.com/..."></div>
                                <div class="webyaz-field"><label><i class="dashicons dashicons-youtube" style="color:#ff0000;"></i> YouTube</label><input type="url" name="webyaz_maintenance[youtube]" value="<?php echo esc_attr($opts['youtube']); ?>" placeholder="https://youtube.com/..."></div>
                                <div class="webyaz-field"><label><i class="dashicons dashicons-linkedin" style="color:#0a66c2;"></i> LinkedIn</label><input type="url" name="webyaz_maintenance[linkedin]" value="<?php echo esc_attr($opts['linkedin']); ?>" placeholder="https://linkedin.com/in/..."></div>
                                <div class="webyaz-field"><label>🎵 TikTok</label><input type="url" name="webyaz_maintenance[tiktok]" value="<?php echo esc_attr($opts['tiktok']); ?>" placeholder="https://tiktok.com/@..."></div>
                            </div>
                        </div>
                    </div>

                    <!-- SAĞ KOLON: Önizleme -->
                    <div style="position:sticky;top:40px;">
                        <div class="webyaz-settings-section">
                            <h3 style="margin:0 0 16px 0;font-size:16px;color:#333;">👁️ Önizleme</h3>
                            <div id="wb-maint-preview" style="background:<?php echo esc_attr($opts['bg_color']); ?>;border-radius:16px;padding:24px;min-height:400px;display:flex;align-items:center;justify-content:center;">
                                <div style="background:#fff;max-width:100%;width:100%;padding:30px 20px;border:2px solid <?php echo esc_attr($colors['primary']); ?>;border-radius:24px;text-align:center;box-shadow:0 8px 20px rgba(0,0,0,.06);">
                                    <!-- Logo -->
                                    <?php if (!empty($opts['logo'])): ?>
                                        <img src="<?php echo esc_url($opts['logo']); ?>" style="max-width:120px;max-height:70px;margin-bottom:12px;object-fit:contain;">
                                    <?php else: ?>
                                        <div style="color:<?php echo esc_attr($colors['primary']); ?>;font-size:32px;margin-bottom:12px;">❤️‍🩹</div>
                                    <?php endif; ?>
                                    <!-- Başlık -->
                                    <div style="color:<?php echo esc_attr($colors['primary']); ?>;font-size:18px;font-weight:700;margin-bottom:8px;"><?php echo esc_html($opts['site_name']); ?></div>
                                    <!-- Mesaj -->
                                    <div style="font-size:11px;color:#666;line-height:1.5;margin-bottom:16px;max-width:90%;margin-left:auto;margin-right:auto;"><?php echo esc_html(mb_substr($opts['message'], 0, 100)); ?><?php echo mb_strlen($opts['message']) > 100 ? '...' : ''; ?></div>
                                    <?php
                                    $has_any = !empty($opts['phone']) || !empty($opts['email']) || !empty($opts['address']) || !empty($opts['whatsapp']) || !empty($opts['facebook']) || !empty($opts['instagram']);
                                    if ($has_any): ?>
                                    <hr style="border:none;border-top:1px solid <?php echo esc_attr($colors['primary']); ?>33;margin:12px auto;width:60%;">
                                    <?php endif; ?>
                                    <!-- İletişim -->
                                    <?php if (!empty($opts['phone'])): ?><div style="font-size:11px;color:#555;margin-bottom:4px;">📞 <?php echo esc_html($opts['phone']); ?></div><?php endif; ?>
                                    <?php if (!empty($opts['email'])): ?><div style="font-size:11px;color:#555;margin-bottom:4px;">📧 <?php echo esc_html($opts['email']); ?></div><?php endif; ?>
                                    <?php if (!empty($opts['address'])): ?><div style="font-size:11px;color:#555;margin-bottom:4px;">📍 <?php echo esc_html($opts['address']); ?></div><?php endif; ?>
                                    <!-- Sosyal medya ikonları -->
                                    <?php if (!empty($opts['facebook']) || !empty($opts['instagram']) || !empty($opts['twitter']) || !empty($opts['youtube'])): ?>
                                    <div style="margin-top:10px;display:flex;justify-content:center;gap:8px;">
                                        <?php if (!empty($opts['facebook'])): ?><span style="display:inline-flex;width:28px;height:28px;border-radius:50%;background:<?php echo esc_attr($colors['primary']); ?>15;color:<?php echo esc_attr($colors['primary']); ?>;align-items:center;justify-content:center;font-size:12px;">f</span><?php endif; ?>
                                        <?php if (!empty($opts['instagram'])): ?><span style="display:inline-flex;width:28px;height:28px;border-radius:50%;background:<?php echo esc_attr($colors['primary']); ?>15;color:<?php echo esc_attr($colors['primary']); ?>;align-items:center;justify-content:center;font-size:12px;">📷</span><?php endif; ?>
                                        <?php if (!empty($opts['twitter'])): ?><span style="display:inline-flex;width:28px;height:28px;border-radius:50%;background:<?php echo esc_attr($colors['primary']); ?>15;color:<?php echo esc_attr($colors['primary']); ?>;align-items:center;justify-content:center;font-size:12px;">𝕏</span><?php endif; ?>
                                        <?php if (!empty($opts['youtube'])): ?><span style="display:inline-flex;width:28px;height:28px;border-radius:50%;background:<?php echo esc_attr($colors['primary']); ?>15;color:<?php echo esc_attr($colors['primary']); ?>;align-items:center;justify-content:center;font-size:12px;">▶</span><?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    <!-- WhatsApp -->
                                    <?php if (!empty($opts['whatsapp'])): ?>
                                    <div style="margin-top:12px;"><span style="display:inline-block;background:#25D366;color:#fff;font-size:11px;font-weight:600;padding:8px 18px;border-radius:20px;">💬 <?php echo esc_html($opts['whatsapp_text']); ?></span></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="margin-top:12px;background:#f8f9ff;border-radius:10px;padding:12px 14px;font-size:12px;color:#555;line-height:1.6;">
                                <div style="font-weight:700;color:#333;margin-bottom:4px;">💡 Bilgi</div>
                                Bakım modu aktifken, yönetici olarak giriş yapmış kullanıcılar siteyi normal görebilir. Sadece çıkış yapmış ziyaretçiler bakım sayfasını görür.
                                <div style="margin-top:6px;padding-top:6px;border-top:1px solid #e5e7eb;font-size:11px;color:#888;">
                                    🎨 Kartın vurgu rengi sitenizin ana renginden alınır: <strong style="color:<?php echo esc_attr($colors['primary']); ?>;"><?php echo esc_html($colors['primary']); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <?php submit_button('💾 Ayarları Kaydet'); ?>
            </form>

            <script>
            jQuery(function($){
                $('#webyaz_maint_logo_btn').on('click', function(e){
                    e.preventDefault();
                    var frame = wp.media({title:'Logo Seç',button:{text:'Seç'},multiple:false});
                    frame.on('select', function(){
                        var url = frame.state().get('selection').first().toJSON().url;
                        $('#webyaz_maint_logo').val(url);
                    });
                    frame.open();
                });
            });
            </script>
        </div>
        <?php
    }
}

new Webyaz_Maintenance();
