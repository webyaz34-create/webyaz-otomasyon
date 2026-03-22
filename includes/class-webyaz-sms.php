<?php
if (!defined('ABSPATH')) exit;

class Webyaz_SMS
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('woocommerce_order_status_changed', array($this, 'send_status_sms'), 10, 4);
        add_action('woocommerce_new_order', array($this, 'send_new_order_admin_sms'));
    }

    public function add_submenu()
    {
        add_submenu_page(
            'webyaz-dashboard',
            'SMS Bildirim',
            'SMS Bildirim',
            'manage_options',
            'webyaz-sms',
            array($this, 'render_admin')
        );
    }

    public function register_settings()
    {
        register_setting('webyaz_sms_group', 'webyaz_sms');
    }

    private static function get_defaults()
    {
        return array(
            'provider'        => 'netgsm',
            'api_key'         => '',
            'api_secret'      => '',
            'sender_id'       => '',
            'admin_phone'     => '',
            'admin_new_order' => '1',
            'sms_processing'  => '1',
            'sms_completed'   => '1',
            'sms_cancelled'   => '0',
            'sms_refunded'    => '0',
            'sms_on_hold'     => '0',
            'tpl_processing'  => 'Sayin {name}, #{order} numarali siparisieriniz hazirlaniyor. Tesekkurler!',
            'tpl_completed'   => 'Sayin {name}, #{order} numarali siparisieriniz kargoya verildi. Iyi gunlerde kullanin!',
            'tpl_cancelled'   => 'Sayin {name}, #{order} numarali siparisieriniz iptal edildi.',
            'tpl_refunded'    => 'Sayin {name}, #{order} numarali siparisieriniz icin iade islemi baslatildi.',
            'tpl_on_hold'     => 'Sayin {name}, #{order} numarali siparisieriniz odeme bekliyor.',
            'tpl_new_order'   => 'Yeni siparis! #{order} - {name} - {total}',
        );
    }

    public static function get($key)
    {
        $opts = wp_parse_args(get_option('webyaz_sms', array()), self::get_defaults());
        return isset($opts[$key]) ? $opts[$key] : '';
    }

    // --- SMS Gonderme (NetGSM / Iletimerkezi) ---
    public static function send($phone, $message)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 10) $phone = '90' . $phone;
        if (strlen($phone) === 11 && substr($phone, 0, 1) === '0') $phone = '9' . $phone;

        $provider = self::get('provider');
        $api_key = self::get('api_key');
        $api_secret = self::get('api_secret');
        $sender = self::get('sender_id');

        if (empty($api_key) || empty($phone)) return false;

        if ($provider === 'netgsm') {
            $url = 'https://api.netgsm.com.tr/sms/send/get/?' . http_build_query(array(
                'usercode'  => $api_key,
                'password'  => $api_secret,
                'gsmno'     => $phone,
                'message'   => $message,
                'msgheader' => $sender,
            ));
            $response = wp_remote_get($url, array('timeout' => 15));
        } else {
            // Iletimerkezi API
            $response = wp_remote_post('https://api.iletimerkezi.com/v1/send-sms/json', array(
                'timeout' => 15,
                'headers' => array('Content-Type' => 'application/json'),
                'body' => wp_json_encode(array(
                    'request' => array(
                        'authentication' => array(
                            'key'  => $api_key,
                            'hash' => $api_secret,
                        ),
                        'order' => array(
                            'sender'  => $sender,
                            'message' => array(
                                'text' => $message,
                                'receipts' => array(
                                    'receipt' => array(array('number' => $phone))
                                ),
                            ),
                        ),
                    ),
                )),
            ));
        }

        if (is_wp_error($response)) {
            error_log('Webyaz SMS Error: ' . $response->get_error_message());
            return false;
        }
        return true;
    }

    // --- Siparis durumu degistiginde ---
    public function send_status_sms($order_id, $old_status, $new_status, $order)
    {
        $key = 'sms_' . $new_status;
        if (self::get($key) !== '1') return;

        $phone = $order->get_billing_phone();
        if (empty($phone)) return;

        $tpl_key = 'tpl_' . $new_status;
        $message = self::get($tpl_key);
        if (empty($message)) return;

        $message = self::parse_template($message, $order);
        self::send($phone, $message);
    }

    // --- Yeni siparis geldiginde admin'e SMS ---
    public function send_new_order_admin_sms($order_id)
    {
        if (self::get('admin_new_order') !== '1') return;
        $admin_phone = self::get('admin_phone');
        if (empty($admin_phone)) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        $message = self::parse_template(self::get('tpl_new_order'), $order);
        self::send($admin_phone, $message);
    }

    // --- Sablon degiskenleri ---
    private static function parse_template($tpl, $order)
    {
        $replacements = array(
            '{name}'    => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            '{order}'   => $order->get_order_number(),
            '{total}'   => $order->get_formatted_order_total(),
            '{phone}'   => $order->get_billing_phone(),
            '{email}'   => $order->get_billing_email(),
            '{date}'    => $order->get_date_created() ? $order->get_date_created()->date('d.m.Y H:i') : '',
            '{site}'    => get_bloginfo('name'),
        );
        return str_replace(array_keys($replacements), array_values($replacements), $tpl);
    }

    // --- Admin Page ---
    public function render_admin()
    {
        $opts = wp_parse_args(get_option('webyaz_sms', array()), self::get_defaults());

        $primary = '#446084';
        $secondary = '#d26e4b';
        if (class_exists('Webyaz_Colors')) {
            $colors = Webyaz_Colors::get_theme_colors();
            $primary = $colors['primary'];
            $secondary = $colors['secondary'];
        }
?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header" style="background:linear-gradient(135deg,<?php echo $primary; ?>,<?php echo $secondary; ?>);">
                <h1>SMS Bildirim Ayarlari</h1>
                <p>Siparis durumu degistiginde musteriye otomatik SMS gonderin</p>
            </div>

            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="webyaz-notice success">Ayarlar kaydedildi!</div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('webyaz_sms_group'); ?>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title" style="border-bottom-color:<?php echo $secondary; ?>;">API Ayarlari</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>SMS Saglayici</label>
                            <select name="webyaz_sms[provider]">
                                <option value="netgsm" <?php selected($opts['provider'], 'netgsm'); ?>>NetGSM</option>
                                <option value="iletimerkezi" <?php selected($opts['provider'], 'iletimerkezi'); ?>>Ileti Merkezi</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Gonderen Basligi</label>
                            <input type="text" name="webyaz_sms[sender_id]" value="<?php echo esc_attr($opts['sender_id']); ?>" placeholder="SIRKETADI">
                        </div>
                        <div class="webyaz-field">
                            <label>API Key / Kullanici Kodu</label>
                            <input type="text" name="webyaz_sms[api_key]" value="<?php echo esc_attr($opts['api_key']); ?>" placeholder="API anahtariniz">
                        </div>
                        <div class="webyaz-field">
                            <label>API Secret / Sifre</label>
                            <input type="password" name="webyaz_sms[api_secret]" value="<?php echo esc_attr($opts['api_secret']); ?>" placeholder="API sifreniz">
                        </div>
                        <div class="webyaz-field">
                            <label>Admin Telefon (yeni siparis bildirimi)</label>
                            <input type="text" name="webyaz_sms[admin_phone]" value="<?php echo esc_attr($opts['admin_phone']); ?>" placeholder="905XXXXXXXXX">
                        </div>
                        <div class="webyaz-field">
                            <label>Yeni Siparis Admin SMS</label>
                            <select name="webyaz_sms[admin_new_order]">
                                <option value="1" <?php selected($opts['admin_new_order'], '1'); ?>>Aktif</option>
                                <option value="0" <?php selected($opts['admin_new_order'], '0'); ?>>Kapali</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title" style="border-bottom-color:<?php echo $secondary; ?>;">Durum Bildirimleri</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:15px;">Hangi siparis durumlarinda SMS gonderilsin?</p>
                    <div class="webyaz-settings-grid">
                        <?php
                        $statuses = array(
                            'processing' => 'Hazirlaniyor',
                            'completed'  => 'Tamamlandi',
                            'cancelled'  => 'Iptal Edildi',
                            'refunded'   => 'Iade Edildi',
                            'on_hold'    => 'Beklemede',
                        );
                        foreach ($statuses as $key => $label):
                        ?>
                            <div class="webyaz-field">
                                <label><?php echo $label; ?></label>
                                <select name="webyaz_sms[sms_<?php echo $key; ?>]">
                                    <option value="1" <?php selected($opts['sms_' . $key], '1'); ?>>SMS Gonder</option>
                                    <option value="0" <?php selected($opts['sms_' . $key], '0'); ?>>Gonderme</option>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title" style="border-bottom-color:<?php echo $secondary; ?>;">Mesaj Sablonlari</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:15px;">Kullanilabilir degiskenler: <code>{name}</code> <code>{order}</code> <code>{total}</code> <code>{phone}</code> <code>{email}</code> <code>{date}</code> <code>{site}</code></p>
                    <?php
                    $templates = array(
                        'tpl_processing' => 'Hazirlaniyor Mesaji',
                        'tpl_completed'  => 'Tamamlandi Mesaji',
                        'tpl_cancelled'  => 'Iptal Mesaji',
                        'tpl_refunded'   => 'Iade Mesaji',
                        'tpl_on_hold'    => 'Beklemede Mesaji',
                        'tpl_new_order'  => 'Admin Yeni Siparis',
                    );
                    foreach ($templates as $key => $label):
                    ?>
                        <div class="webyaz-field" style="margin-bottom:12px;">
                            <label><?php echo $label; ?></label>
                            <textarea name="webyaz_sms[<?php echo $key; ?>]" rows="2" style="width:100%;font-size:13px;border:1px solid #ddd;border-radius:6px;padding:10px;"><?php echo esc_textarea($opts[$key]); ?></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top:20px;">
                    <?php submit_button('Ayarlari Kaydet', 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>
<?php
    }
}

new Webyaz_SMS();
