<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Bulk_WebP
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('wp_ajax_webyaz_bulk_webp', array($this, 'ajax_convert'));
        add_action('wp_ajax_webyaz_bulk_webp_count', array($this, 'ajax_count'));
        add_filter('wp_get_attachment_url', array($this, 'serve_webp'), 10, 2);
        add_filter('wp_get_attachment_image_src', array($this, 'serve_webp_src'), 10, 4);
        add_filter('the_content', array($this, 'replace_in_content'), 99);
    }

    public function serve_webp($url, $id)
    {
        if (!$this->browser_supports_webp()) return $url;
        $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $url);
        if ($webp_url === $url) return $url;
        $upload_dir = wp_upload_dir();
        $webp_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $webp_url);
        return file_exists($webp_path) ? $webp_url : $url;
    }

    public function serve_webp_src($image, $id, $size, $icon)
    {
        if (!$image || !$this->browser_supports_webp()) return $image;
        $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $image[0]);
        if ($webp_url === $image[0]) return $image;
        $upload_dir = wp_upload_dir();
        $webp_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $webp_url);
        if (file_exists($webp_path)) $image[0] = $webp_url;
        return $image;
    }

    public function replace_in_content($content)
    {
        if (!$this->browser_supports_webp()) return $content;
        $upload_dir = wp_upload_dir();
        return preg_replace_callback('/(["\'])(' . preg_quote($upload_dir['baseurl'], '/') . '\/[^"\']+\.(jpe?g|png))(["\'])/i', function ($m) use ($upload_dir) {
            $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $m[2]);
            $webp_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $webp_url);
            return $m[1] . (file_exists($webp_path) ? $webp_url : $m[2]) . $m[4];
        }, $content);
    }

    private function browser_supports_webp()
    {
        return isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false;
    }

    public function add_submenu()
    {
        add_submenu_page('webyaz-dashboard', 'Toplu WebP', 'Toplu WebP', 'manage_options', 'webyaz-bulk-webp', array($this, 'render'));
    }

    public function ajax_count()
    {
        check_ajax_referer('webyaz_bulk_webp_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Yetki yok');

        // Sunucu destegi kontrol
        if (!function_exists('imagecreatefrompng') || !function_exists('imagewebp')) {
            wp_send_json_error('Sunucunuzda GD kutuphanesi veya WebP destegi bulunamadi. Hosting firmanizdan GD + WebP destegi isteyiniz.');
            return;
        }

        $files = $this->scan_media_images();
        wp_send_json_success(array(
            'total' => count($files),
            'webp_support' => function_exists('imagewebp') ? true : false,
        ));
    }

    public function ajax_convert()
    {
        check_ajax_referer('webyaz_bulk_webp_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Yetki yok');

        // Sunucu destegi kontrol
        if (!function_exists('imagewebp')) {
            wp_send_json_error('Sunucunuzda WebP destegi (imagewebp fonksiyonu) bulunamadi.');
            return;
        }

        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $quality = intval($_POST['quality'] ?? 82);
        $offset = intval($_POST['offset'] ?? 0);
        $batch = intval($_POST['batch'] ?? 5);

        if ($quality < 1 || $quality > 100) $quality = 82;
        if ($batch < 1 || $batch > 50) $batch = 5;

        $files = $this->scan_media_images();
        $total = count($files);
        $converted = 0;
        $skipped = 0;
        $details = array();
        $saved = 0;

        $chunk = array_slice($files, $offset, $batch);
        foreach ($chunk as $file) {
            $result = $this->convert_single($file, $quality);
            $fname = basename($file);

            if ($result['status'] === 'ok') {
                $converted++;
                $saved += $result['saved'];
                $details[] = '✅ ' . $fname . ' → WebP (' . round($result['saved'] / 1024) . ' KB tasarruf)';
            } elseif ($result['status'] === 'skipped') {
                $skipped++;
                $details[] = '⏭ ' . $fname . ': ' . $result['reason'];
            } else {
                $skipped++;
                $details[] = '❌ ' . $fname . ': ' . $result['reason'];
            }
        }

        wp_send_json_success(array(
            'total' => $total,
            'offset' => $offset + $batch,
            'converted' => $converted,
            'skipped' => $skipped,
            'saved_bytes' => $saved,
            'details' => $details,
            'done' => ($offset + $batch) >= $total,
        ));
    }

    /**
     * Sadece WordPress ortam kutuphanesindeki resimleri ve boyutlarini tarar
     */
    private function scan_media_images()
    {
        global $wpdb;
        $images = array();

        // Ortam kutuphanesindeki tum resim attachment'larini al
        $attachments = $wpdb->get_results(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'attachment' 
             AND post_mime_type IN ('image/jpeg', 'image/png')
             ORDER BY ID ASC"
        );

        if (empty($attachments)) return $images;

        foreach ($attachments as $att) {
            // Ana dosya yolu
            $file = get_attached_file($att->ID);
            if (!$file || !file_exists($file)) continue;

            // Ana dosya - webp yoksa ekle
            $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file);
            if (!file_exists($webp)) {
                $images[] = $file;
            }

            // Boyutlandirilmis versiyonlar (thumbnail, medium, large vs.)
            $metadata = wp_get_attachment_metadata($att->ID);
            if (!empty($metadata['sizes'])) {
                $dir = dirname($file);
                foreach ($metadata['sizes'] as $size_data) {
                    $size_file = $dir . '/' . $size_data['file'];
                    if (!file_exists($size_file)) continue;
                    $ext = strtolower(pathinfo($size_file, PATHINFO_EXTENSION));
                    if (!in_array($ext, array('jpg', 'jpeg', 'png'))) continue;
                    $size_webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $size_file);
                    if (!file_exists($size_webp)) {
                        $images[] = $size_file;
                    }
                }
            }
        }

        return $images;
    }

    private function convert_single($path, $quality)
    {
        // imagewebp fonksiyonu var mi?
        if (!function_exists('imagewebp')) {
            return array('status' => 'error', 'reason' => 'imagewebp fonksiyonu yok', 'saved' => 0);
        }

        // Dosya var mi, okunabilir mi?
        if (!file_exists($path)) {
            return array('status' => 'error', 'reason' => 'Dosya bulunamadi: ' . $path, 'saved' => 0);
        }
        if (!is_readable($path)) {
            return array('status' => 'error', 'reason' => 'Dosya okunamiyor (izin sorunu)', 'saved' => 0);
        }

        // Hedef klasore yazilabilir mi?
        $dir = dirname($path);
        if (!is_writable($dir)) {
            return array('status' => 'error', 'reason' => 'Klasore yazilamiyor: ' . $dir, 'saved' => 0);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $img = null;

        if (in_array($ext, array('jpg', 'jpeg'))) {
            $img = @imagecreatefromjpeg($path);
            if (!$img) {
                return array('status' => 'error', 'reason' => 'JPEG okunamadi (bozuk veya cok buyuk)', 'saved' => 0);
            }
        } elseif ($ext === 'png') {
            $img = @imagecreatefrompng($path);
            if (!$img) {
                return array('status' => 'error', 'reason' => 'PNG okunamadi (bozuk veya cok buyuk)', 'saved' => 0);
            }
            imagepalettetotruecolor($img);
            imagealphablending($img, true);
            imagesavealpha($img, true);
        } else {
            return array('status' => 'error', 'reason' => 'Desteklenmeyen format: ' . $ext, 'saved' => 0);
        }

        $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
        $orig_size = filesize($path);

        // WebP olustur
        $ok = @imagewebp($img, $webp_path, $quality);
        imagedestroy($img);

        if (!$ok) {
            return array('status' => 'error', 'reason' => 'imagewebp() basarisiz oldu', 'saved' => 0);
        }

        if (!file_exists($webp_path)) {
            return array('status' => 'error', 'reason' => 'WebP dosyasi olusturulamadi (izin sorunu?)', 'saved' => 0);
        }

        $new_size = filesize($webp_path);

        // WebP daha buyukse sil
        if ($new_size >= $orig_size) {
            @unlink($webp_path);
            return array('status' => 'skipped', 'reason' => 'WebP daha buyuk (' . round($new_size / 1024) . 'KB > ' . round($orig_size / 1024) . 'KB)', 'saved' => 0);
        }

        return array('status' => 'ok', 'reason' => '', 'saved' => $orig_size - $new_size);
    }

    public function render()
    {
        $has_gd = function_exists('gd_info');
        $has_webp = function_exists('imagewebp');
        $has_jpeg = function_exists('imagecreatefromjpeg');
        $has_png = function_exists('imagecreatefrompng');
        $nonce = wp_create_nonce('webyaz_bulk_webp_nonce');
?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Toplu WebP Donusturme</h1>
                <p>Mevcut JPG/PNG resimleri topluca WebP formatina cevir</p>
            </div>

            <?php if (!$has_gd || !$has_webp): ?>
                <div style="background:#ffebee;border:1px solid #ffcdd2;border-radius:10px;padding:16px 20px;margin:16px 0;color:#c62828;font-size:14px;">
                    <strong>&#9888; Sunucu Uyarisi:</strong><br>
                    <?php if (!$has_gd): ?>GD kutuphanesi bulunamadi. Hosting firmanizdan PHP GD uzantisini aktif etmesini isteyiniz.<br><?php endif; ?>
                <?php if (!$has_webp): ?>WebP destegi (imagewebp) bulunamadi. PHP'nin WebP destekli GD ile derlenmis olmasi gerekir.<br><?php endif; ?>
            <?php if (!$has_jpeg): ?>JPEG destegi (imagecreatefromjpeg) bulunamadi.<br><?php endif; ?>
        <?php if (!$has_png): ?>PNG destegi (imagecreatefrompng) bulunamadi.<br><?php endif; ?>
                </div>
            <?php else: ?>
                <div style="background:#e8f5e9;border:1px solid #c8e6c9;border-radius:10px;padding:12px 20px;margin:16px 0;color:#2e7d32;font-size:13px;">
                    &#9989; Sunucu destegi: GD &#10004; | WebP &#10004; | JPEG &#10004; | PNG &#10004;
                </div>
            <?php endif; ?>

            <div class="webyaz-settings-section">
                <div class="webyaz-settings-grid">
                    <div class="webyaz-field"><label>Kalite (1-100)</label><input type="number" id="wbWebpQuality" value="82" min="1" max="100"></div>
                    <div class="webyaz-field"><label>Parti Boyutu</label><input type="number" id="wbWebpBatch" value="5" min="1" max="20"></div>
                </div>
                <div style="margin-top:16px;display:flex;gap:10px;">
                    <button id="wbWebpStart" type="button" class="webyaz-btn webyaz-btn-primary" style="padding:12px 28px;">&#128247; Donusturmeyi Baslat</button>
                    <button id="wbWebpCount" type="button" class="webyaz-btn webyaz-btn-outline" style="padding:12px 28px;">&#128270; Kac Resim Var?</button>
                </div>
                <div id="wbWebpProgress" style="display:none;margin-top:20px;">
                    <div style="background:#e0e0e0;border-radius:8px;height:24px;overflow:hidden;margin-bottom:10px;">
                        <div id="wbWebpBar" style="background:linear-gradient(90deg,#4caf50,#66bb6a);height:100%;width:0%;transition:width 0.3s;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                            <span id="wbWebpPct" style="color:#fff;font-size:11px;font-weight:700;text-shadow:0 1px 2px rgba(0,0,0,0.3);"></span>
                        </div>
                    </div>
                    <p id="wbWebpStatus" style="font-size:14px;color:#555;"></p>
                </div>
                <div id="wbWebpLog" style="display:none;margin-top:12px;background:#f8f9fa;border-radius:8px;padding:12px 16px;max-height:150px;overflow-y:auto;font-size:12px;font-family:monospace;color:#555;line-height:1.8;"></div>
                <div id="wbWebpResult" style="display:none;margin-top:16px;padding:16px;background:#e8f5e9;border-radius:10px;font-size:14px;color:#2e7d32;font-weight:600;"></div>
                <div id="wbWebpError" style="display:none;margin-top:16px;padding:16px;background:#ffebee;border-radius:10px;font-size:14px;color:#c62828;font-weight:600;"></div>
            </div>
        </div>
        <script>
            (function() {
                var wbNonce = '<?php echo esc_js($nonce); ?>';
                var wbAjax = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
                var totalConverted = 0,
                    totalSaved = 0,
                    totalSkipped = 0;
                var logEl = document.getElementById('wbWebpLog');

                function addLog(msg) {
                    logEl.style.display = 'block';
                    var t = new Date().toLocaleTimeString('tr-TR');
                    logEl.innerHTML += '<div><span style="color:#999">[' + t + ']</span> ' + msg + '</div>';
                    logEl.scrollTop = logEl.scrollHeight;
                }

                // Kac resim var butonu
                var countBtn = document.getElementById('wbWebpCount');
                if (countBtn) {
                    countBtn.addEventListener('click', function() {
                        this.disabled = true;
                        this.textContent = 'Sayiliyor...';
                        var fd = new FormData();
                        fd.append('action', 'webyaz_bulk_webp_count');
                        fd.append('nonce', wbNonce);

                        fetch(wbAjax, {
                                method: 'POST',
                                body: fd
                            })
                            .then(function(r) {
                                return r.json();
                            })
                            .then(function(d) {
                                if (d.success) {
                                    alert('Donusturulecek ' + d.data.total + ' resim bulundu.\nWebP destegi: ' + (d.data.webp_support ? 'EVET' : 'HAYIR'));
                                } else {
                                    alert('HATA: ' + (d.data || 'Bilinmeyen hata'));
                                }
                                countBtn.disabled = false;
                                countBtn.textContent = '\uD83D\uDD0D Kac Resim Var?';
                            })
                            .catch(function(err) {
                                alert('Baglanti hatasi: ' + err.message);
                                countBtn.disabled = false;
                                countBtn.textContent = '\uD83D\uDD0D Kac Resim Var?';
                            });
                    });
                }

                // Donusturmeyi baslat butonu
                var startBtn = document.getElementById('wbWebpStart');
                if (startBtn) {
                    startBtn.addEventListener('click', function() {
                        addLog('&#128247; Donusturme baslatiliyor...');
                        this.disabled = true;
                        this.textContent = 'Donusturuluyor...';
                        document.getElementById('wbWebpProgress').style.display = 'block';
                        document.getElementById('wbWebpResult').style.display = 'none';
                        document.getElementById('wbWebpError').style.display = 'none';
                        totalConverted = 0;
                        totalSaved = 0;
                        totalSkipped = 0;
                        processBatch(0);
                    });
                }

                function processBatch(offset) {
                    var q = document.getElementById('wbWebpQuality').value;
                    var b = document.getElementById('wbWebpBatch').value;
                    var fd = new FormData();
                    fd.append('action', 'webyaz_bulk_webp');
                    fd.append('nonce', wbNonce);
                    fd.append('offset', offset);
                    fd.append('batch', b);
                    fd.append('quality', q);

                    addLog('Parti isleniyor: offset ' + offset + ', batch ' + b + '...');

                    fetch(wbAjax, {
                            method: 'POST',
                            body: fd
                        })
                        .then(function(r) {
                            if (!r.ok) throw new Error('HTTP ' + r.status);
                            return r.json();
                        })
                        .then(function(d) {
                            if (!d.success) {
                                addLog('&#10060; HATA: ' + (d.data || 'Bilinmeyen hata'));
                                document.getElementById('wbWebpError').style.display = 'block';
                                document.getElementById('wbWebpError').textContent = 'Hata: ' + (d.data || 'Bilinmeyen hata');
                                startBtn.disabled = false;
                                startBtn.textContent = '\uD83D\uDCF7 Donusturmeyi Baslat';
                                return;
                            }
                            var r = d.data;
                            totalConverted += r.converted;
                            totalSkipped += r.skipped;
                            totalSaved += r.saved_bytes;

                            var pct = r.total > 0 ? Math.min(100, Math.round(r.offset / r.total * 100)) : 100;
                            document.getElementById('wbWebpBar').style.width = pct + '%';
                            document.getElementById('wbWebpPct').textContent = pct + '%';

                            var saveMB = (totalSaved / 1024 / 1024).toFixed(2);
                            document.getElementById('wbWebpStatus').textContent =
                                Math.min(r.offset, r.total) + '/' + r.total + ' islem yapildi | ' +
                                totalConverted + ' donusturuldu | ' + totalSkipped + ' atlandi | ' +
                                saveMB + ' MB tasarruf';

                            addLog('Parti sonucu: ' + r.converted + ' donusturuldu, ' + r.skipped + ' atlandi');

                            if (r.details && r.details.length > 0) {
                                r.details.forEach(function(d) {
                                    addLog(d);
                                });
                            }

                            if (!r.done) {
                                processBatch(r.offset);
                            } else {
                                document.getElementById('wbWebpBar').style.width = '100%';
                                document.getElementById('wbWebpPct').textContent = '100%';
                                document.getElementById('wbWebpResult').style.display = 'block';
                                document.getElementById('wbWebpResult').textContent =
                                    'Tamamlandi! ' + totalConverted + ' resim donusturuldu, ' +
                                    saveMB + ' MB tasarruf edildi. (' + totalSkipped + ' resim atlandi)';
                                addLog('&#127881; Tum islemler tamamlandi!');
                                startBtn.disabled = false;
                                startBtn.textContent = '\uD83D\uDCF7 Donusturmeyi Baslat';
                            }
                        })
                        .catch(function(err) {
                            addLog('&#10060; Baglanti hatasi: ' + err.message);
                            document.getElementById('wbWebpError').style.display = 'block';
                            document.getElementById('wbWebpError').textContent = 'Baglanti hatasi: ' + err.message + '. Sayfayi yenileyip tekrar deneyin.';
                            startBtn.disabled = false;
                            startBtn.textContent = '\uD83D\uDCF7 Donusturmeyi Baslat';
                        });
                }
            })();
        </script>
<?php
    }
}

new Webyaz_Bulk_WebP();
