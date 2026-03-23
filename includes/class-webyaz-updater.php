<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Updater {

    private $plugin_slug   = 'webyaz-otomasyon';
    private $plugin_file   = 'webyaz-otomasyon/webyaz-otomasyon.php';
    private $update_url    = 'https://raw.githubusercontent.com/webyaz34-create/webyaz-otomasyon/main/update-info.json';
    private $cache_key     = 'webyaz_update_data';
    private $cache_seconds = 43200; // 12 saat

    public function __construct() {
        // Admin menü
        add_action('admin_menu', array($this, 'add_submenu'));
        // WordPress güncelleme kontrolüne bağlan
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        // Eklenti bilgi popup'ı
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        // Güncelleme sonrası cache temizle
        add_action('upgrader_process_complete', array($this, 'clear_cache'), 10, 2);
        // Dashboard'da güncelleme banner'ı
        add_action('admin_notices', array($this, 'admin_update_notice'));
        // Webyaz Dashboard'da güncelleme banner'ı
        add_action('webyaz_dashboard_before_modules', array($this, 'dashboard_update_banner'));
        // Ayar kaydet
        add_action('admin_init', array($this, 'register_settings'));
        // Zorla kontrol AJAX
        add_action('wp_ajax_webyaz_force_update_check', array($this, 'ajax_force_check'));
        // AJAX güncelleme
        add_action('wp_ajax_webyaz_run_update', array($this, 'ajax_run_update'));
    }

    public function register_settings() {
        register_setting('webyaz_updater_group', 'webyaz_updater_opts');
    }

    private static function get_defaults() {
        return array(
            'update_url'    => '',
            'check_interval' => '12', // saat
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_updater_opts', array()), self::get_defaults());
    }

    /* ─── Admin Menü ─── */
    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Otomatik Guncelleme', 'Otomatik Guncelleme', 'manage_options', 'webyaz-updater', array($this, 'render_admin'));
    }

    /* ─── Admin Sayfa ─── */
    public function render_admin() {
        $opts = self::get_opts();
        $current_version = defined('WEBYAZ_VERSION') ? WEBYAZ_VERSION : '0.0.0';
        $remote = $this->get_remote_data();
        $has_update = $remote && isset($remote->version) && version_compare($remote->version, $current_version, '>');
        $last_check = get_transient($this->cache_key) !== false ? 'Cache aktif (sonraki kontrol ~' . human_time_diff(time(), time() + $this->get_interval()) . ' sonra)' : 'Henüz kontrol yapılmadı';

        if (isset($_GET['settings-updated'])) {
            // Interval değiştiyse cache'i güncelle
            $this->cache_seconds = $this->get_interval();
            delete_transient($this->cache_key);
        }
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>🔄 Otomatik Güncelleme</h1>
                <p>Eklenti güncelleme bildirimi ve tek tık güncelleme sistemi</p>
            </div>

            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">Ayarlar kaydedildi!</div><?php endif; ?>

            <!-- Durum Kartları -->
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:25px;">
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #1565c0;">
                    <div style="font-size:24px;font-weight:700;color:#1565c0;">v<?php echo esc_html($current_version); ?></div>
                    <div style="font-size:12px;color:#666;">Mevcut Sürüm</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid <?php echo $has_update ? '#ff9800' : '#4caf50'; ?>;">
                    <div style="font-size:24px;font-weight:700;color:<?php echo $has_update ? '#ff9800' : '#4caf50'; ?>;">
                        <?php if ($remote && isset($remote->version)): ?>
                            v<?php echo esc_html($remote->version); ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                    <div style="font-size:12px;color:#666;">Sunucudaki Sürüm</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid <?php echo $has_update ? '#ff9800' : '#4caf50'; ?>;">
                    <div style="font-size:20px;font-weight:700;color:<?php echo $has_update ? '#ff9800' : '#4caf50'; ?>;">
                        <?php echo $has_update ? '⬆️ Güncelleme Var' : '✅ Güncel'; ?>
                    </div>
                    <div style="font-size:12px;color:#666;">Durum</div>
                </div>
            </div>

            <?php if ($has_update): ?>
                <div id="wyUpdateBanner" style="background:linear-gradient(135deg,#ff6f00,#ff8f00);border-radius:14px;padding:20px 24px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;box-shadow:0 4px 15px rgba(255,111,0,0.3);">
                    <div style="display:flex;align-items:center;gap:12px;color:#fff;">
                        <span style="font-size:32px;">🔔</span>
                        <div>
                            <div style="font-size:16px;font-weight:700;">Güncelleme Mevcut!</div>
                            <div style="font-size:13px;opacity:0.9;">
                                v<?php echo esc_html($current_version); ?> → v<?php echo esc_html($remote->version); ?> sürümüne güncelleyebilirsiniz.
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="wyRunUpdate()" style="background:#fff;color:#e65100;padding:11px 24px;border-radius:8px;border:none;font-weight:700;font-size:14px;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                        ⬆️ Güncelle
                    </button>
                </div>
            <?php endif; ?>

            <!-- Güncelleme Merkezi - Tek Akış -->
            <div class="webyaz-settings-section" style="margin-bottom:20px;">
                <h2 class="webyaz-section-title">Güncelleme Merkezi</h2>

                <!-- Durum alanı -->
                <div id="wyUpdateArea">
                    <div id="wyIdleState" style="text-align:center;padding:30px 20px;">
                        <div style="font-size:48px;margin-bottom:12px;">🔍</div>
                        <div style="font-size:15px;color:#555;margin-bottom:6px;">Mevcut sürüm: <strong>v<?php echo esc_html($current_version); ?></strong></div>
                        <div style="font-size:12px;color:#999;margin-bottom:20px;">Son kontrol: <?php echo esc_html($last_check); ?></div>
                        <button type="button" id="wyCheckBtn" onclick="wyCheckAndUpdate()" class="webyaz-btn webyaz-btn-primary" style="padding:12px 32px;font-size:15px;">
                            🔍 Güncelleme Kontrol Et
                        </button>
                    </div>

                    <!-- Kontrol ediliyor animasyonu -->
                    <div id="wyCheckingState" style="display:none;text-align:center;padding:30px 20px;">
                        <div style="font-size:48px;margin-bottom:12px;animation:wyPulse 1s infinite;">⏳</div>
                        <div style="font-size:15px;color:#555;font-weight:600;">Güncelleme kontrol ediliyor...</div>
                        <div style="font-size:12px;color:#999;margin-top:4px;">Lütfen bekleyin</div>
                    </div>

                    <!-- Güncelleme bulundu -->
                    <div id="wyFoundState" style="display:none;text-align:center;padding:30px 20px;">
                        <div style="font-size:48px;margin-bottom:12px;">🎉</div>
                        <div style="font-size:15px;color:#e65100;font-weight:700;margin-bottom:4px;">Yeni Güncelleme Mevcut!</div>
                        <div id="wyVersionInfo" style="font-size:13px;color:#555;margin-bottom:20px;"></div>
                        <button type="button" id="wyInstallBtn" onclick="wyRunUpdate()" style="background:linear-gradient(135deg,#ff6f00,#ff8f00);color:#fff;padding:14px 40px;border-radius:10px;border:none;font-weight:700;font-size:15px;cursor:pointer;box-shadow:0 4px 15px rgba(255,111,0,0.3);transition:transform 0.2s;">
                            ⬆️ Güncelle
                        </button>
                    </div>

                    <!-- Güncel - güncelleme yok -->
                    <div id="wyUpToDateState" style="display:none;text-align:center;padding:30px 20px;">
                        <div style="font-size:48px;margin-bottom:12px;">✅</div>
                        <div style="font-size:15px;color:#2e7d32;font-weight:700;">Eklenti Güncel!</div>
                        <div id="wyCurrentInfo" style="font-size:13px;color:#555;margin-top:4px;"></div>
                    </div>

                    <!-- İlerleme barı -->
                    <div id="wyProgressState" style="display:none;padding:30px 20px;">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
                            <span style="font-size:28px;" id="wyUpdateIcon">⏳</span>
                            <div>
                                <div style="font-size:16px;font-weight:700;color:#333;" id="wyUpdateTitle">Güncelleme Başlatılıyor...</div>
                                <div style="font-size:12px;color:#999;" id="wyUpdateSub">Lütfen bekleyin, sayfa kapatmayın</div>
                            </div>
                        </div>
                        <div style="background:#f0f0f0;border-radius:10px;height:22px;overflow:hidden;margin-bottom:20px;">
                            <div id="wyProgressBar" style="background:linear-gradient(90deg,#ff6f00,#ff8f00);height:100%;width:0%;border-radius:10px;transition:width 0.5s ease;display:flex;align-items:center;justify-content:center;">
                                <span style="color:#fff;font-size:11px;font-weight:700;" id="wyProgressText">0%</span>
                            </div>
                        </div>
                        <div id="wyUpdateSteps" style="font-size:13px;color:#555;line-height:2;"></div>
                    </div>

                    <!-- Hata durumu -->
                    <div id="wyErrorState" style="display:none;text-align:center;padding:30px 20px;">
                        <div style="font-size:48px;margin-bottom:12px;">❌</div>
                        <div style="font-size:15px;color:#c62828;font-weight:700;">Bağlantı Hatası</div>
                        <div id="wyErrorMsg" style="font-size:13px;color:#555;margin-top:4px;margin-bottom:20px;"></div>
                        <button type="button" onclick="wyCheckAndUpdate()" class="webyaz-btn webyaz-btn-primary" style="padding:10px 24px;">
                            🔄 Tekrar Dene
                        </button>
                    </div>
                </div>
            </div>

            <style>
            @keyframes wyPulse { 0%,100% { opacity:1; } 50% { opacity:0.4; } }
            #wyInstallBtn:hover { transform:scale(1.05); }
            </style>

        </div>

        <script>
        function wyShowState(stateId) {
            ['wyIdleState','wyCheckingState','wyFoundState','wyUpToDateState','wyProgressState','wyErrorState'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'none';
            });
            var target = document.getElementById(stateId);
            if (target) target.style.display = 'block';
            // Üstteki banner'ı gizle
            var banner = document.getElementById('wyUpdateBanner');
            if (banner && stateId !== 'wyIdleState') banner.style.display = 'none';
        }

        function wyCheckAndUpdate() {
            wyShowState('wyCheckingState');

            jQuery.post(ajaxurl, {
                action: 'webyaz_force_update_check',
                nonce: '<?php echo wp_create_nonce('webyaz_force_check'); ?>'
            }, function(r) {
                if (r.success) {
                    var d = r.data;
                    if (d.has_update) {
                        document.getElementById('wyVersionInfo').innerHTML = 'v' + d.current + ' → <strong>v' + d.remote + '</strong>';
                        wyShowState('wyFoundState');
                    } else {
                        document.getElementById('wyCurrentInfo').textContent = 'Sürüm: v' + d.current + (d.remote ? ' | Sunucu: v' + d.remote : '');
                        wyShowState('wyUpToDateState');
                    }
                } else {
                    document.getElementById('wyErrorMsg').textContent = r.data || 'Sunucuya ulaşılamadı.';
                    wyShowState('wyErrorState');
                }
            }).fail(function() {
                document.getElementById('wyErrorMsg').textContent = 'Bağlantı hatası oluştu.';
                wyShowState('wyErrorState');
            });
        }

        function wyRunUpdate() {
            wyShowState('wyProgressState');

            // Sayfa yenileme koruması
            window._wyUpdating = true;
            window.addEventListener('beforeunload', wyPreventLeave);

            var bar = document.getElementById('wyProgressBar');
            var barText = document.getElementById('wyProgressText');
            var icon = document.getElementById('wyUpdateIcon');
            var title = document.getElementById('wyUpdateTitle');
            var sub = document.getElementById('wyUpdateSub');
            var steps = document.getElementById('wyUpdateSteps');
            steps.innerHTML = '';

            var updateSteps = [
                {pct: 10, text: '📥 Güncelleme paketi indiriliyor...'},
                {pct: 30, text: '📦 Paket açılıyor...'},
                {pct: 50, text: '🔧 Güncel sürüm kuruluyor...'},
                {pct: 70, text: '🗑️ Eski sürüm kaldırılıyor...'},
                {pct: 85, text: '⚙️ Eklenti etkinleştiriliyor...'},
            ];

            var stepIdx = 0;
            function showStep() {
                if (stepIdx < updateSteps.length) {
                    var s = updateSteps[stepIdx];
                    bar.style.width = s.pct + '%';
                    barText.textContent = s.pct + '%';
                    steps.innerHTML += '<div style="padding:3px 0;">⏳ ' + s.text + '</div>';
                    stepIdx++;
                    setTimeout(showStep, 600);
                }
            }
            showStep();

            jQuery.post(ajaxurl, {
                action: 'webyaz_run_update',
                nonce: '<?php echo wp_create_nonce('webyaz_run_update'); ?>'
            }, function(r) {
                window._wyUpdating = false;
                window.removeEventListener('beforeunload', wyPreventLeave);
                if (r.success) {
                    bar.style.width = '100%';
                    barText.textContent = '100%';
                    bar.style.background = 'linear-gradient(90deg,#2e7d32,#4caf50)';
                    icon.textContent = '✅';
                    title.textContent = 'Güncelleme Tamamlandı!';
                    title.style.color = '#2e7d32';
                    sub.textContent = r.data.version ? 'v' + r.data.version + ' sürümüne güncellendi' : 'Başarıyla güncellendi';
                    steps.innerHTML += '<div style="padding:3px 0;color:#2e7d32;font-weight:600;">✅ Eklenti güncellendi ve etkinleştirildi.</div>';
                } else {
                    bar.style.width = '100%';
                    bar.style.background = '#f44336';
                    barText.textContent = 'Hata';
                    icon.textContent = '❌';
                    title.textContent = 'Güncelleme Başarısız';
                    title.style.color = '#c62828';
                    sub.textContent = r.data || 'Bilinmeyen hata';
                    steps.innerHTML += '<div style="padding:3px 0;color:#c62828;">❌ ' + (r.data || 'Güncelleme sırasında hata oluştu.') + '</div>';
                }
            }).fail(function() {
                window._wyUpdating = false;
                window.removeEventListener('beforeunload', wyPreventLeave);
                bar.style.width = '100%';
                bar.style.background = '#f44336';
                barText.textContent = 'Hata';
                icon.textContent = '❌';
                title.textContent = 'Bağlantı Hatası';
                title.style.color = '#c62828';
                sub.textContent = 'Sunucuya ulaşılamadı';
            });
        }

        function wyPreventLeave(e) {
            if (window._wyUpdating) {
                e.preventDefault();
                e.returnValue = 'Güncelleme devam ediyor! Sayfayı kapatırsanız eklenti bozulabilir.';
                return e.returnValue;
            }
        }
        </script>
        <?php
    }

    /* ─── AJAX: Zorla Kontrol ─── */
    public function ajax_force_check() {
        check_ajax_referer('webyaz_force_check', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Yetkiniz yok.');

        delete_transient($this->cache_key);
        $remote = $this->get_remote_data(true);

        if (!$remote || !isset($remote->version)) {
            wp_send_json_error('Sunucuya ulaşılamadı veya JSON dosyası geçersiz. URL: ' . $this->get_update_url());
        }

        $current = defined('WEBYAZ_VERSION') ? WEBYAZ_VERSION : '0.0.0';
        wp_send_json_success(array(
            'current'    => $current,
            'remote'     => $remote->version,
            'has_update' => version_compare($remote->version, $current, '>'),
        ));
    }

    /* ─── AJAX: Güncelleme Çalıştır ─── */
    public function ajax_run_update() {
        check_ajax_referer('webyaz_run_update', 'nonce');
        if (!current_user_can('update_plugins')) wp_send_json_error('Güncelleme yetkiniz yok.');

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/update.php';

        // Cache temizle — WordPress taze download URL alsın
        delete_transient($this->cache_key);
        delete_site_transient('update_plugins');
        wp_update_plugins();

        // Sessiz upgrader — çıktı üretmez
        $skin = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);

        $result = $upgrader->upgrade($this->plugin_file);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        if ($result === false) {
            // Transient'tan detaylı hata bilgisi almayı dene
            $update_plugins = get_site_transient('update_plugins');
            $debug_info = '';
            if (isset($update_plugins->response[$this->plugin_file])) {
                $pkg = $update_plugins->response[$this->plugin_file];
                $debug_info = ' URL: ' . (isset($pkg->package) ? $pkg->package : 'yok');
            } else {
                $debug_info = ' Güncelleme transient\'ında eklenti bulunamadı.';
            }
            wp_send_json_error('Güncelleme başarısız oldu. Paket indirilemedi veya kurulamadı.' . $debug_info);
        }

        // Eklentiyi tekrar etkinleştir
        activate_plugin($this->plugin_file);

        // Cache temizle
        delete_transient($this->cache_key);
        delete_site_transient('update_plugins');

        // Yeni versiyonu oku
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->plugin_file);
        $new_version = isset($plugin_data['Version']) ? $plugin_data['Version'] : '';

        wp_send_json_success(array(
            'version' => $new_version,
            'message' => 'Güncelleme başarıyla tamamlandı.',
        ));
    }

    /**
     * Güncelleme URL'sini al (admin ayarlarından veya sabit)
     */
    private function get_update_url() {
        $opts = self::get_opts();
        return !empty($opts['update_url']) ? $opts['update_url'] : $this->update_url;
    }

    /**
     * Kontrol aralığını al (saniye)
     */
    private function get_interval() {
        $opts = self::get_opts();
        $hours = intval($opts['check_interval']);
        if ($hours < 1) $hours = 12;
        return $hours * 3600;
    }

    /**
     * Uzak sunucudan güncelleme bilgisini al (cache'li)
     */
    private function get_remote_data($force = false) {
        $this->cache_seconds = $this->get_interval();

        if (!$force) {
            $cached = get_transient($this->cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        $url = $this->get_update_url();
        $response = wp_remote_get($url, array(
            'timeout'   => 15,
            'sslverify' => false,
            'headers'   => array(
                'Accept'        => 'application/json',
                'Cache-Control' => 'no-cache',
            ),
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            set_transient($this->cache_key, null, 3600);
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (empty($data) || !isset($data->version)) {
            set_transient($this->cache_key, null, 3600);
            return null;
        }

        set_transient($this->cache_key, $data, $this->cache_seconds);
        return $data;
    }

    /**
     * WordPress güncelleme check'ine bağlan
     */
    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote = $this->get_remote_data();
        if (!$remote || !isset($remote->version)) {
            return $transient;
        }

        $current_version = defined('WEBYAZ_VERSION') ? WEBYAZ_VERSION : '0.0.0';

        if (version_compare($remote->version, $current_version, '>')) {
            $plugin_data = new stdClass();
            $plugin_data->slug        = $this->plugin_slug;
            $plugin_data->plugin      = $this->plugin_file;
            $plugin_data->new_version = $remote->version;
            $plugin_data->url         = isset($remote->homepage) ? $remote->homepage : '';
            $plugin_data->package     = isset($remote->download_url) ? $remote->download_url : '';
            $plugin_data->tested      = isset($remote->tested) ? $remote->tested : '';
            $plugin_data->requires    = isset($remote->requires) ? $remote->requires : '';
            $plugin_data->requires_php = isset($remote->requires_php) ? $remote->requires_php : '';

            if (isset($remote->icons)) {
                $plugin_data->icons = (array)$remote->icons;
            }
            if (isset($remote->banners)) {
                $plugin_data->banners = (array)$remote->banners;
            }

            $transient->response[$this->plugin_file] = $plugin_data;
        }

        return $transient;
    }

    /**
     * Eklenti bilgi popup'ı (Sürüm detaylarını göster)
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || !isset($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        $remote = $this->get_remote_data();
        if (!$remote) {
            return $result;
        }

        $info = new stdClass();
        $info->name          = isset($remote->name) ? $remote->name : 'Webyaz Otomasyon';
        $info->slug          = $this->plugin_slug;
        $info->version       = $remote->version;
        $info->author        = isset($remote->author) ? $remote->author : 'Webyaz';
        $info->homepage      = isset($remote->homepage) ? $remote->homepage : '';
        $info->requires      = isset($remote->requires) ? $remote->requires : '';
        $info->tested        = isset($remote->tested) ? $remote->tested : '';
        $info->requires_php  = isset($remote->requires_php) ? $remote->requires_php : '';
        $info->download_link = isset($remote->download_url) ? $remote->download_url : '';
        $info->last_updated  = isset($remote->last_updated) ? $remote->last_updated : '';

        if (isset($remote->sections)) {
            $info->sections = (array)$remote->sections;
        } else {
            $info->sections = array(
                'description' => 'Flatsome tema uyumlu e-ticaret eklentisi.',
                'changelog'   => '<p>Değişiklik bilgisi mevcut değil.</p>',
            );
        }

        if (isset($remote->banners)) {
            $info->banners = (array)$remote->banners;
        }

        return $info;
    }

    /**
     * Güncelleme sonrası cache temizle
     */
    public function clear_cache($upgrader, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            $plugins = isset($options['plugins']) ? $options['plugins'] : array();
            if (in_array($this->plugin_file, $plugins)) {
                delete_transient($this->cache_key);
            }
        }
    }

    /**
     * Admin panelinde genel güncelleme bildirimi
     */
    public function admin_update_notice() {
        if (!current_user_can('update_plugins')) return;

        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'webyaz') === false) return;

        // Güncelleme sayfasında zaten banner var, tekrar gösterme
        if (strpos($screen->id, 'webyaz-updater') !== false) return;

        $remote = $this->get_remote_data();
        if (!$remote || !isset($remote->version)) return;

        $current = defined('WEBYAZ_VERSION') ? WEBYAZ_VERSION : '0.0.0';
        if (!version_compare($remote->version, $current, '>')) return;

        $updater_url = admin_url('admin.php?page=webyaz-updater');
        ?>
        <div class="notice" style="border:none;padding:0;margin:5px 0 15px;background:transparent;">
            <div style="background:linear-gradient(135deg,#1a237e,#283593);border-radius:12px;padding:20px 24px;color:#fff;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
                <div style="display:flex;align-items:center;gap:14px;flex:1;min-width:280px;">
                    <div style="background:rgba(255,255,255,0.15);border-radius:12px;padding:12px;line-height:1;">
                        <span style="font-size:28px;">🚀</span>
                    </div>
                    <div>
                        <div style="font-size:15px;font-weight:700;margin-bottom:3px;">
                            Webyaz Otomasyon v<?php echo esc_html($remote->version); ?> Yayınlandı!
                        </div>
                        <div style="font-size:12px;opacity:0.85;">
                            Mevcut: v<?php echo esc_html($current); ?> → Yeni: v<?php echo esc_html($remote->version); ?>
                        </div>
                    </div>
                </div>
                <a href="<?php echo esc_url($updater_url); ?>" style="background:#fff;color:#1a237e;padding:10px 22px;border-radius:8px;text-decoration:none;font-weight:700;font-size:13px;">
                    ⬆️ Güncelle
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Webyaz Dashboard'da güncelleme banner'ı
     */
    public function dashboard_update_banner() {
        if (!current_user_can('update_plugins')) return;

        $remote = $this->get_remote_data();
        if (!$remote || !isset($remote->version)) return;

        $current = defined('WEBYAZ_VERSION') ? WEBYAZ_VERSION : '0.0.0';
        if (!version_compare($remote->version, $current, '>')) return;

        $updater_url = admin_url('admin.php?page=webyaz-updater');
        ?>
        <div style="background:linear-gradient(135deg,#ff6f00,#ff8f00);border-radius:14px;padding:20px 24px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;box-shadow:0 4px 15px rgba(255,111,0,0.3);">
            <div style="display:flex;align-items:center;gap:12px;color:#fff;">
                <span style="font-size:32px;">🔔</span>
                <div>
                    <div style="font-size:16px;font-weight:700;">Güncelleme Mevcut!</div>
                    <div style="font-size:13px;opacity:0.9;">
                        v<?php echo esc_html($current); ?> → v<?php echo esc_html($remote->version); ?> sürümüne güncelleyebilirsiniz.
                    </div>
                </div>
            </div>
            <a href="<?php echo esc_url($updater_url); ?>" style="background:#fff;color:#e65100;padding:11px 24px;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                ⬆️ Güncelle
            </a>
        </div>
        <?php
    }

    /**
     * Cache'i zorla temizle
     */
    public static function force_check() {
        delete_transient('webyaz_update_data');
        delete_site_transient('update_plugins');
    }
}

new Webyaz_Updater();
