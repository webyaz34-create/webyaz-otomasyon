<?php
if (!defined('ABSPATH')) exit;

class Webyaz_B2B
{
    private $opts;

    public function __construct()
    {
        // Ayarlar
        add_action('admin_init', array($this, 'register_settings'));

        // Rol & Endpoint
        add_action('init', array($this, 'register_role'));
        add_action('init', array($this, 'add_endpoint'));
        add_filter('woocommerce_account_menu_items', array($this, 'account_menu_items'));
        add_action('woocommerce_account_bayi-panel_endpoint', array($this, 'render_bayi_panel'));
        add_action('woocommerce_account_bayi-basvuru_endpoint', array($this, 'render_bayi_application'));

        // Basvuru isleme
        add_action('template_redirect', array($this, 'handle_application'));

        // Admin menu
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'handle_admin_actions'));

        // Fiyatlandirma
        add_filter('woocommerce_product_get_price', array($this, 'apply_bayi_price'), 99, 2);
        add_filter('woocommerce_product_get_regular_price', array($this, 'apply_bayi_price'), 99, 2);
        add_filter('woocommerce_product_variation_get_price', array($this, 'apply_bayi_price'), 99, 2);
        add_filter('woocommerce_product_variation_get_regular_price', array($this, 'apply_bayi_price'), 99, 2);

        // Urun sayfasina bayi fiyati alani ekle
        add_action('woocommerce_product_options_pricing', array($this, 'add_bayi_price_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_bayi_price_field'));

        // Odeme yontemi
        add_filter('woocommerce_payment_gateways', array($this, 'add_payment_gateway'));

        // Siparis tamamlaninca bakiyeden dus
        add_action('woocommerce_order_status_processing', array($this, 'deduct_balance_on_order'));
        add_action('woocommerce_order_status_completed', array($this, 'deduct_balance_on_order'));

        // Bakiye dusuk uyarisi
        add_action('woocommerce_account_dashboard', array($this, 'low_balance_warning'));

        // Hesabim sayfasinda CSS
        add_action('wp_head', array($this, 'frontend_css'));

        // Urun sayfasinda bayi fiyat etiketi
        add_filter('woocommerce_get_price_html', array($this, 'bayi_price_html'), 99, 2);

        // Giris/Kayit sayfasinda Bayi Ol CTA
        add_action('woocommerce_login_form_end', array($this, 'render_dealer_cta_on_login'));
        add_action('woocommerce_register_form_end', array($this, 'render_dealer_cta_on_register'));
    }

    public function register_settings()
    {
        register_setting('webyaz_b2b_group', 'webyaz_b2b_settings');
    }

    public function get_options()
    {
        if (!$this->opts) {
            $this->opts = wp_parse_args(get_option('webyaz_b2b_settings', array()), array(
                'active' => '1',
                'bank_name' => '',
                'bank_iban' => '',
                'bank_holder' => '',
                'bulk_discount' => 0,
                'low_balance_percent' => 50,
                'show_dealer_cta' => '1',
                'dealer_cta_text' => 'Toptan fiyatlarla alışveriş yapmak, özel bayi indirimlerinden yararlanmak ve bakiye sistemiyle kolayca sipariş vermek için hemen bayimiz olun!',
            ));
        }
        return $this->opts;
    }

    public function get_packages()
    {
        $defaults = array(
            'baslangic' => array(
                'name'     => 'Başlangıç',
                'icon'     => '🥉',
                'balance'  => 50000,
                'discount' => 15,
                'color'    => '#7B8794',
                'gradient' => 'linear-gradient(135deg, #667085 0%, #98A2B3 100%)',
                'features' => array('Başlangıç Bakiyesi', 'Tüm Ürünlerde İndirim', 'Bayi Paneli Erişimi', 'Bakiye ile Ödeme'),
            ),
            'profesyonel' => array(
                'name'     => 'Profesyonel',
                'icon'     => '🥈',
                'balance'  => 100000,
                'discount' => 25,
                'color'    => '#2563EB',
                'gradient' => 'linear-gradient(135deg, #1E40AF 0%, #3B82F6 100%)',
                'features' => array('Başlangıç Bakiyesi', 'Tüm Ürünlerde İndirim', 'Bayi Paneli Erişimi', 'Bakiye ile Ödeme', 'Öncelikli Destek'),
            ),
            'premium' => array(
                'name'     => 'Premium',
                'icon'     => '🥇',
                'balance'  => 150000,
                'discount' => 35,
                'color'    => '#D97706',
                'gradient' => 'linear-gradient(135deg, #92400E 0%, #F59E0B 100%)',
                'features' => array('Başlangıç Bakiyesi', 'Tüm Ürünlerde İndirim', 'Bayi Paneli Erişimi', 'Bakiye ile Ödeme', 'Öncelikli Destek', 'VIP Bayi Statüsü'),
            ),
        );

        // Veritabanından paket ayarlarını oku
        $saved = get_option('webyaz_b2b_packages', array());
        if (!empty($saved) && is_array($saved)) {
            foreach ($defaults as $key => &$pkg) {
                if (isset($saved[$key])) {
                    if (isset($saved[$key]['name']) && !empty($saved[$key]['name'])) {
                        $pkg['name'] = $saved[$key]['name'];
                    }
                    if (isset($saved[$key]['balance']) && is_numeric($saved[$key]['balance'])) {
                        $pkg['balance'] = floatval($saved[$key]['balance']);
                    }
                    if (isset($saved[$key]['discount']) && is_numeric($saved[$key]['discount'])) {
                        $pkg['discount'] = floatval($saved[$key]['discount']);
                    }
                }
                // Feature listesini güncelle (dinamik bakiye ve indirim)
                $pkg['features'][0] = number_format($pkg['balance'], 0, ',', '.') . ' ₺ Başlangıç Bakiyesi';
                $pkg['features'][1] = '%' . $pkg['discount'] . ' Tüm Ürünlerde İndirim';
            }
            unset($pkg);
        } else {
            // Varsayılan feature'ları oluştur
            foreach ($defaults as $key => &$pkg) {
                $pkg['features'][0] = number_format($pkg['balance'], 0, ',', '.') . ' ₺ Başlangıç Bakiyesi';
                $pkg['features'][1] = '%' . $pkg['discount'] . ' Tüm Ürünlerde İndirim';
            }
            unset($pkg);
        }

        return $defaults;
    }

    // =================== ROL & ENDPOINT ===================

    public function register_role()
    {
        if (!get_role('webyaz_bayi')) {
            add_role('webyaz_bayi', 'Bayi', array(
                'read' => true,
                'edit_posts' => false,
                'delete_posts' => false,
            ));
        }
    }

    public function add_endpoint()
    {
        add_rewrite_endpoint('bayi-panel', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('bayi-basvuru', EP_ROOT | EP_PAGES);

        // Ilk kez flush
        if (!get_option('webyaz_b2b_flushed')) {
            flush_rewrite_rules();
            update_option('webyaz_b2b_flushed', '1');
        }
    }

    // =================== HESABIM MENUSU ===================

    public function account_menu_items($items)
    {
        $opts = $this->get_options();
        if ($opts['active'] !== '1') return $items;

        $user = wp_get_current_user();
        if (in_array('webyaz_bayi', (array) $user->roles)) {
            $new_items = array();
            foreach ($items as $key => $label) {
                $new_items[$key] = $label;
                if ($key === 'orders') {
                    $new_items['bayi-panel'] = '🏢 Bayi Paneli';
                }
            }
            return $new_items;
        }

        // Basvuru secenegi
        if (is_user_logged_in() && !in_array('administrator', (array) $user->roles)) {
            $existing = get_user_meta($user->ID, '_webyaz_bayi_application', true);
            if (empty($existing)) {
                $new_items = array();
                foreach ($items as $key => $label) {
                    $new_items[$key] = $label;
                }
                $new_items['bayi-basvuru'] = '🏢 Bayi Ol';
                return $new_items;
            }
        }

        return $items;
    }

    // =================== BASVURU FORMU ===================

    public function render_bayi_application()
    {
        $user = wp_get_current_user();
        $existing = get_user_meta($user->ID, '_webyaz_bayi_application', true);
        $packages = $this->get_packages();

        if (!empty($existing)) {
            $status_labels = array('pending' => 'Beklemede ⏳', 'approved' => 'Onaylandi ✅', 'rejected' => 'Reddedildi ❌');
            $status = $existing['status'] ?? 'pending';
            $pkg_key = $existing['package'] ?? '';
            $pkg = isset($packages[$pkg_key]) ? $packages[$pkg_key] : null;
            echo '<div class="webyaz-b2b-notice info">';
            echo '<h3>Bayi Başvurunuz</h3>';
            echo '<p>Durumu: <strong>' . esc_html($status_labels[$status] ?? $status) . '</strong></p>';
            if ($pkg) {
                echo '<p>Seçilen Paket: <strong>' . esc_html($pkg['icon'] . ' ' . $pkg['name']) . '</strong> — ' . wc_price($pkg['balance']) . ' bakiye, %' . $pkg['discount'] . ' indirim</p>';
            }
            echo '<p>Başvuru tarihi: ' . esc_html($existing['date'] ?? '') . '</p>';
            echo '</div>';
            return;
        }
    ?>
        <div class="webyaz-b2b-form-wrap">
            <h2>Bayi Başvuru Formu</h2>
            <p>Bayimiz olmak için bir paket seçin ve formu doldurun. Başvurunuz incelendikten sonra size dönüş yapılacaktır.</p>

            <!-- PAKET SEÇİMİ -->
            <h3 style="margin:20px 0 12px;font-size:17px;">📦 Bayilik Paketinizi Seçin</h3>
            <div class="webyaz-b2b-packages">
                <?php $idx = 0; foreach ($packages as $key => $pkg): $idx++; ?>
                    <label class="webyaz-b2b-pkg-card" data-color="<?php echo esc_attr($pkg['color']); ?>">
                        <input type="radio" name="bayi_package" value="<?php echo esc_attr($key); ?>" required <?php echo $idx === 2 ? 'checked' : ''; ?>>
                        <div class="webyaz-b2b-pkg-inner" style="--pkg-gradient:<?php echo $pkg['gradient']; ?>;--pkg-color:<?php echo $pkg['color']; ?>">
                            <?php if ($idx === 2): ?><div class="webyaz-b2b-pkg-badge">Popüler</div><?php endif; ?>
                            <?php if ($idx === 3): ?><div class="webyaz-b2b-pkg-badge gold">En Avantajlı</div><?php endif; ?>
                            <div class="webyaz-b2b-pkg-icon"><?php echo $pkg['icon']; ?></div>
                            <h4><?php echo esc_html($pkg['name']); ?></h4>
                            <div class="webyaz-b2b-pkg-price"><?php echo wc_price($pkg['balance']); ?></div>
                            <div class="webyaz-b2b-pkg-discount">%<?php echo $pkg['discount']; ?> İndirim</div>
                            <ul class="webyaz-b2b-pkg-features">
                                <?php foreach ($pkg['features'] as $f): ?>
                                    <li>✓ <?php echo esc_html($f); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <!-- BAŞVURU FORMU -->
            <form method="post" class="webyaz-b2b-form" id="webyaz-b2b-application-form">
                <?php wp_nonce_field('webyaz_bayi_apply', '_wpnonce_bayi_apply'); ?>
                <input type="hidden" name="bayi_package" id="webyaz-b2b-pkg-input" value="profesyonel">

                <h3 style="margin:25px 0 12px;font-size:17px;">📋 Bilgileriniz</h3>
                <div class="webyaz-b2b-field-grid">
                    <div class="webyaz-b2b-field">
                        <label>Ad Soyad *</label>
                        <input type="text" name="bayi_name" required value="<?php echo esc_attr($user->display_name); ?>">
                    </div>
                    <div class="webyaz-b2b-field">
                        <label>E-posta *</label>
                        <input type="email" name="bayi_email" required value="<?php echo esc_attr($user->user_email); ?>">
                    </div>
                    <div class="webyaz-b2b-field">
                        <label>Telefon *</label>
                        <input type="tel" name="bayi_phone" required>
                    </div>
                    <div class="webyaz-b2b-field">
                        <label>Firma Adı</label>
                        <input type="text" name="bayi_company">
                    </div>
                    <div class="webyaz-b2b-field">
                        <label>Vergi Dairesi / No</label>
                        <input type="text" name="bayi_tax">
                    </div>
                    <div class="webyaz-b2b-field">
                        <label>Şehir</label>
                        <input type="text" name="bayi_city">
                    </div>
                </div>
                <div class="webyaz-b2b-field" style="margin-top:12px;">
                    <label>Not / Açıklama</label>
                    <textarea name="bayi_note" rows="3" placeholder="Neden bayi olmak istiyorsunuz?"></textarea>
                </div>
                <button type="submit" name="webyaz_bayi_submit" class="webyaz-b2b-submit-btn">
                    🚀 Başvuruyu Gönder
                </button>
            </form>
        </div>

        <script>
        (function(){
            var radios = document.querySelectorAll('.webyaz-b2b-pkg-card input[type=radio]');
            var hidden = document.getElementById('webyaz-b2b-pkg-input');
            radios.forEach(function(r){
                r.addEventListener('change', function(){
                    hidden.value = this.value;
                    document.querySelectorAll('.webyaz-b2b-pkg-card').forEach(function(c){ c.classList.remove('selected'); });
                    this.closest('.webyaz-b2b-pkg-card').classList.add('selected');
                });
                if(r.checked) r.closest('.webyaz-b2b-pkg-card').classList.add('selected');
            });
        })();
        </script>
    <?php
    }

    // =================== GİRİŞ/KAYIT SAYFASI BAYİ OL CTA ===================

    public function render_dealer_cta_on_login()
    {
        $opts = $this->get_options();
        if ($opts['active'] !== '1' || $opts['show_dealer_cta'] !== '1') return;
        if (is_user_logged_in()) return;

        $account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : '#';
        $cta_text = esc_html($opts['dealer_cta_text']);
        ?>
        <div class="webyaz-b2b-dealer-cta">
            <div class="webyaz-b2b-dealer-cta-icon">🏢</div>
            <h3>Bayimiz Olun</h3>
            <p><?php echo $cta_text; ?></p>
            <div class="webyaz-b2b-dealer-cta-features">
                <span>✓ Özel Bayi Fiyatları</span>
                <span>✓ Bakiye Sistemi</span>
                <span>✓ Toptan Alışveriş</span>
            </div>
            <p class="webyaz-b2b-dealer-cta-hint">Giriş yapın veya kayıt olun, ardından <strong>Hesabım</strong> sayfasından bayilik başvurunuzu tamamlayın.</p>
        </div>
        <?php
    }

    public function render_dealer_cta_on_register()
    {
        $opts = $this->get_options();
        if ($opts['active'] !== '1' || $opts['show_dealer_cta'] !== '1') return;
        if (is_user_logged_in()) return;
        ?>
        <div class="webyaz-b2b-register-hint">
            <span class="webyaz-b2b-register-hint-icon">🏢</span>
            <span>Bayi olmak istiyorsanız önce kayıt olun, ardından <strong>Hesabım → Bayi Ol</strong> menüsünden başvurunuzu yapın.</span>
        </div>
        <?php
    }

    public function handle_application()
    {
        if (!isset($_POST['webyaz_bayi_submit'])) return;
        if (!wp_verify_nonce($_POST['_wpnonce_bayi_apply'] ?? '', 'webyaz_bayi_apply')) return;
        if (!is_user_logged_in()) return;

        $user_id = get_current_user_id();
        $package_key = sanitize_text_field($_POST['bayi_package'] ?? 'baslangic');
        $packages = $this->get_packages();
        if (!isset($packages[$package_key])) $package_key = 'baslangic';

        $app = array(
            'name'    => sanitize_text_field($_POST['bayi_name'] ?? ''),
            'email'   => sanitize_email($_POST['bayi_email'] ?? ''),
            'phone'   => sanitize_text_field($_POST['bayi_phone'] ?? ''),
            'company' => sanitize_text_field($_POST['bayi_company'] ?? ''),
            'tax'     => sanitize_text_field($_POST['bayi_tax'] ?? ''),
            'city'    => sanitize_text_field($_POST['bayi_city'] ?? ''),
            'note'    => sanitize_textarea_field($_POST['bayi_note'] ?? ''),
            'package' => $package_key,
            'date'    => current_time('mysql'),
            'user_id' => $user_id,
            'status'  => 'pending',
        );

        update_user_meta($user_id, '_webyaz_bayi_application', $app);

        // === E-POSTA: Admin'e yeni bayilik basvurusu ===
        if (class_exists('Webyaz_Email_Templates')) {
            $admin_email = get_option('admin_email');
            $pkg = $packages[$package_key];
            $body = '<p style="font-size:15px;margin:0 0 16px;">Merhaba,</p>';
            $body .= '<p>Yeni bir <strong>bayilik başvurusu</strong> alındı!</p>';
            $body .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f8f9ff;border-radius:12px;overflow:hidden;margin:16px 0;">';
            $body .= '<tr><td style="padding:12px 16px;border-bottom:1px solid #eee;color:#888;font-size:12px;width:120px;">Ad Soyad</td><td style="padding:12px 16px;border-bottom:1px solid #eee;font-weight:600;">' . esc_html($app['name']) . '</td></tr>';
            $body .= '<tr><td style="padding:12px 16px;border-bottom:1px solid #eee;color:#888;font-size:12px;">E-posta</td><td style="padding:12px 16px;border-bottom:1px solid #eee;">' . esc_html($app['email']) . '</td></tr>';
            $body .= '<tr><td style="padding:12px 16px;border-bottom:1px solid #eee;color:#888;font-size:12px;">Telefon</td><td style="padding:12px 16px;border-bottom:1px solid #eee;">' . esc_html($app['phone']) . '</td></tr>';
            if (!empty($app['company'])) $body .= '<tr><td style="padding:12px 16px;border-bottom:1px solid #eee;color:#888;font-size:12px;">Firma</td><td style="padding:12px 16px;border-bottom:1px solid #eee;font-weight:600;">' . esc_html($app['company']) . '</td></tr>';
            if (!empty($app['city'])) $body .= '<tr><td style="padding:12px 16px;border-bottom:1px solid #eee;color:#888;font-size:12px;">Şehir</td><td style="padding:12px 16px;border-bottom:1px solid #eee;">' . esc_html($app['city']) . '</td></tr>';
            $body .= '<tr><td style="padding:12px 16px;border-bottom:1px solid #eee;color:#888;font-size:12px;">Paket</td><td style="padding:12px 16px;border-bottom:1px solid #eee;font-weight:700;color:#2196f3;">' . esc_html($pkg['icon'] . ' ' . $pkg['name']) . '</td></tr>';
            $body .= '<tr><td style="padding:12px 16px;color:#888;font-size:12px;">Bakiye / İndirim</td><td style="padding:12px 16px;font-weight:600;">' . number_format($pkg['balance'], 0, ',', '.') . ' ₺ / %' . $pkg['discount'] . '</td></tr>';
            $body .= '</table>';
            if (!empty($app['note'])) {
                $body .= '<div style="background:#f0f4ff;border-radius:8px;padding:12px 16px;margin:8px 0;font-size:13px;"><strong>Not:</strong> ' . esc_html($app['note']) . '</div>';
            }
            $body .= '<p style="margin:16px 0 0;"><a href="' . admin_url('admin.php?page=webyaz-b2b') . '" style="display:inline-block;padding:12px 28px;background:#2196f3;color:#fff;text-decoration:none;border-radius:8px;font-weight:700;">Başvuruları İncele →</a></p>';
            Webyaz_Email_Templates::send_branded_email($admin_email, '🏢 Yeni Bayilik Başvurusu — ' . esc_html($app['name']), 'Yeni Bayilik Başvurusu', $body, '🏢 Yeni Başvuru', '#2196f3');
        }

        wp_safe_redirect(wc_get_account_endpoint_url('bayi-basvuru'));
        exit;
    }

    // =================== BAYI PANELI (FRONTEND) ===================

    public function render_bayi_panel()
    {
        $user = wp_get_current_user();
        if (!in_array('webyaz_bayi', (array) $user->roles)) {
            echo '<p>Bu sayfa sadece bayiler icindir.</p>';
            return;
        }

        $opts = $this->get_options();
        $balance = floatval(get_user_meta($user->ID, '_webyaz_bayi_balance', true));
        $total_loaded = floatval(get_user_meta($user->ID, '_webyaz_bayi_total_loaded', true));
        $total_spent = floatval(get_user_meta($user->ID, '_webyaz_bayi_total_spent', true));
        $initial = floatval(get_user_meta($user->ID, '_webyaz_bayi_initial_balance', true));
        $transactions = get_user_meta($user->ID, '_webyaz_bayi_transactions', true);
        if (!is_array($transactions)) $transactions = array();

        // Bakiye yükleme talebi
        if (isset($_POST['webyaz_bayi_topup']) && wp_verify_nonce($_POST['_wpnonce_bayi_topup'] ?? '', 'webyaz_bayi_topup')) {
            $amount = floatval($_POST['topup_amount'] ?? 0);
            $reference = sanitize_text_field($_POST['topup_reference'] ?? '');
            if ($amount > 0 && !empty($reference)) {
                $pending = get_user_meta($user->ID, '_webyaz_bayi_pending_topup', true);
                if (!is_array($pending)) $pending = array();
                $pending[] = array(
                    'amount' => $amount,
                    'reference' => $reference,
                    'date' => current_time('mysql'),
                    'status' => 'pending',
                );
                update_user_meta($user->ID, '_webyaz_bayi_pending_topup', $pending);
                echo '<div class="webyaz-b2b-notice success">✅ Bakiye yukleme talebiniz gonderildi!<br><strong>Referans No:</strong> ' . esc_html($reference) . ' | <strong>Tutar:</strong> ' . wc_price($amount) . '<br>Admin kontrol edip onayladiktan sonra bakiyeniz yuklenecektir.</div>';
            } elseif ($amount > 0 && empty($reference)) {
                echo '<div class="webyaz-b2b-notice warning">⚠️ Lutfen dekont referans/islem numarasini girin!</div>';
            }
        }

        // Dusuk bakiye kontrolu
        $low_threshold = ($initial > 0) ? ($initial * intval($opts['low_balance_percent']) / 100) : 0;
        $is_low = ($initial > 0 && $balance <= $low_threshold);
        // Paket bilgisi
        $user_package = get_user_meta($user->ID, '_webyaz_bayi_package', true);
        $packages = $this->get_packages();
        $pkg = isset($packages[$user_package]) ? $packages[$user_package] : null;
    ?>
        <div class="webyaz-b2b-panel">
            <?php if ($pkg): ?>
                <div class="webyaz-b2b-package-banner" style="background:<?php echo $pkg['gradient']; ?>">
                    <span class="webyaz-b2b-package-banner-icon"><?php echo $pkg['icon']; ?></span>
                    <div>
                        <strong><?php echo esc_html($pkg['name']); ?> Paket</strong>
                        <span>%<?php echo $pkg['discount']; ?> indirim hakkınız var</span>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($is_low): ?>
                <div class="webyaz-b2b-notice warning">
                    <strong>⚠️ Bakiyeniz dusuk — alisveris yapamazsiniz!</strong><br>
                    Mevcut bakiyeniz <?php echo wc_price($balance); ?> kaldi. Bakiyenizin en az %<?php echo 100 - intval($opts['low_balance_percent']); ?> kadarini harcadiktan sonra yeni siparis veremezsiniz.<br>
                    <strong>Lutfen asagidaki banka bilgilerimize havale yapin ve "Bakiye Yukle Talebi" ile bize bildirin.</strong> Admin onayladiktan sonra bakiyeniz yenilenir.
                </div>
            <?php endif; ?>

            <div class="webyaz-b2b-stats">
                <div class="webyaz-b2b-stat-card green">
                    <span class="dashicons dashicons-wallet"></span>
                    <div>
                        <small>Mevcut Bakiye</small>
                        <strong><?php echo wc_price($balance); ?></strong>
                    </div>
                </div>
                <div class="webyaz-b2b-stat-card blue">
                    <span class="dashicons dashicons-upload"></span>
                    <div>
                        <small>Toplam Yuklenen</small>
                        <strong><?php echo wc_price($total_loaded); ?></strong>
                    </div>
                </div>
                <div class="webyaz-b2b-stat-card orange">
                    <span class="dashicons dashicons-cart"></span>
                    <div>
                        <small>Toplam Harcanan</small>
                        <strong><?php echo wc_price($total_spent); ?></strong>
                    </div>
                </div>
            </div>

            <?php if (!empty($opts['bank_name']) || !empty($opts['bank_iban'])): ?>
                <div class="webyaz-b2b-bank">
                    <h3>💳 Bakiye Yukleme - Banka Bilgileri</h3>
                    <table>
                        <?php if (!empty($opts['bank_name'])): ?>
                            <tr>
                                <td><strong>Banka:</strong></td>
                                <td><?php echo esc_html($opts['bank_name']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($opts['bank_holder'])): ?>
                            <tr>
                                <td><strong>Hesap Sahibi:</strong></td>
                                <td><?php echo esc_html($opts['bank_holder']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($opts['bank_iban'])): ?>
                            <tr>
                                <td><strong>IBAN:</strong></td>
                                <td style="font-family:monospace;letter-spacing:1px;"><?php echo esc_html($opts['bank_iban']); ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                    <form method="post" class="webyaz-b2b-topup-form">
                        <?php wp_nonce_field('webyaz_bayi_topup', '_wpnonce_bayi_topup'); ?>
                        <div style="margin-top:15px;">
                            <p style="margin:0 0 8px;font-size:14px;font-weight:600;color:#333;">📝 Havale/EFT Bildirim Formu</p>
                            <p style="margin:0 0 12px;font-size:12px;color:#888;">Havale veya EFT yaptiktan sonra asagidaki formu doldurup gonderin. Admin kontrolden sonra bakiyenizi yukleyecektir.</p>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
                                <input type="text" name="topup_reference" placeholder="Dekont Referans / Islem No" style="flex:1;min-width:180px;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;" required>
                                <input type="number" name="topup_amount" min="1" step="0.01" placeholder="Yatirilan Tutar (₺)" style="flex:1;min-width:150px;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;" required>
                            </div>
                            <button type="submit" name="webyaz_bayi_topup" value="1" style="background:linear-gradient(135deg,#4CAF50,#388E3C);color:#fff;border:none;padding:12px 24px;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;width:100%;">
                                📤 Odeme Bildirimini Gonder
                            </button>
                        </div>
                        <small style="color:#888;display:block;margin-top:6px;">⚠️ Referans numarasi olmadan talep gonderilemez. Banka dekontunuzdaki islem/referans numarasini girin.</small>
                    </form>
                </div>
            <?php endif; ?>

            <div class="webyaz-b2b-transactions">
                <h3>📋 Islem Gecmisi</h3>
                <?php if (empty($transactions)): ?>
                    <p style="color:#888;">Henuz islem yok.</p>
                <?php else: ?>
                    <table class="webyaz-b2b-table">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Aciklama</th>
                                <th>Tutar</th>
                                <th>Bakiye</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($transactions) as $tx): ?>
                                <tr>
                                    <td><?php echo esc_html($tx['date'] ?? ''); ?></td>
                                    <td><?php echo esc_html($tx['desc'] ?? ''); ?></td>
                                    <td style="color:<?php echo ($tx['type'] ?? '') === 'credit' ? '#4CAF50' : '#f44336'; ?>;">
                                        <?php echo ($tx['type'] ?? '') === 'credit' ? '+' : '-'; ?><?php echo wc_price(abs($tx['amount'] ?? 0)); ?>
                                    </td>
                                    <td><?php echo wc_price($tx['balance_after'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }

    // =================== DUSUK BAKIYE UYARISI ===================

    public function low_balance_warning()
    {
        $user = wp_get_current_user();
        if (!in_array('webyaz_bayi', (array) $user->roles)) return;

        $opts = $this->get_options();
        $balance = floatval(get_user_meta($user->ID, '_webyaz_bayi_balance', true));
        $initial = floatval(get_user_meta($user->ID, '_webyaz_bayi_initial_balance', true));
        $low_threshold = ($initial > 0) ? ($initial * intval($opts['low_balance_percent']) / 100) : 0;

        if ($initial > 0 && $balance <= $low_threshold) {
            echo '<div style="background:linear-gradient(135deg,#ff9800,#f57c00);color:#fff;padding:18px 22px;border-radius:10px;margin-bottom:20px;">';
            echo '<strong>⚠️ Bakiyeniz dusuk!</strong> Mevcut bakiyeniz: ' . wc_price($balance) . '. ';
            echo '<a href="' . esc_url(wc_get_account_endpoint_url('bayi-panel')) . '" style="color:#fff;text-decoration:underline;font-weight:bold;">Bakiye yuklemek icin tiklayin →</a>';
            echo '</div>';
        }
    }

    // =================== FIYATLANDIRMA ===================

    public function apply_bayi_price($price, $product)
    {
        if (is_admin() && !wp_doing_ajax()) return $price;
        if (!is_user_logged_in()) return $price;

        $user = wp_get_current_user();
        if (!in_array('webyaz_bayi', (array) $user->roles)) return $price;

        $opts = $this->get_options();
        $product_id = $product->get_id();

        // 1. Urune ozel bayi fiyati (en yuksek oncelik)
        $bayi_price = get_post_meta($product_id, '_webyaz_bayi_price', true);
        if (!empty($bayi_price) && is_numeric($bayi_price) && floatval($bayi_price) > 0) {
            return floatval($bayi_price);
        }

        // Varyasyon ise parent'in bayi fiyatini kontrol et
        $parent_id = $product->get_parent_id();
        if ($parent_id > 0) {
            $parent_bayi = get_post_meta($parent_id, '_webyaz_bayi_price', true);
            if (!empty($parent_bayi) && is_numeric($parent_bayi) && floatval($parent_bayi) > 0) {
                return floatval($parent_bayi);
            }
        }

        // 2. Kategori indirimi
        $cat_discounts = get_option('webyaz_b2b_cat_discounts', array());
        if (!empty($cat_discounts) && is_array($cat_discounts)) {
            $use_id = $parent_id > 0 ? $parent_id : $product_id;
            $terms = wp_get_post_terms($use_id, 'product_cat', array('fields' => 'ids'));
            $best_discount = 0;
            foreach ($terms as $term_id) {
                if (isset($cat_discounts[$term_id]) && floatval($cat_discounts[$term_id]) > $best_discount) {
                    $best_discount = floatval($cat_discounts[$term_id]);
                }
            }
            if ($best_discount > 0) {
                return round($price * (1 - $best_discount / 100), 2);
            }
        }

        // 3. Paket indirimi (bayinin secili paketi)
        $user_package = get_user_meta($user->ID, '_webyaz_bayi_package', true);
        if (!empty($user_package)) {
            $packages = $this->get_packages();
            if (isset($packages[$user_package])) {
                $pkg_discount = floatval($packages[$user_package]['discount']);
                if ($pkg_discount > 0) {
                    return round($price * (1 - $pkg_discount / 100), 2);
                }
            }
        }

        // 4. Toplu indirim
        $bulk = floatval($opts['bulk_discount'] ?? 0);
        if ($bulk > 0) {
            return round($price * (1 - $bulk / 100), 2);
        }

        return $price;
    }

    public function bayi_price_html($html, $product)
    {
        if (!is_user_logged_in()) return $html;
        $user = wp_get_current_user();
        if (!in_array('webyaz_bayi', (array) $user->roles)) return $html;

        return '<span style="color:#2196F3;font-weight:bold;">🏢 Bayi Fiyati: </span>' . $html;
    }

    // Urun edit - bayi fiyat alani
    public function add_bayi_price_field()
    {
        woocommerce_wp_text_input(array(
            'id' => '_webyaz_bayi_price',
            'label' => 'Bayi Fiyati (₺)',
            'description' => 'Bayilere ozel fiyat. Bos birakilirsa toplu/kategori indirimi uygulanir.',
            'desc_tip' => true,
            'type' => 'text',
            'data_type' => 'price',
        ));
    }

    public function save_bayi_price_field($post_id)
    {
        if (isset($_POST['_webyaz_bayi_price'])) {
            update_post_meta($post_id, '_webyaz_bayi_price', sanitize_text_field($_POST['_webyaz_bayi_price']));
        }
    }

    // =================== ODEME YONTEMI ===================

    public function add_payment_gateway($gateways)
    {
        if (class_exists('WC_Payment_Gateway')) {
            require_once __DIR__ . '/class-webyaz-b2b-gateway.php';
            $gateways[] = 'Webyaz_B2B_Gateway';
        }
        return $gateways;
    }

    public function deduct_balance_on_order($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) return;
        if ($order->get_payment_method() !== 'webyaz_bayi_balance') return;
        if (get_post_meta($order_id, '_webyaz_bayi_deducted', true) === '1') return;

        $user_id = $order->get_customer_id();
        if (!$user_id) return;

        $total = floatval($order->get_total());
        $balance = floatval(get_user_meta($user_id, '_webyaz_bayi_balance', true));
        $spent = floatval(get_user_meta($user_id, '_webyaz_bayi_total_spent', true));

        $new_balance = $balance - $total;
        update_user_meta($user_id, '_webyaz_bayi_balance', $new_balance);
        update_user_meta($user_id, '_webyaz_bayi_total_spent', $spent + $total);

        // Islem kaydi
        $this->add_transaction($user_id, 'debit', $total, 'Siparis #' . $order_id, $new_balance);
        update_post_meta($order_id, '_webyaz_bayi_deducted', '1');
    }

    // =================== ISLEM KAYDI ===================

    private function add_transaction($user_id, $type, $amount, $desc, $balance_after)
    {
        $transactions = get_user_meta($user_id, '_webyaz_bayi_transactions', true);
        if (!is_array($transactions)) $transactions = array();

        $transactions[] = array(
            'type' => $type,
            'amount' => $amount,
            'desc' => $desc,
            'balance_after' => $balance_after,
            'date' => current_time('Y-m-d H:i'),
        );

        // Son 200 islemi tut
        if (count($transactions) > 200) {
            $transactions = array_slice($transactions, -200);
        }

        update_user_meta($user_id, '_webyaz_bayi_transactions', $transactions);
    }

    // =================== ADMIN MENU ===================

    public function admin_menu()
    {
        add_submenu_page(
            'webyaz-dashboard',
            'B2B Bayi Yonetimi',
            'B2B Bayiler',
            'manage_options',
            'webyaz-b2b',
            array($this, 'render_admin_page')
        );
    }

    public function handle_admin_actions()
    {
        if (!current_user_can('manage_options')) return;

        // Bayi onayla
        if (isset($_POST['webyaz_bayi_approve']) && wp_verify_nonce($_POST['_wpnonce_b2b_admin'] ?? '', 'webyaz_b2b_admin')) {
            $user_id = intval($_POST['approve_user_id'] ?? 0);

            if ($user_id) {
                $user = get_userdata($user_id);
                if ($user) {
                    // GUVENLIK: Admin'i bayi yapma
                    if (in_array('administrator', (array) $user->roles)) {
                        add_settings_error('webyaz_b2b', 'admin_error', 'HATA: Administrator bayi yapilamaz!', 'error');
                        return;
                    }

                    // Paket bilgisini al
                    $app = get_user_meta($user_id, '_webyaz_bayi_application', true);
                    $pkg_key = is_array($app) ? ($app['package'] ?? 'baslangic') : 'baslangic';
                    $packages = $this->get_packages();
                    $pkg = isset($packages[$pkg_key]) ? $packages[$pkg_key] : $packages['baslangic'];
                    $initial_balance = floatval($pkg['balance']);

                    $user->set_role('webyaz_bayi');
                    update_user_meta($user_id, '_webyaz_bayi_package', $pkg_key);
                    update_user_meta($user_id, '_webyaz_bayi_balance', $initial_balance);
                    update_user_meta($user_id, '_webyaz_bayi_initial_balance', $initial_balance);
                    update_user_meta($user_id, '_webyaz_bayi_total_loaded', $initial_balance);
                    update_user_meta($user_id, '_webyaz_bayi_total_spent', 0);
                    update_user_meta($user_id, '_webyaz_bayi_approved_date', current_time('mysql'));

                    if ($initial_balance > 0) {
                        $this->add_transaction($user_id, 'credit', $initial_balance, $pkg['name'] . ' paketi — ilk bakiye yüklemesi', $initial_balance);
                    }

                    if (is_array($app)) {
                        $app['status'] = 'approved';
                        update_user_meta($user_id, '_webyaz_bayi_application', $app);
                    }

                    add_settings_error('webyaz_b2b', 'approved', 'Bayi onaylandi! Paket: ' . $pkg['icon'] . ' ' . $pkg['name'] . ' | Bakiye: ' . wc_price($initial_balance) . ' | İndirim: %' . $pkg['discount'], 'success');

                    // === E-POSTA: Kullanıcıya bayi onay bildirimi ===
                    if (class_exists('Webyaz_Email_Templates')) {
                        $body = '<p style="font-size:15px;margin:0 0 16px;">Merhaba <strong>' . esc_html($user->display_name) . '</strong>,</p>';
                        $body .= '<p>Bayilik başvurunuz <strong>onaylandı</strong>! 🎉 Artık tüm bayi avantajlarından yararlanabilirsiniz.</p>';
                        $body .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;overflow:hidden;margin:16px 0;">';
                        $body .= '<tr><td style="padding:14px 16px;border-bottom:1px solid #dcfce7;color:#15803d;font-size:12px;width:120px;">Paket</td><td style="padding:14px 16px;border-bottom:1px solid #dcfce7;font-weight:700;color:#15803d;">' . esc_html($pkg['icon'] . ' ' . $pkg['name']) . '</td></tr>';
                        $body .= '<tr><td style="padding:14px 16px;border-bottom:1px solid #dcfce7;color:#15803d;font-size:12px;">Başlangıç Bakiye</td><td style="padding:14px 16px;border-bottom:1px solid #dcfce7;font-weight:700;font-size:16px;color:#059669;">' . number_format($initial_balance, 2, ',', '.') . ' ₺</td></tr>';
                        $body .= '<tr><td style="padding:14px 16px;color:#15803d;font-size:12px;">İndirim Oranı</td><td style="padding:14px 16px;font-weight:700;color:#059669;">%' . esc_html($pkg['discount']) . ' Tüm Ürünlerde</td></tr>';
                        $body .= '</table>';
                        $body .= '<p>Hesabınızda <strong>Bayi Paneli</strong> menüsünden bakiyenizi, işlemlerinizi ve özel bayi fiyatlarınızı görüntüleyebilirsiniz.</p>';
                        $body .= '<p style="text-align:center;margin:20px 0;"><a href="' . wc_get_account_endpoint_url('bayi-panel') . '" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;text-decoration:none;border-radius:8px;font-weight:700;font-size:15px;">Bayi Panelime Git →</a></p>';
                        Webyaz_Email_Templates::send_branded_email($user->user_email, '✅ Bayilik Başvurunuz Onaylandı!', 'Bayiliğiniz Aktif', $body, '✅ Onaylandı', '#22c55e');
                    }
                }
            }
        }

        // Bayi reddet
        if (isset($_POST['webyaz_bayi_reject']) && wp_verify_nonce($_POST['_wpnonce_b2b_admin'] ?? '', 'webyaz_b2b_admin')) {
            $user_id = intval($_POST['reject_user_id'] ?? 0);
            if ($user_id) {
                $app = get_user_meta($user_id, '_webyaz_bayi_application', true);
                if (is_array($app)) {
                    $app['status'] = 'rejected';
                    update_user_meta($user_id, '_webyaz_bayi_application', $app);
                }
                add_settings_error('webyaz_b2b', 'rejected', 'Basvuru reddedildi.', 'info');

                // === E-POSTA: Kullanıcıya red bildirimi ===
                if (class_exists('Webyaz_Email_Templates') && $user_id) {
                    $user = get_userdata($user_id);
                    if ($user) {
                        $body = '<p style="font-size:15px;margin:0 0 16px;">Merhaba <strong>' . esc_html($user->display_name) . '</strong>,</p>';
                        $body .= '<p>Bayilik başvurunuz maalesef <strong>reddedilmiştir</strong>.</p>';
                        $body .= '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 18px;margin:16px 0;">';
                        $body .= '<div style="font-size:13px;color:#991b1b;">Başvurunuz değerlendirilmiş ancak şu aşamada onaylanmamıştır. Sorularınız için bizimle iletişime geçebilirsiniz.</div>';
                        $body .= '</div>';
                        $body .= '<p style="margin:16px 0 0;"><a href="' . wc_get_page_permalink('myaccount') . '" style="display:inline-block;padding:12px 28px;background:#6b7280;color:#fff;text-decoration:none;border-radius:8px;font-weight:700;">Hesabıma Git →</a></p>';
                        Webyaz_Email_Templates::send_branded_email($user->user_email, '❌ Bayilik Başvurunuz Reddedildi', 'Başvurunuz Reddedildi', $body, '❌ Reddedildi', '#dc2626');
                    }
                }
            }
        }

        // Bakiye yukle (admin onay)
        if (isset($_POST['webyaz_bayi_add_balance']) && wp_verify_nonce($_POST['_wpnonce_b2b_admin'] ?? '', 'webyaz_b2b_admin')) {
            $bayi_id = intval($_POST['balance_user_id'] ?? 0);
            $amount = floatval($_POST['add_amount'] ?? 0);
            $desc = sanitize_text_field($_POST['add_desc'] ?? 'Bakiye yuklemesi');

            if ($bayi_id && $amount > 0) {
                $balance = floatval(get_user_meta($bayi_id, '_webyaz_bayi_balance', true));
                $loaded = floatval(get_user_meta($bayi_id, '_webyaz_bayi_total_loaded', true));
                $new_balance = $balance + $amount;

                update_user_meta($bayi_id, '_webyaz_bayi_balance', $new_balance);
                update_user_meta($bayi_id, '_webyaz_bayi_total_loaded', $loaded + $amount);

                // Ilk bakiyeyi guncelle (dusuk bakiye hesabi icin)
                $initial = floatval(get_user_meta($bayi_id, '_webyaz_bayi_initial_balance', true));
                if ($new_balance > $initial) {
                    update_user_meta($bayi_id, '_webyaz_bayi_initial_balance', $new_balance);
                }

                $this->add_transaction($bayi_id, 'credit', $amount, $desc, $new_balance);

                // Bekleyen talepleri temizle
                delete_user_meta($bayi_id, '_webyaz_bayi_pending_topup');

                add_settings_error('webyaz_b2b', 'balance_added', wc_price($amount) . ' bakiye yuklendi!', 'success');
            }
        }

        // Kategori indirimlerini kaydet
        if (isset($_POST['webyaz_b2b_save_cats']) && wp_verify_nonce($_POST['_wpnonce_b2b_admin'] ?? '', 'webyaz_b2b_admin')) {
            $discounts = array();
            if (isset($_POST['cat_discount']) && is_array($_POST['cat_discount'])) {
                foreach ($_POST['cat_discount'] as $cat_id => $percent) {
                    $p = floatval($percent);
                    if ($p > 0) {
                        $discounts[intval($cat_id)] = $p;
                    }
                }
            }
            update_option('webyaz_b2b_cat_discounts', $discounts);
            add_settings_error('webyaz_b2b', 'cats_saved', 'Kategori indirimleri kaydedildi!', 'success');
        }

        // Bakiye talep onayi (admin)
        if (isset($_POST['webyaz_approve_topup']) && wp_verify_nonce($_POST['_wpnonce_b2b_admin'] ?? '', 'webyaz_b2b_admin')) {
            $bayi_id = intval($_POST['topup_user_id'] ?? 0);
            $topup_index = intval($_POST['topup_index'] ?? -1);

            if ($bayi_id && $topup_index >= 0) {
                $pending = get_user_meta($bayi_id, '_webyaz_bayi_pending_topup', true);
                if (is_array($pending) && isset($pending[$topup_index]) && ($pending[$topup_index]['status'] ?? '') === 'pending') {
                    $amount = floatval($pending[$topup_index]['amount']);

                    // Bakiyeye ekle
                    $balance = floatval(get_user_meta($bayi_id, '_webyaz_bayi_balance', true));
                    $loaded = floatval(get_user_meta($bayi_id, '_webyaz_bayi_total_loaded', true));
                    $new_balance = $balance + $amount;

                    update_user_meta($bayi_id, '_webyaz_bayi_balance', $new_balance);
                    update_user_meta($bayi_id, '_webyaz_bayi_total_loaded', $loaded + $amount);

                    // Initial balance guncelle
                    $initial = floatval(get_user_meta($bayi_id, '_webyaz_bayi_initial_balance', true));
                    if ($new_balance > $initial) {
                        update_user_meta($bayi_id, '_webyaz_bayi_initial_balance', $new_balance);
                    }

                    $this->add_transaction($bayi_id, 'credit', $amount, 'Bakiye talebi onaylandi', $new_balance);

                    // Talebi onayli olarak isaretle
                    $pending[$topup_index]['status'] = 'approved';
                    update_user_meta($bayi_id, '_webyaz_bayi_pending_topup', $pending);

                    add_settings_error('webyaz_b2b', 'topup_approved', wc_price($amount) . ' bakiye talebi onaylandi ve yuklendi!', 'success');
                }
            }
        }

        // Bakiye talep reddi (admin)
        if (isset($_POST['webyaz_reject_topup']) && wp_verify_nonce($_POST['_wpnonce_b2b_admin'] ?? '', 'webyaz_b2b_admin')) {
            $bayi_id = intval($_POST['topup_user_id'] ?? 0);
            $topup_index = intval($_POST['topup_index'] ?? -1);

            if ($bayi_id && $topup_index >= 0) {
                $pending = get_user_meta($bayi_id, '_webyaz_bayi_pending_topup', true);
                if (is_array($pending) && isset($pending[$topup_index])) {
                    $pending[$topup_index]['status'] = 'rejected';
                    update_user_meta($bayi_id, '_webyaz_bayi_pending_topup', $pending);
                    add_settings_error('webyaz_b2b', 'topup_rejected', 'Bakiye talebi reddedildi.', 'info');
                }
            }
        }

        // B2B Ayarlari kaydet
        if (isset($_POST['webyaz_b2b_save_settings_btn']) && wp_verify_nonce($_POST['_wpnonce_b2b_settings'] ?? '', 'webyaz_b2b_save_settings')) {
            $new_settings = array(
                'active' => sanitize_text_field($_POST['webyaz_b2b_settings']['active'] ?? '1'),
                'low_balance_percent' => intval($_POST['webyaz_b2b_settings']['low_balance_percent'] ?? 50),
                'bank_name' => sanitize_text_field($_POST['webyaz_b2b_settings']['bank_name'] ?? ''),
                'bank_holder' => sanitize_text_field($_POST['webyaz_b2b_settings']['bank_holder'] ?? ''),
                'bank_iban' => sanitize_text_field($_POST['webyaz_b2b_settings']['bank_iban'] ?? ''),
                'bulk_discount' => sanitize_text_field($_POST['webyaz_b2b_settings']['bulk_discount'] ?? ''),
                'show_dealer_cta' => sanitize_text_field($_POST['webyaz_b2b_settings']['show_dealer_cta'] ?? '0'),
                'dealer_cta_text' => sanitize_textarea_field($_POST['webyaz_b2b_settings']['dealer_cta_text'] ?? ''),
            );
            update_option('webyaz_b2b_settings', $new_settings);
            $this->opts = null; // cache temizle
            add_settings_error('webyaz_b2b', 'settings_saved', '✅ Ayarlar basariyla kaydedildi!', 'success');
        }

        // Bayilik paketlerini kaydet
        if (isset($_POST['webyaz_b2b_save_packages']) && wp_verify_nonce($_POST['_wpnonce_b2b_admin'] ?? '', 'webyaz_b2b_admin')) {
            $pkg_data = array();
            $pkg_keys = array('baslangic', 'profesyonel', 'premium');
            foreach ($pkg_keys as $key) {
                $pkg_data[$key] = array(
                    'name'     => sanitize_text_field($_POST['pkg_name'][$key] ?? ''),
                    'balance'  => floatval($_POST['pkg_balance'][$key] ?? 0),
                    'discount' => floatval($_POST['pkg_discount'][$key] ?? 0),
                );
            }
            update_option('webyaz_b2b_packages', $pkg_data);
            add_settings_error('webyaz_b2b', 'packages_saved', '✅ Bayilik paketleri başarıyla kaydedildi!', 'success');
        }
    }

    // =================== ADMIN SAYFASI ===================

    public function render_admin_page()
    {
        $opts = $this->get_options();
        $tab = $_GET['tab'] ?? 'bayiler';

        // Istatistikler
        $bayiler = get_users(array('role' => 'webyaz_bayi'));
        $pending_apps = get_users(array('meta_key' => '_webyaz_bayi_application', 'meta_compare' => 'EXISTS'));
        $pending_count = 0;
        foreach ($pending_apps as $pa) {
            $app = get_user_meta($pa->ID, '_webyaz_bayi_application', true);
            if (is_array($app) && ($app['status'] ?? '') === 'pending') $pending_count++;
        }

        // Bekleyen bakiye talepleri
        $topup_pending_count = 0;
        $all_bayiler = get_users(array('role' => 'webyaz_bayi'));
        foreach ($all_bayiler as $b) {
            $pending_topups = get_user_meta($b->ID, '_webyaz_bayi_pending_topup', true);
            if (is_array($pending_topups)) {
                foreach ($pending_topups as $pt) {
                    if (($pt['status'] ?? '') === 'pending') $topup_pending_count++;
                }
            }
        }
    ?>
        <div class="wrap" style="max-width:1100px;">
            <?php settings_errors('webyaz_b2b');
            settings_errors('general'); ?>
            <h1 style="display:flex;align-items:center;gap:10px;">
                <span class="dashicons dashicons-building" style="font-size:28px;"></span>
                B2B Bayi Yonetimi
            </h1>

            <div style="display:flex;gap:10px;margin:20px 0;">
                <div style="background:#4CAF50;color:#fff;padding:15px 25px;border-radius:10px;text-align:center;flex:1;">
                    <div style="font-size:28px;font-weight:700;"><?php echo count($bayiler); ?></div>
                    <div>Aktif Bayi</div>
                </div>
                <div style="background:#ff9800;color:#fff;padding:15px 25px;border-radius:10px;text-align:center;flex:1;">
                    <div style="font-size:28px;font-weight:700;"><?php echo $pending_count; ?></div>
                    <div>Bekleyen Basvuru</div>
                </div>
                <div style="background:#2196F3;color:#fff;padding:15px 25px;border-radius:10px;text-align:center;flex:1;">
                    <div style="font-size:28px;font-weight:700;"><?php echo $topup_pending_count; ?></div>
                    <div>Bakiye Talebi</div>
                </div>
            </div>

            <nav class="nav-tab-wrapper" style="margin-bottom:20px;">
                <a href="<?php echo admin_url('admin.php?page=webyaz-b2b&tab=bayiler'); ?>" class="nav-tab <?php echo $tab === 'bayiler' ? 'nav-tab-active' : ''; ?>">🏢 Bayiler</a>
                <a href="<?php echo admin_url('admin.php?page=webyaz-b2b&tab=basvurular'); ?>" class="nav-tab <?php echo $tab === 'basvurular' ? 'nav-tab-active' : ''; ?>">📋 Basvurular <?php if ($pending_count) echo '<span style="background:#f44336;color:#fff;border-radius:50%;padding:2px 7px;font-size:11px;margin-left:5px;">' . $pending_count . '</span>'; ?></a>
                <a href="<?php echo admin_url('admin.php?page=webyaz-b2b&tab=bakiye_talepleri'); ?>" class="nav-tab <?php echo $tab === 'bakiye_talepleri' ? 'nav-tab-active' : ''; ?>">💳 Bakiye Talepleri <?php if ($topup_pending_count) echo '<span style="background:#f44336;color:#fff;border-radius:50%;padding:2px 7px;font-size:11px;margin-left:5px;">' . $topup_pending_count . '</span>'; ?></a>
                <a href="<?php echo admin_url('admin.php?page=webyaz-b2b&tab=fiyatlandirma'); ?>" class="nav-tab <?php echo $tab === 'fiyatlandirma' ? 'nav-tab-active' : ''; ?>">💰 Fiyatlandirma</a>
                <a href="<?php echo admin_url('admin.php?page=webyaz-b2b&tab=ayarlar'); ?>" class="nav-tab <?php echo $tab === 'ayarlar' ? 'nav-tab-active' : ''; ?>">⚙️ Ayarlar</a>
                <a href="<?php echo admin_url('admin.php?page=webyaz-b2b&tab=rehber'); ?>" class="nav-tab <?php echo $tab === 'rehber' ? 'nav-tab-active' : ''; ?>">📖 Rehber</a>
            </nav>

            <?php
            switch ($tab) {
                case 'basvurular':
                    $this->render_admin_applications();
                    break;
                case 'bakiye_talepleri':
                    $this->render_admin_topup_requests();
                    break;
                case 'fiyatlandirma':
                    $this->render_admin_pricing();
                    break;
                case 'ayarlar':
                    $this->render_admin_settings();
                    break;
                case 'rehber':
                    $this->render_admin_guide();
                    break;
                default:
                    $this->render_admin_dealers();
                    break;
            }
            ?>
        </div>
    <?php
    }

    private function render_admin_dealers()
    {
        $bayiler = get_users(array('role' => 'webyaz_bayi'));
        if (empty($bayiler)) {
            echo '<div style="background:#fff;padding:40px;text-align:center;border-radius:10px;border:1px solid #ddd;">';
            echo '<span class="dashicons dashicons-building" style="font-size:48px;color:#ccc;"></span>';
            echo '<p style="color:#888;font-size:16px;">Henuz bayi yok.</p>';
            echo '</div>';
            return;
        }
    ?>
        <table class="wp-list-table widefat striped" style="border-radius:10px;overflow:hidden;table-layout:auto;">
            <thead>
                <tr>
                    <th style="width:180px;">Bayi</th>
                    <th style="width:120px;">Firma</th>
                    <th style="width:120px;">Bakiye</th>
                    <th style="width:110px;">Yuklenen</th>
                    <th style="width:110px;">Harcanan</th>
                    <th>Bakiye Yukle</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bayiler as $bayi):
                    $balance = floatval(get_user_meta($bayi->ID, '_webyaz_bayi_balance', true));
                    $loaded = floatval(get_user_meta($bayi->ID, '_webyaz_bayi_total_loaded', true));
                    $spent = floatval(get_user_meta($bayi->ID, '_webyaz_bayi_total_spent', true));
                    $initial = floatval(get_user_meta($bayi->ID, '_webyaz_bayi_initial_balance', true));
                    $app = get_user_meta($bayi->ID, '_webyaz_bayi_application', true);
                    $company = is_array($app) ? ($app['company'] ?? '-') : '-';
                    $low_percent = intval($opts['low_balance_percent'] ?? 50);
                    $low_threshold = ($initial > 0) ? ($initial * $low_percent / 100) : 0;
                    $is_low = ($initial > 0 && $balance <= $low_threshold);
                ?>
                    <tr<?php echo $is_low ? ' style="background:#fff3e0;"' : ''; ?>>
                        <td>
                            <strong><?php echo esc_html($bayi->display_name); ?></strong><br>
                            <small style="color:#888;"><?php echo esc_html($bayi->user_email); ?></small>
                        </td>
                        <td><?php echo esc_html($company); ?></td>
                        <td>
                            <strong style="color:<?php echo $balance > 0 ? ($is_low ? '#ff9800' : '#4CAF50') : '#f44336'; ?>;"><?php echo wc_price($balance); ?></strong>
                            <?php if ($is_low): ?>
                                <br><small style="color:#f44336;font-weight:600;">⚠ Dusuk bakiye</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo wc_price($loaded); ?></td>
                        <td><?php echo wc_price($spent); ?></td>
                        <td>
                            <form method="post" style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;">
                                <?php wp_nonce_field('webyaz_b2b_admin', '_wpnonce_b2b_admin'); ?>
                                <input type="hidden" name="balance_user_id" value="<?php echo $bayi->ID; ?>">
                                <input type="number" name="add_amount" min="1" step="0.01" placeholder="Miktar (₺)" style="width:110px;padding:6px;" required>
                                <input type="text" name="add_desc" placeholder="Aciklama" value="Bakiye yuklemesi" style="width:130px;padding:6px;">
                                <button type="submit" name="webyaz_bayi_add_balance" value="1" class="button button-primary" style="padding:6px 16px;white-space:nowrap;">+ Bakiye Yukle</button>
                            </form>
                        </td>
                        </tr>
                    <?php endforeach; ?>
            </tbody>
        </table>
    <?php
    }

    // =================== BAKİYE TALEPLERİ ===================

    private function render_admin_topup_requests()
    {
        $bayiler = get_users(array('role' => 'webyaz_bayi'));
        $has_pending = false;
        $requests = array();

        foreach ($bayiler as $bayi) {
            $pending = get_user_meta($bayi->ID, '_webyaz_bayi_pending_topup', true);
            if (!is_array($pending)) continue;
            $app = get_user_meta($bayi->ID, '_webyaz_bayi_application', true);
            $company = is_array($app) ? ($app['company'] ?? '-') : '-';
            foreach ($pending as $idx => $req) {
                if (($req['status'] ?? '') === 'pending') {
                    $has_pending = true;
                    $requests[] = array(
                        'user' => $bayi,
                        'company' => $company,
                        'index' => $idx,
                        'amount' => floatval($req['amount']),
                        'reference' => $req['reference'] ?? '-',
                        'date' => $req['date'] ?? '-',
                    );
                }
            }
        }

        if (!$has_pending) {
            echo '<div style="background:#fff;padding:40px;text-align:center;border-radius:10px;border:1px solid #ddd;">';
            echo '<span class="dashicons dashicons-yes-alt" style="font-size:48px;color:#4CAF50;"></span>';
            echo '<p style="color:#888;font-size:16px;">Bekleyen bakiye talebi yok.</p>';
            echo '</div>';
            return;
        }
    ?>
        <div style="background:#e3f2fd;padding:15px 20px;border-radius:10px;margin-bottom:15px;border-left:4px solid #2196F3;">
            <strong>💳 Bayi Odeme Bildirimleri</strong>
            <p style="margin:5px 0 0;color:#555;font-size:13px;">Bayilerin havale/EFT yaptiktan sonra gonderdigi odeme bildirimleri. Dekont referans no ile bankanizdan kontrol edip onaylayin.</p>
        </div>

        <table class="wp-list-table widefat striped" style="border-radius:10px;overflow:hidden;table-layout:auto;">
            <thead>
                <tr>
                    <th style="width:180px;">Bayi</th>
                    <th style="width:120px;">Firma</th>
                    <th style="width:160px;">Dekont Referans No</th>
                    <th style="width:130px;">Yatirilan Tutar</th>
                    <th style="width:140px;">Bildirim Tarihi</th>
                    <th style="width:110px;">Mevcut Bakiye</th>
                    <th>Islem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $req):
                    $current_balance = floatval(get_user_meta($req['user']->ID, '_webyaz_bayi_balance', true));
                ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($req['user']->display_name); ?></strong><br>
                            <small style="color:#888;"><?php echo esc_html($req['user']->user_email); ?></small>
                        </td>
                        <td><?php echo esc_html($req['company']); ?></td>
                        <td>
                            <code style="background:#f5f5f5;padding:4px 10px;border-radius:4px;font-size:14px;font-weight:600;color:#1565c0;letter-spacing:0.5px;"><?php echo esc_html($req['reference']); ?></code>
                        </td>
                        <td><strong style="color:#2196F3;font-size:16px;"><?php echo wc_price($req['amount']); ?></strong></td>
                        <td><?php echo esc_html($req['date']); ?></td>
                        <td><?php echo wc_price($current_balance); ?></td>
                        <td>
                            <div style="display:flex;gap:8px;">
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('webyaz_b2b_admin', '_wpnonce_b2b_admin'); ?>
                                    <input type="hidden" name="topup_user_id" value="<?php echo $req['user']->ID; ?>">
                                    <input type="hidden" name="topup_index" value="<?php echo $req['index']; ?>">
                                    <button type="submit" name="webyaz_approve_topup" value="1" class="button button-primary" style="background:#4CAF50;border-color:#388E3C;">✅ Onayla</button>
                                </form>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('webyaz_b2b_admin', '_wpnonce_b2b_admin'); ?>
                                    <input type="hidden" name="topup_user_id" value="<?php echo $req['user']->ID; ?>">
                                    <input type="hidden" name="topup_index" value="<?php echo $req['index']; ?>">
                                    <button type="submit" name="webyaz_reject_topup" value="1" class="button" style="color:#f44336;border-color:#f44336;">❌ Reddet</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_admin_applications()
    {
        $users = get_users(array('meta_key' => '_webyaz_bayi_application', 'meta_compare' => 'EXISTS'));
        $pending = array();
        foreach ($users as $u) {
            $app = get_user_meta($u->ID, '_webyaz_bayi_application', true);
            if (is_array($app) && ($app['status'] ?? '') === 'pending') {
                $app['user'] = $u;
                $pending[] = $app;
            }
        }

        if (empty($pending)) {
            echo '<div style="background:#fff;padding:40px;text-align:center;border-radius:10px;border:1px solid #ddd;">';
            echo '<span class="dashicons dashicons-clipboard" style="font-size:48px;color:#ccc;"></span>';
            echo '<p style="color:#888;font-size:16px;">Bekleyen basvuru yok.</p>';
            echo '</div>';
            return;
        }

        $packages = $this->get_packages();
        foreach ($pending as $app):
            $u = $app['user'];
            $pkg_key = $app['package'] ?? 'baslangic';
            $pkg = isset($packages[$pkg_key]) ? $packages[$pkg_key] : $packages['baslangic'];
        ?>
            <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:20px;margin-bottom:15px;">
                <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:15px;">
                    <div style="flex:1;">
                        <h3 style="margin:0 0 5px;"><?php echo esc_html($app['name'] ?? $u->display_name); ?></h3>
                        <p style="margin:0;color:#888;">
                            📧 <?php echo esc_html($app['email'] ?? ''); ?> &nbsp;
                            📱 <?php echo esc_html($app['phone'] ?? ''); ?> &nbsp;
                            🏢 <?php echo esc_html($app['company'] ?? '-'); ?> &nbsp;
                            📋 <?php echo esc_html($app['tax'] ?? '-'); ?> &nbsp;
                            📍 <?php echo esc_html($app['city'] ?? '-'); ?>
                        </p>
                        <!-- Paket bilgisi -->
                        <div style="margin-top:10px;display:inline-flex;align-items:center;gap:8px;background:<?php echo $pkg['gradient']; ?>;color:#fff;padding:8px 16px;border-radius:8px;font-size:13px;">
                            <span style="font-size:18px;"><?php echo $pkg['icon']; ?></span>
                            <strong><?php echo esc_html($pkg['name']); ?></strong>
                            <span style="opacity:0.85;">|</span>
                            <span><?php echo wc_price($pkg['balance']); ?> bakiye</span>
                            <span style="opacity:0.85;">|</span>
                            <span>%<?php echo $pkg['discount']; ?> indirim</span>
                        </div>
                        <?php if (!empty($app['note'])): ?>
                            <p style="margin:8px 0 0;padding:8px 12px;background:#f5f5f5;border-radius:6px;font-style:italic;">"<?php echo esc_html($app['note']); ?>"</p>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;gap:10px;align-items:start;">
                        <form method="post">
                            <?php wp_nonce_field('webyaz_b2b_admin', '_wpnonce_b2b_admin'); ?>
                            <input type="hidden" name="approve_user_id" value="<?php echo $u->ID; ?>">
                            <button type="submit" name="webyaz_bayi_approve" value="1" class="button button-primary" style="padding:8px 20px;">✅ Onayla<br><small style="font-weight:normal;">Paket otomatik yüklenir</small></button>
                        </form>
                        <form method="post">
                            <?php wp_nonce_field('webyaz_b2b_admin', '_wpnonce_b2b_admin'); ?>
                            <input type="hidden" name="reject_user_id" value="<?php echo $u->ID; ?>">
                            <button type="submit" name="webyaz_bayi_reject" value="1" class="button" style="color:#f44336;padding:8px 20px;">❌ Reddet</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach;
    }

    private function render_admin_pricing()
    {
        $opts = $this->get_options();
        $packages = $this->get_packages();
        $cat_discounts = get_option('webyaz_b2b_cat_discounts', array());
        $categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
        ?>

        <!-- BAYİLİK PAKETLERİ -->
        <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:25px;margin-bottom:20px;">
            <h2 style="margin:0 0 8px;">📦 Bayilik Paketleri</h2>
            <p style="color:#888;margin:0 0 15px;">Başvuru formunda seçilebilir 3 bayilik paketi. Onaylanan başvurularda paket bakiyesi otomatik yüklenir ve indirim oranı uygulanır.</p>
            <form method="post">
                <?php wp_nonce_field('webyaz_b2b_admin', '_wpnonce_b2b_admin'); ?>
                <table class="wp-list-table widefat fixed striped" style="border-radius:8px;overflow:hidden;">
                    <thead>
                        <tr>
                            <th style="width:40px;">Simge</th>
                            <th>Paket Adı</th>
                            <th style="width:160px;">Bakiye (₺)</th>
                            <th style="width:120px;">İndirim (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($packages as $key => $pkg): ?>
                            <tr>
                                <td style="font-size:24px;text-align:center;"><?php echo $pkg['icon']; ?></td>
                                <td><input type="text" name="pkg_name[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($pkg['name']); ?>" style="width:100%;padding:6px 10px;font-size:14px;"></td>
                                <td><input type="number" name="pkg_balance[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($pkg['balance']); ?>" min="0" step="1000" style="width:100%;padding:6px 10px;font-size:14px;"></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:4px;">
                                        <input type="number" name="pkg_discount[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($pkg['discount']); ?>" min="0" max="99" step="1" style="width:70px;padding:6px 10px;font-size:14px;">
                                        <span style="font-size:16px;">%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" name="webyaz_b2b_save_packages" value="1" class="button button-primary" style="margin-top:15px;">Paketleri Kaydet</button>
            </form>

            <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:8px;padding:14px;margin-top:15px;">
                <strong>🔄 Otomasyon:</strong> Başvuru onaylandığında seçilen paketin bakiyesi otomatik yüklenir ve indirim oranı tüm ürünlere uygulanır. Manuel işlem gerekmez.
            </div>
        </div>

        <!-- TOPLU İNDİRİM -->
        <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:25px;margin-bottom:20px;">
            <h2 style="margin:0 0 15px;">Toplu Indirim</h2>
            <p style="color:#888;margin:0 0 15px;">Tum urunlere uygulanacak genel bayi indirimi. Urune ozel fiyat, kategori indirimi veya paket indirimi varsa onlar onceliklidir.</p>
            <form method="post" action="options.php">
                <?php settings_fields('webyaz_b2b_group'); ?>
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="number" name="webyaz_b2b_settings[bulk_discount]" value="<?php echo esc_attr($opts['bulk_discount']); ?>" min="0" max="99" step="1" style="width:100px;padding:8px;font-size:16px;">
                    <span style="font-size:18px;">%</span>
                    <input type="hidden" name="webyaz_b2b_settings[active]" value="<?php echo esc_attr($opts['active']); ?>">
                    <input type="hidden" name="webyaz_b2b_settings[bank_name]" value="<?php echo esc_attr($opts['bank_name']); ?>">
                    <input type="hidden" name="webyaz_b2b_settings[bank_iban]" value="<?php echo esc_attr($opts['bank_iban']); ?>">
                    <input type="hidden" name="webyaz_b2b_settings[bank_holder]" value="<?php echo esc_attr($opts['bank_holder']); ?>">
                    <input type="hidden" name="webyaz_b2b_settings[low_balance_percent]" value="<?php echo esc_attr($opts['low_balance_percent']); ?>">
                </div>
                <?php submit_button('Kaydet'); ?>
            </form>
        </div>

        <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:25px;">
            <h2 style="margin:0 0 15px;">Kategori Indirimleri</h2>
            <p style="color:#888;margin:0 0 15px;">Her kategoriye ozel bayi indirim orani. Bos birakilanlar toplu indirimi kullanir.</p>
            <form method="post">
                <?php wp_nonce_field('webyaz_b2b_admin', '_wpnonce_b2b_admin'); ?>
                <table class="wp-list-table widefat fixed striped" style="border-radius:8px;overflow:hidden;">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th style="width:150px;">Indirim %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($categories) && !is_wp_error($categories)): ?>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><?php echo esc_html($cat->name); ?> <small style="color:#888;">(<?php echo $cat->count; ?> urun)</small></td>
                                    <td><input type="number" name="cat_discount[<?php echo $cat->term_id; ?>]" value="<?php echo esc_attr($cat_discounts[$cat->term_id] ?? ''); ?>" min="0" max="99" step="1" style="width:80px;padding:5px;" placeholder="-"></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" style="text-align:center;color:#888;">Kategori bulunamadi.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <button type="submit" name="webyaz_b2b_save_cats" value="1" class="button button-primary" style="margin-top:15px;">Kategori Indirimlerini Kaydet</button>
            </form>
        </div>

        <div style="background:#e3f2fd;border:1px solid #90caf9;border-radius:10px;padding:20px;margin-top:20px;">
            <h3 style="margin:0 0 8px;">💡 Urune Ozel Bayi Fiyati</h3>
            <p style="margin:0;color:#555;">Urune ozel bayi fiyati tanimlamak icin <strong>Urunler > Urun Duzenle > Genel</strong> sekmesinde "Bayi Fiyati" alanini kullanin. Bu fiyat tum indirimlerden onceliklidir.</p>
        </div>
    <?php
    }

    private function render_admin_settings()
    {
        $opts = $this->get_options();
    ?>
        <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:25px;">
            <h2 style="margin:0 0 20px;">B2B Ayarlari</h2>
            <form method="post">
                <?php wp_nonce_field('webyaz_b2b_save_settings', '_wpnonce_b2b_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th>Aktif</th>
                        <td>
                            <select name="webyaz_b2b_settings[active]">
                                <option value="1" <?php selected($opts['active'], '1'); ?>>Evet</option>
                                <option value="0" <?php selected($opts['active'], '0'); ?>>Hayir</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Dusuk Bakiye Uyari (%)</th>
                        <td>
                            <input type="number" name="webyaz_b2b_settings[low_balance_percent]" value="<?php echo esc_attr($opts['low_balance_percent']); ?>" min="10" max="90" style="width:80px;">
                            <p class="description">Bakiye bu yuzdeye dustugunde uyari gosterilir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2">
                            <h3 style="margin:0;">💳 Banka Bilgileri</h3>
                            <p style="margin:5px 0 0;color:#888;">Bayilerin bakiye yuklemek icin havale yapacagi hesap.</p>
                        </th>
                    </tr>
                    <tr>
                        <th>Banka Adi</th>
                        <td><input type="text" name="webyaz_b2b_settings[bank_name]" value="<?php echo esc_attr($opts['bank_name']); ?>" class="regular-text" placeholder="Ornek: Ziraat Bankasi"></td>
                    </tr>
                    <tr>
                        <th>Hesap Sahibi</th>
                        <td><input type="text" name="webyaz_b2b_settings[bank_holder]" value="<?php echo esc_attr($opts['bank_holder']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>IBAN</th>
                        <td><input type="text" name="webyaz_b2b_settings[bank_iban]" value="<?php echo esc_attr($opts['bank_iban']); ?>" class="regular-text" placeholder="TR..."></td>
                    </tr>
                    <tr>
                        <th colspan="2">
                            <h3 style="margin:0;">🏢 Giriş Sayfası - Bayi Ol Kutusu</h3>
                            <p style="margin:5px 0 0;color:#888;">Giriş/kayıt sayfasında ziyaretçilere gösterilen bayilik başvurusu çağrı kutusu.</p>
                        </th>
                    </tr>
                    <tr>
                        <th>Bayi Ol Kutusunu Göster</th>
                        <td>
                            <select name="webyaz_b2b_settings[show_dealer_cta]">
                                <option value="1" <?php selected($opts['show_dealer_cta'], '1'); ?>>Evet</option>
                                <option value="0" <?php selected($opts['show_dealer_cta'], '0'); ?>>Hayır</option>
                            </select>
                            <p class="description">WooCommerce giriş/kayıt sayfasında "Bayi Ol" kutusunu gösterir.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Bayi Ol Açıklama Metni</th>
                        <td>
                            <textarea name="webyaz_b2b_settings[dealer_cta_text]" rows="3" class="large-text" placeholder="Toptan fiyatlarla alışveriş yapmak için bayimiz olun!"><?php echo esc_textarea($opts['dealer_cta_text']); ?></textarea>
                            <p class="description">Giriş sayfasındaki "Bayi Ol" kutusunda gösterilecek açıklama.</p>
                        </td>
                    </tr>
                </table>
                <input type="hidden" name="webyaz_b2b_settings[bulk_discount]" value="<?php echo esc_attr($opts['bulk_discount']); ?>">
                <?php submit_button('Ayarlari Kaydet', 'primary', 'webyaz_b2b_save_settings_btn'); ?>
            </form>
        </div>
    <?php
    }

    // =================== KULLANIM REHBERI ===================

    private function render_admin_guide()
    {
    ?>
        <style>
            .webyaz-guide-section {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 12px;
                padding: 25px 30px;
                margin-bottom: 20px;
            }

            .webyaz-guide-section h2 {
                margin: 0 0 15px;
                font-size: 18px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .webyaz-guide-section h3 {
                margin: 20px 0 8px;
                font-size: 15px;
                color: #1565c0;
            }

            .webyaz-guide-section p,
            .webyaz-guide-section li {
                font-size: 14px;
                line-height: 1.8;
                color: #444;
            }

            .webyaz-guide-section ul,
            .webyaz-guide-section ol {
                padding-left: 20px;
                margin: 5px 0 15px;
            }

            .webyaz-guide-step {
                display: flex;
                gap: 15px;
                margin-bottom: 18px;
                align-items: flex-start;
            }

            .webyaz-guide-num {
                min-width: 36px;
                height: 36px;
                border-radius: 50%;
                background: linear-gradient(135deg, #446084, #5b7da8);
                color: #fff;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 15px;
                margin-top: 2px;
            }

            .webyaz-guide-step-content {
                flex: 1;
            }

            .webyaz-guide-step-content strong {
                color: #333;
                font-size: 14px;
            }

            .webyaz-guide-step-content p {
                margin: 3px 0 0;
                color: #666;
                font-size: 13px;
            }

            .webyaz-guide-table {
                width: 100%;
                border-collapse: collapse;
                margin: 10px 0 15px;
            }

            .webyaz-guide-table th {
                background: #f0f4f8;
                padding: 10px 14px;
                text-align: left;
                font-size: 13px;
                font-weight: 600;
                border-bottom: 2px solid #ddd;
            }

            .webyaz-guide-table td {
                padding: 10px 14px;
                border-bottom: 1px solid #eee;
                font-size: 13px;
            }

            .webyaz-guide-table tr:last-child td {
                border-bottom: none;
            }

            .webyaz-guide-tip {
                background: #e8f5e9;
                border-left: 4px solid #4CAF50;
                border-radius: 0 8px 8px 0;
                padding: 12px 18px;
                margin: 12px 0;
                font-size: 13px;
                color: #2e7d32;
            }

            .webyaz-guide-warn {
                background: #fff3e0;
                border-left: 4px solid #ff9800;
                border-radius: 0 8px 8px 0;
                padding: 12px 18px;
                margin: 12px 0;
                font-size: 13px;
                color: #e65100;
            }

            .webyaz-guide-flow {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                gap: 10px;
                margin: 15px 0;
            }

            .webyaz-guide-flow-item {
                background: #f5f7fa;
                border: 1px solid #e0e0e0;
                border-radius: 10px;
                padding: 15px;
                text-align: center;
                position: relative;
            }

            .webyaz-guide-flow-item .emoji {
                font-size: 24px;
                display: block;
                margin-bottom: 6px;
            }

            .webyaz-guide-flow-item strong {
                font-size: 13px;
                color: #333;
            }

            .webyaz-guide-flow-item small {
                display: block;
                font-size: 11px;
                color: #888;
                margin-top: 3px;
            }
        </style>

        <!-- GENEL BAKIS -->
        <div class="webyaz-guide-section">
            <h2>📖 B2B Bayi Sistemi — Kullanim Rehberi</h2>
            <p>Bu modul, bayilerinize ozel fiyatlandirma, bakiye (cuzdaan) sistemi ve yonetim paneli sunar. Asagida sistemin nasil calistigini adim adim bulabilirsiniz.</p>

            <div class="webyaz-guide-flow">
                <div class="webyaz-guide-flow-item">
                    <span class="emoji">📝</span>
                    <strong>Basvuru</strong>
                    <small>Musteri basvurur</small>
                </div>
                <div class="webyaz-guide-flow-item">
                    <span class="emoji">✅</span>
                    <strong>Onay</strong>
                    <small>Admin onaylar</small>
                </div>
                <div class="webyaz-guide-flow-item">
                    <span class="emoji">💰</span>
                    <strong>Bakiye</strong>
                    <small>Admin yukler</small>
                </div>
                <div class="webyaz-guide-flow-item">
                    <span class="emoji">🛒</span>
                    <strong>Alisveris</strong>
                    <small>Bakiyeyle oder</small>
                </div>
                <div class="webyaz-guide-flow-item">
                    <span class="emoji">⚠️</span>
                    <strong>Uyari</strong>
                    <small>Dusuk bakiye uyarisi</small>
                </div>
            </div>
        </div>

        <!-- BAYI OL KUTUSU (GİRİŞ SAYFASI) -->
        <div class="webyaz-guide-section">
            <h2>1️⃣ Giriş Sayfası — "Bayi Ol" Kutusu</h2>
            <p>B2B modülü aktifken, WooCommerce giriş/kayıt sayfasında ziyaretçilere otomatik olarak <strong>"Bayimiz Olun"</strong> çağrı kutusu gösterilir. Bu kutu, potansiyel bayileri başvuruya teşvik eder.</p>

            <h3>Nasıl Görünür?</h3>
            <div class="webyaz-guide-step">
                <div class="webyaz-guide-num">1</div>
                <div class="webyaz-guide-step-content">
                    <strong>Giriş Formu Altında — Premium CTA Kutusu</strong>
                    <p>Giriş yapmamış ziyaretçiler WooCommerce Hesabım sayfasını açtığında, giriş formunun hemen altında koyu mavi gradient arka planlı, dikkat çekici bir "Bayimiz Olun" kutusu görür. Bu kutuda:</p>
                    <ul style="margin:5px 0 0;padding-left:18px;">
                        <li>🏢 Simgesi ve "Bayimiz Olun" başlığı</li>
                        <li>Özelleştirilebilir açıklama metni</li>
                        <li>✓ Özel Bayi Fiyatları, ✓ Bakiye Sistemi, ✓ Toptan Alışveriş rozet etiketleri</li>
                        <li>Giriş/kayıt sonrası başvuru yönlendirme bilgisi</li>
                    </ul>
                </div>
            </div>
            <div class="webyaz-guide-step">
                <div class="webyaz-guide-num">2</div>
                <div class="webyaz-guide-step-content">
                    <strong>Kayıt Formu Altında — Yönlendirme Mesajı</strong>
                    <p>WooCommerce'de kayıt formu aktifse, kayıt formunun altında da kısa bir bilgilendirme kutusu görünür: <em>"Bayi olmak istiyorsanız önce kayıt olun, ardından Hesabım → Bayi Ol menüsünden başvurunuzu yapın."</em></p>
                </div>
            </div>

            <h3>Ziyaretçi Akışı</h3>
            <div class="webyaz-guide-flow">
                <div class="webyaz-guide-flow-item">
                    <span class="emoji">👁️</span>
                    <strong>CTA'yı Görür</strong>
                    <small>Giriş sayfasında</small>
                </div>
                <div class="webyaz-guide-flow-item">
                    <span class="emoji">📝</span>
                    <strong>Kayıt Olur</strong>
                    <small>Hesap oluşturur</small>
                </div>
                <div class="webyaz-guide-flow-item">
                    <span class="emoji">🔑</span>
                    <strong>Giriş Yapar</strong>
                    <small>Hesabına girer</small>
                </div>
                <div class="webyaz-guide-flow-item">
                    <span class="emoji">🏢</span>
                    <strong>Bayi Ol</strong>
                    <small>Hesabım menüsünden</small>
                </div>
                <div class="webyaz-guide-flow-item">
                    <span class="emoji">📋</span>
                    <strong>Form Doldurur</strong>
                    <small>Başvuru gönderir</small>
                </div>
            </div>

            <h3>Admin Ayarları</h3>
            <div class="webyaz-guide-step">
                <div class="webyaz-guide-num">A</div>
                <div class="webyaz-guide-step-content">
                    <strong>Kutuyu Açma/Kapatma</strong>
                    <p><strong>Webyaz → B2B Bayiler → Ayarlar</strong> sekmesinde "Bayi Ol Kutusunu Göster" ayarını <strong>Evet/Hayır</strong> olarak değiştirebilirsiniz. Varsayılan olarak <strong>açık</strong> gelir.</p>
                </div>
            </div>
            <div class="webyaz-guide-step">
                <div class="webyaz-guide-num">B</div>
                <div class="webyaz-guide-step-content">
                    <strong>Açıklama Metnini Özelleştirme</strong>
                    <p><strong>Webyaz → B2B Bayiler → Ayarlar</strong> sekmesinde "Bayi Ol Açıklama Metni" alanına istediğiniz metni yazabilirsiniz. Bu metin, giriş sayfasındaki kutuda gösterilir.</p>
                </div>
            </div>

            <div class="webyaz-guide-tip">💡 <strong>İpucu:</strong> Açıklama metninde bayilerinize sunduğunuz avantajları (özel fiyat, kargo avantajı, destek hattı vb.) vurgulayarak daha fazla başvuru alabilirsiniz.</div>
            <div class="webyaz-guide-warn">⚠️ <strong>Not:</strong> Bu kutu sadece giriş yapmamış ziyaretçilere gösterilir. Giriş yapmış kullanıcılar Hesabım menüsündeki "🏢 Bayi Ol" seçeneğini kullanır.</div>
        </div>

        <!-- BAYI BASVURU & ONAY -->
        <div class="webyaz-guide-section">
            <h2>2️⃣ Bayi Basvuru ve Onay Sureci</h2>

            <h3>Musteri Tarafindan</h3>
            <div class="webyaz-guide-step">
                <div class="webyaz-guide-num">1</div>
                <div class="webyaz-guide-step-content">
                    <strong>Hesabim > Bayi Basvuru</strong>
                    <p>Musteri, WooCommerce Hesabim sayfasindaki "Bayi Basvuru" menusunden basvuru formunu doldurur (Ad, firma, vergi no, sehir, telefon, not).</p>
                </div>
            </div>

            <h3>Admin Tarafindan</h3>
            <div class="webyaz-guide-step">
                <div class="webyaz-guide-num">2</div>
                <div class="webyaz-guide-step-content">
                    <strong>Webyaz > B2B Bayiler > Basvurular</strong>
                    <p>Gelen basvurulari inceleyin. "Onayla" butonuyla birlikte ilk bakiye miktarini girin. Onay verildiginde:<br>
                        - Kullaniciya <code>webyaz_bayi</code> rolu atanir<br>
                        - Girdiginiz ilk bakiye hesabina yuklenir<br>
                        - Islem gecmisine "Ilk bakiye yuklemesi" kaydedilir</p>
                </div>
            </div>

            <div class="webyaz-guide-tip">💡 <strong>Ipucu:</strong> Basvuruyu reddedebilir veya daha sonra degerlendirebilirsiniz. Reddedilen basvurular listeden kalkar.</div>
            <div class="webyaz-guide-warn">⚠️ <strong>Onemli:</strong> Administrator (yonetici) hesaplari guvenlik nedeniyle bayi yapilamaz.</div>
        </div>

        <!-- BAKIYE SISTEMI -->
        <div class="webyaz-guide-section">
            <h2>3️⃣ Bakiye (Cuzdan) Sistemi</h2>

            <h3>Bakiye Nasil Yuklenir?</h3>
            <div class="webyaz-guide-step">
                <div class="webyaz-guide-num">A</div>
                <div class="webyaz-guide-step-content">
                    <strong>Admin Manuel Yukleme</strong>
                    <p><strong>Webyaz > B2B Bayiler > Bayiler</strong> tabinda, bayinin satirindaki "Miktar" alanina tutari girin, aciklama yazin ve <strong>+ Yukle</strong> butonuna basin. Bakiye aninda eklenir.</p>
                </div>
            </div>
            <div class="webyaz-guide-step">
                <div class="webyaz-guide-num">B</div>
                <div class="webyaz-guide-step-content">
                    <strong>Bayi Havale Talebi</strong>
                    <p>Bayi, Hesabim > Bayi Paneli'nden banka bilgilerini gorur, havale yapar ve "Bakiye Yukle Talebi" butonuyla talebini gonderir. Admin onayladiktan sonra bakiye eklenir.</p>
                </div>
            </div>

            <h3>Bakiye Takibi</h3>
            <table class="webyaz-guide-table">
                <tr>
                    <th>Bilgi</th>
                    <th>Nerede Gorunur?</th>
                </tr>
                <tr>
                    <td><strong>Mevcut Bakiye</strong></td>
                    <td>Bayi Paneli (yesil kart) + Checkout sayfasi</td>
                </tr>
                <tr>
                    <td><strong>Toplam Yuklenen</strong></td>
                    <td>Bayi Paneli (mavi kart)</td>
                </tr>
                <tr>
                    <td><strong>Toplam Harcanan</strong></td>
                    <td>Bayi Paneli (turuncu kart)</td>
                </tr>
                <tr>
                    <td><strong>Islem Gecmisi</strong></td>
                    <td>Bayi Paneli alt kisim (tarih, aciklama, tutar, kalan bakiye)</td>
                </tr>
            </table>

            <div class="webyaz-guide-warn">⚠️ <strong>Dusuk Bakiye Uyarisi:</strong> Bakiye, ilk yuklenen miktarin belirlediginiz yuzdesine (varsayilan %50) dustugunde bayi otomatik uyari gorur. Bu yuzeyi <strong>Ayarlar</strong> tabindan degistirebilirsiniz.</div>
        </div>

        <!-- ODEME YONTEMI -->
        <div class="webyaz-guide-section">
            <h2>4️⃣ Bakiye ile Odeme (Checkout)</h2>

            <div class="webyaz-guide-step">
                <div class="webyaz-guide-num">1</div>
                <div class="webyaz-guide-step-content">
                    <strong>Sadece Bayilere Gorunur</strong>
                    <p>"Bakiye ile Ode" odeme yontemi checkout'ta sadece <code>webyaz_bayi</code> rolune sahip kullanicilara gosterilir. Normal musteriler bu secenegi goremez.</p>
                </div>
            </div>
            <div class="webyaz-guide-step">
                <div class="webyaz-guide-num">2</div>
                <div class="webyaz-guide-step-content">
                    <strong>Bakiye Kontrolu</strong>
                    <p>Checkout'ta bayi mevcut bakiyesini gorur. Yetersiz bakiye varsa kalan miktar kirmizi olarak gosterilir ve siparis verilemez.</p>
                </div>
            </div>
            <div class="webyaz-guide-step">
                <div class="webyaz-guide-num">3</div>
                <div class="webyaz-guide-step-content">
                    <strong>Siparis Onaylanir</strong>
                    <p>Bakiye yeterliyse siparis otomatik tamamlanir, bakiyeden dusulur ve islem gecmisine kaydedilir.</p>
                </div>
            </div>

            <div class="webyaz-guide-tip">💡 <strong>Ipucu:</strong> Odeme yontemini WooCommerce > Ayarlar > Odemeler sayfasindan da aktif/pasif yapabilirsiniz.</div>
        </div>

        <!-- FIYATLANDIRMA -->
        <div class="webyaz-guide-section">
            <h2>5️⃣ Bayi Ozel Fiyatlandirma</h2>
            <p>Bayiler siteye giris yaptiginda fiyatlar otomatik olarak ozel bayi fiyatlarina donusur. 3 katmanli oncelik sirasi vardir:</p>

            <table class="webyaz-guide-table">
                <tr>
                    <th>Oncelik</th>
                    <th>Tur</th>
                    <th>Nerden Ayarlanir?</th>
                    <th>Ornek</th>
                </tr>
                <tr>
                    <td><strong style="color:#f44336;">1 (En yuksek)</strong></td>
                    <td>Urune ozel bayi fiyati</td>
                    <td>Urunler > Urun Duzenle > Genel > "Bayi Fiyati" alani</td>
                    <td>Urun A → 50₺ (normal 100₺)</td>
                </tr>
                <tr>
                    <td><strong style="color:#ff9800;">2</strong></td>
                    <td>Kategori indirimi</td>
                    <td>Webyaz > B2B Bayiler > Fiyatlandirma > Kategori Indirimleri</td>
                    <td>Elektronik → %20 indirim</td>
                </tr>
                <tr>
                    <td><strong style="color:#4CAF50;">3 (En dusuk)</strong></td>
                    <td>Toplu indirim</td>
                    <td>Webyaz > B2B Bayiler > Fiyatlandirma > Toplu Indirim</td>
                    <td>Tum urunler → %15 indirim</td>
                </tr>
            </table>

            <div class="webyaz-guide-tip">💡 <strong>Nasil calisir?</strong> Sistem once urune ozel fiyat var mi bakar. Yoksa kategori indirimi kontrol eder. O da yoksa toplu indirimi uygular. Hicbiri tanimlanmamissa normal fiyat gosterilir.</div>
            <div class="webyaz-guide-tip">💡 <strong>Fiyat Etiketi:</strong> Bayiler urunu goruntulediginde fiyatin yaninda "🏢 Bayi Fiyati:" etiketi gosterilir.</div>
        </div>

        <!-- ADMIN PANEL REHBERI -->
        <div class="webyaz-guide-section">
            <h2>6️⃣ Admin Panel Tablari</h2>

            <table class="webyaz-guide-table">
                <tr>
                    <th>Tab</th>
                    <th>Ne Yapar?</th>
                </tr>
                <tr>
                    <td><strong>🏢 Bayiler</strong></td>
                    <td>Aktif bayileri listeler. Her bayinin bakiye, yuklenen, harcanan tutarlarini gorursunuz. Satirda dogrudan bakiye yukleyebilirsiniz.</td>
                </tr>
                <tr>
                    <td><strong>📋 Basvurular</strong></td>
                    <td>Bekleyen bayi basvurulari. Firma, vergi, sehir, telefon bilgileri gosterilir. Onay veya red yapabilirsiniz. Onay sirasinda ilk bakiye girilir.</td>
                </tr>
                <tr>
                    <td><strong>💰 Fiyatlandirma</strong></td>
                    <td>Toplu indirim yuzdesini ve kategori bazli indirim oranlarini ayarlayin. Urune ozel fiyat icin urun duzenleme sayfasini kullanin.</td>
                </tr>
                <tr>
                    <td><strong>⚙️ Ayarlar</strong></td>
                    <td>Modulu aktif/pasif yapin. Dusuk bakiye uyari yuzdesini ayarlayin. Banka hesap bilgilerini girin (bayilerin gorecegi bilgi).</td>
                </tr>
                <tr>
                    <td><strong>📖 Rehber</strong></td>
                    <td>Bu sayfadasiniz! Sistemin nasil calistigini buradan kontrol edebilirsiniz.</td>
                </tr>
            </table>
        </div>

        <!-- BAYI PANELI -->
        <div class="webyaz-guide-section">
            <h2>7️⃣ Bayi Paneli (Frontend - Hesabim)</h2>
            <p>Bayi rolu olan kullanicilar WooCommerce "Hesabim" sayfasinda su ek menuleri gorur:</p>

            <table class="webyaz-guide-table">
                <tr>
                    <th>Menu</th>
                    <th>Icerik</th>
                </tr>
                <tr>
                    <td><strong>Bayi Paneli</strong></td>
                    <td>Bakiye karti (mevcut, yuklenen, harcanan), banka bilgileri, bakiye talebi formu, islem gecmisi tablosu</td>
                </tr>
                <tr>
                    <td><strong>Bayi Basvuru</strong></td>
                    <td>Henuz bayi olmayan musteriler icin basvuru formu. Bayi rolu olan kullanicilara gizlenir.</td>
                </tr>
            </table>
        </div>

        <!-- TEKNIK BILGILER -->
        <div class="webyaz-guide-section">
            <h2>8️⃣ Teknik Bilgiler</h2>

            <h3>Kullanici Meta Verileri</h3>
            <table class="webyaz-guide-table">
                <tr>
                    <th>Meta Key</th>
                    <th>Aciklama</th>
                </tr>
                <tr>
                    <td><code>_webyaz_bayi_balance</code></td>
                    <td>Mevcut bakiye</td>
                </tr>
                <tr>
                    <td><code>_webyaz_bayi_total_loaded</code></td>
                    <td>Toplam yuklenen miktar</td>
                </tr>
                <tr>
                    <td><code>_webyaz_bayi_total_spent</code></td>
                    <td>Toplam harcanan miktar</td>
                </tr>
                <tr>
                    <td><code>_webyaz_bayi_transactions</code></td>
                    <td>Islem gecmisi dizisi (son 200 islem tutulur)</td>
                </tr>
                <tr>
                    <td><code>_webyaz_bayi_initial_balance</code></td>
                    <td>Ilk/en yuksek bakiye (dusuk bakiye uyarisi hesabi icin)</td>
                </tr>
                <tr>
                    <td><code>_webyaz_bayi_application</code></td>
                    <td>Basvuru bilgileri (ad, firma, telefon, durum)</td>
                </tr>
            </table>

            <h3>Onemli Notlar</h3>
            <ul>
                <li>Modul aktif edildiginde <strong>Ayarlar > Kalici Baglantilar</strong> sayfasini bir kez ziyaret edin — endpoint'ler icin gereklidir.</li>
                <li>Bayi rolu: <code>webyaz_bayi</code> — WooCommerce musteri yetkileri + read yetkisi icerir.</li>
                <li>Odeme yontemi ID'si: <code>webyaz_bayi_balance</code></li>
                <li>Urun duzenleme sayfasindaki "Bayi Fiyati" alani product meta olarak <code>_webyaz_bayi_price</code> key'inde saklanir.</li>
                <li>Islem gecmisi en fazla 200 kayit tutar, eski kayitlar otomatik silinir.</li>
            </ul>

            <div class="webyaz-guide-warn">⚠️ <strong>Dikkat:</strong> Bayi rolunu silerseniz mevcut bayiler erisim kaybeder. Rolu silmeden once "Kullanicilar" sayfasindan rol degistirin.</div>
        </div>

    <?php
    }

    // =================== FRONTEND CSS ===================

    public function frontend_css()
    {
        if (!function_exists('is_account_page') || !is_account_page()) return;
    ?>
        <style>
            .webyaz-b2b-panel {
                max-width: 900px;
            }

            .webyaz-b2b-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin-bottom: 25px;
            }

            .webyaz-b2b-stat-card {
                display: flex;
                align-items: center;
                gap: 15px;
                padding: 20px;
                border-radius: 12px;
                color: #fff;
            }

            .webyaz-b2b-stat-card .dashicons {
                font-size: 32px;
                width: 32px;
                height: 32px;
            }

            .webyaz-b2b-stat-card small {
                display: block;
                opacity: .85;
                font-size: 13px;
            }

            .webyaz-b2b-stat-card strong {
                display: block;
                font-size: 22px;
                margin-top: 3px;
            }

            .webyaz-b2b-stat-card.green {
                background: linear-gradient(135deg, #4CAF50, #388E3C);
            }

            .webyaz-b2b-stat-card.blue {
                background: linear-gradient(135deg, #2196F3, #1565C0);
            }

            .webyaz-b2b-stat-card.orange {
                background: linear-gradient(135deg, #FF9800, #E65100);
            }

            .webyaz-b2b-bank {
                background: #f8f9fa;
                border: 1px solid #e0e0e0;
                border-radius: 12px;
                padding: 20px;
                margin-bottom: 25px;
            }

            .webyaz-b2b-bank h3 {
                margin: 0 0 12px;
            }

            .webyaz-b2b-bank table {
                margin-bottom: 10px;
            }

            .webyaz-b2b-bank td {
                padding: 5px 10px 5px 0;
            }

            .webyaz-b2b-transactions h3 {
                margin: 0 0 12px;
            }

            .webyaz-b2b-table {
                width: 100%;
                border-collapse: collapse;
            }

            .webyaz-b2b-table th,
            .webyaz-b2b-table td {
                padding: 10px 12px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }

            .webyaz-b2b-table thead th {
                background: #f5f5f5;
                font-weight: 600;
            }

            .webyaz-b2b-notice {
                padding: 15px 20px;
                border-radius: 10px;
                margin-bottom: 20px;
            }

            .webyaz-b2b-notice.success {
                background: #e8f5e9;
                color: #2e7d32;
                border: 1px solid #a5d6a7;
            }

            .webyaz-b2b-notice.warning {
                background: #fff3e0;
                color: #e65100;
                border: 1px solid #ffcc80;
            }

            .webyaz-b2b-notice.info {
                background: #e3f2fd;
                color: #1565c0;
                border: 1px solid #90caf9;
            }

            /* ===== PAKET KARTLARI ===== */
            .webyaz-b2b-packages {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 16px;
                margin-bottom: 20px;
            }

            .webyaz-b2b-pkg-card {
                cursor: pointer;
                position: relative;
            }

            .webyaz-b2b-pkg-card input[type=radio] {
                position: absolute;
                opacity: 0;
                pointer-events: none;
            }

            .webyaz-b2b-pkg-inner {
                background: #fff;
                border: 2px solid #e0e0e0;
                border-radius: 16px;
                padding: 24px 18px;
                text-align: center;
                transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
                position: relative;
                overflow: hidden;
            }

            .webyaz-b2b-pkg-inner::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: var(--pkg-gradient);
                opacity: 0.5;
                transition: all 0.3s ease;
            }

            .webyaz-b2b-pkg-card:hover .webyaz-b2b-pkg-inner {
                border-color: var(--pkg-color);
                transform: translateY(-4px);
                box-shadow: 0 12px 36px rgba(0,0,0,0.12);
            }

            .webyaz-b2b-pkg-card:hover .webyaz-b2b-pkg-inner::before {
                height: 6px;
                opacity: 1;
            }

            .webyaz-b2b-pkg-card.selected .webyaz-b2b-pkg-inner {
                border-color: var(--pkg-color);
                box-shadow: 0 0 0 3px color-mix(in srgb, var(--pkg-color) 20%, transparent),
                            0 12px 36px rgba(0,0,0,0.15);
                transform: translateY(-6px);
            }

            .webyaz-b2b-pkg-card.selected .webyaz-b2b-pkg-inner::before {
                height: 6px;
                opacity: 1;
            }

            .webyaz-b2b-pkg-badge {
                position: absolute;
                top: 14px;
                right: -28px;
                background: linear-gradient(135deg, #2563EB, #3B82F6);
                color: #fff;
                font-size: 10px;
                font-weight: 700;
                padding: 4px 32px;
                transform: rotate(45deg);
                letter-spacing: 0.5px;
                text-transform: uppercase;
            }

            .webyaz-b2b-pkg-badge.gold {
                background: linear-gradient(135deg, #D97706, #F59E0B);
            }

            .webyaz-b2b-pkg-icon {
                font-size: 42px;
                margin-bottom: 6px;
                filter: drop-shadow(0 2px 6px rgba(0,0,0,0.15));
            }

            .webyaz-b2b-pkg-inner h4 {
                margin: 0 0 8px;
                font-size: 18px;
                font-weight: 700;
                color: #1a1a2e;
            }

            .webyaz-b2b-pkg-price {
                font-size: 22px;
                font-weight: 800;
                color: var(--pkg-color);
                margin-bottom: 4px;
            }

            .webyaz-b2b-pkg-discount {
                display: inline-block;
                background: var(--pkg-gradient);
                color: #fff;
                font-size: 13px;
                font-weight: 700;
                padding: 4px 14px;
                border-radius: 20px;
                margin-bottom: 14px;
                letter-spacing: 0.3px;
            }

            .webyaz-b2b-pkg-features {
                list-style: none;
                padding: 0;
                margin: 0;
                text-align: left;
            }

            .webyaz-b2b-pkg-features li {
                font-size: 12.5px;
                color: #555;
                padding: 4px 0;
                border-bottom: 1px solid #f0f0f0;
            }

            .webyaz-b2b-pkg-features li:last-child {
                border-bottom: none;
            }

            /* ===== GÖNDER BUTONU ===== */
            .webyaz-b2b-submit-btn {
                display: block;
                width: 100%;
                margin-top: 18px;
                background: linear-gradient(135deg, #1E40AF 0%, #3B82F6 100%);
                color: #fff;
                border: none;
                padding: 15px 30px;
                border-radius: 12px;
                font-size: 16px;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.3s ease;
                letter-spacing: 0.3px;
                box-shadow: 0 4px 15px rgba(30, 64, 175, 0.3);
            }

            .webyaz-b2b-submit-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(30, 64, 175, 0.4);
            }

            /* ===== PAKET BANNER (BAYİ PANELİ) ===== */
            .webyaz-b2b-package-banner {
                display: flex;
                align-items: center;
                gap: 14px;
                padding: 16px 22px;
                border-radius: 12px;
                color: #fff;
                margin-bottom: 20px;
            }

            .webyaz-b2b-package-banner-icon {
                font-size: 32px;
                filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
            }

            .webyaz-b2b-package-banner strong {
                display: block;
                font-size: 16px;
                margin-bottom: 2px;
            }

            .webyaz-b2b-package-banner span {
                font-size: 13px;
                opacity: 0.9;
            }

            .webyaz-b2b-form-wrap h2 {
                margin-bottom: 10px;
            }

            .webyaz-b2b-field-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }

            .webyaz-b2b-field label {
                display: block;
                margin-bottom: 5px;
                font-weight: 500;
            }

            .webyaz-b2b-field input,
            .webyaz-b2b-field textarea {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 6px;
                font-size: 14px;
            }

            /* Bayi Ol CTA - Giriş Sayfası */
            .webyaz-b2b-dealer-cta {
                background: linear-gradient(135deg, #1a237e 0%, #283593 50%, #3949ab 100%);
                border-radius: 16px;
                padding: 28px 24px;
                margin-top: 24px;
                text-align: center;
                color: #fff;
                position: relative;
                overflow: hidden;
                box-shadow: 0 8px 32px rgba(26, 35, 126, 0.25);
            }

            .webyaz-b2b-dealer-cta::before {
                content: '';
                position: absolute;
                top: -50%;
                right: -30%;
                width: 200px;
                height: 200px;
                background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
                border-radius: 50%;
            }

            .webyaz-b2b-dealer-cta-icon {
                font-size: 40px;
                margin-bottom: 8px;
                filter: drop-shadow(0 2px 8px rgba(0,0,0,0.2));
            }

            .webyaz-b2b-dealer-cta h3 {
                margin: 0 0 8px;
                font-size: 20px;
                font-weight: 700;
                color: #fff;
                letter-spacing: 0.5px;
            }

            .webyaz-b2b-dealer-cta > p {
                margin: 0 0 16px;
                font-size: 14px;
                opacity: 0.9;
                line-height: 1.6;
                color: #fff;
            }

            .webyaz-b2b-dealer-cta-features {
                display: flex;
                justify-content: center;
                gap: 16px;
                flex-wrap: wrap;
                margin-bottom: 16px;
            }

            .webyaz-b2b-dealer-cta-features span {
                background: rgba(255,255,255,0.15);
                padding: 6px 14px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                letter-spacing: 0.3px;
                backdrop-filter: blur(4px);
                border: 1px solid rgba(255,255,255,0.1);
                transition: all 0.3s ease;
            }

            .webyaz-b2b-dealer-cta-features span:hover {
                background: rgba(255,255,255,0.25);
                transform: translateY(-1px);
            }

            .webyaz-b2b-dealer-cta-hint {
                margin: 0;
                font-size: 12px;
                opacity: 0.75;
                color: #fff;
                padding-top: 12px;
                border-top: 1px solid rgba(255,255,255,0.15);
            }

            .webyaz-b2b-dealer-cta-hint strong {
                color: #fff;
            }

            /* Kayıt formu altı hint */
            .webyaz-b2b-register-hint {
                display: flex;
                align-items: center;
                gap: 10px;
                background: linear-gradient(135deg, #e8eaf6, #c5cae9);
                padding: 14px 18px;
                border-radius: 12px;
                margin-top: 18px;
                font-size: 13px;
                color: #283593;
                border: 1px solid #9fa8da;
                line-height: 1.5;
            }

            .webyaz-b2b-register-hint-icon {
                font-size: 24px;
                flex-shrink: 0;
            }

            @media (max-width:600px) {
                .webyaz-b2b-field-grid {
                    grid-template-columns: 1fr;
                }

                .webyaz-b2b-packages {
                    grid-template-columns: 1fr;
                }

                .webyaz-b2b-pkg-inner {
                    padding: 18px 14px;
                }

                .webyaz-b2b-pkg-icon {
                    font-size: 32px;
                }

                .webyaz-b2b-pkg-price {
                    font-size: 18px;
                }

                .webyaz-b2b-package-banner {
                    flex-direction: column;
                    text-align: center;
                    gap: 8px;
                }

                .webyaz-b2b-dealer-cta {
                    padding: 22px 18px;
                }

                .webyaz-b2b-dealer-cta-features {
                    flex-direction: column;
                    align-items: center;
                    gap: 8px;
                }

                .webyaz-b2b-register-hint {
                    flex-direction: column;
                    text-align: center;
                }
            }
        </style>
<?php
    }
}

new Webyaz_B2B();
