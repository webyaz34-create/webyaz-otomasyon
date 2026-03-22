<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('WooCommerce')) return;

class Webyaz_Referral {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        // Referans kodu oluştur (ilk giriş)
        add_action('wp_login', array($this, 'ensure_referral_code'), 10, 2);
        // Referans linki ile gelen ziyaretçiyi cookie'le
        add_action('wp', array($this, 'track_referral'));
        // Yeni kayıtta referans bağlantısı kur
        add_action('user_register', array($this, 'link_referral'));
        // Sipariş tamamlandığında her iki tarafa ödül
        add_action('woocommerce_order_status_completed', array($this, 'reward_on_purchase'));
        // Hesabım'da referans sekmesi
        add_action('woocommerce_account_webyaz-referral_endpoint', array($this, 'my_account_referral'));
        add_action('init', array($this, 'add_endpoint'));
        add_filter('woocommerce_account_menu_items', array($this, 'add_menu_item'));
        add_filter('query_vars', array($this, 'add_query_var'));
    }

    public function register_settings() {
        register_setting('webyaz_referral_group', 'webyaz_referral');
    }

    private static function get_defaults() {
        return array(
            'referrer_reward'  => 'coupon',    // coupon veya points
            'referee_reward'   => 'coupon',    // coupon veya points
            'referrer_amount'  => '50',        // Davet eden ödül miktarı (TL veya puan)
            'referee_amount'   => '30',        // Davet edilen ödül miktarı
            'min_order_amount' => '100',       // Min sipariş tutarı (ödül için)
            'coupon_type'      => 'fixed_cart', // fixed_cart veya percent
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_referral', array()), self::get_defaults());
    }

    public function add_endpoint() {
        add_rewrite_endpoint('webyaz-referral', EP_ROOT | EP_PAGES);
        if (!get_transient('webyaz_referral_flushed')) {
            flush_rewrite_rules();
            set_transient('webyaz_referral_flushed', '1', YEAR_IN_SECONDS);
        }
    }

    public function add_query_var($vars) {
        $vars[] = 'webyaz-referral';
        return $vars;
    }

    public function add_menu_item($items) {
        $new_items = array();
        foreach ($items as $key => $label) {
            $new_items[$key] = $label;
            if ($key === 'orders') {
                $new_items['webyaz-referral'] = '🎁 Arkadaşını Getir';
            }
        }
        return $new_items;
    }

    /* Kullanıcıya benzersiz referans kodu ata */
    public function ensure_referral_code($user_login, $user) {
        if (!get_user_meta($user->ID, '_webyaz_ref_code', true)) {
            $code = strtoupper(substr(md5($user->ID . wp_salt()), 0, 8));
            update_user_meta($user->ID, '_webyaz_ref_code', $code);
        }
    }

    public static function get_ref_code($user_id) {
        $code = get_user_meta($user_id, '_webyaz_ref_code', true);
        if (!$code) {
            $code = strtoupper(substr(md5($user_id . wp_salt()), 0, 8));
            update_user_meta($user_id, '_webyaz_ref_code', $code);
        }
        return $code;
    }

    /* Referans linkiyle gelen ziyaretçiyi cookie'le */
    public function track_referral() {
        if (isset($_GET['ref']) && !is_user_logged_in()) {
            $code = sanitize_text_field($_GET['ref']);
            // Kodu doğrula
            $users = get_users(array('meta_key' => '_webyaz_ref_code', 'meta_value' => $code, 'number' => 1));
            if (!empty($users)) {
                setcookie('webyaz_ref', $code, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);
            }
        }
    }

    /* Yeni kayıtta referans bağlantısı kur */
    public function link_referral($user_id) {
        $code = isset($_COOKIE['webyaz_ref']) ? sanitize_text_field($_COOKIE['webyaz_ref']) : '';
        if (!$code) return;

        $referrers = get_users(array('meta_key' => '_webyaz_ref_code', 'meta_value' => $code, 'number' => 1));
        if (empty($referrers)) return;

        $referrer_id = $referrers[0]->ID;
        if ($referrer_id === $user_id) return; // Kendini refere edemez

        update_user_meta($user_id, '_webyaz_referred_by', $referrer_id);

        // Referrer loguna ekle
        $referrals = get_user_meta($referrer_id, '_webyaz_referral_list', true);
        if (!is_array($referrals)) $referrals = array();
        $referrals[] = array(
            'user_id' => $user_id,
            'date' => current_time('mysql'),
            'status' => 'registered',
            'rewarded' => false,
        );
        update_user_meta($referrer_id, '_webyaz_referral_list', $referrals);

        // Cookie temizle
        setcookie('webyaz_ref', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
    }

    /* Sipariş tamamlandığında ödül ver */
    public function reward_on_purchase($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        if ($order->get_meta('_webyaz_referral_rewarded')) return;

        $user_id = $order->get_customer_id();
        if (!$user_id) return;

        $referrer_id = get_user_meta($user_id, '_webyaz_referred_by', true);
        if (!$referrer_id) return;

        $opts = self::get_opts();
        $min = floatval($opts['min_order_amount']);
        if ($order->get_subtotal() < $min) return;

        // Davet edene ödül
        $this->give_reward($referrer_id, 'referrer', $order_id);
        // Davet edilene ödül
        $this->give_reward($user_id, 'referee', $order_id);

        $order->update_meta_data('_webyaz_referral_rewarded', '1');
        $order->save();

        // Referrer logunu güncelle
        $referrals = get_user_meta($referrer_id, '_webyaz_referral_list', true);
        if (is_array($referrals)) {
            foreach ($referrals as &$r) {
                if ($r['user_id'] == $user_id && !$r['rewarded']) {
                    $r['status'] = 'purchased';
                    $r['rewarded'] = true;
                    $r['order_id'] = $order_id;
                    break;
                }
            }
            update_user_meta($referrer_id, '_webyaz_referral_list', $referrals);
        }

        // E-posta bildirim
        $referrer = get_userdata($referrer_id);
        if ($referrer) {
            $site_name = get_bloginfo('name');
            $amount = $opts['referrer_amount'];
            $subject = $site_name . ' - Referans Ödülünüz Kazandınız!';
            $msg = '<h2>🎁 Tebrikler!</h2>';
            $msg .= '<p>Davet ettiğiniz kişi ilk alışverişini tamamladı.</p>';
            $msg .= '<p><strong>Ödülünüz:</strong> ' . $amount . ($opts['referrer_reward'] === 'points' ? ' puan' : ' TL kupon') . '</p>';
            $msg .= '<p>Hesabınızdan kontrol edebilirsiniz.</p>';
            wp_mail($referrer->user_email, $subject, $msg, array('Content-Type: text/html; charset=UTF-8'));
        }
    }

    private function give_reward($user_id, $role, $order_id) {
        $opts = self::get_opts();
        $type = ($role === 'referrer') ? $opts['referrer_reward'] : $opts['referee_reward'];
        $amount = floatval(($role === 'referrer') ? $opts['referrer_amount'] : $opts['referee_amount']);

        if ($type === 'points' && class_exists('Webyaz_Loyalty')) {
            Webyaz_Loyalty::add_points($user_id, intval($amount), 'Referans ödülü (Sipariş #' . $order_id . ')', $order_id);
        } else {
            // WooCommerce kupon oluştur
            $user = get_userdata($user_id);
            $code = 'REF-' . strtoupper(wp_generate_password(6, false));
            $coupon = new WC_Coupon();
            $coupon->set_code($code);
            $coupon->set_discount_type($opts['coupon_type']);
            $coupon->set_amount($amount);
            $coupon->set_usage_limit(1);
            $coupon->set_email_restrictions(array($user->user_email));
            $coupon->set_date_expires(strtotime('+30 days'));
            $coupon->save();

            // Kullanıcıya e-posta
            $site_name = get_bloginfo('name');
            $label = ($role === 'referrer') ? 'Davet eden ödülü' : 'Hoş geldin hediyesi';
            $subject = $site_name . ' - ' . $label . ': ' . $code;
            $msg = '<h2>🎁 ' . $label . '</h2>';
            $msg .= '<p>İşte kuponunuz:</p>';
            $msg .= '<div style="background:#f0f0f0;padding:15px 25px;font-size:24px;font-weight:700;text-align:center;border-radius:10px;letter-spacing:3px;">' . $code . '</div>';
            $msg .= '<p style="margin-top:15px;">Değer: <strong>' . $amount . ($opts['coupon_type'] === 'percent' ? '%' : ' TL') . '</strong></p>';
            $msg .= '<p>30 gün içinde kullanabilirsiniz.</p>';
            wp_mail($user->user_email, $subject, $msg, array('Content-Type: text/html; charset=UTF-8'));
        }
    }

    /* Hesabım: Referans paneli */
    public function my_account_referral() {
        $user_id = get_current_user_id();
        $code = self::get_ref_code($user_id);
        $link = home_url('?ref=' . $code);
        $referrals = get_user_meta($user_id, '_webyaz_referral_list', true);
        if (!is_array($referrals)) $referrals = array();
        $opts = self::get_opts();
        $referrer_label = $opts['referrer_amount'] . ($opts['referrer_reward'] === 'points' ? ' puan' : ' TL kupon');
        $referee_label = $opts['referee_amount'] . ($opts['referee_reward'] === 'points' ? ' puan' : ' TL kupon');
        ?>
        <div style="background:linear-gradient(135deg,#e3f2fd,#bbdefb);border-radius:14px;padding:24px;margin-bottom:20px;">
            <div style="font-size:18px;font-weight:700;color:#1565c0;margin-bottom:8px;">🎁 Arkadaşını Davet Et, Kazan!</div>
            <div style="font-size:13px;color:#1976d2;margin-bottom:16px;">
                Arkadaşınız alışveriş yaptığında siz <strong><?php echo $referrer_label; ?></strong>, arkadaşınız <strong><?php echo $referee_label; ?></strong> kazanır!
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <input type="text" value="<?php echo esc_attr($link); ?>" id="wyRefLink" readonly style="flex:1;min-width:200px;padding:12px;border:2px solid #1976d2;border-radius:8px;font-size:14px;background:#fff;color:#333;">
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('wyRefLink').value);this.textContent='✅ Kopyalandı!';setTimeout(function(){document.querySelector('#wyRefCopyBtn').textContent='📋 Kopyala';},2000);" id="wyRefCopyBtn" style="background:#1565c0;color:#fff;border:none;padding:12px 22px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">📋 Kopyala</button>
            </div>
            <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;">
                <a href="https://wa.me/?text=<?php echo urlencode('Bu mağazadan alışveriş yap, ikimiz de kazanalım! ' . $link); ?>" target="_blank" style="background:#25D366;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;">WhatsApp</a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($link); ?>" target="_blank" style="background:#1877F2;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;">Facebook</a>
                <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode('Bu mağazadan alışveriş yap, ikimiz de kazanalım! ' . $link); ?>" target="_blank" style="background:#1DA1F2;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;">Twitter</a>
            </div>
        </div>

        <h3 style="font-size:16px;margin-bottom:12px;">Davetlerim (<?php echo count($referrals); ?>)</h3>
        <?php if (!empty($referrals)): ?>
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr style="background:#f8f9fa;"><th style="padding:12px;text-align:left;">Tarih</th><th style="padding:12px;text-align:left;">Durum</th><th style="padding:12px;text-align:left;">Ödül</th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($referrals) as $r):
                $status_map = array('registered' => array('Kayıt Oldu', '#ff9800'), 'purchased' => array('Alışveriş Yaptı', '#4caf50'));
                $s = $status_map[$r['status']] ?? array($r['status'], '#999');
            ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px;font-size:12px;color:#999;"><?php echo esc_html(date_i18n('d.m.Y', strtotime($r['date']))); ?></td>
                    <td style="padding:12px;"><span style="background:<?php echo $s[1]; ?>22;color:<?php echo $s[1]; ?>;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;"><?php echo $s[0]; ?></span></td>
                    <td style="padding:12px;font-size:13px;"><?php echo $r['rewarded'] ? '✅ ' . $referrer_label : '⏳ Bekleniyor'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p style="color:#999;text-align:center;padding:20px;">Henüz davet ettiğiniz kimse yok. Linkinizi paylaşın!</p>
        <?php endif;
    }

    /* Admin */
    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Referans Sistemi', 'Referans Sistemi', 'manage_options', 'webyaz-referral', array($this, 'render_admin'));
    }

    public function render_admin() {
        $opts = self::get_opts();

        // İstatistikler
        $all_users = get_users(array('meta_key' => '_webyaz_referral_list'));
        $total_refs = 0;
        $total_purchases = 0;
        foreach ($all_users as $u) {
            $list = get_user_meta($u->ID, '_webyaz_referral_list', true);
            if (is_array($list)) {
                $total_refs += count($list);
                foreach ($list as $r) {
                    if ($r['rewarded']) $total_purchases++;
                }
            }
        }
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>🎁 Referans Sistemi</h1><p>Müşterilerinizi marka elçiniz yapın</p></div>
            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">Ayarlar kaydedildi!</div><?php endif; ?>

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:25px;">
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #1565c0;">
                    <div style="font-size:24px;font-weight:700;color:#1565c0;"><?php echo $total_refs; ?></div>
                    <div style="font-size:12px;color:#666;">Toplam Davet</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #4caf50;">
                    <div style="font-size:24px;font-weight:700;color:#4caf50;"><?php echo $total_purchases; ?></div>
                    <div style="font-size:12px;color:#666;">Dönüşen Davet</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #ff9800;">
                    <div style="font-size:24px;font-weight:700;color:#ff9800;"><?php echo $total_refs > 0 ? round(($total_purchases / $total_refs) * 100) : 0; ?>%</div>
                    <div style="font-size:12px;color:#666;">Dönüşüm Oranı</div>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('webyaz_referral_group'); ?>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Ödül Ayarları</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Davet Eden Ödül Tipi</label>
                            <select name="webyaz_referral[referrer_reward]">
                                <option value="coupon" <?php selected($opts['referrer_reward'], 'coupon'); ?>>Kupon</option>
                                <option value="points" <?php selected($opts['referrer_reward'], 'points'); ?>>Sadakat Puanı</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Davet Eden Ödül Miktarı</label>
                            <input type="number" name="webyaz_referral[referrer_amount]" value="<?php echo esc_attr($opts['referrer_amount']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Davet Edilen Ödül Tipi</label>
                            <select name="webyaz_referral[referee_reward]">
                                <option value="coupon" <?php selected($opts['referee_reward'], 'coupon'); ?>>Kupon</option>
                                <option value="points" <?php selected($opts['referee_reward'], 'points'); ?>>Sadakat Puanı</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Davet Edilen Ödül Miktarı</label>
                            <input type="number" name="webyaz_referral[referee_amount]" value="<?php echo esc_attr($opts['referee_amount']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Min Sipariş Tutarı (TL)</label>
                            <input type="number" name="webyaz_referral[min_order_amount]" value="<?php echo esc_attr($opts['min_order_amount']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>Kupon Tipi</label>
                            <select name="webyaz_referral[coupon_type]">
                                <option value="fixed_cart" <?php selected($opts['coupon_type'], 'fixed_cart'); ?>>Sabit TL</option>
                                <option value="percent" <?php selected($opts['coupon_type'], 'percent'); ?>>Yüzde %</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div style="background:#e8f5e9;padding:15px 20px;border-left:4px solid #4caf50;border-radius:0 8px 8px 0;margin-bottom:20px;">
                    <strong>💡 Nasıl Çalışır?</strong>
                    <ol style="margin:8px 0 0 18px;font-size:13px;color:#555;line-height:1.8;">
                        <li>Müşteri <strong>Hesabım > Arkadaşını Getir</strong> sayfasından referans linkini alır.</li>
                        <li>Arkadaşı bu linkle siteye gelir ve kayıt olur.</li>
                        <li>Arkadaşı ilk siparişini tamamladığında <strong>her ikisine de</strong> ödül verilir.</li>
                        <li>Referans linki 30 gün geçerlidir (cookie süresi).</li>
                    </ol>
                </div>

                <?php submit_button('Kaydet'); ?>
            </form>
        </div>
        <?php
    }
}

new Webyaz_Referral();
