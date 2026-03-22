<?php
if (!defined('ABSPATH')) exit;

class Webyaz_WebP {

    public function __construct() {
        add_filter('wp_handle_upload', array($this, 'convert_on_upload'));
        add_filter('wp_generate_attachment_metadata', array($this, 'convert_thumbnails'), 10, 2);
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings() {
        register_setting('webyaz_webp_group', 'webyaz_webp');
    }

    private static function get_defaults() {
        return array('active' => '0', 'quality' => '82', 'delete_original' => '0');
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_webp', array()), self::get_defaults());
    }

    private function can_convert() {
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return false;
        if (!function_exists('imagewebp')) return false;
        return true;
    }

    public function convert_on_upload($upload) {
        if (!$this->can_convert()) return $upload;
        $file = $upload['file'];
        $type = $upload['type'];
        if (!in_array($type, array('image/jpeg', 'image/png'))) return $upload;

        $webp_path = $this->to_webp($file);
        if ($webp_path) {
            $opts = self::get_opts();
            if ($opts['delete_original'] === '1' && file_exists($file)) {
                @unlink($file);
            }
            $upload['file'] = $webp_path;
            $upload['url'] = str_replace(basename($file), basename($webp_path), $upload['url']);
            $upload['type'] = 'image/webp';
        }
        return $upload;
    }

    public function convert_thumbnails($metadata, $attachment_id) {
        if (!$this->can_convert()) return $metadata;
        $upload_dir = wp_upload_dir();
        $base = $upload_dir['basedir'];

        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            $dir = dirname($metadata['file']);
            foreach ($metadata['sizes'] as $size => &$info) {
                $thumb_path = $base . '/' . $dir . '/' . $info['file'];
                if (!file_exists($thumb_path)) continue;
                $ext = strtolower(pathinfo($thumb_path, PATHINFO_EXTENSION));
                if (!in_array($ext, array('jpg', 'jpeg', 'png'))) continue;

                $webp_path = $this->to_webp($thumb_path);
                if ($webp_path) {
                    $opts = self::get_opts();
                    if ($opts['delete_original'] === '1') @unlink($thumb_path);
                    $info['file'] = basename($webp_path);
                    $info['mime-type'] = 'image/webp';
                }
            }
        }
        return $metadata;
    }

    private function to_webp($file_path) {
        $opts = self::get_opts();
        $quality = intval($opts['quality']);
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file_path);

        $image = null;
        if (in_array($ext, array('jpg', 'jpeg'))) {
            $image = @imagecreatefromjpeg($file_path);
        } elseif ($ext === 'png') {
            $image = @imagecreatefrompng($file_path);
            if ($image) {
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
            }
        }

        if (!$image) return false;
        $result = imagewebp($image, $webp_path, $quality);
        imagedestroy($image);

        if ($result && file_exists($webp_path)) {
            if (filesize($webp_path) >= filesize($file_path)) {
                @unlink($webp_path);
                return false;
            }
            return $webp_path;
        }
        return false;
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'WebP Donusturme', 'WebP Resim', 'manage_options', 'webyaz-webp', array($this, 'render_admin'));
    }

    public function render_admin() {
        $opts = self::get_opts();
        $gd_ok = function_exists('imagewebp');
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>WebP Resim Donusturme</h1><p>Yuklenen resimleri otomatik WebP formatina cevir</p></div>
            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">Kaydedildi!</div><?php endif; ?>
            <?php if (!$gd_ok): ?>
                <div class="webyaz-notice" style="background:#fff3cd;border-left:4px solid #ff9800;padding:12px;margin-bottom:16px;">
                    <strong>Uyari:</strong> Sunucunuzda GD kutuphanesi WebP destegi bulunamadi. Hosting firmaniz ile iletisime gecin.
                </div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('webyaz_webp_group'); ?>
                <div class="webyaz-settings-section">
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>WebP Donusturme</label>
                            <select name="webyaz_webp[active]">
                                <option value="0" <?php selected($opts['active'], '0'); ?>>Kapali</option>
                                <option value="1" <?php selected($opts['active'], '1'); ?>>Aktif</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Kalite (1-100)</label>
                            <input type="number" name="webyaz_webp[quality]" value="<?php echo esc_attr($opts['quality']); ?>" min="1" max="100">
                            <small style="color:#888;">Onerilen: 80-85</small>
                        </div>
                        <div class="webyaz-field">
                            <label>Orijinal Dosyayi Sil</label>
                            <select name="webyaz_webp[delete_original]">
                                <option value="0" <?php selected($opts['delete_original'], '0'); ?>>Hayir (Her ikisini sakla)</option>
                                <option value="1" <?php selected($opts['delete_original'], '1'); ?>>Evet (Sadece WebP kalsın)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Bilgi</h2>
                    <ul style="font-size:14px;line-height:2;color:#444;">
                        <li>&#10003; JPG ve PNG dosyalari otomatik WebP formatina donusturulur</li>
                        <li>&#10003; Thumbnail boyutlari da donusturulur</li>
                        <li>&#10003; WebP dosya boyutu orijinalden buyukse donusum yapilmaz</li>
                        <li>&#10003; GD Kutuphane Durumu: <strong style="color:<?php echo $gd_ok ? '#4caf50' : '#d32f2f'; ?>;"><?php echo $gd_ok ? 'Aktif' : 'Bulunamadi'; ?></strong></li>
                    </ul>
                </div>
                <?php submit_button('Kaydet'); ?>
            </form>
        </div>
        <?php
    }
}

new Webyaz_WebP();
