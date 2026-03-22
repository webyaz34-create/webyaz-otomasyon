<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Big_Upload
{

    public function __construct()
    {
        // PHP limitlerini artir
        add_filter('upload_size_limit', array($this, 'increase_limit'));

        // WordPress Plupload ayarlarini override et - tum sayfalarda
        add_filter('plupload_init', array($this, 'plupload_settings'));
        add_filter('plupload_default_settings', array($this, 'plupload_defaults'));

        // Chunk upload AJAX handler
        add_action('wp_ajax_webyaz_chunk_upload', array($this, 'handle_chunk'));
        add_action('wp_ajax_webyaz_chunk_merge', array($this, 'handle_merge'));

        // Admin sayfa ve scriptler
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Async upload hook - WordPress'in varsayilan upload handler'ini yakala
        add_filter('wp_handle_upload_prefilter', array($this, 'check_file_size'));

        // Temp dosya temizligi (gunluk)
        add_action('wp_scheduled_delete', array($this, 'cleanup_temp'));
    }

    private static function get_opts()
    {
        return wp_parse_args(get_option('webyaz_big_upload', array()), array(
            'max_size'   => 512,
            'chunk_size' => 2,
        ));
    }

    public function register_settings()
    {
        register_setting('webyaz_big_upload_group', 'webyaz_big_upload');
    }

    // WordPress upload limitini artir
    public function increase_limit()
    {
        $opts = self::get_opts();
        return $opts['max_size'] * 1024 * 1024;
    }

    // PHP ayarlarini runtime'da override et
    private static function push_php_limits()
    {
        $opts = self::get_opts();
        $mb = $opts['max_size'];
        @ini_set('upload_max_filesize', $mb . 'M');
        @ini_set('post_max_size', ($mb + 10) . 'M');
        @ini_set('max_execution_time', '600');
        @ini_set('max_input_time', '600');
        @ini_set('memory_limit', '512M');
    }

    // Buyuk dosya kontrolu - WordPress upload oncesi
    public function check_file_size($file)
    {
        $opts = self::get_opts();
        $max = $opts['max_size'] * 1024 * 1024;
        // WordPress hata vermesin, biz halledelim
        if ($file['size'] <= $max) {
            self::push_php_limits();
        }
        return $file;
    }

    // Plupload init ayarlari - ASIL CHUNKLAMA BURDA
    public function plupload_settings($settings)
    {
        $opts = self::get_opts();
        $chunk_bytes = $opts['chunk_size'] * 1024 * 1024;

        // Max dosya boyutu
        $settings['filters']['max_file_size'] = $opts['max_size'] . 'mb';

        // Chunk ayarlari - plupload'u parcali upload moduna al
        $settings['chunk_size'] = $chunk_bytes;
        $settings['max_retries'] = 5;

        // URL yerine bizim handler'a yonlendir
        $settings['url'] = admin_url('admin-ajax.php');

        // Multipart params
        if (!isset($settings['multipart_params'])) {
            $settings['multipart_params'] = array();
        }
        $settings['multipart_params']['action'] = 'webyaz_chunk_upload';
        $settings['multipart_params']['nonce'] = wp_create_nonce('webyaz_nonce');

        return $settings;
    }

    // Plupload default settings (medya-yukleyici modal icin)
    public function plupload_defaults($settings)
    {
        $opts = self::get_opts();
        $chunk_bytes = $opts['chunk_size'] * 1024 * 1024;

        if (!isset($settings['defaults'])) $settings['defaults'] = array();
        $settings['defaults']['filters'] = array(
            'max_file_size' => $opts['max_size'] . 'mb',
        );
        $settings['defaults']['chunk_size'] = $chunk_bytes;
        $settings['defaults']['max_retries'] = 5;

        if (!isset($settings['defaults']['multipart_params'])) {
            $settings['defaults']['multipart_params'] = array();
        }
        $settings['defaults']['multipart_params']['action'] = 'webyaz_chunk_upload';
        $settings['defaults']['multipart_params']['nonce'] = wp_create_nonce('webyaz_nonce');

        return $settings;
    }

    // Admin scripti yukle - TUM admin sayfalarinda (native uploader icin)
    public function enqueue_scripts($hook)
    {
        // Admin tarafinda webyaz_ajax objesi tanimli degilse olustur
        wp_add_inline_script('jquery', '
            if (typeof webyaz_ajax === "undefined") {
                var webyaz_ajax = {
                    ajax_url: "' . admin_url('admin-ajax.php') . '",
                    nonce: "' . wp_create_nonce('webyaz_nonce') . '"
                };
            }
        ');

        // Sadece kendi sayfamiz icin degil, TUM sayfalarda inject et
        // boylece yazi/urun ekleme vb yerlerde de calisir
        wp_add_inline_script('wp-plupload', $this->get_plupload_override_js(), 'after');

        // Kendi sayfamiz icin ek
        if (strpos($hook, 'webyaz-big-upload') !== false) {
            wp_enqueue_media();
        }
    }

    // Plupload override - chunk upload tamamlaninca merge tetikle
    private function get_plupload_override_js()
    {
        $opts = self::get_opts();
        return '
(function(){
    if (typeof wp === "undefined" || !wp.Uploader) return;

    var origInit = wp.Uploader.prototype.init;
    wp.Uploader.prototype.init = function() {
        origInit.apply(this, arguments);
        var uploader = this.uploader;
        if (!uploader) return;

        uploader.bind("ChunkUploaded", function(up, file, info) {
            try {
                var r = JSON.parse(info.response);
                if (!r.success) {
                    file.status = plupload.FAILED;
                    up.trigger("Error", {
                        code: plupload.HTTP_ERROR,
                        message: r.data || "Chunk hatasi",
                        file: file
                    });
                }
            } catch(e) {}
        });

        uploader.bind("FileUploaded", function(up, file, info) {
            try {
                var r = JSON.parse(info.response);
                if (r.success && r.data && r.data.needs_merge) {
                    // Son chunk geldi, merge yap
                    file.status = plupload.UPLOADING;
                    jQuery.post(ajaxurl, {
                        action: "webyaz_chunk_merge",
                        nonce: "' . wp_create_nonce('webyaz_nonce') . '",
                        name: file.name,
                        chunks: r.data.chunks
                    }, function(mergeRes) {
                        if (mergeRes.success) {
                            // WP medya kutuphanesini guncelle
                            var attachment = mergeRes.data;
                            if (typeof wp.media !== "undefined" && wp.media.model && wp.media.model.Attachment) {
                                var a = wp.media.model.Attachment.create(attachment);
                                if (wp.Uploader.queue) wp.Uploader.queue.add(a);
                            }
                            if (typeof wp.Uploader.queue !== "undefined") {
                                wp.Uploader.queue.reset();
                            }
                            // Medya kutuphanesini yenile
                            if (wp.media && wp.media.frame) {
                                wp.media.frame.content.mode("browse");
                                if (wp.media.frame.content.get()) {
                                    wp.media.frame.content.get().collection.more();
                                }
                            }
                        }
                    });
                }
            } catch(e) {}
        });
    };
})();';
    }

    // Temp dir yolu
    private static function temp_dir()
    {
        $upload_dir = wp_upload_dir();
        $dir = $upload_dir['basedir'] . '/webyaz-temp/';
        if (!file_exists($dir)) wp_mkdir_p($dir);

        // .htaccess koru
        $htaccess = $dir . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "deny from all\n");
        }

        return $dir;
    }

    // Chunk upload handler
    public function handle_chunk()
    {
        self::push_php_limits();
        check_ajax_referer('webyaz_nonce', 'nonce');
        if (!current_user_can('upload_files')) wp_send_json_error('Yetki yok');

        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Dosya yuklenemedi (hata: ' . ($file['error'] ?? 'yok') . ')');
        }

        $chunk  = intval($_POST['chunk'] ?? 0);
        $chunks = intval($_POST['chunks'] ?? 1);
        $name   = sanitize_file_name($_POST['name'] ?? $file['name']);

        $temp_dir = self::temp_dir();
        $uid = md5($name . wp_get_current_user()->ID . date('Y-m-d'));
        $chunk_path = $temp_dir . $uid . '.part' . $chunk;

        if (!move_uploaded_file($file['tmp_name'], $chunk_path)) {
            wp_send_json_error('Parca kaydedilemedi');
        }

        $is_last = ($chunk + 1 >= $chunks);

        // Tek parcaliysa hemen merge et
        if ($chunks <= 1) {
            $this->do_merge($name, $chunks);
            return;
        }

        wp_send_json_success(array(
            'chunk'      => $chunk,
            'chunks'     => $chunks,
            'filename'   => $name,
            'needs_merge' => $is_last,
        ));
    }

    // Merge handler
    public function handle_merge()
    {
        self::push_php_limits();
        check_ajax_referer('webyaz_nonce', 'nonce');
        if (!current_user_can('upload_files')) wp_send_json_error('Yetki yok');

        $name   = sanitize_file_name($_POST['name'] ?? '');
        $chunks = intval($_POST['chunks'] ?? 1);
        if (!$name) wp_send_json_error('Dosya adi bos');

        $this->do_merge($name, $chunks);
    }

    // Parcalari birlestir ve medya kutuphanesine ekle
    private function do_merge($name, $chunks)
    {
        $temp_dir   = self::temp_dir();
        $upload_dir = wp_upload_dir();
        $uid = md5($name . wp_get_current_user()->ID . date('Y-m-d'));

        $final_path = $temp_dir . 'merged_' . $uid . '_' . $name;
        $out = @fopen($final_path, 'wb');
        if (!$out) wp_send_json_error('Dosya olusturulamadi');

        for ($i = 0; $i < $chunks; $i++) {
            $part = $temp_dir . $uid . '.part' . $i;

            // 10 saniye bekle - async chunk'lar icin
            $wait = 0;
            while (!file_exists($part) && $wait < 10) {
                usleep(500000); // 0.5 sn
                $wait++;
            }

            if (!file_exists($part)) {
                fclose($out);
                @unlink($final_path);
                wp_send_json_error('Parca eksik: ' . $i . '/' . $chunks);
            }

            $in = fopen($part, 'rb');
            while (!feof($in)) {
                $buf = fread($in, 8192);
                if ($buf !== false) fwrite($out, $buf);
            }
            fclose($in);
            @unlink($part);
        }
        fclose($out);

        // Dosya tipi kontrol
        $file_type = wp_check_filetype($name);
        if (!$file_type['type']) {
            @unlink($final_path);
            wp_send_json_error('Desteklenmeyen dosya turu: ' . pathinfo($name, PATHINFO_EXTENSION));
        }

        // Hedef yola tasi
        $dest_name = wp_unique_filename($upload_dir['path'], $name);
        $dest = $upload_dir['path'] . '/' . $dest_name;

        if (!rename($final_path, $dest)) {
            @unlink($final_path);
            wp_send_json_error('Dosya tasinamadi');
        }

        // WordPress medya kutuphanesine kaydet
        $attach = array(
            'guid'           => $upload_dir['url'] . '/' . $dest_name,
            'post_mime_type' => $file_type['type'],
            'post_title'     => preg_replace('/\.[^.]+$/', '', $dest_name),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );
        $attach_id = wp_insert_attachment($attach, $dest);

        if (!is_wp_error($attach_id)) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $meta = wp_generate_attachment_metadata($attach_id, $dest);
            wp_update_attachment_metadata($attach_id, $meta);
        }

        $size = @filesize($dest) ?: 0;

        wp_send_json_success(array(
            'id'       => $attach_id,
            'url'      => wp_get_attachment_url($attach_id),
            'filename' => $dest_name,
            'size'     => size_format($size),
            'type'     => $file_type['type'],
            'title'    => preg_replace('/\.[^.]+$/', '', $dest_name),
        ));
    }

    // Gecici dosyalari temizle (24 saatten eski)
    public function cleanup_temp()
    {
        $dir = self::temp_dir();
        if (!is_dir($dir)) return;
        $files = glob($dir . '*.part*');
        $now = time();
        foreach ($files as $f) {
            if ($now - filemtime($f) > 86400) @unlink($f);
        }
        // Merged dosyalari da temizle
        $merged = glob($dir . 'merged_*');
        foreach ($merged as $f) {
            if ($now - filemtime($f) > 86400) @unlink($f);
        }
    }

    // Admin menu
    public function add_submenu()
    {
        add_submenu_page('webyaz-dashboard', 'Buyuk Dosya Yukle', 'Buyuk Dosya Yukle', 'manage_options', 'webyaz-big-upload', array($this, 'render_admin'));
    }

    // Admin sayfasi
    // PHP boyut stringini MB'a cevir (orn: "128M" -> 128)
    private static function parse_php_size($val)
    {
        $val = trim($val);
        $num = (int) $val;
        $unit = strtolower(substr($val, -1));
        if ($unit === 'g') return $num * 1024;
        if ($unit === 'm') return $num;
        if ($unit === 'k') return max(1, (int)($num / 1024));
        return $num;
    }

    // Sunucu PHP limitine gore max guvenli chunk boyutu (MB)
    private static function get_server_max_chunk_mb()
    {
        $upload = self::parse_php_size(@ini_get('upload_max_filesize') ?: '2M');
        $post   = self::parse_php_size(@ini_get('post_max_size') ?: '8M');
        // En dusuk limit belirleyici, biraz pay birak
        $limit = min($upload, $post);
        return max(1, $limit - 1); // 1MB pay birak
    }

    // Admin sayfasi
    public function render_admin()
    {
        $opts = self::get_opts();
        $server_max_chunk = self::get_server_max_chunk_mb();

        if (isset($_POST['webyaz_big_upload_save']) && check_admin_referer('webyaz_big_upload_nonce')) {
            $chunk_input = max(1, intval($_POST['chunk_size'] ?? 2));
            // Sunucu limitini asmamasi icin otomatik kirp
            $safe_chunk = min($chunk_input, $server_max_chunk);
            $opts = array(
                'max_size'   => max(10, intval($_POST['max_size'] ?? 512)),
                'chunk_size' => $safe_chunk,
            );
            update_option('webyaz_big_upload', $opts);
            $msg = 'Kaydedildi!';
            if ($chunk_input > $server_max_chunk) {
                $msg .= ' (Parca boyutu sunucu limiti nedeniyle ' . $safe_chunk . 'MB olarak ayarlandi)';
            }
            echo '<div class="webyaz-notice success" style="background:#e6f9e6;color:#2e7d32;border:1px solid #b7e4c7;padding:12px 18px;border-radius:8px;margin-bottom:15px;">' . $msg . '</div>';
        }

        // Mevcut chunk_size sunucu limitini asiyorsa otomatik dusur
        if ($opts['chunk_size'] > $server_max_chunk) {
            $opts['chunk_size'] = $server_max_chunk;
            update_option('webyaz_big_upload', $opts);
        }

        $php_max  = @ini_get('upload_max_filesize') ?: '?';
        $post_max = @ini_get('post_max_size') ?: '?';

        $primary = '#446084';
        $secondary = '#d26e4b';
        if (class_exists('Webyaz_Colors')) {
            $colors = Webyaz_Colors::get_theme_colors();
            $primary = $colors['primary'];
            $secondary = $colors['secondary'];
        }
?>
        <div class="webyaz-admin-wrap" style="max-width:900px;margin:20px 0;font-family:-apple-system,BlinkMacSystemFont,sans-serif;">

            <div style="background:linear-gradient(135deg,<?php echo $primary; ?>,<?php echo $secondary; ?>);color:#fff;padding:30px 35px;border-radius:12px;margin-bottom:25px;">
                <h1 style="margin:0 0 5px;font-size:26px;font-weight:700;">Buyuk Dosya Yukleme</h1>
                <p style="margin:0;opacity:.85;font-size:14px;">PHP sinirlarini bypass ederek buyuk dosyalari <?php echo $opts['chunk_size']; ?>MB parcalar halinde yukleyin</p>
            </div>

            <!-- Sunucu Bilgileri -->
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:25px;">
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;">
                    <div style="font-size:11px;color:#999;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">PHP Upload</div>
                    <div style="font-size:22px;font-weight:700;color:#d32f2f;"><?php echo esc_html($php_max); ?></div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;">
                    <div style="font-size:11px;color:#999;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">PHP Post</div>
                    <div style="font-size:22px;font-weight:700;color:#d32f2f;"><?php echo esc_html($post_max); ?></div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;">
                    <div style="font-size:11px;color:#999;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Parca Boyutu</div>
                    <div style="font-size:22px;font-weight:700;color:<?php echo $primary; ?>;"><?php echo $opts['chunk_size']; ?>MB</div>
                </div>
                <div style="background:#f0f7ff;border:2px solid <?php echo $primary; ?>;border-radius:10px;padding:16px;text-align:center;">
                    <div style="font-size:11px;color:<?php echo $primary; ?>;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;font-weight:700;">Webyaz Limit</div>
                    <div style="font-size:22px;font-weight:700;color:#2e7d32;"><?php echo $opts['max_size']; ?>MB</div>
                </div>
            </div>

            <!-- Nasil Calisir -->
            <div style="background:linear-gradient(135deg,#1e1e2e,#181825);border:1px solid #313244;border-radius:10px;padding:20px 24px;margin-bottom:25px;">
                <div style="color:#cdd6f4;font-size:15px;font-weight:700;margin-bottom:10px;">Nasil Calisiyor?</div>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
                    <div style="text-align:center;">
                        <div style="font-size:28px;margin-bottom:6px;">📦</div>
                        <div style="color:#89b4fa;font-size:12px;font-weight:600;">1. Parcala</div>
                        <div style="color:#6c7086;font-size:11px;">Dosya <?php echo $opts['chunk_size']; ?>MB parcalara bolunur</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:28px;margin-bottom:6px;">🚀</div>
                        <div style="color:#89b4fa;font-size:12px;font-weight:600;">2. Yukle</div>
                        <div style="color:#6c7086;font-size:11px;">Her parca ayri ayri yuklenir</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:28px;margin-bottom:6px;">🔗</div>
                        <div style="color:#89b4fa;font-size:12px;font-weight:600;">3. Birlestir</div>
                        <div style="color:#6c7086;font-size:11px;">Sunucuda parcalar birlesiyor</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:28px;margin-bottom:6px;">✅</div>
                        <div style="color:#89b4fa;font-size:12px;font-weight:600;">4. Tamamla</div>
                        <div style="color:#6c7086;font-size:11px;">Medya kutuphanesine eklenir</div>
                    </div>
                </div>
            </div>

            <!-- Ayarlar -->
            <form method="post">
                <?php wp_nonce_field('webyaz_big_upload_nonce'); ?>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px;margin-bottom:25px;">
                    <h2 style="font-size:18px;font-weight:700;margin:0 0 16px;color:#1e1e2e;">Ayarlar</h2>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        <div>
                            <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:6px;">Maksimum Dosya Boyutu (MB)</label>
                            <input type="number" name="max_size" value="<?php echo esc_attr($opts['max_size']); ?>" min="10" max="10240" style="width:100%;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:15px;">
                            <p style="font-size:11px;color:#999;margin-top:4px;">Ornek: 512MB, 1024MB (1GB), 2048MB (2GB)</p>
                        </div>
                        <div>
                            <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:6px;">Parca Boyutu (MB)</label>
                            <input type="number" name="chunk_size" value="<?php echo esc_attr($opts['chunk_size']); ?>" min="1" max="<?php echo esc_attr($server_max_chunk); ?>" style="width:100%;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:15px;">
                            <p style="font-size:11px;color:#999;margin-top:4px;">Sunucu limiti: <strong style="color:#d32f2f;"><?php echo $php_max; ?></strong> (upload) / <strong style="color:#d32f2f;"><?php echo $post_max; ?></strong> (post) &bull; Maks parca: <strong style="color:#2e7d32;"><?php echo $server_max_chunk; ?>MB</strong></p>
                        </div>
                    </div>
                    <button type="submit" name="webyaz_big_upload_save" value="1" style="margin-top:16px;background:<?php echo $primary; ?>;color:#fff;border:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">Kaydet</button>
                </div>
            </form>

            <!-- Dosya Yukle -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px;">
                <h2 style="font-size:18px;font-weight:700;margin:0 0 16px;color:#1e1e2e;">Dosya Yukle</h2>

                <div id="wbUploadZone" style="border:3px dashed #ccc;border-radius:16px;padding:50px 40px;text-align:center;cursor:pointer;transition:all 0.3s;background:#fafafa;">
                    <div style="font-size:48px;margin-bottom:10px;">📤</div>
                    <p style="font-size:16px;color:#555;font-weight:600;margin:0 0 6px;">Dosyalari buraya surukleyin veya tiklayin</p>
                    <p style="font-size:13px;color:#999;margin:0;">Maks: <strong><?php echo $opts['max_size']; ?>MB</strong> | Parca: <strong><?php echo $opts['chunk_size']; ?>MB</strong> | PHP limiti bypass edilir</p>
                    <input type="file" id="wbUploadInput" multiple style="display:none;">
                </div>

                <div id="wbUploadList" style="margin-top:16px;"></div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                var chunkSize = <?php echo $opts['chunk_size'] * 1024 * 1024; ?>;
                var maxSize = <?php echo $opts['max_size'] * 1024 * 1024; ?>;
                var zone = document.getElementById('wbUploadZone');
                var input = document.getElementById('wbUploadInput');

                zone.addEventListener('click', function() {
                    input.click();
                });
                zone.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.style.borderColor = '<?php echo $primary; ?>';
                    this.style.background = '#f0f4f8';
                });
                zone.addEventListener('dragleave', function() {
                    this.style.borderColor = '#ccc';
                    this.style.background = '#fafafa';
                });
                zone.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.style.borderColor = '#ccc';
                    this.style.background = '#fafafa';
                    handleFiles(e.dataTransfer.files);
                });
                input.addEventListener('change', function() {
                    handleFiles(this.files);
                    this.value = '';
                });

                function handleFiles(files) {
                    for (var i = 0; i < files.length; i++) {
                        if (files[i].size > maxSize) {
                            alert(files[i].name + ' dosyasi cok buyuk! Maks: <?php echo $opts["max_size"]; ?>MB');
                            continue;
                        }
                        uploadFile(files[i]);
                    }
                }

                function formatSize(bytes) {
                    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
                    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
                    return (bytes / 1024).toFixed(0) + ' KB';
                }

                function formatTime(secs) {
                    if (secs < 60) return Math.round(secs) + ' sn';
                    return Math.floor(secs / 60) + ' dk ' + Math.round(secs % 60) + ' sn';
                }

                function uploadFile(file) {
                    var id = 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
                    var html = '<div id="' + id + '" style="padding:16px;background:#f9f9f9;border:1px solid #e8e8e8;border-radius:12px;margin-bottom:10px;">';
                    html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">';
                    html += '<div style="display:flex;align-items:center;gap:8px;"><span style="font-size:20px;">📄</span><strong style="font-size:14px;">' + file.name + '</strong></div>';
                    html += '<span style="font-size:13px;color:#888;font-weight:600;">' + formatSize(file.size) + '</span>';
                    html += '</div>';
                    html += '<div style="background:#e0e0e0;border-radius:8px;height:10px;overflow:hidden;margin-bottom:6px;">';
                    html += '<div class="wb-prog-bar" style="background:linear-gradient(90deg,<?php echo $primary; ?>,<?php echo $secondary; ?>);height:100%;width:0%;transition:width 0.2s;border-radius:8px;"></div>';
                    html += '</div>';
                    html += '<div style="display:flex;justify-content:space-between;align-items:center;">';
                    html += '<div class="wb-prog-text" style="font-size:12px;color:#888;">Baslatiliyor...</div>';
                    html += '<div class="wb-speed" style="font-size:11px;color:#aaa;"></div>';
                    html += '</div></div>';
                    $('#wbUploadList').prepend(html);

                    var chunks = Math.ceil(file.size / chunkSize);
                    var currentChunk = 0;
                    var retries = 0;
                    var maxRetries = 5;
                    var startTime = Date.now();

                    function sendChunk() {
                        var start = currentChunk * chunkSize;
                        var end = Math.min(start + chunkSize, file.size);
                        var blob = file.slice(start, end);

                        var fd = new FormData();
                        fd.append('action', 'webyaz_chunk_upload');
                        fd.append('nonce', webyaz_ajax.nonce);
                        fd.append('file', blob, file.name);
                        fd.append('chunk', currentChunk);
                        fd.append('chunks', chunks);
                        fd.append('name', file.name);

                        $.ajax({
                            url: webyaz_ajax.ajax_url,
                            type: 'POST',
                            data: fd,
                            processData: false,
                            contentType: false,
                            timeout: 120000,
                            success: function(res) {
                                if (!res.success) {
                                    handleError('Sunucu hatasi: ' + (res.data || ''));
                                    return;
                                }
                                retries = 0;
                                currentChunk++;
                                var pct = Math.round(currentChunk / chunks * 100);
                                var elapsed = (Date.now() - startTime) / 1000;
                                var speed = (currentChunk * chunkSize) / elapsed;
                                var remaining = ((chunks - currentChunk) * chunkSize) / speed;

                                $('#' + id + ' .wb-prog-bar').css('width', pct + '%');
                                $('#' + id + ' .wb-prog-text').text(pct + '% (' + currentChunk + '/' + chunks + ' parca)');
                                $('#' + id + ' .wb-speed').text(formatSize(speed) + '/sn | ~' + formatTime(remaining));

                                if (currentChunk < chunks) {
                                    sendChunk();
                                } else {
                                    $('#' + id + ' .wb-prog-text').text('Parcalar birlestiriliyor...');
                                    $('#' + id + ' .wb-speed').text('');
                                    mergeChunks(id, file.name, chunks);
                                }
                            },
                            error: function(xhr, status) {
                                handleError(status === 'timeout' ? 'Zaman asimi' : 'Baglanti hatasi');
                            }
                        });
                    }

                    function handleError(msg) {
                        retries++;
                        if (retries <= maxRetries) {
                            $('#' + id + ' .wb-prog-text').html('<span style="color:#e65100;">⚠ ' + msg + ' - Tekrar deneniyor (' + retries + '/' + maxRetries + ')...</span>');
                            setTimeout(sendChunk, 2000 * retries);
                        } else {
                            $('#' + id + ' .wb-prog-bar').css('background', 'linear-gradient(90deg,#d32f2f,#ef5350)');
                            $('#' + id + ' .wb-prog-text').html('<span style="color:#d32f2f;font-weight:600;">✗ Basarisiz: ' + msg + '</span>');
                        }
                    }

                    sendChunk();
                }

                function mergeChunks(id, filename, chunks) {
                    $.ajax({
                        url: webyaz_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'webyaz_chunk_merge',
                            nonce: webyaz_ajax.nonce,
                            name: filename,
                            chunks: chunks
                        },
                        timeout: 300000,
                        success: function(res) {
                            if (res.success) {
                                var d = res.data;
                                $('#' + id + ' .wb-prog-bar').css({
                                    width: '100%',
                                    background: 'linear-gradient(90deg,#4caf50,#66bb6a)'
                                });
                                $('#' + id + ' .wb-prog-text').html('<span style="color:#2e7d32;font-weight:700;">✓ ' + d.filename + ' (' + d.size + ')</span>');
                                $('#' + id + ' .wb-speed').text('Medya kutuphanesine eklendi');
                                $('#' + id).css({
                                    background: '#f0f9f0',
                                    borderColor: '#b7e4c7'
                                });
                            } else {
                                $('#' + id + ' .wb-prog-bar').css('background', 'linear-gradient(90deg,#d32f2f,#ef5350)');
                                $('#' + id + ' .wb-prog-text').html('<span style="color:#d32f2f;">✗ Birlestirme hatasi: ' + (res.data || '') + '</span>');
                            }
                        },
                        error: function() {
                            $('#' + id + ' .wb-prog-text').html('<span style="color:#d32f2f;">✗ Birlestirme sirasinda baglanti hatasi</span>');
                        }
                    });
                }
            });
        </script>
<?php
    }
}

new Webyaz_Big_Upload();
