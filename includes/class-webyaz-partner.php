<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Partner
{

    public function __construct()
    {
        // Rol olustur
        add_action('init', array($this, 'register_partner_role'));

        // Admin
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_admin_actions'));

        // WooCommerce Hesabim endpoint
        add_action('init', array($this, 'add_endpoint'));
        add_filter('woocommerce_account_menu_items', array($this, 'account_menu_items'));
        add_action('woocommerce_account_partner-panel_endpoint', array($this, 'partner_dashboard'));
        add_action('woocommerce_account_partner-basvuru_endpoint', array($this, 'application_form'));

        // Basvuru isleme
        add_action('template_redirect', array($this, 'handle_application'));

        // Komisyon hesaplama
        add_action('woocommerce_order_status_completed', array($this, 'calculate_commission'));

        // Frontend stil
        add_action('wp_footer', array($this, 'frontend_styles'));
    }

    // =============================================
    // AYARLAR
    // =============================================

    public function register_settings()
    {
        register_setting('webyaz_partner_group', 'webyaz_partner');
    }

    private static function get_defaults()
    {
        return array(
            'active'              => '0',
            'default_discount'    => '10',
            'default_commission'  => '5',
            'min_payout'          => '100',
            'cookie_days'         => '30',
            'auto_create_coupon'  => '1',
            'application_active'  => '1',
        );
    }

    public static function get_opts()
    {
        return wp_parse_args(get_option('webyaz_partner', array()), self::get_defaults());
    }

    // =============================================
    // PARTNER ROLU
    // =============================================

    public function register_partner_role()
    {
        if (!get_role('webyaz_partner')) {
            add_role('webyaz_partner', 'Partner', array(
                'read' => true,
            ));
        }
    }

    // =============================================
    // WOOCOMMERCE HESABIM ENDPOINT
    // =============================================

    public function add_endpoint()
    {
        add_rewrite_endpoint('partner-panel', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('partner-basvuru', EP_ROOT | EP_PAGES);

        // Endpoint'ler ilk kez eklendiginde rewrite kurallarini yenile (404 onleme)
        if (!get_option('webyaz_partner_flushed')) {
            flush_rewrite_rules(false);
            update_option('webyaz_partner_flushed', '1');
        }
    }

    public function account_menu_items($items)
    {
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return $items;

        $user = wp_get_current_user();

        // Partner ise dashboard goster
        if (in_array('webyaz_partner', (array) $user->roles)) {
            $new_items = array();
            foreach ($items as $key => $label) {
                $new_items[$key] = $label;
                if ($key === 'dashboard') {
                    $new_items['partner-panel'] = 'Partner Paneli';
                }
            }
            return $new_items;
        }

        // Normal kullanici ise basvuru goster
        if ($opts['application_active'] === '1' && is_user_logged_in()) {
            // Zaten basvuru varsa gosterme
            $existing = get_user_meta($user->ID, '_webyaz_partner_application', true);
            if (empty($existing)) {
                $new_items = array();
                foreach ($items as $key => $label) {
                    $new_items[$key] = $label;
                    if ($key === 'dashboard') {
                        $new_items['partner-basvuru'] = 'Partner Ol';
                    }
                }
                return $new_items;
            }
        }

        return $items;
    }

    // =============================================
    // BASVURU FORMU (FRONTEND)
    // =============================================

    public function application_form()
    {
        $opts = self::get_opts();
        if ($opts['active'] !== '1' || $opts['application_active'] !== '1') {
            echo '<p>Partner basvurulari su an kapalidir.</p>';
            return;
        }

        $user = wp_get_current_user();
        $existing = get_user_meta($user->ID, '_webyaz_partner_application', true);

        if (!empty($existing)) {
            $status = $existing['status'] ?? 'pending';
            if ($status === 'pending') {
                echo '<div class="webyaz-partner-notice info">';
                echo '<strong>Basvurunuz inceleniyor.</strong><br>Onaylandiginda size bilgi verilecektir.';
                echo '</div>';
            } elseif ($status === 'rejected') {
                echo '<div class="webyaz-partner-notice error">';
                echo '<strong>Basvurunuz reddedildi.</strong>';
                echo '</div>';
            }
            return;
        }

        if (isset($_GET['partner_applied'])) {
            echo '<div class="webyaz-partner-notice success">';
            echo '<strong>Basvurunuz alindi!</strong> En kisa surede incelenecektir.';
            echo '</div>';
            return;
        }
?>
        <div class="webyaz-partner-apply">
            <h3>Partner Basvurusu</h3>
            <p style="color:#666;margin-bottom:20px;">Partnerimiz olun, size ozel indirim kodu ile musteri yonlendirin ve komisyon kazanin!</p>

            <form method="post" class="webyaz-partner-form">
                <?php wp_nonce_field('webyaz_partner_apply', '_wpnonce_partner'); ?>
                <input type="hidden" name="webyaz_partner_action" value="apply">

                <div class="webyaz-pf-grid">
                    <div class="webyaz-pf-field">
                        <label>Ad Soyad *</label>
                        <input type="text" name="partner_name" value="<?php echo esc_attr($user->display_name); ?>" required>
                    </div>
                    <div class="webyaz-pf-field">
                        <label>E-posta *</label>
                        <input type="email" name="partner_email" value="<?php echo esc_attr($user->user_email); ?>" required>
                    </div>
                    <div class="webyaz-pf-field">
                        <label>Telefon *</label>
                        <input type="tel" name="partner_phone" required placeholder="05XX XXX XX XX">
                    </div>
                    <div class="webyaz-pf-field">
                        <label>Web Sitesi / Sosyal Medya</label>
                        <input type="text" name="partner_website" placeholder="instagram.com/hesabim veya websiteniz">
                    </div>
                </div>
                <div class="webyaz-pf-field" style="margin-top:12px;">
                    <label>Neden partner olmak istiyorsunuz?</label>
                    <textarea name="partner_note" rows="3" placeholder="Kisaca aciklayin..."></textarea>
                </div>
                <button type="submit" class="webyaz-pf-btn">Basvuru Yap</button>
            </form>
        </div>
    <?php
    }

    public function handle_application()
    {
        if (!isset($_POST['webyaz_partner_action']) || $_POST['webyaz_partner_action'] !== 'apply') return;
        if (!wp_verify_nonce($_POST['_wpnonce_partner'] ?? '', 'webyaz_partner_apply')) return;
        if (!is_user_logged_in()) return;

        $user_id = get_current_user_id();

        $application = array(
            'status'   => 'pending',
            'name'     => sanitize_text_field($_POST['partner_name'] ?? ''),
            'email'    => sanitize_email($_POST['partner_email'] ?? ''),
            'phone'    => sanitize_text_field($_POST['partner_phone'] ?? ''),
            'website'  => sanitize_text_field($_POST['partner_website'] ?? ''),
            'note'     => sanitize_textarea_field($_POST['partner_note'] ?? ''),
            'date'     => current_time('mysql'),
            'user_id'  => $user_id,
        );

        update_user_meta($user_id, '_webyaz_partner_application', $application);

        wp_safe_redirect(wc_get_account_endpoint_url('partner-basvuru') . '?partner_applied=1');
        exit;
    }

    // =============================================
    // PARTNER DASHBOARD (HESABIM)
    // =============================================

    public function partner_dashboard()
    {
        $user = wp_get_current_user();
        if (!in_array('webyaz_partner', (array) $user->roles)) {
            echo '<p>Bu sayfaya erisim yetkiniz yok.</p>';
            return;
        }

        $partner_id = $user->ID;
        $coupon_code = get_user_meta($partner_id, '_webyaz_partner_coupon', true);
        $commission_rate = get_user_meta($partner_id, '_webyaz_partner_commission_rate', true);
        $total_earnings = floatval(get_user_meta($partner_id, '_webyaz_partner_total_earnings', true));
        $paid_earnings = floatval(get_user_meta($partner_id, '_webyaz_partner_paid_earnings', true));
        $pending_earnings = $total_earnings - $paid_earnings;

        // Bu ayki kazanc
        $monthly_earnings = $this->get_monthly_earnings($partner_id);

        // Siparis listesi
        $orders = $this->get_partner_orders($partner_id, 20);
        $total_orders = $this->get_partner_order_count($partner_id);

    ?>
        <div class="webyaz-partner-dashboard">

            <!-- Kupon Bilgisi -->
            <div class="webyaz-pd-coupon-box">
                <div class="webyaz-pd-coupon-left">
                    <span class="webyaz-pd-coupon-label">Indirim Kodunuz</span>
                    <span class="webyaz-pd-coupon-code" id="partnerCouponCode"><?php echo esc_html(strtoupper($coupon_code)); ?></span>
                </div>
                <button type="button" class="webyaz-pd-copy-btn" onclick="navigator.clipboard.writeText('<?php echo esc_js($coupon_code); ?>').then(function(){var b=document.querySelector('.webyaz-pd-copy-btn');b.textContent='Kopyalandi!';setTimeout(function(){b.textContent='Kopyala';},2000);});">Kopyala</button>
            </div>

            <!-- Istatistikler -->
            <div class="webyaz-pd-stats">
                <div class="webyaz-pd-stat">
                    <div class="webyaz-pd-stat-icon" style="background:#e3f2fd;color:#1565c0;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1.003 1.003 0 0020 4H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z" />
                        </svg>
                    </div>
                    <div class="webyaz-pd-stat-info">
                        <span class="webyaz-pd-stat-num"><?php echo intval($total_orders); ?></span>
                        <span class="webyaz-pd-stat-label">Toplam Siparis</span>
                    </div>
                </div>
                <div class="webyaz-pd-stat">
                    <div class="webyaz-pd-stat-icon" style="background:#e8f5e9;color:#2e7d32;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z" />
                        </svg>
                    </div>
                    <div class="webyaz-pd-stat-info">
                        <span class="webyaz-pd-stat-num"><?php echo wc_price($total_earnings); ?></span>
                        <span class="webyaz-pd-stat-label">Toplam Kazanc</span>
                    </div>
                </div>
                <div class="webyaz-pd-stat">
                    <div class="webyaz-pd-stat-icon" style="background:#fff3e0;color:#e65100;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z" />
                        </svg>
                    </div>
                    <div class="webyaz-pd-stat-info">
                        <span class="webyaz-pd-stat-num"><?php echo wc_price($pending_earnings); ?></span>
                        <span class="webyaz-pd-stat-label">Bekleyen Kazanc</span>
                    </div>
                </div>
                <div class="webyaz-pd-stat">
                    <div class="webyaz-pd-stat-icon" style="background:#f3e5f5;color:#7b1fa2;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z" />
                        </svg>
                    </div>
                    <div class="webyaz-pd-stat-info">
                        <span class="webyaz-pd-stat-num"><?php echo wc_price($monthly_earnings); ?></span>
                        <span class="webyaz-pd-stat-label">Bu Ay</span>
                    </div>
                </div>
            </div>

            <!-- Bilgi -->
            <div class="webyaz-pd-info-box">
                <strong>Komisyon Oraniniz:</strong> %<?php echo esc_html($commission_rate); ?> &nbsp;|&nbsp;
                <strong>Odenen:</strong> <?php echo wc_price($paid_earnings); ?>
            </div>

            <!-- Siparis Tablosu -->
            <h3 style="margin:25px 0 12px;font-size:16px;">Son Siparisler</h3>
            <?php if (!empty($orders)): ?>
                <table class="webyaz-pd-table">
                    <thead>
                        <tr>
                            <th>Siparis</th>
                            <th>Tarih</th>
                            <th>Musteri</th>
                            <th>Tutar</th>
                            <th>Komisyon</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order):
                            $commission = floatval(get_post_meta($order->get_id(), '_webyaz_partner_commission_amount', true));
                            $customer_name = $order->get_billing_first_name();
                            // Gizlilik: Soyadi sadece bas harfi
                            $last_initial = mb_substr($order->get_billing_last_name(), 0, 1);
                        ?>
                            <tr>
                                <td>#<?php echo $order->get_order_number(); ?></td>
                                <td><?php echo $order->get_date_created()->date_i18n('d.m.Y'); ?></td>
                                <td><?php echo esc_html($customer_name . ' ' . $last_initial . '.'); ?></td>
                                <td><?php echo $order->get_formatted_order_total(); ?></td>
                                <td style="color:#2e7d32;font-weight:700;"><?php echo wc_price($commission); ?></td>
                                <td>
                                    <?php
                                    $status = $order->get_status();
                                    $status_labels = array('completed' => 'Tamamlandi', 'processing' => 'Hazirlaniyor', 'on-hold' => 'Beklemede', 'pending' => 'Odeme Bekleniyor');
                                    $label = $status_labels[$status] ?? ucfirst($status);
                                    $color = $status === 'completed' ? '#2e7d32' : ($status === 'processing' ? '#1565c0' : '#e65100');
                                    ?>
                                    <span style="background:<?php echo $color; ?>15;color:<?php echo $color; ?>;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;"><?php echo esc_html($label); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color:#999;text-align:center;padding:30px;">Henuz siparis bulunmuyor.</p>
            <?php endif; ?>
        </div>
    <?php
    }

    // =============================================
    // KOMISYON HESAPLAMA
    // =============================================

    public function calculate_commission($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) return;

        // Zaten komisyon hesaplanmis mi?
        if (get_post_meta($order_id, '_webyaz_partner_commission_amount', true)) return;

        // Sipariste kullanilan kuponlari kontrol et
        $coupons = $order->get_coupon_codes();
        if (empty($coupons)) return;

        foreach ($coupons as $code) {
            $coupon_id = wc_get_coupon_id_by_code($code);
            if (!$coupon_id) continue;

            $partner_id = get_post_meta($coupon_id, '_webyaz_partner_id', true);
            if (!$partner_id) continue;

            // Partner bulundu - komisyon hesapla
            $commission_rate = floatval(get_user_meta($partner_id, '_webyaz_partner_commission_rate', true));
            if ($commission_rate <= 0) {
                $opts = self::get_opts();
                $commission_rate = floatval($opts['default_commission']);
            }

            $order_total = floatval($order->get_total());
            $commission = round($order_total * ($commission_rate / 100), 2);

            // Siparis meta
            update_post_meta($order_id, '_webyaz_partner_id', $partner_id);
            update_post_meta($order_id, '_webyaz_partner_commission_amount', $commission);
            update_post_meta($order_id, '_webyaz_partner_commission_rate', $commission_rate);
            update_post_meta($order_id, '_webyaz_partner_coupon_code', $code);

            // Partner toplam kazancini guncelle
            $total = floatval(get_user_meta($partner_id, '_webyaz_partner_total_earnings', true));
            update_user_meta($partner_id, '_webyaz_partner_total_earnings', $total + $commission);

            // Siparis notu
            $partner_user = get_userdata($partner_id);
            $partner_name = $partner_user ? $partner_user->display_name : '#' . $partner_id;
            $order->add_order_note(sprintf('Partner komisyonu: %s - %s (%%%s)', $partner_name, wc_price($commission), $commission_rate));

            break; // Sadece ilk partner kuponu
        }
    }

    // =============================================
    // YARDIMCI FONKSIYONLAR
    // =============================================

    private function get_partner_orders($partner_id, $limit = 20)
    {
        $coupon_code = get_user_meta($partner_id, '_webyaz_partner_coupon', true);
        if (empty($coupon_code)) return array();

        $args = array(
            'limit'      => $limit,
            'orderby'    => 'date',
            'order'      => 'DESC',
            'meta_key'   => '_webyaz_partner_id',
            'meta_value' => $partner_id,
        );

        return wc_get_orders($args);
    }

    private function get_partner_order_count($partner_id)
    {
        $args = array(
            'limit'      => -1,
            'return'     => 'ids',
            'meta_key'   => '_webyaz_partner_id',
            'meta_value' => $partner_id,
        );
        $ids = wc_get_orders($args);
        return count($ids);
    }

    private function get_monthly_earnings($partner_id)
    {
        $args = array(
            'limit'      => -1,
            'return'     => 'ids',
            'meta_key'   => '_webyaz_partner_id',
            'meta_value' => $partner_id,
            'date_created' => '>=' . date('Y-m-01'),
        );
        $order_ids = wc_get_orders($args);
        $total = 0;
        foreach ($order_ids as $oid) {
            $total += floatval(get_post_meta($oid, '_webyaz_partner_commission_amount', true));
        }
        return $total;
    }

    private function get_all_partners()
    {
        return get_users(array(
            'role' => 'webyaz_partner',
            'orderby' => 'registered',
            'order' => 'DESC',
        ));
    }

    private function get_pending_applications()
    {
        $users = get_users(array(
            'meta_key'   => '_webyaz_partner_application',
            'meta_compare' => 'EXISTS',
        ));

        $pending = array();
        foreach ($users as $u) {
            $app = get_user_meta($u->ID, '_webyaz_partner_application', true);
            if (is_array($app) && ($app['status'] ?? '') === 'pending') {
                $app['user_id'] = $u->ID;
                $pending[] = $app;
            }
        }
        return $pending;
    }

    // =============================================
    // ADMIN ISLEMLERI
    // =============================================

    public function handle_admin_actions()
    {
        if (!current_user_can('manage_options')) return;

        // Partner onayla
        if (isset($_POST['webyaz_partner_approve']) && wp_verify_nonce($_POST['_wpnonce_partner_admin'] ?? '', 'webyaz_partner_admin')) {
            $user_id = intval($_POST['approve_user_id'] ?? 0);
            $username = sanitize_user($_POST['partner_username'] ?? '');
            $password = $_POST['partner_password'] ?? '';
            $coupon_code = sanitize_title($_POST['partner_coupon_code'] ?? '');
            $discount = floatval($_POST['partner_discount'] ?? 10);
            $commission = floatval($_POST['partner_commission'] ?? 5);

            if ($user_id && $coupon_code) {
                $app = get_user_meta($user_id, '_webyaz_partner_application', true);

                // Eger mevcut kullanici ise rolunu degistir
                $user = get_userdata($user_id);
                if ($user) {
                    // GUVENLIK: Admin kullaniciyi partner yapma!
                    if (in_array('administrator', (array) $user->roles)) {
                        add_settings_error('webyaz_partner', 'admin_error', 'HATA: Administrator kullanici partner yapilamaz! Lutfen farkli bir kullanici secin.', 'error');
                        return;
                    }
                    $user->set_role('webyaz_partner');

                    // Yeni kullanici adi ve sifre belirle (opsiyonel)
                    if (!empty($username) && $username !== $user->user_login) {
                        global $wpdb;
                        $wpdb->update($wpdb->users, array('user_login' => $username), array('ID' => $user_id));
                        clean_user_cache($user_id);
                    }
                    if (!empty($password)) {
                        wp_set_password($password, $user_id);
                    }
                }

                // WooCommerce kupon olustur
                $this->create_partner_coupon($user_id, $coupon_code, $discount);

                // Meta guncelle
                update_user_meta($user_id, '_webyaz_partner_coupon', $coupon_code);
                update_user_meta($user_id, '_webyaz_partner_commission_rate', $commission);
                update_user_meta($user_id, '_webyaz_partner_total_earnings', 0);
                update_user_meta($user_id, '_webyaz_partner_paid_earnings', 0);
                update_user_meta($user_id, '_webyaz_partner_approved_date', current_time('mysql'));

                // Basvuru durumunu guncelle
                if (is_array($app)) {
                    $app['status'] = 'approved';
                    update_user_meta($user_id, '_webyaz_partner_application', $app);
                }

                add_settings_error('webyaz_partner', 'approved', 'Partner onaylandi ve kupon olusturuldu!', 'success');
            }
        }

        // Partner reddet
        if (isset($_POST['webyaz_partner_reject']) && wp_verify_nonce($_POST['_wpnonce_partner_admin'] ?? '', 'webyaz_partner_admin')) {
            $user_id = intval($_POST['reject_user_id'] ?? 0);
            if ($user_id) {
                $app = get_user_meta($user_id, '_webyaz_partner_application', true);
                if (is_array($app)) {
                    $app['status'] = 'rejected';
                    update_user_meta($user_id, '_webyaz_partner_application', $app);
                }
                add_settings_error('webyaz_partner', 'rejected', 'Basvuru reddedildi.', 'info');
            }
        }

        // Odeme yapildi isaretle
        if (isset($_POST['webyaz_partner_mark_paid']) && wp_verify_nonce($_POST['_wpnonce_partner_admin'] ?? '', 'webyaz_partner_admin')) {
            $partner_id = intval($_POST['paid_partner_id'] ?? 0);
            $pay_amount = floatval($_POST['pay_amount'] ?? 0);
            if ($partner_id && $pay_amount > 0) {
                $paid = floatval(get_user_meta($partner_id, '_webyaz_partner_paid_earnings', true));
                update_user_meta($partner_id, '_webyaz_partner_paid_earnings', $paid + $pay_amount);
                add_settings_error('webyaz_partner', 'paid', wc_price($pay_amount) . ' odeme kaydedildi.', 'success');
            }
        }
    }

    private function create_partner_coupon($partner_id, $code, $discount)
    {
        // Varsa mevcut kuponu bul
        $existing = wc_get_coupon_id_by_code($code);
        if ($existing) return $existing;

        $coupon = new WC_Coupon();
        $coupon->set_code($code);
        $coupon->set_discount_type('percent');
        $coupon->set_amount($discount);
        $coupon->set_individual_use(false);
        $coupon->set_usage_limit(0); // sinirsiz
        $coupon->set_usage_limit_per_user(0);
        $coupon->set_description('Partner kupon kodu - User #' . $partner_id);
        $coupon->save();

        // Partner baglantisi
        update_post_meta($coupon->get_id(), '_webyaz_partner_id', $partner_id);

        return $coupon->get_id();
    }

    // =============================================
    // FRONTEND STILLER
    // =============================================

    public function frontend_styles()
    {
        if (!is_account_page()) return;
    ?>
        <style>
            /* Basvuru Formu */
            .webyaz-partner-apply {
                max-width: 600px;
            }

            .webyaz-partner-apply h3 {
                font-size: 20px;
                font-weight: 700;
                margin: 0 0 5px;
            }

            .webyaz-pf-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }

            .webyaz-pf-field {
                display: flex;
                flex-direction: column;
                margin-bottom: 0;
            }

            .webyaz-pf-field label {
                font-size: 13px;
                font-weight: 600;
                color: #333;
                margin-bottom: 5px;
            }

            .webyaz-pf-field input,
            .webyaz-pf-field textarea {
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 10px 12px;
                font-size: 14px;
                transition: border .2s;
                font-family: inherit;
            }

            .webyaz-pf-field input:focus,
            .webyaz-pf-field textarea:focus {
                border-color: #446084;
                outline: none;
                box-shadow: 0 0 0 3px rgba(68, 96, 132, .1);
            }

            .webyaz-pf-btn {
                display: inline-block;
                background: linear-gradient(135deg, #446084, #d26e4b);
                color: #fff;
                border: none;
                padding: 12px 30px;
                border-radius: 8px;
                font-size: 15px;
                font-weight: 700;
                cursor: pointer;
                margin-top: 15px;
                transition: opacity .2s;
            }

            .webyaz-pf-btn:hover {
                opacity: .85;
            }

            .webyaz-partner-notice {
                padding: 16px 20px;
                border-radius: 10px;
                margin-bottom: 20px;
                font-size: 14px;
                line-height: 1.6;
            }

            .webyaz-partner-notice.success {
                background: #e8f5e9;
                color: #2e7d32;
                border: 1px solid #c8e6c9;
            }

            .webyaz-partner-notice.info {
                background: #e3f2fd;
                color: #1565c0;
                border: 1px solid #bbdefb;
            }

            .webyaz-partner-notice.error {
                background: #fde8e8;
                color: #d32f2f;
                border: 1px solid #f5c6cb;
            }

            /* Partner Dashboard */
            .webyaz-partner-dashboard {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }

            .webyaz-pd-coupon-box {
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: linear-gradient(135deg, #446084, #2c4058);
                padding: 20px 24px;
                border-radius: 12px;
                margin-bottom: 20px;
                color: #fff;
            }

            .webyaz-pd-coupon-label {
                display: block;
                font-size: 12px;
                opacity: .7;
                margin-bottom: 4px;
            }

            .webyaz-pd-coupon-code {
                display: block;
                font-size: 28px;
                font-weight: 900;
                letter-spacing: 2px;
            }

            .webyaz-pd-copy-btn {
                background: rgba(255, 255, 255, .2);
                color: #fff;
                border: 1px solid rgba(255, 255, 255, .3);
                padding: 8px 20px;
                border-radius: 8px;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                transition: all .2s;
            }

            .webyaz-pd-copy-btn:hover {
                background: rgba(255, 255, 255, .3);
            }

            .webyaz-pd-stats {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 12px;
                margin-bottom: 20px;
            }

            .webyaz-pd-stat {
                background: #fff;
                border: 1px solid #eee;
                border-radius: 10px;
                padding: 16px;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .webyaz-pd-stat-icon {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .webyaz-pd-stat-num {
                display: block;
                font-size: 18px;
                font-weight: 700;
                color: #1a1a1a;
            }

            .webyaz-pd-stat-label {
                display: block;
                font-size: 12px;
                color: #999;
                margin-top: 2px;
            }

            .webyaz-pd-info-box {
                background: #f5f5f5;
                border-radius: 8px;
                padding: 12px 18px;
                font-size: 13px;
                color: #555;
                margin-bottom: 5px;
            }

            .webyaz-pd-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 13px;
            }

            .webyaz-pd-table th {
                background: #f9f9f9;
                padding: 10px 12px;
                text-align: left;
                font-weight: 600;
                color: #555;
                border-bottom: 2px solid #eee;
                font-size: 12px;
                text-transform: uppercase;
            }

            .webyaz-pd-table td {
                padding: 10px 12px;
                border-bottom: 1px solid #f0f0f0;
                color: #333;
            }

            .webyaz-pd-table tr:hover td {
                background: #fafafa;
            }

            @media(max-width:768px) {
                .webyaz-pd-stats {
                    grid-template-columns: 1fr 1fr;
                }

                .webyaz-pf-grid {
                    grid-template-columns: 1fr;
                }

                .webyaz-pd-coupon-code {
                    font-size: 20px;
                }

                .webyaz-pd-table {
                    font-size: 12px;
                }
            }

            @media(max-width:480px) {
                .webyaz-pd-stats {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    <?php
    }

    // =============================================
    // ADMIN MENU & SAYFA
    // =============================================

    public function add_submenu()
    {
        add_submenu_page('webyaz-dashboard', 'Partner Yonetimi', 'Partner Yonetimi', 'manage_options', 'webyaz-partner', array($this, 'render_admin'));
    }

    public function render_admin()
    {
        $opts = self::get_opts();
        $tab = $_GET['tab'] ?? 'settings';
        $partners = $this->get_all_partners();
        $pending = $this->get_pending_applications();
    ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Partner Yonetimi</h1>
                <p>Ortaklik sistemi - Partner basvurulari, komisyon takibi</p>
            </div>

            <?php settings_errors('webyaz_partner'); ?>

            <!-- Tab Menu -->
            <div style="display:flex;gap:0;margin-bottom:25px;border-bottom:2px solid #eee;">
                <?php
                $tabs = array(
                    'settings' => 'Ayarlar',
                    'pending'  => 'Basvurular (' . count($pending) . ')',
                    'partners' => 'Partnerler (' . count($partners) . ')',
                    'guide'    => 'Kullanim Rehberi',
                );
                foreach ($tabs as $t => $label):
                    $active = ($tab === $t);
                ?>
                    <a href="<?php echo admin_url('admin.php?page=webyaz-partner&tab=' . $t); ?>"
                        style="padding:12px 24px;font-size:14px;font-weight:<?php echo $active ? '700' : '500'; ?>;color:<?php echo $active ? '#446084' : '#888'; ?>;text-decoration:none;border-bottom:<?php echo $active ? '3px solid #446084' : '3px solid transparent'; ?>;margin-bottom:-2px;transition:.2s;">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php
            if ($tab === 'settings') $this->render_tab_settings($opts);
            elseif ($tab === 'pending') $this->render_tab_pending($pending, $opts);
            elseif ($tab === 'partners') $this->render_tab_partners($partners);
            elseif ($tab === 'guide') $this->render_tab_guide();
            ?>
        </div>
    <?php
    }

    // --- TAB: Ayarlar ---
    private function render_tab_settings($opts)
    {
    ?>
        <form method="post" action="options.php">
            <?php settings_fields('webyaz_partner_group'); ?>
            <div class="webyaz-settings-section">
                <h2 class="webyaz-section-title">Genel Ayarlar</h2>
                <div class="webyaz-settings-grid">
                    <div class="webyaz-field"><label>Aktif</label>
                        <select name="webyaz_partner[active]">
                            <option value="0" <?php selected($opts['active'], '0'); ?>>Kapali</option>
                            <option value="1" <?php selected($opts['active'], '1'); ?>>Aktif</option>
                        </select>
                    </div>
                    <div class="webyaz-field"><label>Basvuru Formu</label>
                        <select name="webyaz_partner[application_active]">
                            <option value="1" <?php selected($opts['application_active'], '1'); ?>>Acik</option>
                            <option value="0" <?php selected($opts['application_active'], '0'); ?>>Kapali</option>
                        </select>
                    </div>
                    <div class="webyaz-field"><label>Varsayilan Indirim Orani (%)</label>
                        <input type="number" name="webyaz_partner[default_discount]" value="<?php echo esc_attr($opts['default_discount']); ?>" min="1" max="90" step="1">
                    </div>
                    <div class="webyaz-field"><label>Varsayilan Komisyon Orani (%)</label>
                        <input type="number" name="webyaz_partner[default_commission]" value="<?php echo esc_attr($opts['default_commission']); ?>" min="1" max="90" step="1">
                    </div>
                    <div class="webyaz-field"><label>Minimum Odeme Tutari (TL)</label>
                        <input type="number" name="webyaz_partner[min_payout]" value="<?php echo esc_attr($opts['min_payout']); ?>" min="0" step="10">
                    </div>
                </div>
            </div>
            <?php submit_button('Kaydet'); ?>
        </form>
        <?php
    }

    // --- TAB: Bekleyen Basvurular ---
    private function render_tab_pending($pending, $opts)
    {
        if (empty($pending)): ?>
            <div style="text-align:center;padding:40px;color:#999;">
                <span class="dashicons dashicons-yes-alt" style="font-size:48px;display:block;margin-bottom:10px;color:#ccc;"></span>
                Bekleyen basvuru yok
            </div>
            <?php else:
            foreach ($pending as $app): ?>
                <div class="webyaz-settings-section" style="margin-bottom:15px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:15px;">
                        <div>
                            <h3 style="margin:0 0 8px;font-size:16px;color:#1a1a1a;"><?php echo esc_html($app['name']); ?></h3>
                            <p style="margin:0;font-size:13px;color:#666;line-height:1.8;">
                                <strong>E-posta:</strong> <?php echo esc_html($app['email']); ?><br>
                                <strong>Telefon:</strong> <?php echo esc_html($app['phone']); ?><br>
                                <?php if (!empty($app['website'])): ?><strong>Website:</strong> <?php echo esc_html($app['website']); ?><br><?php endif; ?>
                                <?php if (!empty($app['note'])): ?><strong>Not:</strong> <?php echo esc_html($app['note']); ?><br><?php endif; ?>
                                <strong>Tarih:</strong> <?php echo esc_html($app['date']); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Onay Formu -->
                    <div style="margin-top:15px;padding-top:15px;border-top:1px solid #eee;">
                        <form method="post" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                            <?php wp_nonce_field('webyaz_partner_admin', '_wpnonce_partner_admin'); ?>
                            <input type="hidden" name="approve_user_id" value="<?php echo intval($app['user_id']); ?>">

                            <div style="flex:1;min-width:120px;">
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Kullanici Adi</label>
                                <input type="text" name="partner_username" value="<?php echo esc_attr(sanitize_user(strtolower(str_replace(' ', '', $app['name'])))); ?>" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                            </div>
                            <div style="flex:1;min-width:120px;">
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Sifre</label>
                                <input type="text" name="partner_password" value="<?php echo wp_generate_password(10, false); ?>" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                            </div>
                            <div style="flex:1;min-width:120px;">
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Kupon Kodu</label>
                                <input type="text" name="partner_coupon_code" value="<?php echo esc_attr(strtolower(str_replace(' ', '', $app['name'])) . intval($opts['default_discount'])); ?>" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                            </div>
                            <div style="min-width:80px;">
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Indirim %</label>
                                <input type="number" name="partner_discount" value="<?php echo esc_attr($opts['default_discount']); ?>" min="1" max="90" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                            </div>
                            <div style="min-width:80px;">
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Komisyon %</label>
                                <input type="number" name="partner_commission" value="<?php echo esc_attr($opts['default_commission']); ?>" min="1" max="90" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                            </div>

                            <button type="submit" name="webyaz_partner_approve" value="1" class="webyaz-btn webyaz-btn-primary" style="padding:8px 20px;height:38px;">Onayla</button>
                        </form>

                        <form method="post" style="display:inline;margin-top:8px;">
                            <?php wp_nonce_field('webyaz_partner_admin', '_wpnonce_partner_admin'); ?>
                            <input type="hidden" name="reject_user_id" value="<?php echo intval($app['user_id']); ?>">
                            <button type="submit" name="webyaz_partner_reject" value="1" class="webyaz-btn webyaz-btn-outline" style="padding:6px 16px;font-size:12px;color:#d32f2f;border-color:#d32f2f;margin-top:8px;">Reddet</button>
                        </form>
                    </div>
                </div>
            <?php endforeach;
        endif;
    }

    // --- TAB: Partner Listesi ---
    private function render_tab_partners($partners)
    {
        if (empty($partners)): ?>
            <div style="text-align:center;padding:40px;color:#999;">
                <span class="dashicons dashicons-groups" style="font-size:48px;display:block;margin-bottom:10px;color:#ccc;"></span>
                Henuz partner yok
            </div>
        <?php else: ?>
            <table class="widefat striped" style="border-radius:10px;overflow:hidden;border:1px solid #e0e0e0;">
                <thead>
                    <tr>
                        <th>Partner</th>
                        <th>Kupon Kodu</th>
                        <th>Indirim %</th>
                        <th>Komisyon %</th>
                        <th>Toplam Kazanc</th>
                        <th>Odenen</th>
                        <th>Bekleyen</th>
                        <th>Siparis</th>
                        <th>Islem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($partners as $p):
                        $coupon = get_user_meta($p->ID, '_webyaz_partner_coupon', true);
                        $comm_rate = get_user_meta($p->ID, '_webyaz_partner_commission_rate', true);
                        $total_e = floatval(get_user_meta($p->ID, '_webyaz_partner_total_earnings', true));
                        $paid_e = floatval(get_user_meta($p->ID, '_webyaz_partner_paid_earnings', true));
                        $pending_e = $total_e - $paid_e;
                        $order_count = $this->get_partner_order_count($p->ID);

                        // Kupondan indirim oranini al
                        $discount_rate = 0;
                        if ($coupon) {
                            $coupon_id = wc_get_coupon_id_by_code($coupon);
                            if ($coupon_id) {
                                $wc_coupon = new WC_Coupon($coupon_id);
                                $discount_rate = $wc_coupon->get_amount();
                            }
                        }
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($p->display_name); ?></strong>
                                <br><small style="color:#999;"><?php echo esc_html($p->user_email); ?></small>
                            </td>
                            <td><code style="background:#f0f0f0;padding:3px 8px;border-radius:4px;font-weight:700;"><?php echo esc_html(strtoupper($coupon)); ?></code></td>
                            <td>%<?php echo esc_html($discount_rate); ?></td>
                            <td>%<?php echo esc_html($comm_rate); ?></td>
                            <td style="font-weight:700;color:#2e7d32;"><?php echo wc_price($total_e); ?></td>
                            <td><?php echo wc_price($paid_e); ?></td>
                            <td style="font-weight:700;color:#e65100;"><?php echo wc_price($pending_e); ?></td>
                            <td><?php echo intval($order_count); ?></td>
                            <td>
                                <?php if ($pending_e > 0): ?>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field('webyaz_partner_admin', '_wpnonce_partner_admin'); ?>
                                        <input type="hidden" name="paid_partner_id" value="<?php echo $p->ID; ?>">
                                        <input type="number" name="pay_amount" value="<?php echo esc_attr(round($pending_e, 2)); ?>" step="0.01" min="1" style="width:80px;padding:4px 6px;border:1px solid #ddd;border-radius:4px;font-size:12px;">
                                        <button type="submit" name="webyaz_partner_mark_paid" value="1" class="webyaz-btn webyaz-btn-primary" style="padding:4px 12px;font-size:12px;">Ode</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:#999;font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Toplam Ozet -->
            <?php
            $grand_total = 0;
            $grand_paid = 0;
            foreach ($partners as $p) {
                $grand_total += floatval(get_user_meta($p->ID, '_webyaz_partner_total_earnings', true));
                $grand_paid += floatval(get_user_meta($p->ID, '_webyaz_partner_paid_earnings', true));
            }
            ?>
            <div style="display:flex;gap:15px;margin-top:20px;">
                <div style="background:#e8f5e9;border-radius:10px;padding:15px 20px;flex:1;text-align:center;">
                    <div style="font-size:22px;font-weight:700;color:#2e7d32;"><?php echo wc_price($grand_total); ?></div>
                    <div style="font-size:12px;color:#666;">Toplam Komisyon</div>
                </div>
                <div style="background:#e3f2fd;border-radius:10px;padding:15px 20px;flex:1;text-align:center;">
                    <div style="font-size:22px;font-weight:700;color:#1565c0;"><?php echo wc_price($grand_paid); ?></div>
                    <div style="font-size:12px;color:#666;">Odenen</div>
                </div>
                <div style="background:#fff3e0;border-radius:10px;padding:15px 20px;flex:1;text-align:center;">
                    <div style="font-size:22px;font-weight:700;color:#e65100;"><?php echo wc_price($grand_total - $grand_paid); ?></div>
                    <div style="font-size:12px;color:#666;">Bekleyen</div>
                </div>
            </div>
        <?php endif;
    }

    // --- TAB: Kullanim Rehberi ---
    private function render_tab_guide()
    {
        ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

            <!-- 1. Genel Bakis -->
            <div style="background:#e3f2fd;border-radius:12px;padding:20px;border-left:4px solid #1565c0;">
                <h3 style="margin:0 0 12px;font-size:15px;color:#1565c0;">1. Partner Sistemi Nedir?</h3>
                <ul style="margin:0;padding-left:18px;font-size:13px;color:#333;line-height:2;">
                    <li>Partnerler sizin <strong>is ortaklarinizdir</strong></li>
                    <li>Her partnere ozel bir <strong>indirim kodu</strong> tanimlanir (ornek: ahmet10)</li>
                    <li>Musteriler bu kodu kullanarak <strong>indirim</strong> alir</li>
                    <li>Partner her satistan <strong>komisyon</strong> kazanir</li>
                    <li>Partner kendi <strong>Hesabim</strong> sayfasindan kazancini takip eder</li>
                </ul>
            </div>

            <!-- 2. Ayarlar -->
            <div style="background:#e8f5e9;border-radius:12px;padding:20px;border-left:4px solid #2e7d32;">
                <h3 style="margin:0 0 12px;font-size:15px;color:#2e7d32;">2. Ayarlar Nasil Yapilir?</h3>
                <ul style="margin:0;padding-left:18px;font-size:13px;color:#333;line-height:2;">
                    <li><strong>Aktif:</strong> Modulu acip kapatir</li>
                    <li><strong>Basvuru Formu:</strong> Musterilerin basvuru yapabilmesi icin acik birakin</li>
                    <li><strong>Indirim Orani (%):</strong> Musteriye verilen varsayilan indirim (onay sirasinda degistirilebilir)</li>
                    <li><strong>Komisyon Orani (%):</strong> Partnere verilen varsayilan komisyon (onay sirasinda degistirilebilir)</li>
                    <li><strong>Minimum Odeme:</strong> Partnere odeme yapilacak alt sinir</li>
                </ul>
            </div>

            <!-- 3. Basvuru Sureci -->
            <div style="background:#fff3e0;border-radius:12px;padding:20px;border-left:4px solid #e65100;">
                <h3 style="margin:0 0 12px;font-size:15px;color:#e65100;">3. Basvuru Sureci</h3>
                <ul style="margin:0;padding-left:18px;font-size:13px;color:#333;line-height:2;">
                    <li>Musteri siteye <strong>uye olur</strong> ve giris yapar</li>
                    <li>Hesabim sayfasinda <strong>"Partner Ol"</strong> linkine tiklar</li>
                    <li>Ad, e-posta, telefon ve aciklama girer</li>
                    <li>Basvuru <strong>"Basvurular"</strong> tabinda gorulur</li>
                    <li>Siz basvuruyu inceleyip <strong>Onayla</strong> veya <strong>Reddet</strong> butonuna basarsiniz</li>
                </ul>
            </div>

            <!-- 4. Onaylama -->
            <div style="background:#f3e5f5;border-radius:12px;padding:20px;border-left:4px solid #7b1fa2;">
                <h3 style="margin:0 0 12px;font-size:15px;color:#7b1fa2;">4. Partner Onaylama</h3>
                <ul style="margin:0;padding-left:18px;font-size:13px;color:#333;line-height:2;">
                    <li><strong>Kullanici Adi:</strong> Partnerin giris yapacagi isim (otomatik onerilir)</li>
                    <li><strong>Sifre:</strong> Otomatik sifre uretilir, degistirebilirsiniz</li>
                    <li><strong>Kupon Kodu:</strong> Partnerin paylasacagi indirim kodu (ornek: ahmet10)</li>
                    <li><strong>Indirim %:</strong> Bu kodla musteriye verilecek indirim</li>
                    <li><strong>Komisyon %:</strong> Bu partnerden gelen satislardan kazanacagi oran</li>
                    <li>Onay tusuna basinca: Kullanici rolu <strong>Partner</strong> olur, WooCommerce kuponu <strong>otomatik olusur</strong></li>
                </ul>
            </div>

            <!-- 5. Komisyon -->
            <div style="background:#fce4ec;border-radius:12px;padding:20px;border-left:4px solid #c62828;">
                <h3 style="margin:0 0 12px;font-size:15px;color:#c62828;">5. Komisyon Nasil Hesaplanir?</h3>
                <ul style="margin:0;padding-left:18px;font-size:13px;color:#333;line-height:2;">
                    <li>Musteri alışveris yaparken partnerin <strong>kupon kodunu</strong> girer</li>
                    <li>Siparis durumu <strong>"Tamamlandi"</strong> olunca komisyon otomatik hesaplanir</li>
                    <li>Formul: <code>Siparis Toplami x Komisyon %</code></li>
                    <li>Ornek: 1000 TL x %5 = <strong>50 TL komisyon</strong></li>
                    <li>Siparis notlarina da otomatik yazilir</li>
                    <li>Partner Hesabim sayfasindan kazancini gorur</li>
                </ul>
            </div>

            <!-- 6. Odeme -->
            <div style="background:#e0f2f1;border-radius:12px;padding:20px;border-left:4px solid #00695c;">
                <h3 style="margin:0 0 12px;font-size:15px;color:#00695c;">6. Odeme Takibi</h3>
                <ul style="margin:0;padding-left:18px;font-size:13px;color:#333;line-height:2;">
                    <li><strong>Partnerler</strong> tabinda her partnerin kazancini gorun</li>
                    <li><strong>Toplam Kazanc:</strong> Partnere biriken toplam komisyon</li>
                    <li><strong>Odenen:</strong> Simdiye kadar odediginiz tutar</li>
                    <li><strong>Bekleyen:</strong> Henuz odenmemis komisyon</li>
                    <li>Partnere havale/EFT yaptiktan sonra <strong>"Ode"</strong> butonuyla kayit edin</li>
                    <li>Odemeleri banka transferi, nakit veya anlasmali yontemle yapin</li>
                </ul>
            </div>

            <!-- 7. Partner Gorunumu -->
            <div style="background:#efebe9;border-radius:12px;padding:20px;border-left:4px solid #4e342e;grid-column:span 2;">
                <h3 style="margin:0 0 12px;font-size:15px;color:#4e342e;">7. Partner Ne Gorur?</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;font-size:13px;color:#333;">
                    <div>
                        <p style="font-weight:700;margin:0 0 6px;">Hesabim > Partner Paneli</p>
                        <ul style="margin:0;padding-left:16px;line-height:2;">
                            <li>Kendi indirim kodu (kopyalama butonu)</li>
                            <li>Toplam siparis sayisi</li>
                            <li>Toplam kazanci</li>
                            <li>Bekleyen kazanci</li>
                            <li>Bu ayki kazanci</li>
                        </ul>
                    </div>
                    <div>
                        <p style="font-weight:700;margin:0 0 6px;">Siparis Tablosu</p>
                        <ul style="margin:0;padding-left:16px;line-height:2;">
                            <li>Siparis numarasi</li>
                            <li>Tarih</li>
                            <li>Musteri adi (gizlilik: soyadi kisaltilir)</li>
                            <li>Siparis tutari</li>
                            <li>Kazanilan komisyon</li>
                            <li>Siparis durumu</li>
                        </ul>
                    </div>
                    <div>
                        <p style="font-weight:700;margin:0 0 6px;">Onemli Notlar</p>
                        <ul style="margin:0;padding-left:16px;line-height:2;">
                            <li>Partner sadece <strong>Hesabim</strong> sayfasini gorur</li>
                            <li>Admin paneline erisimi <strong>yoktur</strong></li>
                            <li>Kupon kodu <strong>sinirsiz</strong> kullanilabilir</li>
                            <li>Komisyon sadece <strong>tamamlanan</strong> siparislerde hesaplanir</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>

        <!-- Hizli Hatirlatma -->
        <div style="margin-top:20px;background:linear-gradient(135deg,#446084,#d26e4b);border-radius:12px;padding:20px 25px;color:#fff;">
            <h3 style="margin:0 0 10px;font-size:16px;">Hizli Adimlar</h3>
            <div style="display:flex;gap:20px;flex-wrap:wrap;font-size:13px;line-height:1.8;">
                <div style="flex:1;min-width:200px;">
                    <strong>1.</strong> Ayarlar tabindan indirim ve komisyon oranlarini belirleyin<br>
                    <strong>2.</strong> Basvuru formunu acik birakin
                </div>
                <div style="flex:1;min-width:200px;">
                    <strong>3.</strong> Gelen basvurulari inceleyin ve onaylayin<br>
                    <strong>4.</strong> Partnere kullanici adi, sifre ve kupon kodu verin
                </div>
                <div style="flex:1;min-width:200px;">
                    <strong>5.</strong> Partnerler tabindan kazanclari takip edin<br>
                    <strong>6.</strong> Odeme yaptiktan sonra "Ode" ile kaydedin
                </div>
            </div>
        </div>
<?php
    }
}

new Webyaz_Partner();
