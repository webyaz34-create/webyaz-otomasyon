<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Order_Status {

    public function __construct() {
        add_action('woocommerce_order_status_changed', array($this, 'status_changed'), 10, 4);
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_shortcode('webyaz_order_tracking', array($this, 'tracking_shortcode'));
    }

    public function add_submenu() {
        add_submenu_page(
            'webyaz-dashboard',
            'Siparis Bildirimleri',
            'Siparis Bildirimleri',
            'manage_options',
            'webyaz-order-status',
            array($this, 'render_admin')
        );
    }

    public function register_settings() {
        register_setting('webyaz_order_status_group', 'webyaz_order_status');
    }

    private static function get_defaults() {
        return array(
            'enabled' => '1',
            'processing_msg' => 'Sayın {customer}, #{order_id} numaralı siparişiniz onaylandı ve hazırlanıyor.',
            'shipped_msg' => 'Sayın {customer}, #{order_id} numaralı siparişiniz kargoya verildi.',
            'completed_msg' => 'Sayın {customer}, #{order_id} numaralı siparişiniz teslim edildi. Bizi tercih ettiğiniz için teşekkürler!',
            'cancelled_msg' => 'Sayın {customer}, #{order_id} numaralı siparişiniz iptal edildi.',
            'email_enabled' => '1',
        );
    }

    public static function get($key) {
        $opts = wp_parse_args(get_option('webyaz_order_status', array()), self::get_defaults());
        return isset($opts[$key]) ? $opts[$key] : '';
    }

    public function status_changed($order_id, $old_status, $new_status, $order) {
        $opts = wp_parse_args(get_option('webyaz_order_status', array()), self::get_defaults());
        if ($opts['enabled'] !== '1') return;

        $msg_key = '';
        $subject = '';
        switch ($new_status) {
            case 'processing':
                $msg_key = 'processing_msg';
                $subject = 'Siparişiniz Onaylandı';
                break;
            case 'shipped':
            case 'completed':
                if ($new_status === 'shipped' || ($new_status === 'completed' && $old_status !== 'shipped')) {
                    $msg_key = $new_status === 'shipped' ? 'shipped_msg' : 'completed_msg';
                    $subject = $new_status === 'shipped' ? 'Siparişiniz Kargoya Verildi' : 'Siparişiniz Teslim Edildi';
                }
                if ($new_status === 'completed') {
                    $msg_key = 'completed_msg';
                    $subject = 'Siparişiniz Teslim Edildi';
                }
                break;
            case 'cancelled':
                $msg_key = 'cancelled_msg';
                $subject = 'Siparişiniz İptal Edildi';
                break;
        }

        if (empty($msg_key) || empty($opts[$msg_key])) return;

        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $email = $order->get_billing_email();

        $replacements = array(
            '{customer}' => $customer_name,
            '{order_id}' => $order_id,
            '{total}' => $order->get_formatted_order_total(),
            '{date}' => $order->get_date_created()->date_i18n('d.m.Y H:i'),
        );

        $message = str_replace(array_keys($replacements), array_values($replacements), $opts[$msg_key]);

        if ($opts['email_enabled'] === '1' && $email) {
            $site_name = get_bloginfo('name');
            $html = '<div style="font-family:Roboto,Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">';
            $html .= '<h2 style="color:#333;">' . esc_html($subject) . '</h2>';
            $html .= '<p style="font-size:15px;line-height:1.6;color:#555;">' . nl2br(esc_html($message)) . '</p>';
            $html .= '<p style="font-size:13px;color:#999;margin-top:30px;">' . esc_html($site_name) . '</p>';
            $html .= '</div>';

            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($email, $site_name . ' - ' . $subject, $html, $headers);
        }

        $order->add_order_note('Webyaz: ' . $message);
    }

    public function tracking_shortcode($atts) {
        ob_start();
        ?>
        <div class="webyaz-order-tracking">
            <h3>Sipariş Takibi</h3>
            <form method="post" class="webyaz-tracking-form">
                <input type="text" name="webyaz_order_id" placeholder="Sipariş numaranız" required>
                <input type="email" name="webyaz_order_email" placeholder="E-posta adresiniz" required>
                <button type="submit" name="webyaz_track_order">Sorgula</button>
            </form>
            <?php
            if (isset($_POST['webyaz_track_order']) && function_exists('wc_get_order')) {
                $oid = intval($_POST['webyaz_order_id']);
                $email = sanitize_email($_POST['webyaz_order_email']);
                $order = wc_get_order($oid);
                if ($order && strtolower($order->get_billing_email()) === strtolower($email)) {
                    $statuses = array(
                        'pending' => 'Beklemede',
                        'processing' => 'Hazırlanıyor',
                        'on-hold' => 'Beklemede',
                        'completed' => 'Tamamlandı',
                        'cancelled' => 'İptal',
                        'refunded' => 'İade',
                        'failed' => 'Başarısız',
                        'shipped' => 'Kargoda',
                    );
                    $status = $order->get_status();
                    $label = isset($statuses[$status]) ? $statuses[$status] : $status;
                    echo '<div class="webyaz-tracking-result">';
                    echo '<p><strong>Sipariş #' . $oid . '</strong></p>';
                    echo '<p>Durum: <span class="webyaz-status-badge webyaz-status-' . esc_attr($status) . '">' . esc_html($label) . '</span></p>';
                    echo '<p>Tarih: ' . esc_html($order->get_date_created()->date_i18n('d.m.Y H:i')) . '</p>';
                    echo '</div>';
                } else {
                    echo '<div class="webyaz-tracking-result error"><p>Sipariş bulunamadı. Bilgileri kontrol edin.</p></div>';
                }
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_admin() {
        $opts = wp_parse_args(get_option('webyaz_order_status', array()), self::get_defaults());
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Sipariş Durum Bildirimleri</h1>
                <p>Sipariş durumu değiştiğinde müşteriye otomatik bildirim gönderir</p>
            </div>
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="webyaz-notice success">Ayarlar kaydedildi!</div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('webyaz_order_status_group'); ?>
                <div class="webyaz-settings-section">
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Bildirimler Aktif</label>
                            <select name="webyaz_order_status[enabled]">
                                <option value="1" <?php selected($opts['enabled'], '1'); ?>>Açık</option>
                                <option value="0" <?php selected($opts['enabled'], '0'); ?>>Kapalı</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>E-posta Bildirimi</label>
                            <select name="webyaz_order_status[email_enabled]">
                                <option value="1" <?php selected($opts['email_enabled'], '1'); ?>>Açık</option>
                                <option value="0" <?php selected($opts['email_enabled'], '0'); ?>>Kapalı</option>
                            </select>
                        </div>
                    </div>
                    <h2 class="webyaz-section-title" style="margin-top:20px;">Mesaj Şablonları</h2>
                    <p style="color:#666;font-size:13px;">Kullanılabilir değişkenler: {customer}, {order_id}, {total}, {date}</p>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field" style="grid-column:1/-1;">
                            <label>Sipariş Onaylandı</label>
                            <textarea name="webyaz_order_status[processing_msg]" rows="2"><?php echo esc_textarea($opts['processing_msg']); ?></textarea>
                        </div>
                        <div class="webyaz-field" style="grid-column:1/-1;">
                            <label>Kargoya Verildi</label>
                            <textarea name="webyaz_order_status[shipped_msg]" rows="2"><?php echo esc_textarea($opts['shipped_msg']); ?></textarea>
                        </div>
                        <div class="webyaz-field" style="grid-column:1/-1;">
                            <label>Teslim Edildi</label>
                            <textarea name="webyaz_order_status[completed_msg]" rows="2"><?php echo esc_textarea($opts['completed_msg']); ?></textarea>
                        </div>
                        <div class="webyaz-field" style="grid-column:1/-1;">
                            <label>İptal Edildi</label>
                            <textarea name="webyaz_order_status[cancelled_msg]" rows="2"><?php echo esc_textarea($opts['cancelled_msg']); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Sipariş Takip Sayfası</h2>
                    <p style="color:#666;">Herhangi bir sayfaya <code>[webyaz_order_tracking]</code> shortcode ekleyerek müşterilerin sipariş durumunu sorgulamasını sağlayabilirsiniz.</p>
                </div>
                <div style="margin-top:20px;">
                    <?php submit_button('Kaydet', 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>
        <?php
    }
}

new Webyaz_Order_Status();
