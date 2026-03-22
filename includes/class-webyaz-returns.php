<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('WooCommerce')) return;

class Webyaz_Returns {

    const POST_TYPE = 'webyaz_return';

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('woocommerce_order_details_after_order_table', array($this, 'show_return_button'));
        add_action('wp_ajax_webyaz_submit_return', array($this, 'handle_submit'));
        add_action('wp_ajax_nopriv_webyaz_submit_return', array($this, 'handle_submit'));
        add_action('wp_ajax_webyaz_update_return_status', array($this, 'update_status'));
        add_action('woocommerce_account_webyaz-returns_endpoint', array($this, 'my_account_returns'));
        add_action('init', array($this, 'add_endpoint'));
        add_filter('woocommerce_account_menu_items', array($this, 'add_menu_item'));
        add_filter('query_vars', array($this, 'add_query_var'));
    }

    public function register_post_type() {
        register_post_type(self::POST_TYPE, array(
            'labels' => array('name' => 'İade Talepleri', 'singular_name' => 'İade Talebi'),
            'public' => false,
            'show_ui' => false,
            'supports' => array('title'),
        ));
    }

    public function add_endpoint() {
        add_rewrite_endpoint('webyaz-returns', EP_ROOT | EP_PAGES);
        // İlk yüklemede rewrite kurallarını yenile (bir kerelik)
        if (!get_transient('webyaz_returns_flushed')) {
            flush_rewrite_rules();
            set_transient('webyaz_returns_flushed', '1', YEAR_IN_SECONDS);
        }
    }

    public function add_query_var($vars) {
        $vars[] = 'webyaz-returns';
        return $vars;
    }

    public function add_menu_item($items) {
        $new_items = array();
        foreach ($items as $key => $label) {
            $new_items[$key] = $label;
            if ($key === 'orders') {
                $new_items['webyaz-returns'] = 'İade Taleplerim';
            }
        }
        return $new_items;
    }

    /* Sipariş detay sayfasında İade butonu */
    public function show_return_button($order) {
        if (!$order || !in_array($order->get_status(), array('completed', 'processing'))) return;

        // 14 gün limitini kontrol et
        $order_date = $order->get_date_completed() ? $order->get_date_completed() : $order->get_date_created();
        $days_passed = (time() - $order_date->getTimestamp()) / DAY_IN_SECONDS;
        if ($days_passed > 14) return;

        $order_id = $order->get_id();
        // Zaten iade talebi var mı?
        $existing = get_posts(array(
            'post_type' => self::POST_TYPE,
            'meta_key' => '_wy_return_order_id',
            'meta_value' => $order_id,
            'posts_per_page' => 1,
        ));

        if (!empty($existing)) {
            $status = get_post_meta($existing[0]->ID, '_wy_return_status', true);
            $status_labels = array('pending' => 'Bekliyor', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi', 'completed' => 'Tamamlandı');
            $status_colors = array('pending' => '#ff9800', 'approved' => '#4caf50', 'rejected' => '#f44336', 'completed' => '#2196f3');
            echo '<div style="background:#f8f9fa;border-radius:10px;padding:16px 20px;margin-top:20px;border-left:4px solid ' . ($status_colors[$status] ?? '#999') . ';">';
            echo '<strong>📦 İade Durumu:</strong> <span style="color:' . ($status_colors[$status] ?? '#999') . ';font-weight:700;">' . ($status_labels[$status] ?? $status) . '</span>';
            $note = get_post_meta($existing[0]->ID, '_wy_return_admin_note', true);
            if ($note) echo '<p style="margin:8px 0 0;font-size:13px;color:#666;">' . esc_html($note) . '</p>';
            echo '</div>';
            return;
        }
        ?>
        <div id="wyReturnSection" style="margin-top:20px;">
            <button type="button" onclick="document.getElementById('wyReturnForm').style.display='block';this.style.display='none';" style="background:#f44336;color:#fff;border:none;padding:12px 24px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">📦 İade Talebi Oluştur</button>
            <div id="wyReturnForm" style="display:none;background:#f8f9fa;border-radius:10px;padding:20px;margin-top:12px;border:1px solid #e0e0e0;">
                <h3 style="margin:0 0 15px;font-size:16px;color:#333;">İade Talebi</h3>
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:5px;">İade Sebebi</label>
                    <select id="wyReturnReason" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
                        <option value="defective">Ürün kusurlu / hasarlı</option>
                        <option value="wrong_product">Yanlış ürün gönderildi</option>
                        <option value="not_as_described">Ürün açıklamaya uymuyor</option>
                        <option value="changed_mind">Fikir değişikliği</option>
                        <option value="size_issue">Beden / boyut uyumsuzluğu</option>
                        <option value="other">Diğer</option>
                    </select>
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:5px;">Açıklama</label>
                    <textarea id="wyReturnNote" rows="3" placeholder="İade sebebinizi detaylı yazın..." style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;resize:vertical;box-sizing:border-box;"></textarea>
                </div>
                <button type="button" id="wyReturnSubmit" style="background:#f44336;color:#fff;border:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">Talebi Gönder</button>
                <div id="wyReturnMsg" style="display:none;margin-top:10px;padding:10px;border-radius:6px;"></div>
            </div>
        </div>
        <script>
        document.getElementById('wyReturnSubmit').addEventListener('click', function(){
            var btn = this;
            btn.disabled = true;
            btn.textContent = 'Gönderiliyor...';
            jQuery.post(webyaz_ajax.ajax_url, {
                action: 'webyaz_submit_return',
                nonce: webyaz_ajax.nonce,
                order_id: <?php echo $order_id; ?>,
                reason: document.getElementById('wyReturnReason').value,
                note: document.getElementById('wyReturnNote').value
            }, function(r){
                var msg = document.getElementById('wyReturnMsg');
                msg.style.display = 'block';
                if(r.success) {
                    msg.style.background = '#e8f5e9';
                    msg.style.color = '#2e7d32';
                    msg.innerHTML = '✅ ' + r.data;
                    btn.style.display = 'none';
                } else {
                    msg.style.background = '#fde8e8';
                    msg.style.color = '#c62828';
                    msg.innerHTML = '❌ ' + r.data;
                    btn.disabled = false;
                    btn.textContent = 'Talebi Gönder';
                }
            });
        });
        </script>
        <?php
    }

    /* AJAX: İade talebi gönder */
    public function handle_submit() {
        check_ajax_referer('webyaz_nonce', 'nonce');

        $order_id = absint($_POST['order_id'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        $note = sanitize_textarea_field($_POST['note'] ?? '');
        $user_id = get_current_user_id();

        if (!$order_id || !$user_id) {
            wp_send_json_error('Geçersiz istek.');
        }

        $order = wc_get_order($order_id);
        if (!$order || intval($order->get_customer_id()) !== $user_id) {
            wp_send_json_error('Bu sipariş size ait değil.');
        }

        $reason_labels = array(
            'defective' => 'Kusurlu/Hasarlı',
            'wrong_product' => 'Yanlış Ürün',
            'not_as_described' => 'Açıklamaya Uymuyor',
            'changed_mind' => 'Fikir Değişikliği',
            'size_issue' => 'Beden Uyumsuz',
            'other' => 'Diğer',
        );

        $return_id = wp_insert_post(array(
            'post_type' => self::POST_TYPE,
            'post_title' => 'İade #' . $order_id . ' - ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'post_status' => 'publish',
        ));

        if ($return_id && !is_wp_error($return_id)) {
            update_post_meta($return_id, '_wy_return_order_id', $order_id);
            update_post_meta($return_id, '_wy_return_user_id', $user_id);
            update_post_meta($return_id, '_wy_return_reason', $reason);
            update_post_meta($return_id, '_wy_return_reason_label', $reason_labels[$reason] ?? $reason);
            update_post_meta($return_id, '_wy_return_note', $note);
            update_post_meta($return_id, '_wy_return_status', 'pending');
            update_post_meta($return_id, '_wy_return_date', current_time('mysql'));

            // Admin'e e-posta bildirim
            $admin_email = get_option('admin_email');
            $site_name = get_bloginfo('name');
            $subject = $site_name . ' - Yeni İade Talebi #' . $order_id;
            $message = '<h2>Yeni İade Talebi</h2>';
            $message .= '<p><strong>Sipariş:</strong> #' . $order_id . '</p>';
            $message .= '<p><strong>Müşteri:</strong> ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . '</p>';
            $message .= '<p><strong>Sebep:</strong> ' . ($reason_labels[$reason] ?? $reason) . '</p>';
            $message .= '<p><strong>Not:</strong> ' . esc_html($note) . '</p>';
            $message .= '<p><a href="' . admin_url('admin.php?page=webyaz-returns') . '">İade Taleplerini Görüntüle</a></p>';
            wp_mail($admin_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));

            wp_send_json_success('İade talebiniz oluşturuldu. En kısa sürede değerlendirilerek size bilgi verilecektir.');
        } else {
            wp_send_json_error('Talep oluşturulurken hata oluştu.');
        }
    }

    /* Admin: Durum güncelle */
    public function update_status() {
        if (!current_user_can('manage_options')) wp_send_json_error('Yetki yok.');
        check_ajax_referer('webyaz_nonce', 'nonce');

        $return_id = absint($_POST['return_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $admin_note = sanitize_textarea_field($_POST['admin_note'] ?? '');

        if (!$return_id || !in_array($status, array('pending', 'approved', 'rejected', 'completed'))) {
            wp_send_json_error('Geçersiz parametre.');
        }

        update_post_meta($return_id, '_wy_return_status', $status);
        if ($admin_note) {
            update_post_meta($return_id, '_wy_return_admin_note', $admin_note);
        }

        // Müşteriye e-posta
        $user_id = get_post_meta($return_id, '_wy_return_user_id', true);
        $order_id = get_post_meta($return_id, '_wy_return_order_id', true);
        $user = get_userdata($user_id);
        if ($user) {
            $status_labels = array('pending' => 'Bekliyor', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi', 'completed' => 'Tamamlandı');
            $site_name = get_bloginfo('name');
            $subject = $site_name . ' - İade Talebiniz Güncellendi #' . $order_id;
            $msg = '<h2>İade Talebi Güncellendi</h2>';
            $msg .= '<p><strong>Sipariş:</strong> #' . $order_id . '</p>';
            $msg .= '<p><strong>Durum:</strong> ' . ($status_labels[$status] ?? $status) . '</p>';
            if ($admin_note) $msg .= '<p><strong>Not:</strong> ' . esc_html($admin_note) . '</p>';
            wp_mail($user->user_email, $subject, $msg, array('Content-Type: text/html; charset=UTF-8'));
        }

        wp_send_json_success('Durum güncellendi.');
    }

    /* Hesabım: İade listesi */
    public function my_account_returns() {
        $user_id = get_current_user_id();
        $returns = get_posts(array(
            'post_type' => self::POST_TYPE,
            'meta_key' => '_wy_return_user_id',
            'meta_value' => $user_id,
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        $status_labels = array('pending' => 'Bekliyor', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi', 'completed' => 'Tamamlandı');
        $status_colors = array('pending' => '#ff9800', 'approved' => '#4caf50', 'rejected' => '#f44336', 'completed' => '#2196f3');

        if (empty($returns)) {
            echo '<p style="color:#666;">Henüz iade talebiniz bulunmamaktadır.</p>';
            return;
        }

        echo '<table class="woocommerce-orders-table" style="width:100%;border-collapse:collapse;">';
        echo '<thead><tr style="background:#f8f9fa;"><th style="padding:12px;text-align:left;">Sipariş</th><th style="padding:12px;text-align:left;">Sebep</th><th style="padding:12px;text-align:left;">Tarih</th><th style="padding:12px;text-align:left;">Durum</th></tr></thead><tbody>';
        foreach ($returns as $r) {
            $order_id = get_post_meta($r->ID, '_wy_return_order_id', true);
            $reason = get_post_meta($r->ID, '_wy_return_reason_label', true);
            $status = get_post_meta($r->ID, '_wy_return_status', true);
            $date = get_post_meta($r->ID, '_wy_return_date', true);
            $color = $status_colors[$status] ?? '#999';
            echo '<tr style="border-bottom:1px solid #eee;">';
            echo '<td style="padding:12px;"><a href="' . esc_url(wc_get_endpoint_url('view-order', $order_id)) . '">#' . esc_html($order_id) . '</a></td>';
            echo '<td style="padding:12px;">' . esc_html($reason) . '</td>';
            echo '<td style="padding:12px;">' . esc_html(date_i18n('d.m.Y', strtotime($date))) . '</td>';
            echo '<td style="padding:12px;"><span style="background:' . $color . '22;color:' . $color . ';padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;">' . ($status_labels[$status] ?? $status) . '</span></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /* Admin Sayfası */
    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Iade Talepleri', 'Iade Talepleri', 'manage_options', 'webyaz-returns', array($this, 'render_admin'));
    }

    public function render_admin() {
        $returns = get_posts(array(
            'post_type' => self::POST_TYPE,
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        $status_labels = array('pending' => 'Bekliyor', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi', 'completed' => 'Tamamlandı');
        $status_colors = array('pending' => '#ff9800', 'approved' => '#4caf50', 'rejected' => '#f44336', 'completed' => '#2196f3');

        $stats = array('pending' => 0, 'approved' => 0, 'rejected' => 0, 'completed' => 0);
        foreach ($returns as $r) {
            $s = get_post_meta($r->ID, '_wy_return_status', true);
            if (isset($stats[$s])) $stats[$s]++;
        }
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>📦 İade Yönetimi</h1><p>Müşteri iade taleplerini yönetin</p></div>

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:25px;">
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #ff9800;">
                    <div style="font-size:24px;font-weight:700;color:#ff9800;"><?php echo $stats['pending']; ?></div>
                    <div style="font-size:12px;color:#666;">Bekliyor</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #4caf50;">
                    <div style="font-size:24px;font-weight:700;color:#4caf50;"><?php echo $stats['approved']; ?></div>
                    <div style="font-size:12px;color:#666;">Onaylı</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #f44336;">
                    <div style="font-size:24px;font-weight:700;color:#f44336;"><?php echo $stats['rejected']; ?></div>
                    <div style="font-size:12px;color:#666;">Reddedildi</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #2196f3;">
                    <div style="font-size:24px;font-weight:700;color:#2196f3;"><?php echo $stats['completed']; ?></div>
                    <div style="font-size:12px;color:#666;">Tamamlandı</div>
                </div>
            </div>

            <?php if (empty($returns)): ?>
                <div style="background:#f8f9fa;padding:40px;text-align:center;border-radius:12px;color:#999;">
                    <span style="font-size:48px;">📦</span>
                    <p style="font-size:16px;margin-top:12px;">Henüz iade talebi bulunmuyor.</p>
                </div>
            <?php else: ?>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;overflow:hidden;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f8f9fa;">
                                <th style="padding:14px 16px;text-align:left;font-size:13px;color:#666;">Sipariş</th>
                                <th style="padding:14px 16px;text-align:left;font-size:13px;color:#666;">Müşteri</th>
                                <th style="padding:14px 16px;text-align:left;font-size:13px;color:#666;">Sebep</th>
                                <th style="padding:14px 16px;text-align:left;font-size:13px;color:#666;">Not</th>
                                <th style="padding:14px 16px;text-align:left;font-size:13px;color:#666;">Tarih</th>
                                <th style="padding:14px 16px;text-align:left;font-size:13px;color:#666;">Durum</th>
                                <th style="padding:14px 16px;text-align:left;font-size:13px;color:#666;">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($returns as $r):
                                $order_id = get_post_meta($r->ID, '_wy_return_order_id', true);
                                $reason = get_post_meta($r->ID, '_wy_return_reason_label', true);
                                $note = get_post_meta($r->ID, '_wy_return_note', true);
                                $status = get_post_meta($r->ID, '_wy_return_status', true);
                                $date = get_post_meta($r->ID, '_wy_return_date', true);
                                $admin_note = get_post_meta($r->ID, '_wy_return_admin_note', true);
                                $color = $status_colors[$status] ?? '#999';
                                $order = wc_get_order($order_id);
                                $customer = $order ? $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() : '-';
                            ?>
                            <tr style="border-bottom:1px solid #f0f0f0;" id="wyReturn<?php echo $r->ID; ?>">
                                <td style="padding:14px 16px;"><a href="<?php echo admin_url('post.php?post=' . $order_id . '&action=edit'); ?>" style="font-weight:600;">#<?php echo esc_html($order_id); ?></a></td>
                                <td style="padding:14px 16px;font-size:13px;"><?php echo esc_html($customer); ?></td>
                                <td style="padding:14px 16px;font-size:13px;"><?php echo esc_html($reason); ?></td>
                                <td style="padding:14px 16px;font-size:12px;color:#666;max-width:150px;overflow:hidden;text-overflow:ellipsis;" title="<?php echo esc_attr($note); ?>"><?php echo esc_html(mb_substr($note, 0, 40)); ?></td>
                                <td style="padding:14px 16px;font-size:12px;color:#999;"><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($date))); ?></td>
                                <td style="padding:14px 16px;">
                                    <span style="background:<?php echo $color; ?>22;color:<?php echo $color; ?>;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;"><?php echo $status_labels[$status] ?? $status; ?></span>
                                </td>
                                <td style="padding:14px 16px;">
                                    <select onchange="wyUpdateReturn(<?php echo $r->ID; ?>, this.value)" style="padding:6px 10px;border-radius:6px;border:1px solid #ddd;font-size:12px;">
                                        <?php foreach ($status_labels as $sk => $sl): ?>
                                            <option value="<?php echo $sk; ?>" <?php selected($status, $sk); ?>><?php echo $sl; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <script>
        function wyUpdateReturn(id, status) {
            var note = prompt('Müşteriye not eklemek ister misiniz? (Boş bırakabilirsiniz)', '');
            jQuery.post(ajaxurl, {
                action: 'webyaz_update_return_status',
                nonce: '<?php echo wp_create_nonce('webyaz_nonce'); ?>',
                return_id: id,
                status: status,
                admin_note: note || ''
            }, function(r) {
                if (r.success) {
                    location.reload();
                } else {
                    alert('Hata: ' + r.data);
                }
            });
        }
        </script>
        <?php
    }
}

new Webyaz_Returns();
