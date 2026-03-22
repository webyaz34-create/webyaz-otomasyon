<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Order_Note
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('wp_ajax_webyaz_send_order_note', array($this, 'ajax_send'));
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_note_frontend'));
    }

    public function add_submenu()
    {
        add_submenu_page(
            'webyaz-dashboard',
            'Siparis Notu',
            'Siparis Notu',
            'manage_options',
            'webyaz-order-note',
            array($this, 'render_admin')
        );
    }

    public function register_settings()
    {
        register_setting('webyaz_order_note_group', 'webyaz_order_note');
    }

    private static function get_defaults()
    {
        return array(
            'send_email'      => '1',
            'send_sms'        => '0',
            'default_subject' => 'Siparisieriniz hakkinda bilgilendirme',
            'email_footer'    => '',
        );
    }

    public static function get($key)
    {
        $opts = wp_parse_args(get_option('webyaz_order_note', array()), self::get_defaults());
        return isset($opts[$key]) ? $opts[$key] : '';
    }

    // --- Admin meta box ---
    public function add_meta_box()
    {
        add_meta_box(
            'webyaz_order_note_box',
            'Musteriye Ozel Not Gonder',
            array($this, 'meta_box_html'),
            'shop_order',
            'normal',
            'default'
        );
    }

    public function meta_box_html($post)
    {
        $notes = get_post_meta($post->ID, '_webyaz_custom_notes', true);
        if (!is_array($notes)) $notes = array();

        $primary = '#446084';
        $secondary = '#d26e4b';
        if (class_exists('Webyaz_Colors')) {
            $c = Webyaz_Colors::get_theme_colors();
            $primary = $c['primary'];
            $secondary = $c['secondary'];
        }
?>
        <div style="padding:10px 0;">
            <?php if (!empty($notes)): ?>
                <div style="margin-bottom:15px;max-height:250px;overflow-y:auto;">
                    <?php foreach (array_reverse($notes) as $note): ?>
                        <div style="padding:12px;background:#f8f9fa;border-left:3px solid <?php echo $primary; ?>;border-radius:0 6px 6px 0;margin-bottom:8px;">
                            <div style="font-size:12px;color:#999;margin-bottom:4px;"><?php echo esc_html($note['date']); ?></div>
                            <div style="font-size:14px;color:#333;"><?php echo esc_html($note['message']); ?></div>
                            <?php if (!empty($note['sent_email'])): ?>
                                <span style="display:inline-block;margin-top:4px;padding:2px 8px;background:#e8f5e9;color:#2e7d32;border-radius:4px;font-size:11px;">E-posta gonderildi</span>
                            <?php endif; ?>
                            <?php if (!empty($note['sent_sms'])): ?>
                                <span style="display:inline-block;margin-top:4px;padding:2px 8px;background:#e3f2fd;color:#1565c0;border-radius:4px;font-size:11px;">SMS gonderildi</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <textarea id="webyaz_note_message" rows="3" style="width:100%;font-size:14px;border:1px solid #ddd;border-radius:6px;padding:10px;margin-bottom:10px;" placeholder="Musteriye gondermek istediginiz mesaji yazin..."></textarea>
            <div style="display:flex;gap:8px;align-items:center;">
                <button type="button" id="webyaz_send_note_btn" class="button button-primary" style="background:<?php echo $secondary; ?>;border:none;padding:8px 20px;font-weight:700;">
                    Notu Gonder
                </button>
                <label style="font-size:13px;color:#666;display:flex;align-items:center;gap:4px;">
                    <input type="checkbox" id="webyaz_note_email" checked> E-posta gonder
                </label>
                <?php if (class_exists('Webyaz_SMS')): ?>
                    <label style="font-size:13px;color:#666;display:flex;align-items:center;gap:4px;">
                        <input type="checkbox" id="webyaz_note_sms"> SMS gonder
                    </label>
                <?php endif; ?>
            </div>
        </div>
        <script>
            jQuery('#webyaz_send_note_btn').on('click', function() {
                var message = jQuery('#webyaz_note_message').val().trim();
                if (!message) {
                    alert('Mesaj yazmalisiniz.');
                    return;
                }
                var $btn = jQuery(this);
                $btn.prop('disabled', true).text('Gonderiliyor...');
                jQuery.post(ajaxurl, {
                    action: 'webyaz_send_order_note',
                    nonce: '<?php echo wp_create_nonce('webyaz_nonce'); ?>',
                    order_id: <?php echo $post->ID; ?>,
                    message: message,
                    send_email: jQuery('#webyaz_note_email').is(':checked') ? 1 : 0,
                    send_sms: jQuery('#webyaz_note_sms').is(':checked') ? 1 : 0
                }, function(r) {
                    if (r.success) {
                        alert('Not basariyla gonderildi!');
                        location.reload();
                    } else {
                        alert('Hata: ' + r.data);
                    }
                    $btn.prop('disabled', false).text('Notu Gonder');
                });
            });
        </script>
    <?php
    }

    // --- AJAX: Not gonder ---
    public function ajax_send()
    {
        check_ajax_referer('webyaz_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Yetki yok');

        $order_id = intval($_POST['order_id']);
        $message = sanitize_textarea_field($_POST['message']);
        $send_email = intval($_POST['send_email']);
        $send_sms = intval($_POST['send_sms']);

        $order = wc_get_order($order_id);
        if (!$order) wp_send_json_error('Siparis bulunamadi');

        // Notu kaydet
        $notes = get_post_meta($order_id, '_webyaz_custom_notes', true);
        if (!is_array($notes)) $notes = array();

        $note_entry = array(
            'date'       => current_time('d.m.Y H:i'),
            'message'    => $message,
            'sent_email' => false,
            'sent_sms'   => false,
        );

        // E-posta gonder
        if ($send_email) {
            $to = $order->get_billing_email();
            $subject = self::get('default_subject');
            $company = class_exists('Webyaz_Settings') ? Webyaz_Settings::get('company_name') : get_bloginfo('name');

            $primary = '#446084';
            $secondary = '#d26e4b';
            if (class_exists('Webyaz_Colors')) {
                $c = Webyaz_Colors::get_theme_colors();
                $primary = $c['primary'];
                $secondary = $c['secondary'];
            }

            $body = '<div style="font-family:Roboto,Arial,sans-serif;max-width:600px;margin:0 auto;">';
            $body .= '<div style="background:linear-gradient(135deg,' . $primary . ',' . $secondary . ');padding:25px;border-radius:10px 10px 0 0;">';
            $body .= '<h2 style="color:#fff;margin:0;font-size:20px;">' . esc_html($company) . '</h2>';
            $body .= '</div>';
            $body .= '<div style="padding:25px;background:#fff;border:1px solid #eee;">';
            $body .= '<p style="font-size:15px;">Sayin ' . esc_html($order->get_billing_first_name()) . ',</p>';
            $body .= '<p style="font-size:14px;line-height:1.8;color:#333;">' . nl2br(esc_html($message)) . '</p>';
            $body .= '<p style="font-size:13px;color:#999;margin-top:20px;">Siparis No: #' . $order->get_order_number() . '</p>';
            $footer = self::get('email_footer');
            if (!empty($footer)) $body .= '<p style="font-size:12px;color:#999;margin-top:15px;">' . esc_html($footer) . '</p>';
            $body .= '</div></div>';

            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($to, $subject, $body, $headers);
            $note_entry['sent_email'] = true;
        }

        // SMS gonder
        if ($send_sms && class_exists('Webyaz_SMS')) {
            $phone = $order->get_billing_phone();
            if (!empty($phone)) {
                Webyaz_SMS::send($phone, $message);
                $note_entry['sent_sms'] = true;
            }
        }

        $notes[] = $note_entry;
        update_post_meta($order_id, '_webyaz_custom_notes', $notes);

        // WooCommerce siparis notuna da ekle
        $order->add_order_note('Webyaz Ozel Not: ' . $message);

        wp_send_json_success();
    }

    // --- Frontend: Musteri siparis sayfasinda notu goster ---
    public function display_note_frontend($order)
    {
        $order_id = $order->get_id();
        $notes = get_post_meta($order_id, '_webyaz_custom_notes', true);
        if (!is_array($notes) || empty($notes)) return;

        $primary = '#446084';
        if (class_exists('Webyaz_Colors')) {
            $c = Webyaz_Colors::get_theme_colors();
            $primary = $c['primary'];
        }

        echo '<h2 style="font-family:Roboto,sans-serif;font-size:18px;color:' . $primary . ';margin-top:30px;">Siparis Notlari</h2>';
        foreach ($notes as $note) {
            echo '<div style="padding:12px 16px;background:#f8f9fa;border-left:3px solid ' . $primary . ';border-radius:0 6px 6px 0;margin-bottom:8px;font-family:Roboto,sans-serif;">';
            echo '<div style="font-size:12px;color:#999;margin-bottom:4px;">' . esc_html($note['date']) . '</div>';
            echo '<div style="font-size:14px;color:#333;">' . esc_html($note['message']) . '</div>';
            echo '</div>';
        }
    }

    // --- Admin Page ---
    public function render_admin()
    {
        $opts = wp_parse_args(get_option('webyaz_order_note', array()), self::get_defaults());

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
                <h1>Siparis Notu Ayarlari</h1>
                <p>Musterilere siparis uzerinden ozel mesaj gonderin</p>
            </div>

            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="webyaz-notice success">Ayarlar kaydedildi!</div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('webyaz_order_note_group'); ?>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title" style="border-bottom-color:<?php echo $secondary; ?>;">Genel Ayarlar</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:15px;">Siparis duzenle sayfasindan musteriye ozel not/mesaj gonderebilirsiniz. Notlar e-posta ve/veya SMS ile iletilir.</p>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>E-posta Konusu</label>
                            <input type="text" name="webyaz_order_note[default_subject]" value="<?php echo esc_attr($opts['default_subject']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>E-posta Alt Notu</label>
                            <input type="text" name="webyaz_order_note[email_footer]" value="<?php echo esc_attr($opts['email_footer']); ?>" placeholder="Bizi tercih ettiginiz icin tesekkur ederiz.">
                        </div>
                    </div>
                </div>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title" style="border-bottom-color:<?php echo $secondary; ?>;">Kullanim</h2>
                    <div style="padding:15px;background:#f8f9fa;border-radius:8px;font-size:14px;line-height:1.8;color:#444;">
                        <strong>Nasil Kullanilir:</strong><br>
                        1. WooCommerce > Siparisler > Bir siparis acin<br>
                        2. "Musteriye Ozel Not Gonder" kutusunda mesajinizi yazin<br>
                        3. E-posta ve/veya SMS seceneklerini isaretleyin<br>
                        4. "Notu Gonder" butonuna basin<br>
                        <br>
                        <strong>Not:</strong> SMS gondermek icin SMS Bildirim modulunun aktif olmasi gerekir.
                    </div>
                </div>

                <div style="margin-top:20px;">
                    <?php submit_button('Ayarlari Kaydet', 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>
<?php
    }
}

new Webyaz_Order_Note();
