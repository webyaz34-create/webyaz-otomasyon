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
                            <?php if (isset($remote->sections) && isset($remote->sections->changelog)): ?>
                                <div style="font-size:11px;opacity:0.75;margin-top:4px;"><?php echo wp_kses_post($remote->sections->changelog); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="button" id="wyUpdateBtn" onclick="wyRunUpdate()" style="background:#fff;color:#e65100;padding:11px 24px;border-radius:8px;border:none;font-weight:700;font-size:14px;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                        ⬆️ Şimdi Güncelle
                    </button>
                </div>

                <!-- İlerleme Barı -->
                <div id="wyUpdateProgress" style="display:none;background:#fff;border:1px solid #e0e0e0;border-radius:14px;padding:24px;margin-bottom:20px;box-shadow:0 4px 20px rgba(0,0,0,0.06);">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                        <span style="font-size:28px;" id="wyUpdateIcon">⏳</span>
                        <div>
                            <div style="font-size:16px;font-weight:700;color:#333;" id="wyUpdateTitle">Güncelleme Başlatılıyor...</div>
                            <div style="font-size:12px;color:#999;" id="wyUpdateSub">Lütfen bekleyin, sayfa kapatmayın</div>
                        </div>
                    </div>
                    <!-- Bar -->
                    <div style="background:#f0f0f0;border-radius:10px;height:20px;overflow:hidden;margin-bottom:16px;">
                        <div id="wyProgressBar" style="background:linear-gradient(90deg,#ff6f00,#ff8f00);height:100%;width:0%;border-radius:10px;transition:width 0.5s ease;display:flex;align-items:center;justify-content:center;">
                            <span style="color:#fff;font-size:11px;font-weight:700;" id="wyProgressText">0%</span>
                        </div>
                    </div>
                    <!-- Adımlar -->
                    <div id="wyUpdateSteps" style="font-size:13px;color:#555;line-height:2;"></div>
                </div>
            <?php endif; ?>

            <!-- Ayarlar -->
            <form method="post" action="options.php">
                <?php settings_fields('webyaz_updater_group'); ?>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Güncelleme Ayarları</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Güncelleme JSON URL'si</label>
                            <input type="url" name="webyaz_updater_opts[update_url]" value="<?php echo esc_attr($opts['update_url']); ?>" placeholder="<?php echo esc_attr($this->update_url); ?>" style="font-size:12px;">
                            <small style="color:#999;margin-top:4px;">Boş bırakırsanız varsayılan URL kullanılır</small>
                        </div>
                        <div class="webyaz-field">
                            <label>Kontrol Sıklığı (Saat)</label>
                            <select name="webyaz_updater_opts[check_interval]">
                                <option value="6" <?php selected($opts['check_interval'], '6'); ?>>6 saat</option>
                                <option value="12" <?php selected($opts['check_interval'], '12'); ?>>12 saat</option>
                                <option value="24" <?php selected($opts['check_interval'], '24'); ?>>24 saat</option>
                                <option value="48" <?php selected($opts['check_interval'], '48'); ?>>48 saat</option>
                            </select>
                        </div>
                    </div>
                </div>
                <?php submit_button('Kaydet'); ?>
            </form>

            <!-- Şimdi Kontrol Et -->
            <div class="webyaz-settings-section" style="margin-top:20px;">
                <h2 class="webyaz-section-title">Manuel Kontrol</h2>
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                    <div>
                        <div style="font-size:13px;color:#333;">Son kontrol: <strong id="wyLastCheck"><?php echo esc_html($last_check); ?></strong></div>
                        <div style="font-size:12px;color:#999;margin-top:2px;">Aktif URL: <code style="font-size:11px;background:#f5f5f5;padding:2px 6px;border-radius:4px;"><?php echo esc_html($this->get_update_url()); ?></code></div>
                    </div>
                    <button type="button" id="wyForceCheck" onclick="wyForceUpdateCheck()" class="webyaz-btn webyaz-btn-primary" style="padding:10px 22px;">
                        🔍 Şimdi Kontrol Et
                    </button>
                </div>
                <div id="wyCheckResult" style="margin-top:12px;display:none;"></div>
            </div>

            <!-- Kullanım Rehberi -->
            <div class="webyaz-settings-section" style="margin-top:20px;">
                <h2 class="webyaz-section-title">📘 Kullanım Rehberi</h2>

                <!-- Tab Başlıkları -->
                <div id="wyUpdaterTabs" style="display:flex;border-bottom:2px solid #e0e0e0;background:#fafafa;border-radius:8px 8px 0 0;overflow-x:auto;margin-bottom:0;">
                    <button type="button" class="wy-upd-tab wy-upd-tab-active" data-tab="upd-tab-setup" style="flex:1;min-width:0;padding:14px 10px;border:none;background:#fff;font-size:13px;font-weight:600;color:#1565c0;cursor:pointer;position:relative;border-bottom:3px solid #1565c0;display:flex;flex-direction:column;align-items:center;gap:4px;white-space:nowrap;">
                        <span style="font-size:18px;">⚙️</span> İlk Kurulum
                    </button>
                    <button type="button" class="wy-upd-tab" data-tab="upd-tab-publish" style="flex:1;min-width:0;padding:14px 10px;border:none;background:transparent;font-size:13px;font-weight:600;color:#666;cursor:pointer;position:relative;border-bottom:3px solid transparent;display:flex;flex-direction:column;align-items:center;gap:4px;white-space:nowrap;">
                        <span style="font-size:18px;">🚀</span> Güncelleme Yayınla
                    </button>
                    <button type="button" class="wy-upd-tab" data-tab="upd-tab-how" style="flex:1;min-width:0;padding:14px 10px;border:none;background:transparent;font-size:13px;font-weight:600;color:#666;cursor:pointer;position:relative;border-bottom:3px solid transparent;display:flex;flex-direction:column;align-items:center;gap:4px;white-space:nowrap;">
                        <span style="font-size:18px;">💡</span> Nasıl Çalışır?
                    </button>
                </div>

                <!-- Tab 1: İlk Kurulum -->
                <div id="upd-tab-setup" class="wy-upd-panel" style="padding:22px 25px;background:#fff;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px;">
                    <h3 style="margin:0 0 16px;font-size:16px;color:#1565c0;">⚙️ İlk Kurulum (Bir kere yapılır)</h3>

                    <div style="display:grid;grid-template-columns:1fr;gap:16px;">
                        <div style="background:#f8f9fa;border-radius:10px;padding:18px;border-left:4px solid #1565c0;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                                <span style="background:#1565c0;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;">1</span>
                                <strong style="font-size:14px;">GitHub Hesabı Aç</strong>
                            </div>
                            <p style="margin:0;font-size:13px;color:#555;">
                                👉 <a href="https://github.com/signup" target="_blank" style="color:#1565c0;font-weight:600;">github.com/signup</a> → E-posta, şifre girin → Hesap oluşturun
                            </p>
                        </div>

                        <div style="background:#f8f9fa;border-radius:10px;padding:18px;border-left:4px solid #1565c0;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                                <span style="background:#1565c0;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;">2</span>
                                <strong style="font-size:14px;">Repo Oluştur</strong>
                            </div>
                            <p style="margin:0;font-size:13px;color:#555;">
                                👉 <a href="https://github.com/new" target="_blank" style="color:#1565c0;font-weight:600;">github.com/new</a> →
                                Repository name: <code style="background:#e3f2fd;padding:2px 6px;border-radius:4px;">webyaz-otomasyon</code> →
                                <strong>Public</strong> seçili olsun →
                                ✅ "Add a README file" işaretle →
                                <strong>Create repository</strong> bas
                            </p>
                        </div>

                        <div style="background:#f8f9fa;border-radius:10px;padding:18px;border-left:4px solid #1565c0;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                                <span style="background:#1565c0;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;">3</span>
                                <strong style="font-size:14px;">JSON Dosyasını Yükle</strong>
                            </div>
                            <p style="margin:0;font-size:13px;color:#555;">
                                Repo sayfanızda: <strong>Add file → Upload files</strong> →
                                Bilgisayarınızdaki <code style="background:#e3f2fd;padding:2px 6px;border-radius:4px;">update-info.json</code> dosyasını sürükleyin →
                                <strong>Commit changes</strong> bas
                            </p>
                        </div>

                        <div style="background:#f8f9fa;border-radius:10px;padding:18px;border-left:4px solid #1565c0;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                                <span style="background:#1565c0;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;">4</span>
                                <strong style="font-size:14px;">URL'yi Buraya Yapıştır</strong>
                            </div>
                            <p style="margin:0;font-size:13px;color:#555;">
                                JSON URL'niz: <code style="background:#fff3e0;padding:3px 8px;border-radius:4px;font-size:12px;word-break:break-all;">https://raw.githubusercontent.com/KULLANICI_ADIN/webyaz-otomasyon/main/update-info.json</code><br>
                                <span style="margin-top:4px;display:inline-block;">↑ Yukarıdaki "Güncelleme JSON URL'si" alanına yapıştırıp <strong>Kaydet</strong> bas</span>
                            </p>
                        </div>

                        <div style="background:#e8f5e9;border-radius:10px;padding:14px 18px;border-left:4px solid #4caf50;">
                            <strong style="color:#2e7d32;">✅ Kurulum tamamlandı!</strong>
                            <span style="font-size:13px;color:#555;"> — "Şimdi Kontrol Et" butonuna basarak test edin.</span>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Güncelleme Yayınla -->
                <div id="upd-tab-publish" class="wy-upd-panel" style="padding:22px 25px;background:#fff;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px;display:none;">
                    <h3 style="margin:0 0 16px;font-size:16px;color:#e65100;">🚀 Güncelleme Yayınlama (Her seferinde)</h3>

                    <div style="display:grid;grid-template-columns:1fr;gap:14px;">
                        <div style="background:#fff3e0;border-radius:10px;padding:16px;border-left:4px solid #e65100;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                                <span style="background:#e65100;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;">1</span>
                                <strong style="font-size:14px;">Versiyonu Artır</strong>
                            </div>
                            <p style="margin:0;font-size:13px;color:#555;">
                                <code>webyaz-otomasyon.php</code> dosyasında:
                                <code>Version: 4.0.0</code> → <code>4.1.0</code> ve
                                <code>WEBYAZ_VERSION</code> → <code>'4.1.0'</code>
                            </p>
                        </div>

                        <div style="background:#fff3e0;border-radius:10px;padding:16px;border-left:4px solid #e65100;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                                <span style="background:#e65100;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;">2</span>
                                <strong style="font-size:14px;">ZIP'le</strong>
                            </div>
                            <p style="margin:0;font-size:13px;color:#555;">
                                <code>webyaz-otomasyon</code> klasörünü sağ tık → <strong>Sıkıştırılmış klasöre gönder</strong>
                            </p>
                        </div>

                        <div style="background:#fff3e0;border-radius:10px;padding:16px;border-left:4px solid #e65100;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                                <span style="background:#e65100;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;">3</span>
                                <strong style="font-size:14px;">GitHub'da Release Oluştur</strong>
                            </div>
                            <p style="margin:0;font-size:13px;color:#555;">
                                👉 <code style="font-size:11px;">github.com/KULLANICI_ADIN/webyaz-otomasyon/releases/new</code><br>
                                Tag: <code>v4.1.0</code> → ZIP'i sürükle → <strong>Publish release</strong> → ZIP linkini kopyala
                            </p>
                        </div>

                        <div style="background:#fff3e0;border-radius:10px;padding:16px;border-left:4px solid #e65100;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                                <span style="background:#e65100;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;">4</span>
                                <strong style="font-size:14px;">JSON Dosyasını Güncelle</strong>
                            </div>
                            <p style="margin:0;font-size:13px;color:#555;">
                                👉 GitHub'da <code>update-info.json</code> dosyasına tıkla → ✏️ kalem ikonuna bas →
                                <code>"version"</code>, <code>"download_url"</code>, <code>"changelog"</code> güncelle →
                                <strong>Commit changes</strong>
                            </p>
                        </div>

                        <div style="background:#e8f5e9;border-radius:10px;padding:14px 18px;border-left:4px solid #4caf50;">
                            <strong style="color:#2e7d32;">✅ Bitti!</strong>
                            <span style="font-size:13px;color:#555;"> — Müşteri siteleri otomatik bildirim alacak.</span>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Nasıl Çalışır -->
                <div id="upd-tab-how" class="wy-upd-panel" style="padding:22px 25px;background:#fff;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px;display:none;">
                    <h3 style="margin:0 0 16px;font-size:16px;color:#4527a0;">💡 Sistem Nasıl Çalışır?</h3>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:20px;">
                        <div style="background:#ede7f6;border-radius:10px;padding:16px;text-align:center;">
                            <div style="font-size:28px;margin-bottom:6px;">📝</div>
                            <div style="font-size:13px;font-weight:600;color:#4527a0;">1. Siz güncellersiniz</div>
                            <div style="font-size:11px;color:#666;margin-top:4px;">GitHub'daki JSON dosyasında versiyonu artırırsınız</div>
                        </div>
                        <div style="background:#ede7f6;border-radius:10px;padding:16px;text-align:center;">
                            <div style="font-size:28px;margin-bottom:6px;">🔍</div>
                            <div style="font-size:13px;font-weight:600;color:#4527a0;">2. Siteler kontrol eder</div>
                            <div style="font-size:11px;color:#666;margin-top:4px;">Her site belirlenen aralıkta JSON'u kontrol eder</div>
                        </div>
                        <div style="background:#ede7f6;border-radius:10px;padding:16px;text-align:center;">
                            <div style="font-size:28px;margin-bottom:6px;">🔔</div>
                            <div style="font-size:13px;font-weight:600;color:#4527a0;">3. Bildirim çıkar</div>
                            <div style="font-size:11px;color:#666;margin-top:4px;">Admin panelinde güncelleme bildirimi gösterilir</div>
                        </div>
                        <div style="background:#ede7f6;border-radius:10px;padding:16px;text-align:center;">
                            <div style="font-size:28px;margin-bottom:6px;">⬆️</div>
                            <div style="font-size:13px;font-weight:600;color:#4527a0;">4. Tek tık güncelle</div>
                            <div style="font-size:11px;color:#666;margin-top:4px;">Kullanıcı "Güncelle" butonuna basar, otomatik güncellenir</div>
                        </div>
                    </div>

                    <div style="background:#f5f5f5;border-radius:8px;padding:14px 18px;font-size:13px;color:#555;line-height:1.8;">
                        <strong>📌 Önemli Notlar:</strong>
                        <ul style="margin:6px 0 0 16px;padding:0;">
                            <li>Bu modülün toggle'ı <strong>kapalıysa</strong> site güncelleme kontrolü yapmaz</li>
                            <li>JSON URL hatalıysa bildirim gelmez — "Şimdi Kontrol Et" ile test edin</li>
                            <li>ZIP dosyasının içinde <code>webyaz-otomasyon</code> klasörü olmalı</li>
                            <li>Güncelleme sonrası tüm modül ayarları korunur</li>
                        </ul>
                    </div>
                </div>
            </div>

            <script>
            (function(){
                var tabs = document.querySelectorAll('.wy-upd-tab');
                var panels = document.querySelectorAll('.wy-upd-panel');
                tabs.forEach(function(tab){
                    tab.addEventListener('click', function(){
                        tabs.forEach(function(t){
                            t.style.background = 'transparent';
                            t.style.color = '#666';
                            t.style.borderBottom = '3px solid transparent';
                        });
                        panels.forEach(function(p){ p.style.display = 'none'; });
                        tab.style.background = '#fff';
                        tab.style.color = '#1565c0';
                        tab.style.borderBottom = '3px solid #1565c0';
                        var target = document.getElementById(tab.dataset.tab);
                        if(target) target.style.display = 'block';
                    });
                });
            })();
            </script>
        </div>

        <script>
        function wyForceUpdateCheck() {
            var btn = document.getElementById('wyForceCheck');
            var result = document.getElementById('wyCheckResult');
            btn.disabled = true;
            btn.textContent = '⏳ Kontrol ediliyor...';
            result.style.display = 'none';

            jQuery.post(ajaxurl, {
                action: 'webyaz_force_update_check',
                nonce: '<?php echo wp_create_nonce('webyaz_force_check'); ?>'
            }, function(r) {
                btn.disabled = false;
                btn.textContent = '🔍 Şimdi Kontrol Et';
                result.style.display = 'block';

                if (r.success) {
                    var d = r.data;
                    if (d.has_update) {
                        result.innerHTML = '<div style="background:#fff3e0;border:1px solid #ffe082;border-radius:8px;padding:12px 16px;">' +
                            '<strong style="color:#e65100;">⬆️ Güncelleme mevcut!</strong><br>' +
                            '<span style="font-size:13px;color:#555;">Mevcut: v' + d.current + ' → Yeni: v' + d.remote + '</span>' +
                            '</div>';
                    } else {
                        result.innerHTML = '<div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:8px;padding:12px 16px;">' +
                            '<strong style="color:#2e7d32;">✅ Eklenti güncel!</strong><br>' +
                            '<span style="font-size:13px;color:#555;">Mevcut sürüm: v' + d.current + (d.remote ? ' | Sunucu: v' + d.remote : '') + '</span>' +
                            '</div>';
                    }
                    document.getElementById('wyLastCheck').textContent = 'Az önce kontrol edildi';
                } else {
                    result.innerHTML = '<div style="background:#fce4ec;border:1px solid #ef9a9a;border-radius:8px;padding:12px 16px;">' +
                        '<strong style="color:#c62828;">❌ Bağlantı hatası!</strong><br>' +
                        '<span style="font-size:13px;color:#555;">' + (r.data || 'Sunucuya ulaşılamadı.') + '</span>' +
                        '</div>';
                }
            }).fail(function() {
                btn.disabled = false;
                btn.textContent = '🔍 Şimdi Kontrol Et';
                result.style.display = 'block';
                result.innerHTML = '<div style="background:#fce4ec;border:1px solid #ef9a9a;border-radius:8px;padding:12px 16px;"><strong style="color:#c62828;">❌ AJAX hatası</strong></div>';
            });
        }

        function wyRunUpdate() {
            var banner = document.getElementById('wyUpdateBanner');
            var progress = document.getElementById('wyUpdateProgress');
            var bar = document.getElementById('wyProgressBar');
            var barText = document.getElementById('wyProgressText');
            var icon = document.getElementById('wyUpdateIcon');
            var title = document.getElementById('wyUpdateTitle');
            var sub = document.getElementById('wyUpdateSub');
            var steps = document.getElementById('wyUpdateSteps');

            banner.style.display = 'none';
            progress.style.display = 'block';

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
                if (r.success) {
                    bar.style.width = '100%';
                    barText.textContent = '100%';
                    bar.style.background = 'linear-gradient(90deg,#2e7d32,#4caf50)';
                    icon.textContent = '✅';
                    title.textContent = 'Güncelleme Tamamlandı!';
                    title.style.color = '#2e7d32';
                    sub.textContent = r.data.version ? 'v' + r.data.version + ' sürümüne güncellendi' : 'Başarıyla güncellendi';
                    steps.innerHTML += '<div style="padding:3px 0;color:#2e7d32;font-weight:600;">✅ Eklenti güncellendi ve etkinleştirildi.</div>';
                    setTimeout(function(){
                        steps.innerHTML += '<div style="padding:8px 0;"><a href="' + location.href + '" style="background:#2e7d32;color:#fff;padding:8px 20px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600;">🔄 Sayfayı Yenile</a></div>';
                    }, 500);
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
                bar.style.width = '100%';
                bar.style.background = '#f44336';
                barText.textContent = 'Hata';
                icon.textContent = '❌';
                title.textContent = 'Bağlantı Hatası';
                title.style.color = '#c62828';
                sub.textContent = 'Sunucuya ulaşılamadı';
            });
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

        // Sessiz upgrader — çıktı üretmez
        $skin = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);

        $result = $upgrader->upgrade($this->plugin_file);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        if ($result === false) {
            wp_send_json_error('Güncelleme başarısız oldu. Paket indirilemedi veya kurulamadı.');
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

        $remote = $this->get_remote_data();
        if (!$remote || !isset($remote->version)) return;

        $current = defined('WEBYAZ_VERSION') ? WEBYAZ_VERSION : '0.0.0';
        if (!version_compare($remote->version, $current, '>')) return;

        $update_url = wp_nonce_url(
            self_admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($this->plugin_file)),
            'upgrade-plugin_' . $this->plugin_file
        );
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
                <a href="<?php echo esc_url($update_url); ?>" style="background:#fff;color:#1a237e;padding:10px 22px;border-radius:8px;text-decoration:none;font-weight:700;font-size:13px;">
                    ⬆️ Şimdi Güncelle
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

        $update_url = wp_nonce_url(
            self_admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($this->plugin_file)),
            'upgrade-plugin_' . $this->plugin_file
        );
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
            <a href="<?php echo esc_url($update_url); ?>" style="background:#fff;color:#e65100;padding:11px 24px;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
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
