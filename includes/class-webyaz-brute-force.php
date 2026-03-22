<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Brute_Force {

    private $max_attempts = 5;
    private $lockout_time = 1800;

    public function __construct() {
        add_filter('authenticate', array($this, 'check_login'), 30, 3);
        add_action('wp_login_failed', array($this, 'log_failed'));
        add_action('wp_login', array($this, 'clear_on_success'), 10, 2);
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_submenu() {
        add_submenu_page(
            'webyaz-dashboard',
            'Güvenlik',
            'Güvenlik',
            'manage_options',
            'webyaz-security',
            array($this, 'render_admin')
        );
    }

    public function register_settings() {
        register_setting('webyaz_security_group', 'webyaz_security');
    }

    private static function get_defaults() {
        return array(
            'enabled' => '1',
            'max_attempts' => '5',
            'lockout_minutes' => '30',
        );
    }

    private function get_ip() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    private function get_transient_key() {
        return 'webyaz_login_' . md5($this->get_ip());
    }

    public function check_login($user, $username, $password) {
        $opts = wp_parse_args(get_option('webyaz_security', array()), self::get_defaults());
        if ($opts['enabled'] !== '1') return $user;
        if (empty($username)) return $user;

        $this->max_attempts = intval($opts['max_attempts']);
        $this->lockout_time = intval($opts['lockout_minutes']) * 60;

        $key = $this->get_transient_key();
        $data = get_transient($key);

        if ($data && isset($data['locked']) && $data['locked']) {
            $remaining = isset($data['time']) ? ($data['time'] + $this->lockout_time - time()) : 0;
            if ($remaining > 0) {
                $mins = ceil($remaining / 60);
                return new WP_Error('webyaz_locked', sprintf(
                    'Çok fazla başarısız giriş denemesi. %d dakika sonra tekrar deneyin.',
                    $mins
                ));
            }
            delete_transient($key);
        }

        return $user;
    }

    public function log_failed($username) {
        $opts = wp_parse_args(get_option('webyaz_security', array()), self::get_defaults());
        if ($opts['enabled'] !== '1') return;

        $this->max_attempts = intval($opts['max_attempts']);
        $this->lockout_time = intval($opts['lockout_minutes']) * 60;

        $key = $this->get_transient_key();
        $data = get_transient($key);

        if (!$data || !is_array($data)) {
            $data = array('attempts' => 0, 'locked' => false, 'time' => 0);
        }

        $data['attempts'] = intval($data['attempts']) + 1;

        if ($data['attempts'] >= $this->max_attempts) {
            $data['locked'] = true;
            $data['time'] = time();
        }

        set_transient($key, $data, $this->lockout_time);

        $this->log_attempt($username, $this->get_ip(), $data['attempts']);
    }

    public function clear_on_success($user_login, $user) {
        $key = $this->get_transient_key();
        delete_transient($key);
    }

    private function log_attempt($username, $ip, $attempt) {
        $logs = get_option('webyaz_login_logs', array());
        array_unshift($logs, array(
            'time' => current_time('mysql'),
            'username' => $username,
            'ip' => $ip,
            'attempt' => $attempt,
        ));
        $logs = array_slice($logs, 0, 100);
        update_option('webyaz_login_logs', $logs);
    }

    public function render_admin() {
        $opts = wp_parse_args(get_option('webyaz_security', array()), self::get_defaults());
        $logs = get_option('webyaz_login_logs', array());
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Güvenlik Ayarları</h1>
                <p>Brute force saldırı koruması ve giriş denemesi logları</p>
            </div>
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="webyaz-notice success">Ayarlar kaydedildi!</div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('webyaz_security_group'); ?>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Brute Force Koruması</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Koruma Aktif</label>
                            <select name="webyaz_security[enabled]">
                                <option value="1" <?php selected($opts['enabled'], '1'); ?>>Açık</option>
                                <option value="0" <?php selected($opts['enabled'], '0'); ?>>Kapalı</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Maksimum Deneme</label>
                            <input type="number" name="webyaz_security[max_attempts]" value="<?php echo esc_attr($opts['max_attempts']); ?>" min="2" max="20">
                        </div>
                        <div class="webyaz-field">
                            <label>Kilitleme Süresi (dakika)</label>
                            <input type="number" name="webyaz_security[lockout_minutes]" value="<?php echo esc_attr($opts['lockout_minutes']); ?>" min="5" max="1440">
                        </div>
                    </div>
                </div>
                <div style="margin-top:20px;">
                    <?php submit_button('Kaydet', 'primary', 'submit', false); ?>
                </div>
            </form>
            <?php if (!empty($logs)): ?>
            <div class="webyaz-settings-section" style="margin-top:30px;">
                <h2 class="webyaz-section-title">Son Başarısız Giriş Denemeleri</h2>
                <table class="webyaz-table">
                    <thead>
                        <tr><th>Tarih</th><th>Kullanıcı</th><th>IP</th><th>Deneme</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($logs, 0, 20) as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log['time']); ?></td>
                            <td><?php echo esc_html($log['username']); ?></td>
                            <td><?php echo esc_html($log['ip']); ?></td>
                            <td><?php echo intval($log['attempt']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

new Webyaz_Brute_Force();
