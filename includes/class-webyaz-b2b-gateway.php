<?php
if (!defined('ABSPATH')) exit;

class Webyaz_B2B_Gateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'webyaz_bayi_balance';
        $this->method_title = 'Bayi Bakiye';
        $this->method_description = 'Bayiler bakiyeleriyle odeme yapar.';
        $this->title = 'Bakiye ile Ode';
        $this->description = 'Bayi bakiyenizden odeme yapilir.';
        $this->has_fields = true;
        $this->enabled = 'yes';

        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Aktif',
                'type' => 'checkbox',
                'label' => 'Bayi bakiye odemesini aktif et',
                'default' => 'yes',
            ),
        );
    }

    public function is_available()
    {
        if (!is_user_logged_in()) return false;
        $user = wp_get_current_user();
        if (!in_array('webyaz_bayi', (array) $user->roles)) return false;

        // Bakiye %50 altindaysa odeme yontemini gizle
        $balance = floatval(get_user_meta($user->ID, '_webyaz_bayi_balance', true));
        $initial = floatval(get_user_meta($user->ID, '_webyaz_bayi_initial_balance', true));
        $opts = get_option('webyaz_b2b_settings', array());
        $low_percent = intval($opts['low_balance_percent'] ?? 50);
        $low_threshold = ($initial > 0) ? ($initial * $low_percent / 100) : 0;

        if ($initial > 0 && $balance <= $low_threshold) {
            return false;
        }

        return true;
    }

    public function payment_fields()
    {
        $user = wp_get_current_user();
        $balance = floatval(get_user_meta($user->ID, '_webyaz_bayi_balance', true));
        $initial = floatval(get_user_meta($user->ID, '_webyaz_bayi_initial_balance', true));
        $opts = get_option('webyaz_b2b_settings', array());
        $low_percent = intval($opts['low_balance_percent'] ?? 50);
        $low_threshold = ($initial > 0) ? ($initial * $low_percent / 100) : 0;
        $usable = ($initial > 0) ? max(0, $balance - $low_threshold) : $balance;

        echo '<div style="background:#e8f5e9;padding:15px;border-radius:8px;margin:10px 0;">';
        echo '<p style="margin:0;font-size:15px;">💰 Mevcut Bakiyeniz: <strong style="color:#4CAF50;">' . wc_price($balance) . '</strong></p>';
        echo '<p style="margin:5px 0 0;font-size:13px;color:#666;">🔒 Kullanilabilir limit: <strong>' . wc_price($usable) . '</strong> <small>(bakiyenizin %' . (100 - $low_percent) . ' kadarini kullanabilirsiniz)</small></p>';

        $cart_total = 0;
        if (WC()->cart) {
            $cart_total = floatval(WC()->cart->get_total('edit'));
        }

        if ($cart_total > $usable) {
            echo '<p style="margin:8px 0 0;color:#f44336;font-weight:bold;">⚠️ Kullanilabilir limitiniz asiliyor! ' . wc_price($cart_total - $usable) . ' eksik. Lutfen bakiye yukleyin.</p>';
        }
        echo '</div>';
    }

    public function validate_fields()
    {
        $user = wp_get_current_user();
        $balance = floatval(get_user_meta($user->ID, '_webyaz_bayi_balance', true));
        $initial = floatval(get_user_meta($user->ID, '_webyaz_bayi_initial_balance', true));
        $opts = get_option('webyaz_b2b_settings', array());
        $low_percent = intval($opts['low_balance_percent'] ?? 50);
        $low_threshold = ($initial > 0) ? ($initial * $low_percent / 100) : 0;
        $usable = ($initial > 0) ? max(0, $balance - $low_threshold) : $balance;

        $cart_total = 0;
        if (WC()->cart) {
            $cart_total = floatval(WC()->cart->get_total('edit'));
        }

        if ($cart_total > $usable) {
            wc_add_notice('Kullanilabilir bakiye limitiniz yetersiz! Kullanilabilir: ' . wc_price($usable) . ', Gerekli: ' . wc_price($cart_total) . '. Lutfen bakiye yukleyin.', 'error');
            return false;
        }

        if ($balance < $cart_total) {
            wc_add_notice('Yetersiz bakiye! Mevcut: ' . wc_price($balance) . ', Gerekli: ' . wc_price($cart_total), 'error');
            return false;
        }

        return true;
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $order->payment_complete();
        $order->add_order_note('Bayi bakiyesinden odendi.');
        WC()->cart->empty_cart();

        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }
}
