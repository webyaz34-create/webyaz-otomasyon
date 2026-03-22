<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Antibot {

    public function __construct() {
        add_action('register_form', [$this, 'add_honeypot_fields']);
        add_action('woocommerce_register_form', [$this, 'add_honeypot_fields']);
        add_filter('registration_errors', [$this, 'validate_registration'], 10, 3);
        add_filter('woocommerce_process_registration_errors', [$this, 'validate_woo_registration'], 10, 4);
        add_action('login_enqueue_scripts', [$this, 'enqueue_antibot_css']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_antibot_css']);
    }

    public function add_honeypot_fields() {
        $ts = time();
        ?>
        <div class="webyaz-hp-wrap" aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;overflow:hidden;">
            <label for="webyaz_website_url">Website</label>
            <input type="text" name="webyaz_website_url" id="webyaz_website_url" value="" tabindex="-1" autocomplete="off">
        </div>
        <input type="hidden" name="webyaz_ts" value="<?php echo esc_attr($ts); ?>">
        <input type="hidden" name="webyaz_token" value="<?php echo esc_attr(wp_hash($ts . 'webyaz_salt')); ?>">
        <?php
    }

    public function validate_registration($errors, $sanitized_user_login, $user_email) {
        $bot_check = $this->check_bot();
        if (is_wp_error($bot_check)) {
            $errors->add($bot_check->get_error_code(), $bot_check->get_error_message());
        }
        return $errors;
    }

    public function validate_woo_registration($errors, $username, $password, $email) {
        $bot_check = $this->check_bot();
        if (is_wp_error($bot_check)) {
            $errors->add($bot_check->get_error_code(), $bot_check->get_error_message());
        }
        return $errors;
    }

    private function check_bot() {
        if (!empty($_POST['webyaz_website_url'])) {
            return new WP_Error('bot_detected', 'Kayıt işlemi reddedildi.');
        }

        $ts = intval($_POST['webyaz_ts'] ?? 0);
        $token = sanitize_text_field($_POST['webyaz_token'] ?? '');

        if (empty($ts) || empty($token)) {
            return new WP_Error('bot_detected', 'Güvenlik doğrulaması başarısız.');
        }

        if ($token !== wp_hash($ts . 'webyaz_salt')) {
            return new WP_Error('bot_detected', 'Güvenlik doğrulaması başarısız.');
        }

        $elapsed = time() - $ts;
        if ($elapsed < 3) {
            return new WP_Error('bot_detected', 'Form çok hızlı dolduruldu. Lütfen tekrar deneyin.');
        }

        if ($elapsed > 3600) {
            return new WP_Error('form_expired', 'Form süresi doldu. Lütfen sayfayı yenileyip tekrar deneyin.');
        }

        return true;
    }

    public function enqueue_antibot_css() {
        $css = '.webyaz-hp-wrap{position:absolute!important;left:-9999px!important;top:-9999px!important;height:0!important;width:0!important;overflow:hidden!important;}';
        if (wp_style_is('webyaz-style', 'enqueued')) {
            wp_add_inline_style('webyaz-style', $css);
        } else {
            echo '<style>' . $css . '</style>';
        }
    }
}

new Webyaz_Antibot();
