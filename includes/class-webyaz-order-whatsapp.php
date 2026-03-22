<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Order_WhatsApp {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('woocommerce_order_status_changed', array($this, 'send_notification'), 10, 4);
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
    }

    public function register_settings() {
        register_setting('webyaz_order_wa_group', 'webyaz_order_wa');
    }

    private static function get_defaults() {
        return array(
            'active' => '0',
            'api_type' => 'link',
            'statuses' => array('processing', 'completed', 'shipped'),
            'messages' => array(
                'processing' => 'Merhaba {name}, #{order_id} numarali siparisini aldik ve hazirliyoruz.',
                'completed' => 'Merhaba {name}, #{order_id} numarali siparisin tamamlandi. Bizi tercih ettigin icin tesekkurler!',
                'shipped' => 'Merhaba {name}, #{order_id} numarali siparisin kargoya verildi. Takip No: {tracking}',
                'on-hold' => 'Merhaba {name}, #{order_id} numarali siparisin icin odeme bekleniyor.',
                'cancelled' => 'Merhaba {name}, #{order_id} numarali siparisin iptal edildi.',
            ),
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_order_wa', array()), self::get_defaults());
    }

    public function send_notification($order_id, $old_status, $new_status, $order) {
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return;

        $statuses = isset($opts['statuses']) ? (array)$opts['statuses'] : array();
        if (!in_array($new_status, $statuses)) return;

        $phone = $order->get_billing_phone();
        if (empty($phone)) return;

        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strpos($phone, '0') === 0) $phone = '9' . $phone;
        if (strlen($phone) === 10) $phone = '90' . $phone;

        $messages = isset($opts['messages']) ? $opts['messages'] : array();
        $template = isset($messages[$new_status]) ? $messages[$new_status] : '';
        if (empty($template)) return;

        $tracking = get_post_meta($order_id, '_webyaz_tracking_code', true);
        $replacements = array(
            '{name}' => $order->get_billing_first_name(),
            '{surname}' => $order->get_billing_last_name(),
            '{order_id}' => $order_id,
            '{total}' => $order->get_total(),
            '{status}' => wc_get_order_status_name($new_status),
            '{tracking}' => $tracking ?: '-',
            '{date}' => $order->get_date_created() ? $order->get_date_created()->date_i18n('d.m.Y') : '',
            '{site}' => get_bloginfo('name'),
        );
        $message = str_replace(array_keys($replacements), array_values($replacements), $template);

        $wa_url = 'https://api.whatsapp.com/send?phone=' . $phone . '&text=' . rawurlencode($message);
        update_post_meta($order_id, '_webyaz_wa_last_msg', $message);
        update_post_meta($order_id, '_webyaz_wa_last_url', $wa_url);
    }

    public function add_meta_box() {
        add_meta_box('webyaz_order_wa', 'WhatsApp Bildirim', array($this, 'meta_box_html'), 'shop_order', 'side', 'default');
        add_meta_box('webyaz_order_wa', 'WhatsApp Bildirim', array($this, 'meta_box_html'), 'woocommerce_page_wc-orders', 'side', 'default');
    }

    public function meta_box_html($post) {
        $order_id = is_a($post, 'WP_Post') ? $post->ID : (isset($_GET['id']) ? intval($_GET['id']) : 0);
        $order = wc_get_order($order_id);
        if (!$order) { echo '<p>Siparis bulunamadi.</p>'; return; }

        $phone = $order->get_billing_phone();
        $last_url = get_post_meta($order_id, '_webyaz_wa_last_url', true);
        $last_msg = get_post_meta($order_id, '_webyaz_wa_last_msg', true);

        echo '<div style="font-family:Roboto,sans-serif;font-size:13px;">';
        echo '<p><strong>Telefon:</strong> ' . esc_html($phone) . '</p>';
        if ($last_url) {
            echo '<a href="' . esc_url($last_url) . '" target="_blank" class="button button-primary" style="width:100%;text-align:center;margin:6px 0;">WhatsApp ile Gonder</a>';
            echo '<p style="color:#888;font-size:11px;margin-top:6px;">' . esc_html(wp_trim_words($last_msg, 15)) . '</p>';
        } else {
            echo '<p style="color:#999;">Henuz bildirim olusturulmadi.</p>';
        }
        echo '</div>';
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Siparis WhatsApp', 'Siparis WhatsApp', 'manage_options', 'webyaz-order-wa', array($this, 'render_admin'));
    }

    public function render_admin() {
        $opts = self::get_opts();
        $all_statuses = array(
            'processing' => 'Hazirlaniyor',
            'completed' => 'Tamamlandi',
            'shipped' => 'Kargoda',
            'on-hold' => 'Beklemede',
            'cancelled' => 'Iptal',
        );
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>Siparis WhatsApp Bildirimi</h1><p>Siparis durumu degisince musteriye otomatik WhatsApp mesaji olustur</p></div>
            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">Kaydedildi!</div><?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('webyaz_order_wa_group'); ?>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Genel</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field"><label>Aktif</label><select name="webyaz_order_wa[active]"><option value="0" <?php selected($opts['active'], '0'); ?>>Kapali</option><option value="1" <?php selected($opts['active'], '1'); ?>>Aktif</option></select></div>
                    </div>
                </div>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Hangi Durumlarda Bildirim Gitsin</h2>
                    <div class="webyaz-settings-grid">
                        <?php $active_statuses = isset($opts['statuses']) ? (array)$opts['statuses'] : array();
                        foreach ($all_statuses as $sk => $sl): ?>
                        <div class="webyaz-field"><label><input type="checkbox" name="webyaz_order_wa[statuses][]" value="<?php echo $sk; ?>" <?php checked(in_array($sk, $active_statuses)); ?>> <?php echo esc_html($sl); ?></label></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Mesaj Sablonlari</h2>
                    <p style="color:#666;font-size:12px;margin-bottom:12px;">Degiskenler: {name} {surname} {order_id} {total} {status} {tracking} {date} {site}</p>
                    <?php $msgs = isset($opts['messages']) ? $opts['messages'] : array();
                    foreach ($all_statuses as $sk => $sl): ?>
                    <div class="webyaz-field" style="margin-bottom:14px;">
                        <label><?php echo esc_html($sl); ?></label>
                        <textarea name="webyaz_order_wa[messages][<?php echo $sk; ?>]" rows="2" style="width:100%;font-size:13px;"><?php echo esc_textarea(isset($msgs[$sk]) ? $msgs[$sk] : ''); ?></textarea>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php submit_button('Kaydet'); ?>
            </form>
        </div>
        <?php
    }
}

new Webyaz_Order_WhatsApp();
