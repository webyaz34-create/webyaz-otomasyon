<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('WooCommerce')) return;

class Webyaz_Loyalty {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        // Sipariş tamamlandığında puan ekle
        add_action('woocommerce_order_status_completed', array($this, 'award_points'));
        // Hesap oluşturma puanı
        add_action('user_register', array($this, 'registration_bonus'));
        // Sepette puan kullanma
        add_action('woocommerce_cart_calculate_fees', array($this, 'apply_points_discount'));
        add_action('wp_ajax_webyaz_apply_loyalty_points', array($this, 'ajax_apply_points'));
        add_action('wp_ajax_webyaz_remove_loyalty_points', array($this, 'ajax_remove_points'));
        // Sepet sayfasında puan kutusu
        add_action('woocommerce_before_cart_totals', array($this, 'show_points_box'));
        // Hesabım'da puan sekmesi
        add_action('woocommerce_account_webyaz-loyalty_endpoint', array($this, 'my_account_points'));
        add_action('init', array($this, 'add_endpoint'));
        add_filter('woocommerce_account_menu_items', array($this, 'add_menu_item'));
        add_filter('query_vars', array($this, 'add_query_var'));
        // Ürün sayfasında kazanılacak puanı göster
        add_action('woocommerce_single_product_summary', array($this, 'show_earn_info'), 25);
    }

    public function register_settings() {
        register_setting('webyaz_loyalty_group', 'webyaz_loyalty');
    }

    private static function get_defaults() {
        return array(
            'points_per_tl'      => '1',     // Her 1 TL = 1 puan
            'points_value'       => '0.01',  // 1 puan = 0.01 TL
            'min_redeem'         => '100',   // Min kullanım puanı
            'max_discount_pct'   => '50',    // Sepet tutarının max %'si
            'registration_bonus' => '50',    // Kayıt bonusu
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_loyalty', array()), self::get_defaults());
    }

    public function add_endpoint() {
        add_rewrite_endpoint('webyaz-loyalty', EP_ROOT | EP_PAGES);
        if (!get_transient('webyaz_loyalty_flushed')) {
            flush_rewrite_rules();
            set_transient('webyaz_loyalty_flushed', '1', YEAR_IN_SECONDS);
        }
    }

    public function add_query_var($vars) {
        $vars[] = 'webyaz-loyalty';
        return $vars;
    }

    public function add_menu_item($items) {
        $new_items = array();
        foreach ($items as $key => $label) {
            $new_items[$key] = $label;
            if ($key === 'orders') {
                $new_items['webyaz-loyalty'] = '⭐ Puanlarım';
            }
        }
        return $new_items;
    }

    /* Puan işlemleri */
    public static function get_user_points($user_id) {
        return intval(get_user_meta($user_id, '_webyaz_loyalty_points', true));
    }

    public static function add_points($user_id, $points, $reason = '', $order_id = 0) {
        $current = self::get_user_points($user_id);
        $new_total = $current + $points;
        update_user_meta($user_id, '_webyaz_loyalty_points', $new_total);
        // Log kaydı
        $log = get_user_meta($user_id, '_webyaz_loyalty_log', true);
        if (!is_array($log)) $log = array();
        $log[] = array(
            'points' => $points,
            'reason' => $reason,
            'order_id' => $order_id,
            'date' => current_time('mysql'),
            'balance' => $new_total,
        );
        // Son 100 kayıt tut
        if (count($log) > 100) $log = array_slice($log, -100);
        update_user_meta($user_id, '_webyaz_loyalty_log', $log);
    }

    public static function deduct_points($user_id, $points, $reason = '', $order_id = 0) {
        self::add_points($user_id, -$points, $reason, $order_id);
    }

    /* Sipariş tamamlandığında puan ver */
    public function award_points($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        // Daha önce puan verildiyse atla
        if ($order->get_meta('_webyaz_points_awarded')) return;

        $opts = self::get_opts();
        $total = $order->get_subtotal();
        $points = floor($total * floatval($opts['points_per_tl']));
        $user_id = $order->get_customer_id();

        if ($points > 0 && $user_id) {
            self::add_points($user_id, $points, 'Sipariş #' . $order_id . ' puanı', $order_id);
            $order->update_meta_data('_webyaz_points_awarded', $points);
            $order->save();
            // Sipariş notuna ekle
            $order->add_order_note('⭐ ' . $points . ' sadakat puanı eklendi.');
        }
    }

    /* Kayıt bonusu */
    public function registration_bonus($user_id) {
        $opts = self::get_opts();
        $bonus = intval($opts['registration_bonus']);
        if ($bonus > 0) {
            self::add_points($user_id, $bonus, 'Üyelik hoş geldin bonusu');
        }
    }

    /* Ürün sayfasında kazanılacak puan */
    public function show_earn_info() {
        global $product;
        if (!$product || !is_user_logged_in()) return;
        $opts = self::get_opts();
        $price = floatval($product->get_price());
        $earn = floor($price * floatval($opts['points_per_tl']));
        if ($earn > 0) {
            echo '<div style="display:inline-flex;align-items:center;gap:6px;background:#fff8e1;border:1px solid #ffe082;border-radius:8px;padding:8px 14px;margin:8px 0;font-size:13px;color:#f57f17;">';
            echo '<span style="font-size:16px;">⭐</span> Bu ürünü alarak <strong>' . $earn . ' puan</strong> kazanırsınız';
            echo '</div>';
        }
    }

    /* Sepet sayfasında puan kullanım kutusu */
    public function show_points_box() {
        if (!is_user_logged_in()) return;
        $user_id = get_current_user_id();
        $points = self::get_user_points($user_id);
        $opts = self::get_opts();
        $min = intval($opts['min_redeem']);
        $value_per_point = floatval($opts['points_value']);
        $max_pct = floatval($opts['max_discount_pct']);
        $subtotal = WC()->cart ? WC()->cart->get_subtotal() : 0;
        $max_discount = $subtotal * $max_pct / 100;
        $max_points_usable = floor($max_discount / $value_per_point);
        $usable_points = min($points, $max_points_usable);
        $discount_value = $usable_points * $value_per_point;

        $using = WC()->session ? WC()->session->get('webyaz_loyalty_use_points', 0) : 0;
        ?>
        <div style="background:linear-gradient(135deg,#fff8e1,#fff3e0);border:2px solid #ffe082;border-radius:12px;padding:18px 22px;margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
                <div>
                    <div style="font-size:15px;font-weight:700;color:#f57f17;">⭐ Sadakat Puanlarınız</div>
                    <div style="font-size:12px;color:#999;margin-top:2px;">1 puan = <?php echo number_format($value_per_point, 2, ',', '.'); ?> ₺</div>
                </div>
                <div style="background:#f57f17;color:#fff;padding:8px 18px;border-radius:20px;font-size:16px;font-weight:700;">
                    <?php echo number_format($points); ?> puan
                </div>
            </div>

            <?php if ($using > 0): ?>
                <div style="background:#e8f5e9;border-radius:8px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                    <div style="color:#2e7d32;font-weight:600;font-size:13px;">
                        ✅ <?php echo number_format($using); ?> puan kullanılıyor (−<?php echo wc_price($using * $value_per_point); ?>)
                    </div>
                    <button type="button" onclick="wyRemoveLoyalty()" style="background:#fff;color:#d32f2f;border:1px solid #d32f2f;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">İptal Et</button>
                </div>
            <?php elseif ($points >= $min && $subtotal > 0): ?>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <input type="number" id="wyLoyaltyInput" min="<?php echo $min; ?>" max="<?php echo $usable_points; ?>" value="<?php echo $usable_points; ?>" style="width:100px;padding:8px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px;text-align:center;">
                    <span style="font-size:12px;color:#666;">puan = <strong id="wyLoyaltyValue"><?php echo number_format($discount_value, 2, ',', '.'); ?> ₺</strong></span>
                    <button type="button" onclick="wyApplyLoyalty()" style="background:#f57f17;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">Puanları Kullan</button>
                </div>
                <div style="font-size:11px;color:#999;margin-top:8px;">
                    Min <?php echo $min; ?> puan · Max sepet tutarının %<?php echo intval($max_pct); ?>'i (<?php echo wc_price($max_discount); ?>)
                </div>
            <?php elseif ($points < $min): ?>
                <div style="font-size:13px;color:#999;">
                    Puan kullanmak için en az <strong><?php echo $min; ?></strong> puan gereklidir. (<strong><?php echo $min - $points; ?></strong> puan daha kazanın)
                </div>
            <?php endif; ?>
        </div>
        <script>
        function wyApplyLoyalty() {
            var pts = document.getElementById('wyLoyaltyInput').value;
            jQuery.post(webyaz_ajax.ajax_url, {action:'webyaz_apply_loyalty_points',nonce:webyaz_ajax.nonce,points:pts}, function(r){
                if(r.success) { location.reload(); } else { alert(r.data); }
            });
        }
        function wyRemoveLoyalty() {
            jQuery.post(webyaz_ajax.ajax_url, {action:'webyaz_remove_loyalty_points',nonce:webyaz_ajax.nonce}, function(r){
                if(r.success) location.reload();
            });
        }
        var inp = document.getElementById('wyLoyaltyInput');
        if(inp) {
            inp.addEventListener('input', function(){
                var v = parseFloat(this.value) * <?php echo $value_per_point; ?>;
                document.getElementById('wyLoyaltyValue').textContent = v.toFixed(2).replace('.',',') + ' ₺';
            });
        }
        </script>
        <?php
    }

    /* AJAX: Puan uygula */
    public function ajax_apply_points() {
        check_ajax_referer('webyaz_nonce', 'nonce');
        $user_id = get_current_user_id();
        if (!$user_id) wp_send_json_error('Giriş yapmalısınız.');

        $opts = self::get_opts();
        $points = absint($_POST['points'] ?? 0);
        $available = self::get_user_points($user_id);
        $min = intval($opts['min_redeem']);

        if ($points < $min) wp_send_json_error('Minimum ' . $min . ' puan gerekli.');
        if ($points > $available) wp_send_json_error('Yeterli puanınız yok.');

        WC()->session->set('webyaz_loyalty_use_points', $points);
        wp_send_json_success();
    }

    public function ajax_remove_points() {
        check_ajax_referer('webyaz_nonce', 'nonce');
        WC()->session->set('webyaz_loyalty_use_points', 0);
        wp_send_json_success();
    }

    /* Sepet hesaplamasında puan indirimi uygula */
    public function apply_points_discount($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        $user_id = get_current_user_id();
        if (!$user_id) return;

        $using = WC()->session ? WC()->session->get('webyaz_loyalty_use_points', 0) : 0;
        if ($using <= 0) return;

        $opts = self::get_opts();
        $value = $using * floatval($opts['points_value']);
        $max_pct = floatval($opts['max_discount_pct']);
        $subtotal = $cart->get_subtotal();
        $max_discount = $subtotal * $max_pct / 100;
        $value = min($value, $max_discount);

        if ($value > 0) {
            $cart->add_fee('⭐ Puan İndirimi (' . number_format($using) . ' puan)', -$value);
        }
    }

    /* Hesabım: Puan geçmişi */
    public function my_account_points() {
        $user_id = get_current_user_id();
        $points = self::get_user_points($user_id);
        $opts = self::get_opts();
        $value = $points * floatval($opts['points_value']);
        $log = get_user_meta($user_id, '_webyaz_loyalty_log', true);
        if (!is_array($log)) $log = array();
        $log = array_reverse($log);
        ?>
        <div style="background:linear-gradient(135deg,#fff8e1,#fff3e0);border-radius:14px;padding:24px;margin-bottom:20px;text-align:center;">
            <div style="font-size:14px;color:#f57f17;font-weight:600;">Toplam Puanınız</div>
            <div style="font-size:42px;font-weight:800;color:#f57f17;margin:8px 0;">⭐ <?php echo number_format($points); ?></div>
            <div style="font-size:13px;color:#999;">≈ <?php echo number_format($value, 2, ',', '.'); ?> ₺ değerinde</div>
        </div>

        <?php if (!empty($log)): ?>
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr style="background:#f8f9fa;"><th style="padding:12px;text-align:left;">Tarih</th><th style="padding:12px;text-align:left;">Açıklama</th><th style="padding:12px;text-align:right;">Puan</th><th style="padding:12px;text-align:right;">Bakiye</th></tr></thead>
            <tbody>
            <?php foreach ($log as $entry): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px;font-size:12px;color:#999;"><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($entry['date']))); ?></td>
                    <td style="padding:12px;font-size:13px;"><?php echo esc_html($entry['reason']); ?></td>
                    <td style="padding:12px;text-align:right;font-weight:700;color:<?php echo $entry['points'] > 0 ? '#4caf50' : '#f44336'; ?>;">
                        <?php echo ($entry['points'] > 0 ? '+' : '') . number_format($entry['points']); ?>
                    </td>
                    <td style="padding:12px;text-align:right;font-size:13px;color:#666;"><?php echo number_format($entry['balance']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p style="color:#999;text-align:center;">Henüz puan hareketiniz bulunmamaktadır.</p>
        <?php endif;
    }

    /* Admin */
    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Sadakat Puani', 'Sadakat Puani', 'manage_options', 'webyaz-loyalty', array($this, 'render_admin'));
    }

    public function render_admin() {
        $opts = self::get_opts();

        // İstatistikler
        $users = get_users(array('meta_key' => '_webyaz_loyalty_points', 'meta_compare' => '>', 'meta_value' => '0'));
        $total_points = 0;
        $top_users = array();
        foreach ($users as $u) {
            $p = self::get_user_points($u->ID);
            $total_points += $p;
            $top_users[] = array('name' => $u->display_name, 'email' => $u->user_email, 'points' => $p, 'id' => $u->ID);
        }
        usort($top_users, function($a, $b) { return $b['points'] <=> $a['points']; });
        $top_users = array_slice($top_users, 0, 10);
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>⭐ Sadakat Puanı Sistemi</h1><p>Müşterilerinizi ödüllendirin ve geri dönüşlerini artırın</p></div>
            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">Ayarlar kaydedildi!</div><?php endif; ?>

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:25px;">
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #f57f17;">
                    <div style="font-size:24px;font-weight:700;color:#f57f17;"><?php echo count($users); ?></div>
                    <div style="font-size:12px;color:#666;">Puanlı Üye</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #4caf50;">
                    <div style="font-size:24px;font-weight:700;color:#4caf50;"><?php echo number_format($total_points); ?></div>
                    <div style="font-size:12px;color:#666;">Toplam Puan</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #2196f3;">
                    <div style="font-size:24px;font-weight:700;color:#2196f3;"><?php echo number_format($total_points * floatval($opts['points_value']), 2, ',', '.'); ?> ₺</div>
                    <div style="font-size:12px;color:#666;">Toplam Değer</div>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('webyaz_loyalty_group'); ?>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Puan Ayarları</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Her 1 TL = Kaç Puan?</label>
                            <input type="number" step="0.1" name="webyaz_loyalty[points_per_tl]" value="<?php echo esc_attr($opts['points_per_tl']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>1 Puan = Kaç TL?</label>
                            <input type="number" step="0.001" name="webyaz_loyalty[points_value]" value="<?php echo esc_attr($opts['points_value']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Min Kullanım Puanı</label>
                            <input type="number" name="webyaz_loyalty[min_redeem]" value="<?php echo esc_attr($opts['min_redeem']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Max İndirim (Sepet %)</label>
                            <input type="number" name="webyaz_loyalty[max_discount_pct]" value="<?php echo esc_attr($opts['max_discount_pct']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Kayıt Bonusu (Puan)</label>
                            <input type="number" name="webyaz_loyalty[registration_bonus]" value="<?php echo esc_attr($opts['registration_bonus']); ?>">
                        </div>
                    </div>
                </div>
                <?php submit_button('Kaydet'); ?>
            </form>

            <?php if (!empty($top_users)): ?>
            <div class="webyaz-settings-section" style="margin-top:20px;">
                <h2 class="webyaz-section-title">🏆 En Çok Puanlı Üyeler</h2>
                <table style="width:100%;border-collapse:collapse;">
                    <thead><tr style="background:#f8f9fa;"><th style="padding:12px;text-align:left;">#</th><th style="padding:12px;text-align:left;">Üye</th><th style="padding:12px;text-align:left;">E-posta</th><th style="padding:12px;text-align:right;">Puan</th><th style="padding:12px;text-align:right;">Değer</th></tr></thead>
                    <tbody>
                    <?php foreach ($top_users as $i => $u): ?>
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:12px;font-weight:600;"><?php echo $i + 1; ?></td>
                            <td style="padding:12px;"><?php echo esc_html($u['name']); ?></td>
                            <td style="padding:12px;font-size:12px;color:#666;"><?php echo esc_html($u['email']); ?></td>
                            <td style="padding:12px;text-align:right;font-weight:700;color:#f57f17;"><?php echo number_format($u['points']); ?></td>
                            <td style="padding:12px;text-align:right;font-size:12px;color:#666;"><?php echo number_format($u['points'] * floatval($opts['points_value']), 2, ',', '.'); ?> ₺</td>
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

new Webyaz_Loyalty();
