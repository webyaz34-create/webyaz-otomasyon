<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Cargo_Tracking {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_submenu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('init', [$this, 'register_shortcode']);
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post_shop_order', [$this, 'save_tracking']);
        add_action('woocommerce_process_shop_order_meta', [$this, 'save_tracking']);
        add_action('woocommerce_order_details_after_order_table', [$this, 'show_tracking_on_order']);
        add_action('woocommerce_email_after_order_table', [$this, 'show_tracking_in_email'], 10, 4);
        add_action('wp_footer', [$this, 'frontend_css']);
    }

    // =========================================
    // AYARLAR
    // =========================================
    public function register_settings() {
        register_setting('webyaz_cargo_group', 'webyaz_cargo');
    }

    private static function get_defaults() {
        return array(
            'active'              => '1',
            'default_company'     => '',
            'auto_status'        => '1',
            'auto_status_value'  => 'wc-completed',
            'show_in_email'      => '1',
            'show_in_account'    => '1',
            'shortcode_page'     => '',
            'btn_color'          => '#2196f3',
            'btn_text'           => 'Kargoyu Takip Et',
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_cargo', array()), self::get_defaults());
    }

    private static function cargo_companies() {
        return [
            'yurtici' => ['name' => 'Yurtiçi Kargo', 'url' => 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code='],
            'aras'    => ['name' => 'Aras Kargo', 'url' => 'https://www.araskargo.com.tr/trs_gonderi_tracking.aspx?code='],
            'mng'     => ['name' => 'MNG Kargo', 'url' => 'https://www.mngkargo.com.tr/gonderi-takip/?code='],
            'ptt'     => ['name' => 'PTT Kargo', 'url' => 'https://gonderitakip.ptt.gov.tr/Track/Verify?q='],
            'surat'   => ['name' => 'Sürat Kargo', 'url' => 'https://www.suratkargo.com.tr/gonderi-takip?code='],
            'ups'     => ['name' => 'UPS', 'url' => 'https://www.ups.com/track?tracknum='],
            'trendyol'=> ['name' => 'Trendyol Express', 'url' => 'https://www.trendyolexpress.com/gonderi-takip?code='],
            'hepsijet'=> ['name' => 'HepsiJet', 'url' => 'https://www.hepsijet.com/gonderi-takip?code='],
            'diger'   => ['name' => 'Diğer', 'url' => ''],
        ];
    }

    // =========================================
    // SİPARİŞ META BOX
    // =========================================
    public function add_meta_box() {
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return;

        add_meta_box('webyaz_cargo_tracking', '📦 Kargo Takip (Webyaz)', [$this, 'render_meta_box'], 'shop_order', 'side', 'high');
        add_meta_box('webyaz_cargo_tracking', '📦 Kargo Takip (Webyaz)', [$this, 'render_meta_box'], 'woocommerce_page_wc-orders', 'side', 'high');
    }

    public function render_meta_box($post) {
        // HPOS uyumlu: hem WP_Post hem WC_Order destekle
        if (is_a($post, 'WC_Order')) {
            $order = $post;
        } else {
            $order = wc_get_order($post->ID);
        }
        if (!$order) return;

        $company = $order->get_meta('_webyaz_cargo_company', true);
        $tracking = $order->get_meta('_webyaz_tracking_no', true);
        $opts = self::get_opts();
        wp_nonce_field('webyaz_cargo', 'webyaz_cargo_nonce');
        $companies = self::cargo_companies();

        // Varsayılan firma seçimi
        if (empty($company) && !empty($opts['default_company'])) {
            $company = $opts['default_company'];
        }
        ?>
        <style>
            #webyaz_cargo_tracking .inside { padding: 0 !important; }
            .webyaz-cargo-metabox { padding: 10px; }
            .webyaz-cargo-metabox label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 12px; color: #333; }
            .webyaz-cargo-metabox select,
            .webyaz-cargo-metabox input[type="text"] { width: 100%; margin-bottom: 10px; }
            .webyaz-cargo-metabox .webyaz-track-link { display: block; text-align: center; background: #2196f3; color: #fff; padding: 8px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 12px; margin-top: 4px; }
            .webyaz-cargo-metabox .webyaz-track-link:hover { background: #1976d2; }
            .webyaz-cargo-info-note { background: #fff8e1; border-left: 3px solid #ffc107; padding: 6px 8px; font-size: 11px; color: #795548; border-radius: 3px; margin-top: 6px; }
        </style>
        <div class="webyaz-cargo-metabox">
            <label>🚚 Kargo Firması</label>
            <select name="webyaz_cargo_company">
                <option value="">Seçiniz</option>
                <?php foreach ($companies as $key => $c): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($company, $key); ?>><?php echo esc_html($c['name']); ?></option>
                <?php endforeach; ?>
            </select>

            <label>📋 Takip Numarası</label>
            <input type="text" name="webyaz_tracking_no" value="<?php echo esc_attr($tracking); ?>" placeholder="Kargo takip numarası girin">

            <?php if (!empty($tracking) && !empty($company) && isset($companies[$company]) && !empty($companies[$company]['url'])): ?>
                <a href="<?php echo esc_url($companies[$company]['url'] . urlencode($tracking)); ?>" target="_blank" class="webyaz-track-link">🔗 Kargoyu Takip Et</a>
            <?php endif; ?>

            <?php if ($opts['auto_status'] === '1'): ?>
            <div class="webyaz-cargo-info-note">
                💡 Takip numarası girildiğinde sipariş durumu otomatik olarak <strong>"<?php
                    $statuses = wc_get_order_statuses();
                    echo esc_html($statuses[$opts['auto_status_value']] ?? 'Tamamlandı');
                ?>"</strong> olarak güncellenir.
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function save_tracking($order_id) {
        if (!isset($_POST['webyaz_cargo_nonce']) || !wp_verify_nonce($_POST['webyaz_cargo_nonce'], 'webyaz_cargo')) return;
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        $old_tracking = $order->get_meta('_webyaz_tracking_no', true);

        if (isset($_POST['webyaz_cargo_company'])) {
            $order->update_meta_data('_webyaz_cargo_company', sanitize_text_field($_POST['webyaz_cargo_company']));
        }
        if (isset($_POST['webyaz_tracking_no'])) {
            $new_tracking = sanitize_text_field($_POST['webyaz_tracking_no']);
            $order->update_meta_data('_webyaz_tracking_no', $new_tracking);
            $order->save();

            // Otomatik durum güncelleme: yeni takip no girildiğinde
            if ($opts['auto_status'] === '1' && !empty($new_tracking) && $new_tracking !== $old_tracking) {
                $target_status = str_replace('wc-', '', $opts['auto_status_value']);
                if ($order->get_status() !== $target_status) {
                    $order->update_status($target_status, 'Webyaz: Kargo takip numarası girildi. ');
                }
            }
        } else {
            $order->save();
        }
    }

    // =========================================
    // FRONTEND: Sipariş Detayı
    // =========================================
    public function show_tracking_on_order($order) {
        $opts = self::get_opts();
        if ($opts['active'] !== '1' || $opts['show_in_account'] !== '1') return;

        $company = $order->get_meta('_webyaz_cargo_company', true);
        $tracking = $order->get_meta('_webyaz_tracking_no', true);
        if (empty($company) || empty($tracking)) return;

        $companies = self::cargo_companies();
        $c = $companies[$company] ?? null;
        if (!$c) return;

        $btn_color = esc_attr($opts['btn_color']);
        $btn_text = esc_html($opts['btn_text']);
        $url = $c['url'] . urlencode($tracking);
        ?>
        <div class="webyaz-cargo-info">
            <h3>📦 Kargo Takip Bilgileri</h3>
            <div class="webyaz-cargo-details">
                <div class="webyaz-cargo-detail-item">
                    <span class="webyaz-cargo-icon">🚚</span>
                    <div>
                        <small>Kargo Firması</small>
                        <strong><?php echo esc_html($c['name']); ?></strong>
                    </div>
                </div>
                <div class="webyaz-cargo-detail-item">
                    <span class="webyaz-cargo-icon">📋</span>
                    <div>
                        <small>Takip Numarası</small>
                        <strong><?php echo esc_html($tracking); ?></strong>
                    </div>
                </div>
            </div>
            <?php if (!empty($c['url'])): ?>
                <a href="<?php echo esc_url($url); ?>" target="_blank" class="webyaz-cargo-track-btn" style="background:<?php echo $btn_color; ?>;">
                    📍 <?php echo $btn_text; ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    public function show_tracking_in_email($order, $sent_to_admin, $plain_text, $email) {
        $opts = self::get_opts();
        if ($opts['show_in_email'] !== '1') return;
        if ($plain_text) return;
        $this->show_tracking_on_order($order);
    }

    // =========================================
    // FRONTEND CSS
    // =========================================
    public function frontend_css() {
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return;
        if (!is_account_page() && !is_checkout()) return;
        $btn = esc_attr($opts['btn_color']);
        ?>
        <style>
            .webyaz-cargo-info {
                margin: 20px 0; padding: 24px; background: #f8fffe;
                border: 1px solid #e0f2f1; border-radius: 12px;
                border-left: 4px solid <?php echo $btn; ?>;
            }
            .webyaz-cargo-info h3 { margin: 0 0 16px; font-size: 17px; color: #333; }
            .webyaz-cargo-details {
                display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;
            }
            .webyaz-cargo-detail-item {
                display: flex; align-items: center; gap: 10px;
                background: #fff; padding: 12px 16px; border-radius: 8px;
                border: 1px solid #e8e8e8; flex: 1; min-width: 200px;
            }
            .webyaz-cargo-icon { font-size: 24px; }
            .webyaz-cargo-detail-item small { display: block; font-size: 11px; color: #999; margin-bottom: 2px; }
            .webyaz-cargo-detail-item strong { font-size: 14px; color: #333; }
            .webyaz-cargo-track-btn {
                display: inline-flex; align-items: center; justify-content: center;
                gap: 6px; width: 100%; padding: 14px; border-radius: 8px;
                color: #fff; text-decoration: none; font-weight: 600; font-size: 15px;
                transition: all .2s; text-align: center;
            }
            .webyaz-cargo-track-btn:hover {
                filter: brightness(1.1); transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0,0,0,.15); color: #fff;
            }

            /* Shortcode Form */
            .webyaz-tracking-form { max-width: 500px; margin: 30px auto; }
            .webyaz-tracking-form h3 { text-align: center; margin-bottom: 20px; font-size: 22px; color: #333; }
            .webyaz-track-form { display: flex; flex-direction: column; gap: 12px; }
            .webyaz-track-form input {
                padding: 14px 16px; border: 2px solid #e0e0e0; border-radius: 10px;
                font-size: 15px; transition: border .2s; outline: none;
            }
            .webyaz-track-form input:focus { border-color: <?php echo $btn; ?>; }
            .webyaz-track-form button {
                padding: 14px; background: <?php echo $btn; ?>; color: #fff;
                border: none; border-radius: 10px; font-size: 16px; font-weight: 600;
                cursor: pointer; transition: all .2s;
            }
            .webyaz-track-form button:hover { filter: brightness(1.1); transform: translateY(-1px); }
            .webyaz-track-result {
                margin-top: 16px; padding: 16px; border-radius: 10px; font-size: 14px;
            }
            .webyaz-track-result.success { background: #e8f5e9; border: 1px solid #c8e6c9; color: #2e7d32; }
            .webyaz-track-result.error { background: #ffebee; border: 1px solid #ffcdd2; color: #c62828; }
            .webyaz-track-result.info { background: #fff8e1; border: 1px solid #ffecb3; color: #f57f17; }

            @media(max-width:600px) {
                .webyaz-cargo-details { flex-direction: column; }
                .webyaz-cargo-detail-item { min-width: auto; }
            }
        </style>
        <?php
    }

    // =========================================
    // SHORTCODE
    // =========================================
    public function register_shortcode() {
        add_shortcode('webyaz_kargo_takip', [$this, 'tracking_shortcode']);
    }

    public function tracking_shortcode() {
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return '';

        ob_start();
        ?>
        <div class="webyaz-tracking-form">
            <h3>📦 Kargo Takip</h3>
            <form method="post" class="webyaz-track-form">
                <?php wp_nonce_field('webyaz_track_cargo', 'webyaz_track_nonce'); ?>
                <input type="text" name="webyaz_track_order" placeholder="Sipariş numaranız" required>
                <input type="email" name="webyaz_track_email" placeholder="E-posta adresiniz" required>
                <button type="submit">🔍 Sorgula</button>
            </form>
            <?php
            if (!empty($_POST['webyaz_track_order']) && !empty($_POST['webyaz_track_email']) && wp_verify_nonce($_POST['webyaz_track_nonce'] ?? '', 'webyaz_track_cargo')) {
                $order_id = absint($_POST['webyaz_track_order']);
                $email = sanitize_email($_POST['webyaz_track_email']);
                $order = wc_get_order($order_id);
                if ($order && $order->get_billing_email() === $email) {
                    $company = $order->get_meta('_webyaz_cargo_company', true);
                    $tracking = $order->get_meta('_webyaz_tracking_no', true);
                    $companies = self::cargo_companies();
                    if (!empty($tracking) && isset($companies[$company])) {
                        $c = $companies[$company];
                        echo '<div class="webyaz-track-result success">';
                        echo '<p><strong>🚚 Kargo:</strong> ' . esc_html($c['name']) . '</p>';
                        echo '<p><strong>📋 Takip No:</strong> ' . esc_html($tracking) . '</p>';
                        if (!empty($c['url'])) {
                            echo '<a href="' . esc_url($c['url'] . $tracking) . '" target="_blank" class="webyaz-cargo-track-btn" style="background:' . esc_attr($opts['btn_color']) . ';margin-top:10px;">📍 ' . esc_html($opts['btn_text']) . '</a>';
                        }
                        echo '</div>';
                    } else {
                        echo '<div class="webyaz-track-result info"><p>📦 Kargonuz henüz hazırlanmadı. Lütfen daha sonra tekrar deneyin.</p></div>';
                    }
                } else {
                    echo '<div class="webyaz-track-result error"><p>❌ Sipariş bulunamadı. Bilgilerinizi kontrol ediniz.</p></div>';
                }
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // =========================================
    // ADMIN PANEL
    // =========================================
    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Kargo Entegrasyonu', 'Kargo Entegrasyonu', 'manage_options', 'webyaz-cargo', [$this, 'render_admin']);
    }

    public function render_admin() {
        $opts = self::get_opts();
        $companies = self::cargo_companies();
        $statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>📦 Kargo Entegrasyonu</h1>
                <p>Siparişlere kargo takip numarası ekleyin, müşterileriniz kargolarını kolayca takip etsin</p>
            </div>

            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">✅ Ayarlar kaydedildi!</div><?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('webyaz_cargo_group'); ?>

                <!-- Genel Ayarlar -->
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">⚙️ Genel Ayarlar</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Modül Durumu</label>
                            <select name="webyaz_cargo[active]">
                                <option value="0" <?php selected($opts['active'], '0'); ?>>Kapalı</option>
                                <option value="1" <?php selected($opts['active'], '1'); ?>>Aktif</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Varsayılan Kargo Firması</label>
                            <select name="webyaz_cargo[default_company]">
                                <option value="">Seçiniz (Her seferinde manuel)</option>
                                <?php foreach ($companies as $key => $c): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($opts['default_company'], $key); ?>><?php echo esc_html($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small>Yeni siparişlerde otomatik seçilecek kargo firması</small>
                        </div>
                    </div>
                </div>

                <!-- Otomatik Durum Güncelleme -->
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">🔄 Otomatik Durum Güncelleme</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Takip No Girildiğinde Durumu Güncelle</label>
                            <select name="webyaz_cargo[auto_status]">
                                <option value="0" <?php selected($opts['auto_status'], '0'); ?>>Hayır</option>
                                <option value="1" <?php selected($opts['auto_status'], '1'); ?>>Evet</option>
                            </select>
                            <small>Kargo takip numarası girildiğinde sipariş durumu otomatik değişir</small>
                        </div>
                        <div class="webyaz-field">
                            <label>Hedef Sipariş Durumu</label>
                            <select name="webyaz_cargo[auto_status_value]">
                                <?php foreach ($statuses as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($opts['auto_status_value'], $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small>Takip numarası girildiğinde sipariş bu duruma geçer</small>
                        </div>
                    </div>
                </div>

                <!-- Görünürlük Ayarları -->
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">👁️ Görünürlük Ayarları</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Hesabım Sayfasında Göster</label>
                            <select name="webyaz_cargo[show_in_account]">
                                <option value="1" <?php selected($opts['show_in_account'], '1'); ?>>Evet</option>
                                <option value="0" <?php selected($opts['show_in_account'], '0'); ?>>Hayır</option>
                            </select>
                            <small>Müşteri sipariş detayında kargo bilgisi görünsün mü?</small>
                        </div>
                        <div class="webyaz-field">
                            <label>E-postalarda Göster</label>
                            <select name="webyaz_cargo[show_in_email]">
                                <option value="1" <?php selected($opts['show_in_email'], '1'); ?>>Evet</option>
                                <option value="0" <?php selected($opts['show_in_email'], '0'); ?>>Hayır</option>
                            </select>
                            <small>Sipariş e-postalarına kargo bilgisi eklensin mi?</small>
                        </div>
                    </div>
                </div>

                <!-- Görünüm Ayarları -->
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">🎨 Görünüm Ayarları</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Buton Rengi</label>
                            <input type="color" name="webyaz_cargo[btn_color]" value="<?php echo esc_attr($opts['btn_color']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Buton Metni</label>
                            <input type="text" name="webyaz_cargo[btn_text]" value="<?php echo esc_attr($opts['btn_text']); ?>" placeholder="Kargoyu Takip Et">
                        </div>
                    </div>
                </div>

                <!-- Shortcode Bilgisi -->
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">📝 Shortcode Kullanımı</h2>
                    <div style="background:#f8f9ff;padding:20px;border-radius:10px;border:1px solid #e8ecf4;">
                        <p style="margin:0 0 12px;font-size:14px;color:#333;">Herhangi bir sayfaya kargo sorgulama formu eklemek için aşağıdaki shortcode'u kullanın:</p>
                        <code style="display:block;background:#1a1a2e;color:#4fc3f7;padding:14px 18px;border-radius:8px;font-size:15px;font-family:monospace;letter-spacing:.5px;">[webyaz_kargo_takip]</code>
                        <p style="margin:12px 0 0;font-size:12px;color:#888;">Müşteriler sipariş numarası ve e-posta adresi ile kargolarını sorgulayabilir.</p>
                    </div>
                </div>

                <!-- Desteklenen Firmalar -->
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">🚚 Desteklenen Kargo Firmaları</h2>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;">
                        <?php foreach ($companies as $key => $c): ?>
                        <div style="background:#fff;border:1px solid #e8e8e8;border-radius:10px;padding:14px;display:flex;align-items:center;gap:10px;">
                            <span style="font-size:20px;">📦</span>
                            <div>
                                <strong style="font-size:13px;color:#333;"><?php echo esc_html($c['name']); ?></strong>
                                <small style="display:block;color:#999;font-size:11px;"><?php echo $key === 'diger' ? 'Manuel link' : '✅ Otomatik takip'; ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php submit_button('💾 Ayarları Kaydet'); ?>
            </form>
        </div>
        <?php
    }
}

new Webyaz_Cargo_Tracking();
