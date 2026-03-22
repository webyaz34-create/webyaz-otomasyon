<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('WooCommerce')) return;

class Webyaz_Checkout {

    public function __construct() {
        add_filter('woocommerce_checkout_fields', [$this, 'reorder_fields'], 20);
        add_action('woocommerce_before_checkout_billing_form', [$this, 'customer_type_toggle']);
        add_action('woocommerce_checkout_process', [$this, 'validate_fields']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_fields']);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_in_admin']);
        add_action('woocommerce_review_order_before_submit', [$this, 'legal_checkboxes']);
        add_action('woocommerce_checkout_process', [$this, 'validate_legal_checkboxes']);
        add_action('woocommerce_review_order_after_submit', [$this, 'trust_badge']);
    }

    public function customer_type_toggle() {
        ?>
        <div class="webyaz-customer-type">
            <div class="webyaz-toggle-wrap">
                <button type="button" class="webyaz-toggle-btn active" data-type="bireysel">Bireysel</button>
                <button type="button" class="webyaz-toggle-btn" data-type="kurumsal">Kurumsal</button>
            </div>
            <input type="hidden" name="webyaz_customer_type" id="webyaz_customer_type" value="bireysel">
        </div>
        <?php
    }

    public function reorder_fields($fields) {
        $fields['billing']['webyaz_tc_kimlik'] = [
            'type' => 'text',
            'label' => 'T.C. Kimlik Numarası (isteğe bağlı)',
            'placeholder' => '11 haneli T.C. Numaranız',
            'class' => ['form-row-wide', 'webyaz-bireysel-field'],
            'required' => false,
            'maxlength' => 11,
            'custom_attributes' => ['pattern' => '[0-9]{11}'],
            'priority' => 25,
        ];

        $fields['billing']['webyaz_firma_adi'] = [
            'type' => 'text',
            'label' => 'Firma Adı',
            'placeholder' => 'Firma Adınız',
            'class' => ['form-row-wide', 'webyaz-kurumsal-field'],
            'required' => false,
            'priority' => 25,
        ];

        $fields['billing']['webyaz_vergi_dairesi'] = [
            'type' => 'text',
            'label' => 'Vergi Dairesi',
            'placeholder' => 'Vergi Dairesi',
            'class' => ['form-row-first', 'webyaz-kurumsal-field'],
            'required' => false,
            'priority' => 26,
        ];

        $fields['billing']['webyaz_vergi_no'] = [
            'type' => 'text',
            'label' => 'Vergi No',
            'placeholder' => 'Vergi Numaranız',
            'class' => ['form-row-last', 'webyaz-kurumsal-field'],
            'required' => false,
            'maxlength' => 11,
            'priority' => 27,
        ];

        return $fields;
    }

    public function legal_checkboxes() {
        $mss_page = get_page_by_path('mesafeli-satis-sozlesmesi');
        $kk_page = get_page_by_path('site-kullanim-kurallari');

        $mss_url = $mss_page ? get_permalink($mss_page->ID) : '#';
        $kk_url = $kk_page ? get_permalink($kk_page->ID) : '#';
        ?>
        <div class="webyaz-legal-checkboxes">
            <label class="webyaz-legal-check">
                <input type="checkbox" name="webyaz_mss_accepted" value="1">
                <span><a href="<?php echo esc_url($mss_url); ?>" target="_blank">Mesafeli Satış Sözleşmesi</a>'ni okudum, anladım ve kabul ediyorum. <abbr class="required">*</abbr></span>
            </label>
            <label class="webyaz-legal-check">
                <input type="checkbox" name="webyaz_kk_accepted" value="1">
                <span><a href="<?php echo esc_url($kk_url); ?>" target="_blank">Site Kullanım Kuralları</a>'nı okudum ve kabul ediyorum. <abbr class="required">*</abbr></span>
            </label>
        </div>
        <?php
    }

    public function validate_legal_checkboxes() {
        if (empty($_POST['webyaz_mss_accepted'])) {
            wc_add_notice('Mesafeli Satış Sözleşmesi\'ni kabul etmelisiniz.', 'error');
        }
        if (empty($_POST['webyaz_kk_accepted'])) {
            wc_add_notice('Site Kullanım Kuralları\'nı kabul etmelisiniz.', 'error');
        }
    }

    public function trust_badge() {
        ?>
        <div class="webyaz-trust-badge">
            <strong>%100 Güvenli Alışveriş</strong>
            <p>Bu site 256-bit SSL Sertifikası ile korunmaktadır.<br>Bizi tercih ettiğiniz için teşekkür ederiz.</p>
        </div>
        <?php
    }

    public function validate_fields() {
        $type = sanitize_text_field($_POST['webyaz_customer_type'] ?? 'bireysel');

        if ($type === 'bireysel') {
            $tc = sanitize_text_field($_POST['webyaz_tc_kimlik'] ?? '');
            if (!empty($tc) && (!preg_match('/^[0-9]{11}$/', $tc) || !self::validate_tc($tc))) {
                wc_add_notice('Geçerli bir T.C. Kimlik No giriniz.', 'error');
            }
        } else {
            if (empty($_POST['webyaz_firma_adi'])) {
                wc_add_notice('Firma Adı alanı zorunludur.', 'error');
            }
            if (empty($_POST['webyaz_vergi_dairesi'])) {
                wc_add_notice('Vergi Dairesi alanı zorunludur.', 'error');
            }
            if (empty($_POST['webyaz_vergi_no'])) {
                wc_add_notice('Vergi No alanı zorunludur.', 'error');
            }
        }
    }

    public static function validate_tc($tc) {
        if (strlen($tc) !== 11 || $tc[0] === '0') return false;
        $digits = array_map('intval', str_split($tc));
        $odd = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8];
        $even = $digits[1] + $digits[3] + $digits[5] + $digits[7];
        if (($odd * 7 - $even) % 10 !== $digits[9]) return false;
        $sum = 0;
        for ($i = 0; $i < 10; $i++) $sum += $digits[$i];
        if ($sum % 10 !== $digits[10]) return false;
        return true;
    }

    public function save_fields($order_id) {
        $type = sanitize_text_field($_POST['webyaz_customer_type'] ?? 'bireysel');
        update_post_meta($order_id, '_webyaz_customer_type', $type);

        if ($type === 'bireysel') {
            update_post_meta($order_id, '_webyaz_tc_kimlik', sanitize_text_field($_POST['webyaz_tc_kimlik'] ?? ''));
        } else {
            update_post_meta($order_id, '_webyaz_firma_adi', sanitize_text_field($_POST['webyaz_firma_adi'] ?? ''));
            update_post_meta($order_id, '_webyaz_vergi_dairesi', sanitize_text_field($_POST['webyaz_vergi_dairesi'] ?? ''));
            update_post_meta($order_id, '_webyaz_vergi_no', sanitize_text_field($_POST['webyaz_vergi_no'] ?? ''));
        }
    }

    public function display_in_admin($order) {
        $order_id = $order->get_id();
        $type = get_post_meta($order_id, '_webyaz_customer_type', true);

        echo '<div class="webyaz-admin-fields">';
        echo '<h3>Webyaz Fatura Bilgileri</h3>';
        echo '<p><strong>Müşteri Tipi:</strong> ' . esc_html(ucfirst($type)) . '</p>';

        if ($type === 'bireysel') {
            echo '<p><strong>T.C. Kimlik No:</strong> ' . esc_html(get_post_meta($order_id, '_webyaz_tc_kimlik', true)) . '</p>';
        } else {
            echo '<p><strong>Firma Adı:</strong> ' . esc_html(get_post_meta($order_id, '_webyaz_firma_adi', true)) . '</p>';
            echo '<p><strong>Vergi Dairesi:</strong> ' . esc_html(get_post_meta($order_id, '_webyaz_vergi_dairesi', true)) . '</p>';
            echo '<p><strong>Vergi No:</strong> ' . esc_html(get_post_meta($order_id, '_webyaz_vergi_no', true)) . '</p>';
        }
        echo '</div>';
    }
}

new Webyaz_Checkout();
