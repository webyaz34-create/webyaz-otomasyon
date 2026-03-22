<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Customer_Ledger {

    private static $opts_key = 'webyaz_customer_ledger_opts';

    public function __construct() {
        // Admin
        add_action('admin_menu', array($this, 'admin_menu'));
        // Hesabim sekmesi
        add_action('init', array($this, 'add_endpoint'));
        add_filter('query_vars', array($this, 'query_vars'));
        add_filter('woocommerce_account_menu_items', array($this, 'menu_items'));
        add_action('woocommerce_account_musteri-cari_endpoint', array($this, 'render_account_tab'));
        // Siparis tamamlaninca kontrol
        add_action('woocommerce_order_status_completed', array($this, 'check_milestone'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this, 'check_milestone'), 10, 1);
    }

    // Varsayilan ayarlar
    public static function get_opts() {
        $defaults = array(
            'active'          => '1',
            'threshold'       => '10000',
            'coupon_amount'   => '500',
            'coupon_type'     => 'fixed_cart',
            'coupon_expiry'   => '30',
            'repeatable'      => '1',
            'email_notify'    => '1',
            'coupon_prefix'   => 'CARI',
        );
        return wp_parse_args(get_option(self::$opts_key, array()), $defaults);
    }

    // Endpoint
    public function add_endpoint() {
        add_rewrite_endpoint('musteri-cari', EP_ROOT | EP_PAGES);
        // Ilk aktivasyonda otomatik flush
        if (!get_transient('webyaz_cari_flushed')) {
            flush_rewrite_rules(false);
            set_transient('webyaz_cari_flushed', '1', DAY_IN_SECONDS * 30);
        }
    }

    public function query_vars($vars) {
        $vars[] = 'musteri-cari';
        return $vars;
    }

    // Hesabim menusune ekle
    public function menu_items($items) {
        $new = array();
        foreach ($items as $k => $v) {
            $new[$k] = $v;
            if ($k === 'orders') {
                $new['musteri-cari'] = 'Cari Hesabim';
            }
        }
        return $new;
    }

    // ===== ADMIN =====
    public function admin_menu() {
        add_submenu_page(
            'webyaz-dashboard',
            'Musteri Cari',
            'Musteri Cari',
            'manage_options',
            'webyaz-customer-ledger',
            array($this, 'render_admin')
        );
    }

    public function render_admin() {
        $opts = self::get_opts();

        if (isset($_POST['webyaz_cari_save']) && wp_verify_nonce($_POST['_wpnonce_cari'], 'webyaz_cari_settings')) {
            $opts['active']        = isset($_POST['cari_active']) ? '1' : '0';
            $opts['threshold']     = absint($_POST['cari_threshold'] ?? 10000);
            $opts['coupon_amount'] = absint($_POST['cari_coupon_amount'] ?? 500);
            $opts['coupon_type']   = sanitize_text_field($_POST['cari_coupon_type'] ?? 'fixed_cart');
            $opts['coupon_expiry'] = absint($_POST['cari_coupon_expiry'] ?? 30);
            $opts['repeatable']    = isset($_POST['cari_repeatable']) ? '1' : '0';
            $opts['email_notify']  = isset($_POST['cari_email_notify']) ? '1' : '0';
            $opts['coupon_prefix'] = sanitize_text_field($_POST['cari_coupon_prefix'] ?? 'CARI');
            update_option(self::$opts_key, $opts);
            echo '<div class="webyaz-notice success">Ayarlar kaydedildi!</div>';
        }
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>💰 Musteri Cari Sistemi</h1>
                <p>Musterilerin toplam harcamasini takip edin, belirlenen limite ulasildiginda otomatik indirim kuponu olusturun.</p>
            </div>

            <form method="post">
                <?php wp_nonce_field('webyaz_cari_settings', '_wpnonce_cari'); ?>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
                    <!-- Sol: Ayarlar -->
                    <div class="webyaz-card" style="padding:20px;">
                        <h3 style="margin:0 0 16px;font-size:16px;display:flex;align-items:center;gap:8px;">⚙️ Genel Ayarlar</h3>

                        <div style="margin-bottom:14px;">
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                <input type="checkbox" name="cari_active" value="1" <?php checked($opts['active'], '1'); ?> style="width:18px;height:18px;">
                                <span style="font-weight:600;">Musteri Cari Sistemi Aktif</span>
                            </label>
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px;">Hedef Tutar (₺)</label>
                            <input type="number" name="cari_threshold" value="<?php echo esc_attr($opts['threshold']); ?>" min="100" step="100" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                            <p style="margin:4px 0 0;font-size:11px;color:#999;">Musteri bu tutara ulasildiginda kupon olusur</p>
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                <input type="checkbox" name="cari_repeatable" value="1" <?php checked($opts['repeatable'], '1'); ?> style="width:18px;height:18px;">
                                <span style="font-size:13px;">Tekrarlanabilir (her 10.000₺'de yeni kupon)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Sag: Kupon Ayarlari -->
                    <div class="webyaz-card" style="padding:20px;">
                        <h3 style="margin:0 0 16px;font-size:16px;display:flex;align-items:center;gap:8px;">🎫 Kupon Ayarlari</h3>

                        <div style="margin-bottom:14px;">
                            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px;">Kupon Tutari</label>
                            <input type="number" name="cari_coupon_amount" value="<?php echo esc_attr($opts['coupon_amount']); ?>" min="1" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px;">Kupon Tipi</label>
                            <select name="cari_coupon_type" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                                <option value="fixed_cart" <?php selected($opts['coupon_type'], 'fixed_cart'); ?>>Sabit Tutar (₺)</option>
                                <option value="percent" <?php selected($opts['coupon_type'], 'percent'); ?>>Yuzde (%)</option>
                            </select>
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px;">Gecerlilik Suresi (gun)</label>
                            <input type="number" name="cari_coupon_expiry" value="<?php echo esc_attr($opts['coupon_expiry']); ?>" min="1" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px;">Kupon On Eki</label>
                            <input type="text" name="cari_coupon_prefix" value="<?php echo esc_attr($opts['coupon_prefix']); ?>" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                            <p style="margin:4px 0 0;font-size:11px;color:#999;">Ornek: CARI-12345</p>
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                <input type="checkbox" name="cari_email_notify" value="1" <?php checked($opts['email_notify'], '1'); ?> style="width:18px;height:18px;">
                                <span style="font-size:13px;">Kupon olusunca musteriye e-posta gonder</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Musteri Listesi -->
                <div class="webyaz-card" style="padding:20px;margin-bottom:20px;">
                    <h3 style="margin:0 0 16px;font-size:16px;display:flex;align-items:center;gap:8px;">👥 En Cok Harcayan Musteriler</h3>
                    <?php $this->render_top_customers(); ?>
                </div>

                <button type="submit" name="webyaz_cari_save" value="1" class="webyaz-btn webyaz-btn-primary" style="padding:12px 32px;font-size:14px;">Kaydet</button>
            </form>
        </div>
        <?php
    }

    // Admin: En cok harcayan musteriler tablosu
    private function render_top_customers() {
        $customers = get_users(array('role' => 'customer', 'number' => 20, 'orderby' => 'registered', 'order' => 'DESC'));
        if (empty($customers)) {
            echo '<p style="color:#999;font-size:13px;">Henuz musteri yok.</p>';
            return;
        }

        $opts = self::get_opts();
        $threshold = floatval($opts['threshold']);

        // Toplam harcamalari hesapla
        $data = array();
        foreach ($customers as $user) {
            $total = floatval(get_user_meta($user->ID, '_webyaz_cari_total', true));
            if ($total <= 0) {
                // Hesaplanmamissa hesapla
                $total = $this->calculate_total($user->ID);
            }
            $data[] = array('user' => $user, 'total' => $total);
        }

        // Tutara gore sirala
        usort($data, function($a, $b) { return $b['total'] - $a['total']; });

        echo '<div style="overflow-x:auto;">';
        echo '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
        echo '<thead><tr style="background:#f5f5f5;">';
        echo '<th style="text-align:left;padding:10px 12px;border-bottom:2px solid #e0e0e0;">Musteri</th>';
        echo '<th style="text-align:left;padding:10px 12px;border-bottom:2px solid #e0e0e0;">E-posta</th>';
        echo '<th style="text-align:right;padding:10px 12px;border-bottom:2px solid #e0e0e0;">Toplam Harcama</th>';
        echo '<th style="text-align:center;padding:10px 12px;border-bottom:2px solid #e0e0e0;">Ilerleme</th>';
        echo '<th style="text-align:center;padding:10px 12px;border-bottom:2px solid #e0e0e0;">Durum</th>';
        echo '</tr></thead><tbody>';

        foreach ($data as $row) {
            $user = $row['user'];
            $total = $row['total'];
            $pct = $threshold > 0 ? min(100, ($total / $threshold) * 100) : 0;
            $reached = $total >= $threshold;
            $milestone_count = $threshold > 0 ? floor($total / $threshold) : 0;

            echo '<tr style="border-bottom:1px solid #f0f0f0;">';
            echo '<td style="padding:10px 12px;font-weight:600;">' . esc_html($user->display_name ?: $user->user_login) . '</td>';
            echo '<td style="padding:10px 12px;color:#666;">' . esc_html($user->user_email) . '</td>';
            echo '<td style="padding:10px 12px;text-align:right;font-weight:700;color:' . ($reached ? '#2e7d32' : '#333') . ';">' . number_format($total, 0, ',', '.') . ' ₺</td>';
            echo '<td style="padding:10px 12px;text-align:center;">';
            echo '<div style="background:#e0e0e0;border-radius:10px;height:8px;width:120px;display:inline-block;overflow:hidden;">';
            echo '<div style="background:' . ($reached ? '#4caf50' : '#ff9800') . ';height:100%;width:' . $pct . '%;border-radius:10px;transition:0.3s;"></div>';
            echo '</div>';
            echo '<div style="font-size:10px;color:#999;margin-top:2px;">' . number_format($pct, 0) . '%</div>';
            echo '</td>';
            echo '<td style="padding:10px 12px;text-align:center;">';
            if ($reached) {
                echo '<span style="background:#e8f5e9;color:#2e7d32;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;">✅ ' . $milestone_count . 'x ulasildi</span>';
            } else {
                $remaining = $threshold - fmod($total, $threshold);
                echo '<span style="color:#999;font-size:11px;">' . number_format($remaining, 0, ',', '.') . ' ₺ kaldi</span>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    // Musterinin toplam harcamasini hesapla
    private function calculate_total($user_id) {
        $total = 0;
        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'status'      => array('completed', 'processing'),
            'limit'       => -1,
        ));
        foreach ($orders as $order) {
            $total += floatval($order->get_total());
        }
        update_user_meta($user_id, '_webyaz_cari_total', $total);
        return $total;
    }

    // ===== HESABIM SEKMESI =====
    public function render_account_tab() {
        $user_id = get_current_user_id();
        if (!$user_id) return;

        $opts  = self::get_opts();
        $total = floatval(get_user_meta($user_id, '_webyaz_cari_total', true));
        if ($total <= 0) {
            $total = $this->calculate_total($user_id);
        }

        $threshold = floatval($opts['threshold']);
        $coupon_amount = floatval($opts['coupon_amount']);
        $repeatable = $opts['repeatable'] === '1';

        // Mevcut basamak
        $milestone_count = $threshold > 0 ? floor($total / $threshold) : 0;
        $current_in_cycle = $threshold > 0 ? fmod($total, $threshold) : $total;
        $pct = $threshold > 0 ? min(100, ($current_in_cycle / $threshold) * 100) : 0;
        $remaining = $threshold - $current_in_cycle;

        // Kazanilan kuponlar
        $coupons_earned = get_user_meta($user_id, '_webyaz_cari_coupons', true);
        $coupons_earned = is_array($coupons_earned) ? $coupons_earned : array();
        ?>
        <div style="max-width:700px;">
            <!-- Ozet Kart -->
            <div style="background:linear-gradient(135deg,#1a237e,#283593);color:#fff;border-radius:16px;padding:28px;margin-bottom:24px;position:relative;overflow:hidden;">
                <div style="position:absolute;right:-20px;top:-20px;width:120px;height:120px;background:rgba(255,255,255,0.05);border-radius:50%;"></div>
                <div style="position:absolute;right:30px;bottom:-30px;width:80px;height:80px;background:rgba(255,255,255,0.03);border-radius:50%;"></div>
                <div style="font-size:13px;opacity:0.8;margin-bottom:6px;">Toplam Harcamaniz</div>
                <div style="font-size:36px;font-weight:900;letter-spacing:-1px;"><?php echo number_format($total, 0, ',', '.'); ?> ₺</div>
                <div style="margin-top:16px;background:rgba(255,255,255,0.15);border-radius:10px;height:10px;overflow:hidden;">
                    <div style="background:linear-gradient(90deg,#ffab00,#ff6d00);height:100%;width:<?php echo $pct; ?>%;border-radius:10px;transition:0.5s;"></div>
                </div>
                <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:12px;opacity:0.75;">
                    <span><?php echo number_format($current_in_cycle, 0, ',', '.'); ?> ₺</span>
                    <span>Hedef: <?php echo number_format($threshold, 0, ',', '.'); ?> ₺</span>
                </div>
            </div>

            <!-- Durum Bilgisi -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:24px;">
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:16px;text-align:center;">
                    <div style="font-size:24px;margin-bottom:4px;">🎯</div>
                    <div style="font-size:22px;font-weight:800;color:#1a237e;"><?php echo number_format($remaining, 0, ',', '.'); ?> ₺</div>
                    <div style="font-size:11px;color:#999;">Hedefe Kalan</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:16px;text-align:center;">
                    <div style="font-size:24px;margin-bottom:4px;">🏆</div>
                    <div style="font-size:22px;font-weight:800;color:#e65100;"><?php echo $milestone_count; ?></div>
                    <div style="font-size:11px;color:#999;">Hedefe Ulasma</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:16px;text-align:center;">
                    <div style="font-size:24px;margin-bottom:4px;">🎫</div>
                    <div style="font-size:22px;font-weight:800;color:#2e7d32;"><?php echo count($coupons_earned); ?></div>
                    <div style="font-size:11px;color:#999;">Kazanilan Kupon</div>
                </div>
            </div>

            <!-- Kuponlar -->
            <?php if (!empty($coupons_earned)): ?>
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:12px;padding:20px;margin-bottom:20px;">
                <h3 style="margin:0 0 14px;font-size:15px;display:flex;align-items:center;gap:8px;">🎫 Kazandiginiz Kuponlar</h3>
                <?php foreach ($coupons_earned as $c):
                    $coupon = new WC_Coupon($c['code']);
                    $is_valid = $coupon->get_id() > 0;
                    $expiry = $coupon->get_date_expires();
                    $expired = $expiry && $expiry->getTimestamp() < time();
                    $used = $coupon->get_usage_count() >= $coupon->get_usage_limit();
                ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:<?php echo ($expired || $used) ? '#fafafa' : '#f1f8e9'; ?>;border-radius:8px;margin-bottom:6px;border-left:4px solid <?php echo ($expired || $used) ? '#bdbdbd' : '#4caf50'; ?>;">
                    <div>
                        <div style="font-family:monospace;font-size:15px;font-weight:700;color:<?php echo ($expired || $used) ? '#999' : '#2e7d32'; ?>;letter-spacing:1px;"><?php echo esc_html(strtoupper($c['code'])); ?></div>
                        <div style="font-size:11px;color:#999;">
                            <?php echo esc_html($c['date']); ?>
                            <?php if ($expired): ?> — <span style="color:#e53935;">Suresi doldu</span><?php endif; ?>
                            <?php if ($used): ?> — <span style="color:#666;">Kullanildi</span><?php endif; ?>
                        </div>
                    </div>
                    <div style="font-size:18px;font-weight:800;color:<?php echo ($expired || $used) ? '#bdbdbd' : '#e65100'; ?>;">
                        <?php echo $opts['coupon_type'] === 'percent' ? $opts['coupon_amount'] . '%' : number_format($opts['coupon_amount'], 0, ',', '.') . ' ₺'; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Bilgilendirme -->
            <div style="background:linear-gradient(135deg,#fff8e1,#ffecb3);border-radius:12px;padding:16px 20px;border-left:4px solid #ff8f00;font-size:13px;color:#333;">
                <strong>📢 Nasil Calisir?</strong><br>
                Her <?php echo number_format($threshold, 0, ',', '.'); ?> ₺ harcamanizda otomatik olarak
                <strong><?php echo $opts['coupon_type'] === 'percent' ? $opts['coupon_amount'] . '%' : number_format($coupon_amount, 0, ',', '.') . ' ₺'; ?> indirim kuponu</strong>
                olusturulur ve size e-posta ile bildirilir.
                <?php if ($repeatable): ?>Kuponlar tekrarlanabilir — her hedefe ulastiginizda yeni kupon kazanirsiniz!<?php endif; ?>
            </div>
        </div>
        <?php
    }

    // ===== SIPARIS TAMAMLANINCA =====
    public function check_milestone($order_id) {
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        $user_id = $order->get_user_id();
        if (!$user_id) return; // Misafir siparis

        // Zaten bu siparis icin kontrol edildi mi?
        $checked = get_post_meta($order_id, '_webyaz_cari_checked', true);
        if ($checked === 'yes') return;
        update_post_meta($order_id, '_webyaz_cari_checked', 'yes');

        // Toplami yeniden hesapla
        $total = $this->calculate_total($user_id);
        $threshold = floatval($opts['threshold']);
        if ($threshold <= 0) return;

        // Kac kez hedefe ulasmis
        $milestone_count = floor($total / $threshold);
        $prev_milestones = absint(get_user_meta($user_id, '_webyaz_cari_milestones', true));

        if ($milestone_count > $prev_milestones) {
            // Yeni milestone(lar) var
            $new_milestones = $milestone_count - $prev_milestones;
            if ($opts['repeatable'] !== '1') {
                $new_milestones = min($new_milestones, 1);
                if ($prev_milestones > 0) return;
            }

            for ($i = 0; $i < $new_milestones; $i++) {
                $this->create_coupon($user_id, $opts);
            }

            update_user_meta($user_id, '_webyaz_cari_milestones', $milestone_count);
        }
    }

    // Kupon olustur
    private function create_coupon($user_id, $opts) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $code = strtolower($opts['coupon_prefix'] . '-' . $user_id . '-' . wp_rand(1000, 9999));

        $coupon = new WC_Coupon();
        $coupon->set_code($code);
        $coupon->set_amount(floatval($opts['coupon_amount']));
        $coupon->set_discount_type($opts['coupon_type']);
        $coupon->set_individual_use(true);
        $coupon->set_usage_limit(1);
        $coupon->set_email_restrictions(array($user->user_email));

        if ($opts['coupon_expiry'] > 0) {
            $expiry = date('Y-m-d', strtotime('+' . intval($opts['coupon_expiry']) . ' days'));
            $coupon->set_date_expires($expiry);
        }

        $coupon->save();

        // Kullaniciya kaydet
        $coupons = get_user_meta($user_id, '_webyaz_cari_coupons', true);
        $coupons = is_array($coupons) ? $coupons : array();
        $coupons[] = array(
            'code' => $code,
            'date' => date_i18n('d.m.Y H:i'),
        );
        update_user_meta($user_id, '_webyaz_cari_coupons', $coupons);

        // E-posta bildir
        if ($opts['email_notify'] === '1') {
            $this->send_email($user, $code, $opts);
        }
    }

    // E-posta gonder
    private function send_email($user, $code, $opts) {
        $amount = $opts['coupon_type'] === 'percent'
            ? $opts['coupon_amount'] . '%'
            : number_format($opts['coupon_amount'], 0, ',', '.') . ' ₺';

        $subject = 'Tebrikler! ' . $amount . ' indirim kuponunuz hazir 🎉';

        $body = '<div style="font-family:Arial,sans-serif;max-width:500px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);">';
        $body .= '<div style="background:linear-gradient(135deg,#1a237e,#283593);color:#fff;padding:30px;text-align:center;">';
        $body .= '<div style="font-size:40px;margin-bottom:10px;">🎉</div>';
        $body .= '<h2 style="margin:0;font-size:22px;">Tebrikler ' . esc_html($user->display_name ?: $user->user_login) . '!</h2>';
        $body .= '<p style="margin:8px 0 0;opacity:0.85;font-size:14px;">' . number_format(floatval($opts['threshold']), 0, ',', '.') . ' ₺ harcama hedefine ulastiniz</p>';
        $body .= '</div>';
        $body .= '<div style="padding:30px;text-align:center;">';
        $body .= '<p style="font-size:14px;color:#333;margin:0 0 20px;">Size ozel <strong>' . $amount . '</strong> indirim kuponunuz:</p>';
        $body .= '<div style="background:linear-gradient(135deg,#f1f8e9,#dcedc8);border:2px dashed #4caf50;border-radius:12px;padding:20px;margin:0 auto 20px;display:inline-block;">';
        $body .= '<span style="font-family:monospace;font-size:28px;font-weight:900;color:#2e7d32;letter-spacing:2px;">' . strtoupper($code) . '</span>';
        $body .= '</div>';
        if ($opts['coupon_expiry'] > 0) {
            $body .= '<p style="font-size:12px;color:#999;">Kuponunuz ' . $opts['coupon_expiry'] . ' gun icerisinde gecerlidir.</p>';
        }
        $body .= '</div>';
        $body .= '</div>';

        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($user->user_email, $subject, $body, $headers);
    }
}

new Webyaz_Customer_Ledger();
