<?php
if (!defined('ABSPATH')) exit;

if (!defined('WBAK_VERSION')) define('WBAK_VERSION', '1.0.0');
if (!defined('WBAK_BACKUP_DIR')) define('WBAK_BACKUP_DIR', WP_CONTENT_DIR . '/webyaz-backups/');

// Export/Import dosyalari yoksa bile menu gosterelim ama uyari verelim
$wbak_export_file = __DIR__ . '/class-webyaz-backup-export.php';
$wbak_import_file = __DIR__ . '/class-webyaz-backup-import.php';
$wbak_files_ok = true;
if (file_exists($wbak_export_file)) {
    require_once $wbak_export_file;
} else {
    $wbak_files_ok = false;
}
if (file_exists($wbak_import_file)) {
    require_once $wbak_import_file;
} else {
    $wbak_files_ok = false;
}

class Webyaz_Backup
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_submenu'), 20);
        add_action('admin_post_wbak_export', array($this, 'handle_export'));
        add_action('admin_post_wbak_import', array($this, 'handle_import'));
        add_action('admin_post_wbak_delete', array($this, 'handle_delete'));
        add_action('admin_init', array($this, 'handle_download'));
        add_action('wp_ajax_wbak_step', array($this, 'ajax_backup_step'));
        add_action('wp_ajax_wbak_restore_step', array($this, 'ajax_restore_step'));

        // Otomatik yedek cron hook
        add_action('wbak_auto_backup_cron', array($this, 'run_auto_backup'));
        add_action('admin_post_wbak_save_schedule', array($this, 'handle_save_schedule'));

        // Cron zamanlama kontrol - aktifse ve schedule yoksa ekle
        if (get_option('wbak_auto_enabled', '0') === '1' && !wp_next_scheduled('wbak_auto_backup_cron')) {
            $this->schedule_backup();
        }

        // Backup dizinini olustur
        if (!file_exists(WBAK_BACKUP_DIR)) {
            wp_mkdir_p(WBAK_BACKUP_DIR);
            file_put_contents(WBAK_BACKUP_DIR . '.htaccess', 'deny from all');
            file_put_contents(WBAK_BACKUP_DIR . 'index.php', '<?php // Silence is golden.');
        }
    }

    public function ajax_backup_step()
    {
        check_ajax_referer('wbak_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Yetki yok');

        $step = sanitize_text_field($_POST['step'] ?? '');
        $context = isset($_POST['context']) ? json_decode(stripslashes($_POST['context']), true) : array();

        $result = Webyaz_Backup_Export::run_step($step, $context);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    public function ajax_restore_step()
    {
        $step = sanitize_text_field($_POST['step'] ?? '');
        $context = isset($_POST['context']) ? json_decode(stripslashes($_POST['context']), true) : array();

        // Ilk adimda normal WordPress yetkilendirme kullan ve token olustur
        if ($step === 'extract') {
            check_ajax_referer('wbak_ajax_nonce', 'nonce');
            if (!current_user_can('manage_options')) wp_send_json_error('Yetki yok');

            // Guvenlık tokeni olustur ve dosyaya kaydet
            $token = wp_generate_password(64, false);
            $token_file = WBAK_BACKUP_DIR . '.restore_token';
            file_put_contents($token_file, $token . '|' . time());
            $context['restore_token'] = $token;
        } else {
            // Sonraki adimlarda: veritabani degismis olabilir, token ile dogrula
            $token_file = WBAK_BACKUP_DIR . '.restore_token';
            $client_token = $context['restore_token'] ?? '';

            if (empty($client_token) || !file_exists($token_file)) {
                wp_send_json_error('Gecersiz geri yukleme oturumu. Lutfen tekrar baslatin.');
                return;
            }

            $stored = file_get_contents($token_file);
            $parts = explode('|', $stored, 2);
            $stored_token = $parts[0];
            $stored_time = intval($parts[1] ?? 0);

            // Token eslesmesi ve 30 dakika zaman asimi kontrolu
            if (!hash_equals($stored_token, $client_token) || (time() - $stored_time) > 1800) {
                wp_send_json_error('Geri yukleme oturumu suresi doldu. Lutfen tekrar baslatin.');
                return;
            }
        }

        $result = Webyaz_Backup_Import::run_restore_step($step, $context);

        if (is_wp_error($result)) {
            // Hata durumunda token dosyasini temizle
            $tf = WBAK_BACKUP_DIR . '.restore_token';
            if (file_exists($tf)) @unlink($tf);
            wp_send_json_error($result->get_error_message());
        }

        // Tamamlandiginda token dosyasini temizle
        if (isset($result['status']) && $result['status'] === 'done') {
            $tf = WBAK_BACKUP_DIR . '.restore_token';
            if (file_exists($tf)) @unlink($tf);
        }

        wp_send_json_success($result);
    }

    public function add_submenu()
    {
        add_submenu_page(
            'webyaz-dashboard',
            'Yedek & Geri Yukle',
            'Yedek & Geri Yukle',
            'manage_options',
            'webyaz-backup',
            array($this, 'render_page')
        );
    }

    private function format_size($bytes)
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }

    public function render_page()
    {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'backup';
        $message = isset($_GET['wbak_msg']) ? sanitize_text_field($_GET['wbak_msg']) : '';
        $error = isset($_GET['wbak_err']) ? sanitize_text_field($_GET['wbak_err']) : '';
        $backups = Webyaz_Backup_Import::get_available_backups();

        $primary = '#446084';
        $secondary = '#d26e4b';
        if (function_exists('get_theme_mod')) {
            $p = get_theme_mod('color_primary', '');
            $s = get_theme_mod('color_secondary', '');
            if ($p) $primary = $p;
            if ($s) $secondary = $s;
        }
?>
        <style>
            .wbak-wrap {
                max-width: 900px;
                margin: 20px auto;
                font-family: 'Roboto', Arial, sans-serif;
            }

            .wbak-header {
                background: linear-gradient(135deg, <?php echo esc_attr($primary); ?>, <?php echo esc_attr($secondary); ?>);
                color: #fff;
                padding: 30px 36px;
                border-radius: 16px 16px 0 0;
            }

            .wbak-header h1 {
                margin: 0 0 6px;
                font-size: 26px;
                font-weight: 700;
            }

            .wbak-header p {
                margin: 0;
                opacity: 0.7;
                font-size: 14px;
            }

            .wbak-tabs {
                display: flex;
                background: <?php echo esc_attr($primary); ?>;
                border-radius: 0 0 16px 16px;
                overflow: hidden;
            }

            .wbak-tab {
                flex: 1;
                text-align: center;
                padding: 14px;
                color: rgba(255, 255, 255, 0.6);
                text-decoration: none;
                font-weight: 600;
                font-size: 14px;
                transition: all 0.2s;
            }

            .wbak-tab:hover {
                color: #fff;
                background: rgba(255, 255, 255, 0.05);
            }

            .wbak-tab.active {
                color: #fff;
                background: rgba(255, 255, 255, 0.15);
                border-bottom: 3px solid <?php echo esc_attr($secondary); ?>;
            }

            .wbak-body {
                background: #fff;
                margin-top: 20px;
                border-radius: 16px;
                padding: 32px;
                box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            }

            .wbak-section {
                margin-bottom: 28px;
            }

            .wbak-section h2 {
                font-size: 18px;
                font-weight: 700;
                color: #333;
                margin: 0 0 6px;
            }

            .wbak-section p {
                color: #888;
                font-size: 13px;
                margin: 0 0 16px;
            }

            .wbak-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 14px 28px;
                border: none;
                border-radius: 12px;
                font-size: 15px;
                font-weight: 700;
                cursor: pointer;
                text-decoration: none;
                transition: all 0.2s;
                font-family: 'Roboto', sans-serif;
            }

            .wbak-btn-primary {
                background: <?php echo esc_attr($primary); ?>;
                color: #fff;
            }

            .wbak-btn-primary:hover {
                opacity: 0.9;
                color: #fff;
                transform: translateY(-1px);
            }

            .wbak-btn-danger {
                background: <?php echo esc_attr($secondary); ?>;
                color: #fff;
            }

            .wbak-btn-danger:hover {
                opacity: 0.85;
                color: #fff;
            }

            .wbak-btn-success {
                background: #4caf50;
                color: #fff;
            }

            .wbak-btn-success:hover {
                background: #388e3c;
                color: #fff;
            }

            .wbak-btn-sm {
                padding: 8px 16px;
                font-size: 13px;
                border-radius: 8px;
            }

            .wbak-notice {
                padding: 14px 20px;
                border-radius: 10px;
                margin-bottom: 20px;
                font-size: 14px;
                font-weight: 500;
            }

            .wbak-notice.success {
                background: #e8f5e9;
                color: #2e7d32;
                border: 1px solid #c8e6c9;
            }

            .wbak-notice.error {
                background: #ffebee;
                color: #c62828;
                border: 1px solid #ffcdd2;
            }

            .wbak-field {
                margin-bottom: 14px;
            }

            .wbak-field label {
                display: block;
                font-size: 13px;
                font-weight: 600;
                color: #555;
                margin-bottom: 6px;
            }

            .wbak-field input,
            .wbak-field select {
                width: 100%;
                padding: 12px 16px;
                border: 2px solid #e0e0e0;
                border-radius: 10px;
                font-size: 14px;
                font-family: 'Roboto', sans-serif;
                transition: border-color 0.2s;
                box-sizing: border-box;
            }

            .wbak-field input:focus,
            .wbak-field select:focus {
                border-color: <?php echo esc_attr($primary); ?>;
                outline: none;
            }

            .wbak-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 14px;
            }

            .wbak-backup-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 16px 20px;
                border: 1px solid #eee;
                border-radius: 12px;
                margin-bottom: 10px;
                transition: box-shadow 0.2s;
            }

            .wbak-backup-item:hover {
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            }

            .wbak-backup-info {
                flex: 1;
            }

            .wbak-backup-name {
                font-size: 14px;
                font-weight: 600;
                color: #333;
                word-break: break-all;
            }

            .wbak-backup-meta {
                font-size: 12px;
                color: #999;
                margin-top: 3px;
            }

            .wbak-backup-actions {
                display: flex;
                gap: 6px;
            }

            .wbak-upload-zone {
                border: 3px dashed #d0d0d0;
                border-radius: 14px;
                padding: 30px;
                text-align: center;
                transition: border-color 0.2s;
                margin-bottom: 16px;
            }

            .wbak-upload-zone:hover {
                border-color: <?php echo esc_attr($primary); ?>;
            }

            .wbak-warning {
                background: #fff3e0;
                border: 1px solid #ffe0b2;
                border-radius: 10px;
                padding: 14px 20px;
                margin-bottom: 16px;
                font-size: 13px;
                color: #e65100;
            }
        </style>

        <div class="wbak-wrap">
            <div class="wbak-header">
                <h1>&#128190; Webyaz Backup</h1>
                <p>Tam site yedekleme ve geri yukleme</p>
            </div>
            <div class="wbak-tabs">
                <a href="?page=webyaz-backup&tab=backup" class="wbak-tab <?php echo $tab === 'backup' ? 'active' : ''; ?>">Yedek Al</a>
                <a href="?page=webyaz-backup&tab=restore" class="wbak-tab <?php echo $tab === 'restore' ? 'active' : ''; ?>">Geri Yukle</a>
                <a href="?page=webyaz-backup&tab=list" class="wbak-tab <?php echo $tab === 'list' ? 'active' : ''; ?>">Yedekler (<?php echo count($backups); ?>)</a>
                <a href="?page=webyaz-backup&tab=schedule" class="wbak-tab <?php echo $tab === 'schedule' ? 'active' : ''; ?>">&#9200; Otomatik Yedek</a>
            </div>

            <div class="wbak-body">
                <?php if ($message): ?><div class="wbak-notice success"><?php echo esc_html($message); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="wbak-notice error"><?php echo esc_html($error); ?></div><?php endif; ?>

                <?php if ($tab === 'backup'): ?>
                    <div class="wbak-section">
                        <h2>Tam Site Yedegi Al</h2>
                        <p>Veritabani, temalar, eklentiler, yuklenen dosyalar ve ayarlarin tamami yedeklenir.</p>

                        <!-- Adim gostergesi -->
                        <div id="wbak-steps" style="display:none;margin-bottom:24px;">
                            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px;">
                                <div class="wbak-step-card" data-step="database" style="text-align:center;padding:14px 10px;background:#f5f5f5;border-radius:10px;transition:all 0.3s;">
                                    <div style="font-size:22px;margin-bottom:4px;">&#128451;</div>
                                    <div style="font-size:11px;font-weight:600;color:#888;">Veritabani</div>
                                </div>
                                <div class="wbak-step-card" data-step="themes" style="text-align:center;padding:14px 10px;background:#f5f5f5;border-radius:10px;transition:all 0.3s;">
                                    <div style="font-size:22px;margin-bottom:4px;">&#127912;</div>
                                    <div style="font-size:11px;font-weight:600;color:#888;">Temalar</div>
                                </div>
                                <div class="wbak-step-card" data-step="plugins" style="text-align:center;padding:14px 10px;background:#f5f5f5;border-radius:10px;transition:all 0.3s;">
                                    <div style="font-size:22px;margin-bottom:4px;">&#128268;</div>
                                    <div style="font-size:11px;font-weight:600;color:#888;">Eklentiler</div>
                                </div>
                                <div class="wbak-step-card" data-step="uploads" style="text-align:center;padding:14px 10px;background:#f5f5f5;border-radius:10px;transition:all 0.3s;">
                                    <div style="font-size:22px;margin-bottom:4px;">&#128444;</div>
                                    <div style="font-size:11px;font-weight:600;color:#888;">Medya</div>
                                </div>
                            </div>

                            <!-- Progress bar -->
                            <div style="background:#e0e0e0;border-radius:8px;overflow:hidden;height:28px;position:relative;margin-bottom:10px;">
                                <div id="wbak-progress-bar" style="width:0%;height:100%;background:linear-gradient(90deg,<?php echo esc_attr($primary); ?>,<?php echo esc_attr($secondary); ?>);border-radius:8px;transition:width 0.4s ease;display:flex;align-items:center;justify-content:center;">
                                    <span id="wbak-progress-pct" style="color:#fff;font-size:12px;font-weight:700;text-shadow:0 1px 2px rgba(0,0,0,0.3);"></span>
                                </div>
                            </div>

                            <!-- Durum mesaji -->
                            <div id="wbak-status" style="font-size:13px;color:#666;display:flex;align-items:center;gap:8px;">
                                <span class="wbak-spinner" style="display:inline-block;width:16px;height:16px;border:2px solid #ddd;border-top-color:<?php echo esc_attr($primary); ?>;border-radius:50%;animation:wbak-spin 0.6s linear infinite;"></span>
                                <span id="wbak-status-text">Hazirlaniyor...</span>
                            </div>

                            <!-- Log -->
                            <div id="wbak-log" style="margin-top:12px;background:#f8f9fa;border-radius:8px;padding:12px 16px;max-height:180px;overflow-y:auto;font-size:12px;font-family:monospace;color:#555;line-height:1.8;"></div>
                        </div>

                        <!-- Sonuc -->
                        <div id="wbak-result" style="display:none;background:#e8f5e9;border-radius:12px;padding:24px;text-align:center;margin-bottom:20px;">
                            <div style="font-size:48px;margin-bottom:18px;">&#9989;</div>
                            <h3 style="margin:0 0 6px;color:#2e7d32;">Yedek Basariyla Olusturuldu!</h3>
                            <p id="wbak-result-info" style="margin:0 0 16px;color:#555;font-size:14px;"></p>
                            <a id="wbak-result-link" href="#" class="wbak-btn wbak-btn-success">&#11015; Yedeği İndir</a>
                        </div>

                        <button id="wbak-start-btn" type="button" class="wbak-btn wbak-btn-primary" style="font-size:16px;padding:16px 36px;">
                            &#128190; Yedek Olustur
                        </button>

                        <style>
                            @keyframes wbak-spin {
                                to {
                                    transform: rotate(360deg);
                                }
                            }

                            .wbak-step-active {
                                background: #e3f2fd !important;
                                border: 2px solid <?php echo esc_attr($primary); ?>;
                            }

                            .wbak-step-active div:last-child {
                                color: <?php echo esc_attr($primary); ?> !important;
                            }

                            .wbak-step-done {
                                background: #e8f5e9 !important;
                            }

                            .wbak-step-done div:last-child {
                                color: #2e7d32 !important;
                            }

                            .wbak-step-done div:first-child::after {
                                content: ' \2713';
                            }
                        </style>

                        <script>
                            (function() {
                                var steps = [{
                                        id: 'init',
                                        label: 'Hazirlaniyor...',
                                        pct: 5
                                    },
                                    {
                                        id: 'database',
                                        label: 'Veritabani yedekleniyor...',
                                        pct: 15
                                    },
                                    {
                                        id: 'siteinfo',
                                        label: 'Site bilgileri kaydediliyor...',
                                        pct: 20
                                    },
                                    {
                                        id: 'themes',
                                        label: 'Temalar kopyalaniyor...',
                                        pct: 35
                                    },
                                    {
                                        id: 'plugins',
                                        label: 'Eklentiler kopyalaniyor...',
                                        pct: 55
                                    },
                                    {
                                        id: 'uploads',
                                        label: 'Medya dosyalari kopyalaniyor...',
                                        pct: 75
                                    },
                                    {
                                        id: 'rootfiles',
                                        label: 'Yapilandirma dosyalari...',
                                        pct: 80
                                    },
                                    {
                                        id: 'zip',
                                        label: 'Arsiv olusturuluyor...',
                                        pct: 95
                                    },
                                    {
                                        id: 'cleanup',
                                        label: 'Temizlik yapiliyor...',
                                        pct: 100
                                    }
                                ];
                                var btn = document.getElementById('wbak-start-btn');
                                var stepsEl = document.getElementById('wbak-steps');
                                var bar = document.getElementById('wbak-progress-bar');
                                var pctEl = document.getElementById('wbak-progress-pct');
                                var statusText = document.getElementById('wbak-status-text');
                                var logEl = document.getElementById('wbak-log');
                                var resultEl = document.getElementById('wbak-result');
                                var context = {};

                                function addLog(icon, msg) {
                                    var time = new Date().toLocaleTimeString('tr-TR');
                                    logEl.innerHTML += '<div>' + icon + ' <span style="color:#999">[' + time + ']</span> ' + msg + '</div>';
                                    logEl.scrollTop = logEl.scrollHeight;
                                }

                                function setCard(stepId, state) {
                                    var cards = document.querySelectorAll('.wbak-step-card');
                                    cards.forEach(function(c) {
                                        if (c.getAttribute('data-step') === stepId) {
                                            c.className = 'wbak-step-card' + (state === 'active' ? ' wbak-step-active' : (state === 'done' ? ' wbak-step-done' : ''));
                                        }
                                    });
                                }

                                function runStep(idx) {
                                    if (idx >= steps.length) return;
                                    var s = steps[idx];
                                    statusText.textContent = s.label;
                                    bar.style.width = s.pct + '%';
                                    pctEl.textContent = s.pct + '%';
                                    setCard(s.id, 'active');
                                    addLog('&#9997;', s.label);

                                    var fd = new FormData();
                                    fd.append('action', 'wbak_step');
                                    fd.append('nonce', '<?php echo wp_create_nonce("wbak_ajax_nonce"); ?>');
                                    fd.append('step', s.id);
                                    fd.append('context', JSON.stringify(context));

                                    fetch(ajaxurl, {
                                            method: 'POST',
                                            body: fd
                                        })
                                        .then(function(r) {
                                            return r.json();
                                        })
                                        .then(function(res) {
                                            if (!res.success) {
                                                addLog('&#10060;', 'HATA: ' + (res.data || 'Bilinmeyen hata'));
                                                statusText.textContent = 'Hata olustu!';
                                                btn.style.display = 'inline-flex';
                                                btn.textContent = '\u{1F504} Tekrar Dene';
                                                return;
                                            }
                                            var d = res.data;
                                            if (d.context) context = d.context;
                                            if (d.detail) addLog('&#9989;', d.detail);
                                            setCard(s.id, 'done');

                                            if (d.status === 'done') {
                                                bar.style.width = '100%';
                                                pctEl.textContent = '100%';
                                                statusText.textContent = 'Tamamlandi!';
                                                document.querySelector('.wbak-spinner').style.display = 'none';
                                                resultEl.style.display = 'block';
                                                var sz = d.result && d.result.size ? (d.result.size > 1048576 ? (d.result.size / 1048576).toFixed(2) + ' MB' : (d.result.size / 1024).toFixed(1) + ' KB') : '';
                                                document.getElementById('wbak-result-info').textContent = d.result.name + ' (' + sz + ')';
                                                document.getElementById('wbak-result-link').href = '?page=webyaz-backup&tab=list';
                                                document.getElementById('wbak-result-link').textContent = '\u{1F4C2} Yedekleri Gor';
                                                addLog('\u{1F389}', 'Yedek basariyla tamamlandi! Boyut: ' + sz);
                                            } else {
                                                runStep(idx + 1);
                                            }
                                        })
                                        .catch(function(err) {
                                            addLog('&#10060;', 'Baglanti hatasi: ' + err.message);
                                            statusText.textContent = 'Baglanti hatasi!';
                                        });
                                }

                                btn.addEventListener('click', function() {
                                    btn.style.display = 'none';
                                    stepsEl.style.display = 'block';
                                    resultEl.style.display = 'none';
                                    logEl.innerHTML = '';
                                    context = {};
                                    addLog('&#128640;', 'Yedekleme baslatiliyor...');
                                    runStep(0);
                                });
                            })();
                        </script>
                    </div>

                <?php elseif ($tab === 'restore'): ?>
                    <div class="wbak-section">
                        <h2>Geri Yukle</h2>
                        <p>FTP ile <code>wp-content/webyaz-backups/</code> klasorune .wbak dosyanizi yukleyin veya asagidan secin.</p>

                        <div class="wbak-warning">
                            <strong>&#9888; Dikkat:</strong> Geri yukleme mevcut siteyi tamamen degistirecektir. Islem geri alinamaz!
                        </div>

                        <!-- Form Alani -->
                        <div id="wbak-restore-form">
                            <?php if (!empty($backups)): ?>
                                <div class="wbak-field">
                                    <label>Mevcut Yedeklerden Sec</label>
                                    <select id="wbak-restore-select">
                                        <option value="">-- Yedek secin --</option>
                                        <?php foreach ($backups as $b): ?>
                                            <option value="<?php echo esc_attr($b['path']); ?>"><?php echo esc_html($b['name']); ?> (<?php echo $this->format_size($b['size']); ?> - <?php echo date('d.m.Y H:i', $b['date']); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <div style="background:#f5f5f5;border-radius:10px;padding:20px;text-align:center;margin-bottom:16px;color:#888;">
                                    <strong>Yedek bulunamadi.</strong><br>FTP ile <code>wp-content/webyaz-backups/</code> klasorune .wbak dosyanizi yukleyin.
                                </div>
                            <?php endif; ?>

                            <div style="background:#f0f4ff;border-radius:12px;padding:20px;margin-bottom:20px;">
                                <h3 style="margin:0 0 12px;font-size:15px;font-weight:700;color:#0f3460;">&#128274; Yeni Admin Hesabi</h3>
                                <p style="font-size:12px;color:#888;margin:0 0 14px;">Geri yuklemeden sonra kullanacaginiz yonetici hesabi. Bos birakirsaniz yedekteki hesaplar kullanilir.</p>
                                <div class="wbak-grid">
                                    <div class="wbak-field">
                                        <label>Kullanici Adi</label>
                                        <input type="text" id="wbak-admin-user" placeholder="ornek: admin" autocomplete="off">
                                    </div>
                                    <div class="wbak-field">
                                        <label>Sifre</label>
                                        <input type="password" id="wbak-admin-pass" placeholder="Guclu bir sifre girin" autocomplete="new-password">
                                    </div>
                                </div>
                                <div class="wbak-field">
                                    <label>E-posta (opsiyonel)</label>
                                    <input type="email" id="wbak-admin-email" placeholder="admin@site.com">
                                </div>
                            </div>

                            <button id="wbak-restore-btn" type="button" class="wbak-btn wbak-btn-danger" style="font-size:16px;padding:16px 36px;">
                                &#128260; Geri Yuklemeyi Baslat
                            </button>
                        </div>

                        <!-- Progress Bar Alani -->
                        <div id="wbak-restore-progress" style="display:none;">
                            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;">
                                <div class="wbak-rstep-card" data-step="extract" style="text-align:center;padding:14px 10px;background:#f5f5f5;border-radius:10px;transition:all 0.3s;">
                                    <div style="font-size:22px;margin-bottom:4px;">&#128230;</div>
                                    <div style="font-size:11px;font-weight:600;color:#888;">ZIP Acma</div>
                                </div>
                                <div class="wbak-rstep-card" data-step="database" style="text-align:center;padding:14px 10px;background:#f5f5f5;border-radius:10px;transition:all 0.3s;">
                                    <div style="font-size:22px;margin-bottom:4px;">&#128451;</div>
                                    <div style="font-size:11px;font-weight:600;color:#888;">Veritabani</div>
                                </div>
                                <div class="wbak-rstep-card" data-step="files" style="text-align:center;padding:14px 10px;background:#f5f5f5;border-radius:10px;transition:all 0.3s;">
                                    <div style="font-size:22px;margin-bottom:4px;">&#128194;</div>
                                    <div style="font-size:11px;font-weight:600;color:#888;">Dosyalar</div>
                                </div>
                                <div class="wbak-rstep-card" data-step="search_replace" style="text-align:center;padding:14px 10px;background:#f5f5f5;border-radius:10px;transition:all 0.3s;">
                                    <div style="font-size:22px;margin-bottom:4px;">&#128269;</div>
                                    <div style="font-size:11px;font-weight:600;color:#888;">URL Degistirme</div>
                                </div>
                                <div class="wbak-rstep-card" data-step="admin" style="text-align:center;padding:14px 10px;background:#f5f5f5;border-radius:10px;transition:all 0.3s;">
                                    <div style="font-size:22px;margin-bottom:4px;">&#128100;</div>
                                    <div style="font-size:11px;font-weight:600;color:#888;">Admin Hesap</div>
                                </div>
                                <div class="wbak-rstep-card" data-step="cleanup" style="text-align:center;padding:14px 10px;background:#f5f5f5;border-radius:10px;transition:all 0.3s;">
                                    <div style="font-size:22px;margin-bottom:4px;">&#10004;</div>
                                    <div style="font-size:11px;font-weight:600;color:#888;">Tamamla</div>
                                </div>
                            </div>

                            <!-- Progress bar -->
                            <div style="background:#e0e0e0;border-radius:8px;overflow:hidden;height:28px;position:relative;margin-bottom:10px;">
                                <div id="wbak-restore-bar" style="width:0%;height:100%;background:linear-gradient(90deg,<?php echo esc_attr($secondary); ?>,<?php echo esc_attr($primary); ?>);border-radius:8px;transition:width 0.5s ease;display:flex;align-items:center;justify-content:center;">
                                    <span id="wbak-restore-pct" style="color:#fff;font-size:12px;font-weight:700;text-shadow:0 1px 2px rgba(0,0,0,0.3);"></span>
                                </div>
                            </div>

                            <!-- Durum mesaji -->
                            <div id="wbak-restore-status" style="font-size:13px;color:#666;display:flex;align-items:center;gap:8px;">
                                <span class="wbak-restore-spinner" style="display:inline-block;width:16px;height:16px;border:2px solid #ddd;border-top-color:<?php echo esc_attr($secondary); ?>;border-radius:50%;animation:wbak-spin 0.6s linear infinite;"></span>
                                <span id="wbak-restore-status-text">Baslatiliyor...</span>
                            </div>

                            <!-- Log -->
                            <div id="wbak-restore-log" style="margin-top:12px;background:#f8f9fa;border-radius:8px;padding:12px 16px;max-height:220px;overflow-y:auto;font-size:12px;font-family:monospace;color:#555;line-height:1.8;"></div>
                        </div>

                        <!-- Sonuc -->
                        <div id="wbak-restore-result" style="display:none;background:#e8f5e9;border-radius:12px;padding:24px;text-align:center;margin-top:20px;">
                            <div style="font-size:48px;margin-bottom:18px;">&#9989;</div>
                            <h3 style="margin:0 0 6px;color:#2e7d32;">Site Basariyla Geri Yuklendi!</h3>
                            <p style="margin:0 0 16px;color:#555;font-size:14px;">Simdi giris sayfasina yonlendirileceksiniz.</p>
                            <a href="<?php echo wp_login_url(); ?>" class="wbak-btn wbak-btn-success">&#128274; Giris Yap</a>
                        </div>

                        <!-- Hata -->
                        <div id="wbak-restore-error" style="display:none;background:#ffebee;border-radius:12px;padding:24px;text-align:center;margin-top:20px;">
                            <div style="font-size:48px;margin-bottom:18px;">&#10060;</div>
                            <h3 style="margin:0 0 6px;color:#c62828;">Geri Yukleme Hatasi!</h3>
                            <p id="wbak-restore-error-text" style="margin:0 0 16px;color:#555;font-size:14px;"></p>
                            <button type="button" onclick="location.reload();" class="wbak-btn wbak-btn-danger">&#128260; Tekrar Dene</button>
                        </div>

                        <style>
                            .wbak-rstep-active {
                                background: #fff3e0 !important;
                                border: 2px solid <?php echo esc_attr($secondary); ?>;
                            }

                            .wbak-rstep-active div:last-child {
                                color: <?php echo esc_attr($secondary); ?> !important;
                            }

                            .wbak-rstep-done {
                                background: #e8f5e9 !important;
                            }

                            .wbak-rstep-done div:last-child {
                                color: #2e7d32 !important;
                            }

                            .wbak-rstep-done div:first-child::after {
                                content: ' \2713';
                            }
                        </style>

                        <script>
                            (function() {
                                var steps = [{
                                        id: 'extract',
                                        label: 'ZIP dosyasi aciliyor...',
                                        pct: 10
                                    },
                                    {
                                        id: 'database',
                                        label: 'Veritabani geri yukleniyor...',
                                        pct: 30
                                    },
                                    {
                                        id: 'files',
                                        label: 'Dosyalar geri yukleniyor...',
                                        pct: 60
                                    },
                                    {
                                        id: 'search_replace',
                                        label: 'URL degistirme yapiliyor...',
                                        pct: 80
                                    },
                                    {
                                        id: 'admin',
                                        label: 'Admin hesabi ve ayarlar...',
                                        pct: 90
                                    },
                                    {
                                        id: 'cleanup',
                                        label: 'Temizlik yapiliyor...',
                                        pct: 100
                                    }
                                ];
                                var btn = document.getElementById('wbak-restore-btn');
                                var formEl = document.getElementById('wbak-restore-form');
                                var progressEl = document.getElementById('wbak-restore-progress');
                                var bar = document.getElementById('wbak-restore-bar');
                                var pctEl = document.getElementById('wbak-restore-pct');
                                var statusText = document.getElementById('wbak-restore-status-text');
                                var logEl = document.getElementById('wbak-restore-log');
                                var resultEl = document.getElementById('wbak-restore-result');
                                var errorEl = document.getElementById('wbak-restore-error');
                                var context = {};

                                function addLog(icon, msg) {
                                    var time = new Date().toLocaleTimeString('tr-TR');
                                    logEl.innerHTML += '<div>' + icon + ' <span style="color:#999">[' + time + ']</span> ' + msg + '</div>';
                                    logEl.scrollTop = logEl.scrollHeight;
                                }

                                function setCard(stepId, state) {
                                    var cards = document.querySelectorAll('.wbak-rstep-card');
                                    cards.forEach(function(c) {
                                        if (c.getAttribute('data-step') === stepId) {
                                            c.className = 'wbak-rstep-card' + (state === 'active' ? ' wbak-rstep-active' : (state === 'done' ? ' wbak-rstep-done' : ''));
                                        }
                                    });
                                }

                                function runStep(idx) {
                                    if (idx >= steps.length) return;
                                    var s = steps[idx];
                                    statusText.textContent = s.label;
                                    bar.style.width = s.pct + '%';
                                    pctEl.textContent = s.pct + '%';
                                    setCard(s.id, 'active');
                                    addLog('&#9997;', s.label);

                                    var fd = new FormData();
                                    fd.append('action', 'wbak_restore_step');
                                    fd.append('nonce', '<?php echo wp_create_nonce("wbak_ajax_nonce"); ?>');
                                    fd.append('step', s.id);
                                    fd.append('context', JSON.stringify(context));

                                    fetch(ajaxurl, {
                                            method: 'POST',
                                            body: fd
                                        })
                                        .then(function(r) {
                                            return r.json();
                                        })
                                        .then(function(res) {
                                            if (!res.success) {
                                                addLog('&#10060;', 'HATA: ' + (res.data || 'Bilinmeyen hata'));
                                                statusText.textContent = 'Hata olustu!';
                                                document.querySelector('.wbak-restore-spinner').style.display = 'none';
                                                errorEl.style.display = 'block';
                                                document.getElementById('wbak-restore-error-text').textContent = res.data || 'Bilinmeyen hata';
                                                return;
                                            }
                                            var d = res.data;
                                            if (d.context) context = d.context;
                                            if (d.detail) addLog('&#9989;', d.detail);
                                            setCard(s.id, 'done');

                                            if (d.status === 'done') {
                                                bar.style.width = '100%';
                                                pctEl.textContent = '100%';
                                                statusText.textContent = 'Tamamlandi!';
                                                document.querySelector('.wbak-restore-spinner').style.display = 'none';
                                                resultEl.style.display = 'block';
                                                addLog('\u{1F389}', 'Geri yukleme basariyla tamamlandi!');
                                            } else {
                                                runStep(idx + 1);
                                            }
                                        })
                                        .catch(function(err) {
                                            addLog('&#10060;', 'Baglanti hatasi: ' + err.message);
                                            statusText.textContent = 'Baglanti hatasi!';
                                            document.querySelector('.wbak-restore-spinner').style.display = 'none';
                                            errorEl.style.display = 'block';
                                            document.getElementById('wbak-restore-error-text').textContent = 'Sunucu ile baglanti kesildi. Lutfen tekrar deneyin.';
                                        });
                                }

                                btn.addEventListener('click', function() {
                                    var sel = document.getElementById('wbak-restore-select');
                                    var filePath = sel ? sel.value : '';
                                    if (!filePath) {
                                        alert('Lutfen bir yedek dosyasi secin!');
                                        return;
                                    }
                                    if (!confirm('DIKKAT: Mevcut site tamamen degisecek!\nDevam etmek istiyor musunuz?')) return;

                                    context = {
                                        file_path: filePath,
                                        new_admin_user: document.getElementById('wbak-admin-user').value,
                                        new_admin_pass: document.getElementById('wbak-admin-pass').value,
                                        new_admin_email: document.getElementById('wbak-admin-email').value
                                    };

                                    formEl.style.display = 'none';
                                    progressEl.style.display = 'block';
                                    resultEl.style.display = 'none';
                                    errorEl.style.display = 'none';
                                    logEl.innerHTML = '';
                                    addLog('&#128640;', 'Geri yukleme baslatiliyor...');
                                    addLog('&#128194;', 'Dosya: ' + filePath.split('/').pop());
                                    runStep(0);
                                });
                            })();
                        </script>
                    </div>

                <?php elseif ($tab === 'list'): ?>
                    <div class="wbak-section">
                        <h2>Mevcut Yedekler</h2>
                        <p>FTP: <code>wp-content/webyaz-backups/</code> klasorune .wbak dosyasi yukleyebilirsiniz.</p>

                        <?php if (empty($backups)): ?>
                            <div style="text-align:center;padding:40px;color:#999;">
                                <div style="font-size:48px;margin-bottom:10px;">&#128194;</div>
                                <p>Henuz yedek bulunmuyor.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($backups as $b): ?>
                                <div class="wbak-backup-item">
                                    <div class="wbak-backup-info">
                                        <div class="wbak-backup-name">&#128190; <?php echo esc_html($b['name']); ?></div>
                                        <div class="wbak-backup-meta"><?php echo $this->format_size($b['size']); ?> &bull; <?php echo date('d.m.Y H:i:s', $b['date']); ?></div>
                                    </div>
                                    <div class="wbak-backup-actions">
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=webyaz-backup&wbak_dl=' . urlencode($b['name'])), 'wbak_download_action'); ?>" class="wbak-btn wbak-btn-success wbak-btn-sm">&#11015; Indir</a>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
                                            <input type="hidden" name="action" value="wbak_delete">
                                            <input type="hidden" name="file" value="<?php echo esc_attr($b['name']); ?>">
                                            <?php wp_nonce_field('wbak_delete_action'); ?>
                                            <button type="submit" class="wbak-btn wbak-btn-danger wbak-btn-sm" onclick="return confirm('Bu yedegi silmek istediginize emin misiniz?');">&#128465; Sil</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php elseif ($tab === 'schedule'): ?>
                    <?php
                    $auto_enabled = get_option('wbak_auto_enabled', '0');
                    $auto_hour = intval(get_option('wbak_auto_hour', '3'));
                    $auto_keep = intval(get_option('wbak_auto_keep', '5'));
                    $auto_last = get_option('wbak_auto_last', array());
                    $next_run = wp_next_scheduled('wbak_auto_backup_cron');
                    ?>
                    <div class="wbak-section">
                        <h2>&#9200; Otomatik Yedekleme</h2>
                        <p>Belirlediginiz saatte otomatik olarak tam site yedegi olusturulur ve eski yedekler temizlenir.</p>

                        <?php if (!empty($auto_last)): ?>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:24px;">
                                <div style="background:<?php echo !empty($auto_last['status']) && $auto_last['status'] === 'success' ? '#e8f5e9' : '#ffebee'; ?>;border-radius:12px;padding:18px;text-align:center;">
                                    <div style="font-size:13px;color:#888;margin-bottom:4px;">Son Otomatik Yedek</div>
                                    <div style="font-size:15px;font-weight:700;color:<?php echo !empty($auto_last['status']) && $auto_last['status'] === 'success' ? '#2e7d32' : '#c62828'; ?>;">
                                        <?php
                                        if (!empty($auto_last['date'])) {
                                            echo date('d.m.Y H:i', $auto_last['date']);
                                        } else {
                                            echo 'Henuz calistirilmadi';
                                        }
                                        ?>
                                    </div>
                                    <?php if (!empty($auto_last['size'])): ?>
                                        <div style="font-size:12px;color:#888;margin-top:4px;"><?php echo $this->format_size($auto_last['size']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($auto_last['error'])): ?>
                                        <div style="font-size:12px;color:#c62828;margin-top:4px;"><?php echo esc_html($auto_last['error']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div style="background:#e3f2fd;border-radius:12px;padding:18px;text-align:center;">
                                    <div style="font-size:13px;color:#888;margin-bottom:4px;">Sonraki Yedek</div>
                                    <div style="font-size:15px;font-weight:700;color:#1565c0;">
                                        <?php
                                        if ($next_run && $auto_enabled === '1') {
                                            echo date('d.m.Y H:i', $next_run + (3 * 3600));
                                        } else {
                                            echo 'Planlanmadi';
                                        }
                                        ?>
                                    </div>
                                    <div style="font-size:12px;color:#888;margin-top:4px;">
                                        <?php echo $auto_enabled === '1' ? 'Her gun saat ' . str_pad($auto_hour, 2, '0', STR_PAD_LEFT) . ':00' : 'Otomatik yedek kapali'; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                            <input type="hidden" name="action" value="wbak_save_schedule">
                            <?php wp_nonce_field('wbak_schedule_action'); ?>

                            <div style="background:#fff;border:2px solid #e0e0e0;border-radius:14px;padding:28px;margin-bottom:20px;">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;padding-bottom:18px;border-bottom:1px solid #f0f0f0;">
                                    <div>
                                        <div style="font-size:16px;font-weight:700;color:#333;">Otomatik Yedekleme</div>
                                        <div style="font-size:13px;color:#888;margin-top:2px;">Her gun belirlenen saatte otomatik yedek olusturulur</div>
                                    </div>
                                    <label style="position:relative;display:inline-block;width:52px;height:28px;cursor:pointer;">
                                        <input type="checkbox" name="wbak_auto_enabled" value="1" <?php checked($auto_enabled, '1'); ?> style="display:none;" onchange="var s=this.parentElement.querySelectorAll('span');s[0].style.background=this.checked?'#4caf50':'#ccc';s[1].style.left=this.checked?'27px':'3px';">
                                        <span style="position:absolute;top:0;left:0;right:0;bottom:0;background:<?php echo $auto_enabled === '1' ? '#4caf50' : '#ccc'; ?>;border-radius:28px;transition:0.3s;"></span>
                                        <span style="position:absolute;top:3px;left:<?php echo $auto_enabled === '1' ? '27px' : '3px'; ?>;width:22px;height:22px;background:#fff;border-radius:50%;transition:0.3s;box-shadow:0 1px 3px rgba(0,0,0,0.2);"></span>
                                    </label>
                                </div>

                                <div class="wbak-grid">
                                    <div class="wbak-field">
                                        <label>&#128344; Yedek Saati</label>
                                        <select name="wbak_auto_hour" style="font-size:15px;">
                                            <?php for ($h = 0; $h < 24; $h++): ?>
                                                <option value="<?php echo $h; ?>" <?php selected($auto_hour, $h); ?>>
                                                    <?php echo str_pad($h, 2, '0', STR_PAD_LEFT) . ':00'; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="wbak-field">
                                        <label>&#128451; Saklanacak Yedek Sayisi</label>
                                        <select name="wbak_auto_keep" style="font-size:15px;">
                                            <?php foreach (array(1, 2, 3, 5, 7, 10, 15) as $k): ?>
                                                <option value="<?php echo $k; ?>" <?php selected($auto_keep, $k); ?>>
                                                    Son <?php echo $k; ?> yedek
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="wbak-btn wbak-btn-primary" style="font-size:15px;padding:14px 32px;">
                                &#128190; Ayarlari Kaydet
                            </button>
                        </form>

                        <!-- KURULUM REHBERI -->
                        <div style="margin-top:30px;background:linear-gradient(135deg,#e3f2fd,#f3e5f5);border-radius:14px;padding:28px;border:1px solid #bbdefb;">
                            <h3 style="margin:0 0 16px;font-size:17px;color:#1565c0;">&#128218; Kurulum Rehberi</h3>
                            <p style="font-size:13px;color:#555;margin:0 0 20px;line-height:1.7;">
                                Otomatik yedeklemenin zamaninda calismasi icin asagidaki <strong>2 adimi</strong> tamamlayin.
                                Bu adimlar bir kez yapilir, tekrar gerekmez.
                            </p>

                            <!-- ADIM 1 -->
                            <div style="background:#fff;border-radius:12px;padding:20px;margin-bottom:16px;border-left:4px solid #e65100;">
                                <h4 style="margin:0 0 10px;font-size:14px;color:#e65100;">Adim 1: wp-config.php Dosyasina Kod Ekleyin</h4>
                                <p style="font-size:12px;color:#666;margin:0 0 10px;line-height:1.6;">
                                    Plesk &rarr; Dosya Yoneticisi &rarr; <code>wp-config.php</code> dosyasini acin.<br>
                                    <code>/* That's all, stop editing! */</code> satirinin <strong>hemen ustune</strong> asagidaki kodu ekleyin:
                                </p>
                                <div style="position:relative;">
                                    <pre id="wbak-config-code" style="background:#1a1a2e;color:#4caf50;border-radius:8px;padding:14px 18px;font-size:14px;font-family:'Courier New',monospace;margin:0;overflow-x:auto;cursor:pointer;" onclick="navigator.clipboard.writeText(this.textContent.trim());var b=document.getElementById('wbak-copy-msg');b.style.opacity='1';setTimeout(function(){b.style.opacity='0';},2000);" title="Kopyalamak icin tiklayin">define('DISABLE_WP_CRON', true);</pre>
                                    <span id="wbak-copy-msg" style="position:absolute;top:50%;right:12px;transform:translateY(-50%);background:#4caf50;color:#fff;padding:4px 12px;border-radius:6px;font-size:11px;font-weight:600;opacity:0;transition:opacity 0.3s;pointer-events:none;">&#10003; Kopyalandi!</span>
                                    <div style="text-align:right;margin-top:6px;">
                                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('wbak-config-code').textContent.trim());var b=document.getElementById('wbak-copy-msg');b.style.opacity='1';setTimeout(function(){b.style.opacity='0';},2000);" style="background:#446084;color:#fff;border:none;border-radius:6px;padding:6px 14px;font-size:12px;font-weight:600;cursor:pointer;">&#128203; Kodu Kopyala</button>
                                    </div>
                                </div>
                                <p style="font-size:11px;color:#999;margin:8px 0 0;">Bu kod WordPress'in her sayfa yuklemesinde gereksiz cron calistirmasini engeller.</p>
                            </div>

                            <!-- ADIM 2 -->
                            <div style="background:#fff;border-radius:12px;padding:20px;border-left:4px solid #1565c0;">
                                <h4 style="margin:0 0 10px;font-size:14px;color:#1565c0;">Adim 2: Hosting Panelinizde Cron Job Ekleyin</h4>
                                <p style="font-size:12px;color:#666;margin:0 0 10px;line-height:1.6;">
                                    Plesk &rarr; <strong>Planlanmis Gorevler</strong> &rarr; <strong>Gorev Ekle</strong> &rarr; asagidaki ayarlari girin:
                                </p>
                                <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:10px;">
                                    <tr style="border-bottom:1px solid #eee;">
                                        <td style="padding:8px 12px;font-weight:600;color:#333;width:40%;">Gorev Turu</td>
                                        <td style="padding:8px 12px;color:#555;">Bir URL Getir</td>
                                    </tr>
                                    <tr style="border-bottom:1px solid #eee;">
                                        <td style="padding:8px 12px;font-weight:600;color:#333;">URL</td>
                                        <td style="padding:8px 12px;"><code style="background:#f5f5f5;padding:3px 8px;border-radius:4px;font-size:12px;word-break:break-all;"><?php echo esc_url(site_url('/wp-cron.php?doing_wp_cron')); ?></code></td>
                                    </tr>
                                    <tr style="border-bottom:1px solid #eee;">
                                        <td style="padding:8px 12px;font-weight:600;color:#333;">Zamanlama</td>
                                        <td style="padding:8px 12px;color:#555;">Gunluk, saat <strong>01:00</strong> (Berlin saati = TR 03:00)</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px 12px;font-weight:600;color:#333;">Bildirim</td>
                                        <td style="padding:8px 12px;color:#555;">Yalnizca Hatalar</td>
                                    </tr>
                                </table>
                                <p style="font-size:11px;color:#999;margin:0;">&#9200; Sunucu saat dilimi Europe/Berlin (UTC+1) ise: Berlin 01:00 = Turkiye 03:00</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
<?php
    }

    /* ========== OTOMATIK YEDEK FONKSIYONLARI ========== */

    public function schedule_backup()
    {
        $this->unschedule_backup();
        $hour = intval(get_option('wbak_auto_hour', '3'));
        // Turkiye saati (UTC+3) -> UTC
        $utc_hour = ($hour - 3 + 24) % 24;
        $now = time();
        $target = strtotime('today ' . $utc_hour . ':00:00 UTC');
        if ($target <= $now) {
            $target = strtotime('tomorrow ' . $utc_hour . ':00:00 UTC');
        }
        wp_schedule_event($target, 'daily', 'wbak_auto_backup_cron');
    }

    public function unschedule_backup()
    {
        $timestamp = wp_next_scheduled('wbak_auto_backup_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wbak_auto_backup_cron');
        }
    }

    public function run_auto_backup()
    {
        if (get_option('wbak_auto_enabled', '0') !== '1') return;

        // Export dosyasi yuklu mu kontrol et
        if (!class_exists('Webyaz_Backup_Export')) {
            $export_file = __DIR__ . '/class-webyaz-backup-export.php';
            if (file_exists($export_file)) {
                require_once $export_file;
            } else {
                update_option('wbak_auto_last', array(
                    'date' => time(),
                    'status' => 'error',
                    'error' => 'Export dosyasi bulunamadi',
                ));
                return;
            }
        }

        // Bellek ve sure limiti artir
        @ini_set('memory_limit', '512M');
        @set_time_limit(600);

        $result = Webyaz_Backup_Export::create_backup();

        if (is_wp_error($result)) {
            update_option('wbak_auto_last', array(
                'date' => time(),
                'status' => 'error',
                'error' => $result->get_error_message(),
            ));
        } else {
            update_option('wbak_auto_last', array(
                'date' => time(),
                'status' => 'success',
                'name' => $result['name'],
                'size' => $result['size'],
            ));

            // Eski yedekleri temizle
            $keep = intval(get_option('wbak_auto_keep', '5'));
            $this->cleanup_old_backups($keep);
        }
    }

    public function cleanup_old_backups($keep = 5)
    {
        if (!file_exists(WBAK_BACKUP_DIR)) return;

        $files = glob(WBAK_BACKUP_DIR . '*.wbak');
        if (!$files || count($files) <= $keep) return;

        // Tarihe gore sirala (en yeni en basta)
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // $keep kadar tut, gerisini sil
        $to_delete = array_slice($files, $keep);
        foreach ($to_delete as $file) {
            @unlink($file);
        }
    }

    public function handle_save_schedule()
    {
        check_admin_referer('wbak_schedule_action');
        if (!current_user_can('manage_options')) wp_die('Yetki yok');

        $enabled = isset($_POST['wbak_auto_enabled']) ? '1' : '0';
        $hour = intval($_POST['wbak_auto_hour'] ?? 3);
        $keep = intval($_POST['wbak_auto_keep'] ?? 5);

        if ($hour < 0 || $hour > 23) $hour = 3;
        if ($keep < 1 || $keep > 30) $keep = 5;

        update_option('wbak_auto_enabled', $enabled);
        update_option('wbak_auto_hour', $hour);
        update_option('wbak_auto_keep', $keep);

        if ($enabled === '1') {
            $this->schedule_backup();
        } else {
            $this->unschedule_backup();
        }

        wp_redirect(admin_url('admin.php?page=webyaz-backup&tab=schedule&wbak_msg=' . urlencode('Otomatik yedek ayarlari kaydedildi!')));
        exit;
    }

    public function handle_export()
    {
        check_admin_referer('wbak_export_action');
        if (!current_user_can('manage_options')) wp_die('Yetki yok');

        $result = Webyaz_Backup_Export::create_backup();
        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=webyaz-backup&tab=backup&wbak_err=' . urlencode($result->get_error_message())));
        } else {
            wp_redirect(admin_url('admin.php?page=webyaz-backup&tab=list&wbak_msg=' . urlencode('Yedek basariyla olusturuldu! (' . $this->format_size($result['size']) . ')')));
        }
        exit;
    }

    public function handle_import()
    {
        check_admin_referer('wbak_import_action');
        if (!current_user_can('manage_options')) wp_die('Yetki yok');

        $file_path = '';
        if (!empty($_FILES['backup_upload']['tmp_name'])) {
            $uploaded = $_FILES['backup_upload'];
            $ext = strtolower(pathinfo($uploaded['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, array('wbak', 'zip'))) {
                wp_redirect(admin_url('admin.php?page=webyaz-backup&tab=restore&wbak_err=' . urlencode('Gecersiz dosya turu. .wbak veya .zip olmali.')));
                exit;
            }
            $dest = WBAK_BACKUP_DIR . sanitize_file_name($uploaded['name']);
            move_uploaded_file($uploaded['tmp_name'], $dest);
            $file_path = $dest;
        } elseif (!empty($_POST['backup_file'])) {
            $file_path = sanitize_text_field($_POST['backup_file']);
        }

        if (empty($file_path) || !file_exists($file_path)) {
            wp_redirect(admin_url('admin.php?page=webyaz-backup&tab=restore&wbak_err=' . urlencode('Yedek dosyasi bulunamadi.')));
            exit;
        }

        $options = array(
            'new_admin_user' => sanitize_user($_POST['new_admin_user'] ?? ''),
            'new_admin_pass' => $_POST['new_admin_pass'] ?? '',
            'new_admin_email' => sanitize_email($_POST['new_admin_email'] ?? ''),
        );

        $result = Webyaz_Backup_Import::restore_backup($file_path, $options);
        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=webyaz-backup&tab=restore&wbak_err=' . urlencode($result->get_error_message())));
        } else {
            wp_redirect(admin_url('admin.php?page=webyaz-backup&tab=restore&wbak_msg=' . urlencode('Site basariyla geri yuklendi!')));
        }
        exit;
    }

    public function handle_download()
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'webyaz-backup' || !isset($_GET['wbak_dl'])) return;
        check_admin_referer('wbak_download_action');
        if (!current_user_can('manage_options')) wp_die('Yetki yok');

        $name = sanitize_file_name($_GET['wbak_dl']);
        $path = WBAK_BACKUP_DIR . $name;
        if (!file_exists($path)) wp_die('Dosya bulunamadi');

        $size = filesize($path);
        while (ob_get_level()) ob_end_clean();

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . $size);
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Expires: 0');

        set_time_limit(0);
        $fp = fopen($path, 'rb');
        if ($fp) {
            while (!feof($fp) && !connection_aborted()) {
                echo fread($fp, 8192);
                flush();
            }
            fclose($fp);
        }
        exit;
    }

    public function handle_delete()
    {
        check_admin_referer('wbak_delete_action');
        if (!current_user_can('manage_options')) wp_die('Yetki yok');

        $name = sanitize_file_name($_POST['file'] ?? '');
        $path = WBAK_BACKUP_DIR . $name;
        if (file_exists($path)) {
            @unlink($path);
            wp_redirect(admin_url('admin.php?page=webyaz-backup&tab=list&wbak_msg=' . urlencode('Yedek silindi.')));
        } else {
            wp_redirect(admin_url('admin.php?page=webyaz-backup&tab=list&wbak_err=' . urlencode('Dosya bulunamadi.')));
        }
        exit;
    }
}

new Webyaz_Backup();
