<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Role_Manager {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'restrict_menus'), 999);
        add_action('admin_init', array($this, 'restrict_access'));
        add_action('admin_head', array($this, 'hide_elements'));

        // active_only modunda shop_manager'a Webyaz sayfalarında manage_options yetkisi ver
        add_filter('user_has_cap', array($this, 'grant_webyaz_caps'), 10, 4);

        // Ayarlar kaydedildiğinde webyaz_client_lock senkronunu yap
        add_action('update_option_webyaz_role_manager', array($this, 'sync_client_lock'), 10, 2);
    }

    public function register_settings() {
        register_setting('webyaz_role_group', 'webyaz_role_manager');
    }

    private static function get_defaults() {
        return array(
            'active' => '0',
            'hide_dashboard_widgets' => '1',
            'hide_updates' => '1',
            'hide_plugins' => '1',
            'hide_themes' => '1',
            'hide_tools' => '1',
            'hide_settings' => '1',
            'hide_users' => '1',
            'hide_comments' => '1',
            'hide_posts' => '1',
            'hide_pages' => '0',
            'hide_media' => '0',
            'hide_third_party' => '1',
            'webyaz_mode' => 'active_only', // hide, active_only, full
            'hide_admin_bar_wp' => '1',
            'custom_dashboard_text' => 'Hosgeldiniz! Magaza yonetiminiz icin sol menuden WooCommerce ve Urunler bolumlerni kullanabilirsiniz.',
            'allowed_roles' => array('shop_manager'),
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_role_manager', array()), self::get_defaults());
    }

    private function is_restricted_user() {
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return false;
        if (current_user_can('administrator')) return false;

        $user = wp_get_current_user();
        $allowed_roles = isset($opts['allowed_roles']) ? (array)$opts['allowed_roles'] : array('shop_manager');
        foreach ($user->roles as $role) {
            if (in_array($role, $allowed_roles)) return true;
        }
        return false;
    }

    /**
     * active_only modunda shop_manager'a Webyaz sayfalarında manage_options yetkisi ver
     * Bu sayede tüm modüllerin add_submenu_page çağrıları çalışır
     */
    public function grant_webyaz_caps($allcaps, $caps, $args, $user) {
        // Sadece manage_options kontrolü yapılıyorsa
        if (!in_array('manage_options', $caps)) return $allcaps;
        // Admin zaten her şeyi görebilir
        if (isset($allcaps['manage_options']) && $allcaps['manage_options']) return $allcaps;

        $opts = self::get_opts();
        // Rol yönetimi aktif ve active_only modunda olmalı
        if ($opts['active'] !== '1') return $allcaps;
        $webyaz_mode = isset($opts['webyaz_mode']) ? $opts['webyaz_mode'] : 'active_only';
        if ($webyaz_mode !== 'active_only') return $allcaps;

        // Kullanıcı izin verilen rollerden birinde mi?
        $allowed_roles = isset($opts['allowed_roles']) ? (array)$opts['allowed_roles'] : array('shop_manager');
        $user_roles = isset($user->roles) ? $user->roles : array();
        $is_allowed = false;
        foreach ($user_roles as $role) {
            if (in_array($role, $allowed_roles)) { $is_allowed = true; break; }
        }
        if (!$is_allowed) return $allcaps;

        // Sadece Webyaz admin sayfalarında izin ver
        $is_webyaz_page = false;
        if (is_admin()) {
            // admin_menu hook sırasında menü oluşturma — tüm manage_options kontrollerine izin ver
            if (doing_action('admin_menu') || doing_filter('admin_menu')) {
                $is_webyaz_page = true;
            }
            // Sayfa ziyaretinde
            $page = isset($_GET['page']) ? $_GET['page'] : '';
            if (strpos($page, 'webyaz') === 0) {
                $is_webyaz_page = true;
            }
            // options.php (ayar kaydetme) — webyaz option group'ları için
            global $pagenow;
            if ($pagenow === 'options.php') {
                $option_page = isset($_POST['option_page']) ? $_POST['option_page'] : '';
                if (strpos($option_page, 'webyaz') === 0) {
                    $is_webyaz_page = true;
                }
            }
        }

        if ($is_webyaz_page) {
            $allcaps['manage_options'] = true;
        }

        return $allcaps;
    }

    /**
     * Ayarlar kaydedildiğinde webyaz_client_lock senkronunu yap
     */
    public function sync_client_lock($old_value, $new_value) {
        $defaults = self::get_defaults();
        $new_opts = wp_parse_args($new_value, $defaults);
        $active = $new_opts['active'] === '1';
        $mode = isset($new_opts['webyaz_mode']) ? $new_opts['webyaz_mode'] : 'active_only';

        // active_only modundaysa client_lock'u aç
        if ($active && $mode === 'active_only') {
            update_option('webyaz_client_lock', '1');
        } else {
            update_option('webyaz_client_lock', '0');
        }
    }

    public function restrict_menus() {
        if (!$this->is_restricted_user()) return;
        $opts = self::get_opts();

        if ($opts['hide_plugins'] === '1') remove_menu_page('plugins.php');
        if ($opts['hide_themes'] === '1') {
            remove_menu_page('themes.php');
            remove_submenu_page('themes.php', 'themes.php');
            remove_submenu_page('themes.php', 'customize.php');
        }
        if ($opts['hide_tools'] === '1') remove_menu_page('tools.php');
        if ($opts['hide_settings'] === '1') remove_menu_page('options-general.php');
        if ($opts['hide_users'] === '1') remove_menu_page('users.php');
        if ($opts['hide_comments'] === '1') remove_menu_page('edit-comments.php');
        if ($opts['hide_posts'] === '1') remove_menu_page('edit.php');
        if ($opts['hide_pages'] === '1') remove_menu_page('edit.php?post_type=page');
        if ($opts['hide_media'] === '1') remove_menu_page('upload.php');
        // Webyaz modu: hide=tamamen gizle, active_only=sadece aktif modulleri goster
        $webyaz_mode = isset($opts['webyaz_mode']) ? $opts['webyaz_mode'] : 'active_only';
        if ($webyaz_mode === 'hide') {
            remove_menu_page('webyaz-dashboard');
        }
        // active_only modunda menu gorunur, kilitli gorunum Webyaz_Settings tarafindan otomatik uygulanir
        if ($opts['hide_updates'] === '1') {
            remove_submenu_page('index.php', 'update-core.php');
        }

        // 3. parti eklenti menuleri gizle (whitelist disindaki her sey kaldirilir)
        if (isset($opts['hide_third_party']) && $opts['hide_third_party'] === '1') {
            global $menu;
            // Bu sluglar gorunur kalacak (whitelist)
            $whitelist = array(
                'index.php',                       // Baslangic
                'edit.php?post_type=page',          // Sayfalar
                'upload.php',                       // Medya
                'woocommerce',                      // WooCommerce
                'edit.php?post_type=product',       // Urunler
                'edit.php?post_type=shop_order',    // Siparisler
                'wc-admin&path=/analytics/overview', // Analiz
                'webyaz-dashboard',                 // Webyaz
                'edit-comments.php',                // Yorumlar
                'edit.php',                         // Yazilar
                'users.php',                        // Kullanicilar
                'themes.php',                       // Gorunum
                'plugins.php',                      // Eklentiler
                'tools.php',                        // Araclar
                'options-general.php',              // Ayarlar
            );
            if ($menu) {
                foreach ($menu as $key => $item) {
                    if (!empty($item[2]) && !in_array($item[2], $whitelist)) {
                        remove_menu_page($item[2]);
                    }
                }
            }
        }
    }

    public function restrict_access() {
        if (!$this->is_restricted_user()) return;
        $opts = self::get_opts();

        global $pagenow;
        $blocked = array();
        if ($opts['hide_plugins'] === '1') $blocked[] = 'plugins.php';
        if ($opts['hide_themes'] === '1') { $blocked[] = 'themes.php'; $blocked[] = 'customize.php'; }
        if ($opts['hide_tools'] === '1') $blocked[] = 'tools.php';
        if ($opts['hide_settings'] === '1') $blocked[] = 'options-general.php';
        if ($opts['hide_users'] === '1') $blocked[] = 'users.php';
        if ($opts['hide_updates'] === '1') $blocked[] = 'update-core.php';

        if (in_array($pagenow, $blocked)) {
            wp_redirect(admin_url());
            exit;
        }

        $webyaz_mode = isset($opts['webyaz_mode']) ? $opts['webyaz_mode'] : 'active_only';
        if ($webyaz_mode === 'hide' && isset($_GET['page']) && strpos($_GET['page'], 'webyaz') === 0) {
            wp_redirect(admin_url());
            exit;
        }
    }

    public function hide_elements() {
        if (!$this->is_restricted_user()) return;
        $opts = self::get_opts();

        echo '<style>';
        if ($opts['hide_updates'] === '1') {
            echo '.update-plugins,.plugin-count,.update-count{display:none !important;}';
            echo '#wp-admin-bar-updates{display:none !important;}';
        }
        if ($opts['hide_admin_bar_wp'] === '1') {
            echo '#wp-admin-bar-wp-logo,#wp-admin-bar-comments,#wp-admin-bar-new-content .ab-submenu li:not(.ab-item-product){display:none !important;}';
        }
        if ($opts['hide_dashboard_widgets'] === '1') {
            echo '#dashboard-widgets .postbox:not(#woocommerce_dashboard_status){display:none !important;}';
            echo '#welcome-panel{display:none !important;}';
        }
        echo '</style>';

        if ($opts['hide_dashboard_widgets'] === '1' && !empty($opts['custom_dashboard_text'])) {
            global $pagenow;
            if ($pagenow === 'index.php') {
                echo '<script>jQuery(document).ready(function($){';
                echo '$("#dashboard-widgets-wrap").prepend(\'<div style="background:#fff;border-left:4px solid #446084;padding:20px 24px;margin-bottom:20px;border-radius:4px;font-family:Roboto,sans-serif;font-size:15px;line-height:1.7;box-shadow:0 1px 3px rgba(0,0,0,0.06);">' . esc_js($opts['custom_dashboard_text']) . '</div>\');';
                echo '});</script>';
            }
        }
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Rol Yonetimi', 'Rol Yonetimi', 'manage_options', 'webyaz-role-manager', array($this, 'render_admin'));
    }

    public function render_admin() {
        $opts = self::get_opts();
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>Rol Yonetimi</h1><p>Magaza yoneticilerinin gorebilecegi alanlari sinirlandir</p></div>
            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">Kaydedildi!</div><?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('webyaz_role_group'); ?>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Genel</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field"><label>Kisitlamayi Aktif Et</label><select name="webyaz_role_manager[active]"><option value="0" <?php selected($opts['active'], '0'); ?>>Kapali</option><option value="1" <?php selected($opts['active'], '1'); ?>>Aktif</option></select></div>
                    </div>
                </div>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Gizlenecek Menuler</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:12px;">Isaretlenen menuler magaza yoneticisi icin gizlenir. Admin (siz) her zaman her seyi gorur.</p>
                    <div class="webyaz-settings-grid">
                        <?php
                        $items = array(
                            'hide_plugins' => 'Eklentiler',
                            'hide_themes' => 'Gorunum / Temalar',
                            'hide_tools' => 'Araclar',
                            'hide_settings' => 'Ayarlar',
                            'hide_users' => 'Kullanicilar',
                            'hide_comments' => 'Yorumlar',
                            'hide_posts' => 'Yazilar',
                            'hide_pages' => 'Sayfalar',
                            'hide_media' => 'Medya',
                            'hide_updates' => 'Guncellemeler',
                            'hide_dashboard_widgets' => 'Dashboard Widgetlari',
                            'hide_admin_bar_wp' => 'Admin Bar (WP logosu vb.)',
                            'hide_third_party' => '3. Parti Eklenti Menuleri (Flatsome, YITH, WPForms vb.)',
                        );
                        foreach ($items as $key => $label):
                        ?>
                        <div class="webyaz-field">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="hidden" name="webyaz_role_manager[<?php echo $key; ?>]" value="0">
                                <input type="checkbox" name="webyaz_role_manager[<?php echo $key; ?>]" value="1" <?php checked(isset($opts[$key]) ? $opts[$key] : '0', '1'); ?> style="width:18px;height:18px;">
                                <?php echo esc_html($label); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Webyaz Otomasyon Erisimi</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:12px;">Magaza yoneticisinin Webyaz eklenti panelini nasil gorecegini secin.</p>
                    <div class="webyaz-field">
                        <label>Webyaz Paneli</label>
                        <select name="webyaz_role_manager[webyaz_mode]" style="min-width:250px;">
                            <option value="active_only" <?php selected(isset($opts['webyaz_mode']) ? $opts['webyaz_mode'] : 'active_only', 'active_only'); ?>>Sadece Aktif Modulleri Gorsun</option>
                            <option value="hide" <?php selected(isset($opts['webyaz_mode']) ? $opts['webyaz_mode'] : 'active_only', 'hide'); ?>>Tamamen Gizle</option>
                        </select>
                        <p style="font-size:11px;color:#999;margin-top:6px;">
                            <strong>Sadece Aktif Modulleri Gorsun:</strong> Musteri Webyaz menusunu gorebilir ama sadece sizin actiginiz modullerin ayarlarini gorur. Toggle yok.<br>
                            <strong>Tamamen Gizle:</strong> Musteri Webyaz menusunu hic goremez.
                        </p>
                    </div>
                </div>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Ozel Dashboard Mesaji</h2>
                    <div class="webyaz-field">
                        <label>Magaza yoneticisi panele girdiginde gosterilecek mesaj</label>
                        <textarea name="webyaz_role_manager[custom_dashboard_text]" rows="3" style="width:100%;"><?php echo esc_textarea($opts['custom_dashboard_text']); ?></textarea>
                    </div>
                </div>
                <?php submit_button('Kaydet'); ?>
            </form>

            <?php
            // Musteri hesabi olusturma islemi
            $create_msg = '';
            $create_type = '';
            if (isset($_POST['webyaz_create_client']) && wp_verify_nonce($_POST['_wpnonce_client'], 'webyaz_create_client')) {
                $c_user = sanitize_user(trim($_POST['client_username']));
                $c_email = sanitize_email(trim($_POST['client_email']));
                $c_pass = trim($_POST['client_password']);
                $c_name = sanitize_text_field(trim($_POST['client_display_name']));

                if (empty($c_user) || empty($c_email) || empty($c_pass)) {
                    $create_msg = 'Kullanici adi, e-posta ve sifre zorunludur.';
                    $create_type = 'error';
                } elseif (username_exists($c_user)) {
                    $create_msg = 'Bu kullanici adi zaten kullaniliyor: ' . esc_html($c_user);
                    $create_type = 'error';
                } elseif (email_exists($c_email)) {
                    $create_msg = 'Bu e-posta zaten kullaniliyor: ' . esc_html($c_email);
                    $create_type = 'error';
                } elseif (strlen($c_pass) < 6) {
                    $create_msg = 'Sifre en az 6 karakter olmalidir.';
                    $create_type = 'error';
                } else {
                    $user_id = wp_insert_user(array(
                        'user_login' => $c_user,
                        'user_email' => $c_email,
                        'user_pass'  => $c_pass,
                        'display_name' => $c_name ? $c_name : $c_user,
                        'role' => 'shop_manager',
                    ));
                    if (is_wp_error($user_id)) {
                        $create_msg = 'Hata: ' . $user_id->get_error_message();
                        $create_type = 'error';
                    } else {
                        $create_msg = 'Musteri hesabi basariyla olusturuldu!<br><strong>Kullanici:</strong> ' . esc_html($c_user) . ' &nbsp;|&nbsp; <strong>Sifre:</strong> ' . esc_html($c_pass);
                        $create_type = 'success';
                    }
                }
            }

            // Musteri hesabi silme
            if (isset($_POST['webyaz_delete_client']) && wp_verify_nonce($_POST['_wpnonce_del_client'], 'webyaz_del_client')) {
                $del_id = intval($_POST['webyaz_delete_client']);
                $del_user = get_userdata($del_id);
                if ($del_user && in_array('shop_manager', $del_user->roles) && !in_array('administrator', $del_user->roles)) {
                    require_once(ABSPATH . 'wp-admin/includes/user.php');
                    wp_delete_user($del_id);
                    $create_msg = 'Kullanici silindi: ' . esc_html($del_user->user_login);
                    $create_type = 'success';
                }
            }

            // Şifre sıfırlama
            if (isset($_POST['webyaz_reset_password']) && wp_verify_nonce($_POST['_wpnonce_reset_pass'], 'webyaz_reset_pass')) {
                $reset_id = intval($_POST['webyaz_reset_password']);
                $reset_user = get_userdata($reset_id);
                if ($reset_user && in_array('shop_manager', $reset_user->roles) && !in_array('administrator', $reset_user->roles)) {
                    $custom_pass = isset($_POST['webyaz_new_password']) ? trim($_POST['webyaz_new_password']) : '';
                    if (!empty($custom_pass) && strlen($custom_pass) >= 6) {
                        $new_pass = $custom_pass;
                    } else {
                        $chars = 'abcdefghkmnpqrstuvwxyzABCDEFGHKMNPQRSTUVWXYZ23456789';
                        $new_pass = '';
                        for ($i = 0; $i < 10; $i++) {
                            $new_pass .= $chars[wp_rand(0, strlen($chars) - 1)];
                        }
                    }
                    wp_set_password($new_pass, $reset_id);
                    $create_msg = '🔑 Sifre sifirlandi!<br><strong>Kullanici:</strong> ' . esc_html($reset_user->user_login) . ' &nbsp;|&nbsp; <strong>Yeni Sifre:</strong> <code style="background:#e3f2fd;padding:3px 10px;border-radius:4px;font-size:14px;font-weight:700;letter-spacing:1px;">' . esc_html($new_pass) . '</code>';
                    $create_type = 'success';
                    if (!empty($custom_pass) && strlen($custom_pass) < 6) {
                        $create_msg .= '<br><small style="color:#e65100;">⚠️ Girdiginiz sifre 6 karakterden kisa oldugu icin rastgele sifre uretildi.</small>';
                    }
                }
            }
            ?>

            <div class="webyaz-settings-section" style="margin-top:30px;border-top:2px solid #e0e0e0;padding-top:24px;">
                <h2 class="webyaz-section-title">👤 Musteri Hesabi Olustur</h2>
                <p style="color:#666;font-size:13px;margin-bottom:16px;">Musteriniz icin magaza yoneticisi hesabi olusturun. Olusturulan hesap yukaridaki kisitlamalarla otomatik calisan.</p>

                <?php if ($create_msg): ?>
                    <div class="webyaz-notice <?php echo $create_type; ?>" style="margin-bottom:16px;"><?php echo $create_msg; ?></div>
                <?php endif; ?>

                <form method="post" style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:10px;padding:20px;">
                    <?php wp_nonce_field('webyaz_create_client', '_wpnonce_client'); ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div class="webyaz-field">
                            <label style="font-weight:600;font-size:13px;margin-bottom:4px;display:block;">Kullanici Adi *</label>
                            <input type="text" name="client_username" required placeholder="ornek: magazam" style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                        </div>
                        <div class="webyaz-field">
                            <label style="font-weight:600;font-size:13px;margin-bottom:4px;display:block;">E-posta *</label>
                            <input type="email" name="client_email" required placeholder="musteri@site.com" style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                        </div>
                        <div class="webyaz-field">
                            <label style="font-weight:600;font-size:13px;margin-bottom:4px;display:block;">Sifre *</label>
                            <div style="position:relative;">
                                <input type="text" name="client_password" id="webyaz_client_pass" required placeholder="Min 6 karakter" style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;padding-right:110px;">
                                <button type="button" onclick="var c='abcdefghkmnpqrstuvwxyzABCDEFGHKMNPQRSTUVWXYZ23456789';var p='';for(var i=0;i<10;i++)p+=c[Math.floor(Math.random()*c.length)];document.getElementById('webyaz_client_pass').value=p;" style="position:absolute;right:4px;top:50%;transform:translateY(-50%);background:#446084;color:#fff;border:none;padding:6px 12px;border-radius:6px;font-size:11px;cursor:pointer;">🔑 Olustur</button>
                            </div>
                        </div>
                        <div class="webyaz-field">
                            <label style="font-weight:600;font-size:13px;margin-bottom:4px;display:block;">Gorunen Ad</label>
                            <input type="text" name="client_display_name" placeholder="Magaza Sahibi" style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                        </div>
                    </div>
                    <div style="margin-top:16px;display:flex;align-items:center;gap:12px;">
                        <button type="submit" name="webyaz_create_client" value="1" class="webyaz-btn webyaz-btn-primary" style="padding:12px 28px;font-size:14px;">👤 Hesap Olustur</button>
                        <span style="font-size:11px;color:#999;">Rol: Magaza Yoneticisi (shop_manager)</span>
                    </div>
                </form>
            </div>

            <?php
            // Mevcut magaza yoneticisi hesaplari
            $shop_managers = get_users(array('role' => 'shop_manager'));
            if (!empty($shop_managers)):
            ?>
            <div class="webyaz-settings-section" style="margin-top:24px;">
                <h2 class="webyaz-section-title">Mevcut Musteri Hesaplari</h2>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="background:#f5f5f5;">
                                <th style="padding:10px 14px;text-align:left;border-bottom:2px solid #e0e0e0;">Kullanici Adi</th>
                                <th style="padding:10px 14px;text-align:left;border-bottom:2px solid #e0e0e0;">E-posta</th>
                                <th style="padding:10px 14px;text-align:left;border-bottom:2px solid #e0e0e0;">Gorunen Ad</th>
                                <th style="padding:10px 14px;text-align:left;border-bottom:2px solid #e0e0e0;">Kayit Tarihi</th>
                                <th style="padding:10px 14px;text-align:center;border-bottom:2px solid #e0e0e0;">Islem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shop_managers as $sm): ?>
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:10px 14px;font-weight:600;"><?php echo esc_html($sm->user_login); ?></td>
                                <td style="padding:10px 14px;"><?php echo esc_html($sm->user_email); ?></td>
                                <td style="padding:10px 14px;"><?php echo esc_html($sm->display_name); ?></td>
                                <td style="padding:10px 14px;"><?php echo date_i18n('d.m.Y H:i', strtotime($sm->user_registered)); ?></td>
                                <td style="padding:10px 14px;text-align:center;">
                                    <div style="display:flex;flex-direction:column;gap:6px;align-items:center;">
                                        <form method="post" style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;justify-content:center;" onsubmit="return confirm('Sifre degistirilecek. Devam?');">
                                            <?php wp_nonce_field('webyaz_reset_pass', '_wpnonce_reset_pass'); ?>
                                            <div style="position:relative;">
                                                <input type="text" name="webyaz_new_password" id="wyResetPass_<?php echo $sm->ID; ?>" placeholder="Yeni sifre veya bos birak" style="width:160px;padding:5px 8px;border:1px solid #ddd;border-radius:6px;font-size:11px;padding-right:30px;">
                                                <button type="button" onclick="var c='abcdefghkmnpqrstuvwxyzABCDEFGHKMNPQRSTUVWXYZ23456789';var p='';for(var i=0;i<10;i++)p+=c[Math.floor(Math.random()*c.length)];document.getElementById('wyResetPass_<?php echo $sm->ID; ?>').value=p;" style="position:absolute;right:3px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:12px;" title="Rastgele sifre olustur">🎲</button>
                                            </div>
                                            <button type="submit" name="webyaz_reset_password" value="<?php echo $sm->ID; ?>" style="background:#1565c0;color:#fff;border:none;padding:5px 10px;border-radius:6px;font-size:11px;cursor:pointer;white-space:nowrap;">🔑 Kaydet</button>
                                        </form>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Bu hesabi silmek istediginize emin misiniz?');">
                                            <?php wp_nonce_field('webyaz_del_client', '_wpnonce_del_client'); ?>
                                            <button type="submit" name="webyaz_delete_client" value="<?php echo $sm->ID; ?>" style="background:#f44336;color:#fff;border:none;padding:4px 10px;border-radius:6px;font-size:11px;cursor:pointer;">🗑 Sil</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php
    }
}

new Webyaz_Role_Manager();
