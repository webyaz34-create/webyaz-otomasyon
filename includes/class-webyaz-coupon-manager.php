<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('WooCommerce')) return;

class Webyaz_Coupon_Manager {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('wp_ajax_webyaz_generate_coupons', array($this, 'ajax_generate'));
        add_action('wp_ajax_webyaz_export_coupons', array($this, 'ajax_export'));
        add_action('wp_ajax_webyaz_delete_expired_coupons', array($this, 'ajax_delete_expired'));
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Kupon Yonetici', 'Kupon Yonetici', 'manage_options', 'webyaz-coupon-manager', array($this, 'render_admin'));
    }

    /* Toplu kupon oluştur */
    public function ajax_generate() {
        if (!current_user_can('manage_options')) wp_send_json_error('Yetki yok.');
        check_ajax_referer('webyaz_nonce', 'nonce');

        $prefix = sanitize_text_field($_POST['prefix'] ?? 'WY');
        $count = min(500, absint($_POST['count'] ?? 10));
        $type = sanitize_text_field($_POST['discount_type'] ?? 'fixed_cart');
        $amount = floatval($_POST['amount'] ?? 0);
        $usage_limit = absint($_POST['usage_limit'] ?? 1);
        $min_spend = floatval($_POST['min_spend'] ?? 0);
        $expiry_days = absint($_POST['expiry_days'] ?? 30);
        $free_shipping = ($_POST['free_shipping'] ?? '0') === '1';
        $categories = array_map('absint', (array)($_POST['categories'] ?? array()));
        $products = array_map('absint', (array)($_POST['products'] ?? array()));

        if ($amount <= 0 || $count <= 0) {
            wp_send_json_error('Geçersiz tutar veya adet.');
        }

        $generated = array();
        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper($prefix . '-' . wp_generate_password(6, false));

            $coupon = new WC_Coupon();
            $coupon->set_code($code);
            $coupon->set_discount_type($type);
            $coupon->set_amount($amount);
            $coupon->set_usage_limit($usage_limit);
            if ($min_spend > 0) $coupon->set_minimum_amount($min_spend);
            if ($expiry_days > 0) $coupon->set_date_expires(strtotime('+' . $expiry_days . ' days'));
            if ($free_shipping) $coupon->set_free_shipping(true);
            if (!empty($categories)) $coupon->set_product_categories($categories);
            if (!empty($products)) $coupon->set_product_ids($products);
            $coupon->set_individual_use(true);
            $coupon->save();

            $generated[] = $code;
        }

        // Son oluşturulan toplu işlem kaydı
        update_option('_webyaz_last_bulk_coupons', array(
            'codes' => $generated,
            'count' => count($generated),
            'type' => $type,
            'amount' => $amount,
            'date' => current_time('mysql'),
        ));

        wp_send_json_success(array(
            'count' => count($generated),
            'codes' => $generated,
        ));
    }

    /* CSV dışa aktarma */
    public function ajax_export() {
        if (!current_user_can('manage_options')) wp_die('Yetki yok.');
        check_ajax_referer('webyaz_coupon_nonce', 'nonce');

        $filter = sanitize_text_field($_GET['filter'] ?? 'all');

        $args = array(
            'post_type' => 'shop_coupon',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        );

        if ($filter === 'unused') {
            $args['meta_query'] = array(
                array('key' => 'usage_count', 'value' => '0', 'compare' => '='),
            );
        } elseif ($filter === 'expired') {
            $args['meta_query'] = array(
                array('key' => 'date_expires', 'value' => time(), 'compare' => '<', 'type' => 'NUMERIC'),
            );
        }

        $coupons = get_posts($args);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="kuponlar-' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
        fputcsv($output, array('Kupon Kodu', 'Tip', 'Tutar', 'Kullanım', 'Min Harcama', 'Son Kullanma', 'Durum'), ';');

        $type_labels = array('fixed_cart' => 'Sabit TL', 'percent' => 'Yüzde %', 'fixed_product' => 'Ürün Sabit TL');
        foreach ($coupons as $c) {
            $coupon = new WC_Coupon($c->ID);
            $expires = $coupon->get_date_expires();
            $expired = $expires && $expires->getTimestamp() < time();
            fputcsv($output, array(
                $coupon->get_code(),
                $type_labels[$coupon->get_discount_type()] ?? $coupon->get_discount_type(),
                $coupon->get_amount(),
                $coupon->get_usage_count() . '/' . ($coupon->get_usage_limit() ?: '∞'),
                $coupon->get_minimum_amount() ?: '-',
                $expires ? $expires->date_i18n('d.m.Y') : '-',
                $expired ? 'Süresi Dolmuş' : 'Aktif',
            ), ';');
        }
        fclose($output);
        exit;
    }

    /* Süresi dolmuş kuponları sil */
    public function ajax_delete_expired() {
        if (!current_user_can('manage_options')) wp_send_json_error('Yetki yok.');
        check_ajax_referer('webyaz_nonce', 'nonce');

        $expired = get_posts(array(
            'post_type' => 'shop_coupon',
            'posts_per_page' => -1,
            'meta_query' => array(
                array('key' => 'date_expires', 'value' => time(), 'compare' => '<', 'type' => 'NUMERIC'),
            ),
        ));

        $deleted = 0;
        foreach ($expired as $c) {
            wp_delete_post($c->ID, true);
            $deleted++;
        }

        wp_send_json_success(array('deleted' => $deleted));
    }

    public function render_admin() {
        // Kupon istatistikleri
        $all_coupons = get_posts(array('post_type' => 'shop_coupon', 'posts_per_page' => -1, 'post_status' => 'publish'));
        $total = count($all_coupons);
        $used = 0;
        $expired = 0;
        $total_discount = 0;

        $coupon_list = array();
        foreach ($all_coupons as $c) {
            $coupon = new WC_Coupon($c->ID);
            $usage = $coupon->get_usage_count();
            if ($usage > 0) $used++;

            $expires = $coupon->get_date_expires();
            $is_expired = $expires && $expires->getTimestamp() < time();
            if ($is_expired) $expired++;

            $total_discount += $coupon->get_amount() * $usage;

            $coupon_list[] = array(
                'id' => $c->ID,
                'code' => $coupon->get_code(),
                'type' => $coupon->get_discount_type(),
                'amount' => $coupon->get_amount(),
                'usage' => $usage,
                'limit' => $coupon->get_usage_limit(),
                'min_spend' => $coupon->get_minimum_amount(),
                'expires' => $expires ? $expires->date_i18n('d.m.Y') : '-',
                'expired' => $is_expired,
            );
        }
        $coupon_list = array_slice($coupon_list, 0, 50);
        $type_labels = array('fixed_cart' => 'Sabit TL', 'percent' => 'Yüzde %', 'fixed_product' => 'Ürün TL');
        $nonce = wp_create_nonce('webyaz_nonce');
        $export_nonce = wp_create_nonce('webyaz_coupon_nonce');

        $categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>🎟️ Kupon Yöneticisi</h1><p>Toplu kupon oluşturun, istatistikleri takip edin, CSV dışa aktarın</p></div>

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:25px;">
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #9c27b0;">
                    <div style="font-size:24px;font-weight:700;color:#9c27b0;"><?php echo $total; ?></div>
                    <div style="font-size:12px;color:#666;">Toplam Kupon</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #4caf50;">
                    <div style="font-size:24px;font-weight:700;color:#4caf50;"><?php echo $used; ?></div>
                    <div style="font-size:12px;color:#666;">Kullanılmış</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #f44336;">
                    <div style="font-size:24px;font-weight:700;color:#f44336;"><?php echo $expired; ?></div>
                    <div style="font-size:12px;color:#666;">Süresi Dolmuş</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #2196f3;">
                    <div style="font-size:24px;font-weight:700;color:#2196f3;"><?php echo number_format($total_discount, 0, ',', '.'); ?> ₺</div>
                    <div style="font-size:12px;color:#666;">Toplam İndirim</div>
                </div>
            </div>

            <!-- Toplu Oluşturma -->
            <div class="webyaz-settings-section">
                <h2 class="webyaz-section-title">⚡ Toplu Kupon Oluştur</h2>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Önek (Prefix)</label>
                        <input type="text" id="wyCpnPrefix" value="WY" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Adet</label>
                        <input type="number" id="wyCpnCount" value="10" min="1" max="500" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">İndirim Tipi</label>
                        <select id="wyCpnType" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;">
                            <option value="fixed_cart">Sabit TL</option>
                            <option value="percent">Yüzde %</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Tutar</label>
                        <input type="number" id="wyCpnAmount" value="50" step="0.01" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Min Harcama (TL)</label>
                        <input type="number" id="wyCpnMinSpend" value="0" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Kullanım Limiti</label>
                        <input type="number" id="wyCpnLimit" value="1" min="1" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Geçerlilik (Gün)</label>
                        <input type="number" id="wyCpnDays" value="30" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Ücretsiz Kargo</label>
                        <select id="wyCpnFreeShip" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;">
                            <option value="0">Hayır</option>
                            <option value="1">Evet</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top:16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <button type="button" id="wyCpnGenBtn" onclick="wyCpnGenerate()" style="background:linear-gradient(135deg,#9c27b0,#7b1fa2);color:#fff;border:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">🎟️ Kuponları Oluştur</button>
                    <span id="wyCpnResult" style="font-size:13px;color:#666;"></span>
                </div>
            </div>

            <!-- İşlem Butonları -->
            <div style="display:flex;gap:10px;margin:20px 0;flex-wrap:wrap;">
                <a href="<?php echo admin_url('admin-ajax.php?action=webyaz_export_coupons&nonce=' . $export_nonce . '&filter=all'); ?>" class="button" style="display:inline-flex;align-items:center;gap:6px;">📥 Tümünü CSV İndir</a>
                <a href="<?php echo admin_url('admin-ajax.php?action=webyaz_export_coupons&nonce=' . $export_nonce . '&filter=unused'); ?>" class="button" style="display:inline-flex;align-items:center;gap:6px;">📥 Kullanılmamışları İndir</a>
                <button type="button" onclick="wyCpnDeleteExpired()" class="button" style="color:#d32f2f;display:inline-flex;align-items:center;gap:6px;">🗑️ Süresi Dolmuşları Sil (<?php echo $expired; ?>)</button>
            </div>

            <!-- Kupon Listesi -->
            <?php if (!empty($coupon_list)): ?>
            <div class="webyaz-settings-section">
                <h2 class="webyaz-section-title">Kupon Listesi (Son 50)</h2>
                <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead><tr style="background:#f8f9fa;">
                        <th style="padding:10px;text-align:left;font-size:12px;color:#666;">Kod</th>
                        <th style="padding:10px;text-align:left;font-size:12px;color:#666;">Tip</th>
                        <th style="padding:10px;text-align:right;font-size:12px;color:#666;">Tutar</th>
                        <th style="padding:10px;text-align:center;font-size:12px;color:#666;">Kullanım</th>
                        <th style="padding:10px;text-align:right;font-size:12px;color:#666;">Min Harcama</th>
                        <th style="padding:10px;text-align:left;font-size:12px;color:#666;">Son Kullanma</th>
                        <th style="padding:10px;text-align:center;font-size:12px;color:#666;">Durum</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($coupon_list as $cp): ?>
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:10px;"><code style="background:#f0f0f0;padding:3px 8px;border-radius:4px;font-weight:600;letter-spacing:1px;"><?php echo esc_html($cp['code']); ?></code></td>
                            <td style="padding:10px;font-size:12px;"><?php echo $type_labels[$cp['type']] ?? $cp['type']; ?></td>
                            <td style="padding:10px;text-align:right;font-weight:600;"><?php echo $cp['amount']; ?><?php echo $cp['type'] === 'percent' ? '%' : ' ₺'; ?></td>
                            <td style="padding:10px;text-align:center;font-size:12px;"><?php echo $cp['usage']; ?>/<?php echo $cp['limit'] ?: '∞'; ?></td>
                            <td style="padding:10px;text-align:right;font-size:12px;color:#666;"><?php echo $cp['min_spend'] ? $cp['min_spend'] . ' ₺' : '-'; ?></td>
                            <td style="padding:10px;font-size:12px;"><?php echo $cp['expires']; ?></td>
                            <td style="padding:10px;text-align:center;">
                                <?php if ($cp['expired']): ?>
                                    <span style="background:#f4433622;color:#f44336;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:600;">Dolmuş</span>
                                <?php else: ?>
                                    <span style="background:#4caf5022;color:#4caf50;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:600;">Aktif</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <script>
        function wyCpnGenerate() {
            var btn = document.getElementById('wyCpnGenBtn');
            btn.disabled = true;
            btn.textContent = 'Oluşturuluyor...';
            jQuery.post(ajaxurl, {
                action: 'webyaz_generate_coupons',
                nonce: '<?php echo $nonce; ?>',
                prefix: document.getElementById('wyCpnPrefix').value,
                count: document.getElementById('wyCpnCount').value,
                discount_type: document.getElementById('wyCpnType').value,
                amount: document.getElementById('wyCpnAmount').value,
                usage_limit: document.getElementById('wyCpnLimit').value,
                min_spend: document.getElementById('wyCpnMinSpend').value,
                expiry_days: document.getElementById('wyCpnDays').value,
                free_shipping: document.getElementById('wyCpnFreeShip').value,
            }, function(r) {
                btn.disabled = false;
                btn.textContent = '🎟️ Kuponları Oluştur';
                if (r.success) {
                    document.getElementById('wyCpnResult').innerHTML = '✅ <strong>' + r.data.count + '</strong> kupon oluşturuldu!';
                    setTimeout(function(){ location.reload(); }, 1500);
                } else {
                    document.getElementById('wyCpnResult').innerHTML = '❌ ' + r.data;
                }
            });
        }

        function wyCpnDeleteExpired() {
            if (!confirm('Süresi dolmuş tüm kuponlar silinecek. Emin misiniz?')) return;
            jQuery.post(ajaxurl, {
                action: 'webyaz_delete_expired_coupons',
                nonce: '<?php echo $nonce; ?>'
            }, function(r) {
                if (r.success) {
                    alert(r.data.deleted + ' kupon silindi.');
                    location.reload();
                }
            });
        }
        </script>
        <?php
    }
}

new Webyaz_Coupon_Manager();
