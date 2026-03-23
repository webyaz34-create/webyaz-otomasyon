<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Support {

    const POST_TYPE = 'webyaz_support';

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'add_endpoint'));
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_styles'));

        // WooCommerce Hesabım
        add_filter('woocommerce_account_menu_items', array($this, 'add_menu_item'));
        add_filter('query_vars', array($this, 'add_query_var'));
        add_action('woocommerce_account_webyaz-support_endpoint', array($this, 'my_account_page'));

        // AJAX
        add_action('wp_ajax_webyaz_submit_support', array($this, 'ajax_submit'));
        add_action('wp_ajax_webyaz_reply_support', array($this, 'ajax_reply'));
        add_action('wp_ajax_webyaz_update_support_status', array($this, 'ajax_update_status'));
    }

    /* ─── Post Type ─── */
    public function register_post_type() {
        register_post_type(self::POST_TYPE, array(
            'labels' => array('name' => 'Destek Talepleri', 'singular_name' => 'Destek Talebi'),
            'public' => false,
            'show_ui' => false,
            'supports' => array('title'),
        ));
    }

    /* ─── Endpoint ─── */
    public function add_endpoint() {
        add_rewrite_endpoint('webyaz-support', EP_ROOT | EP_PAGES);
        if (!get_transient('webyaz_support_flushed')) {
            flush_rewrite_rules();
            set_transient('webyaz_support_flushed', '1', YEAR_IN_SECONDS);
        }
    }

    public function add_query_var($vars) {
        $vars[] = 'webyaz-support';
        return $vars;
    }

    public function add_menu_item($items) {
        $new = array();
        foreach ($items as $key => $label) {
            $new[$key] = $label;
            if ($key === 'orders') {
                $new['webyaz-support'] = 'Destek Taleplerim';
            }
        }
        return $new;
    }

    /* ─── Durum Yardımcıları ─── */
    private static function status_labels() {
        return array('open' => 'Açık', 'waiting' => 'Bekliyor', 'replied' => 'Cevaplandı', 'closed' => 'Kapatıldı');
    }

    private static function status_colors() {
        return array('open' => '#2196f3', 'waiting' => '#ff9800', 'replied' => '#4caf50', 'closed' => '#9e9e9e');
    }

    private static function priority_labels() {
        return array('normal' => 'Normal', 'urgent' => 'Acil');
    }

    /* ═══════════════════════════════════════════════════════
       MÜŞTERİ TARAFI — Hesabım → Destek Taleplerim
       ═══════════════════════════════════════════════════════ */
    public function my_account_page() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            echo '<p>Giriş yapmanız gerekiyor.</p>';
            return;
        }

        $ticket_id = isset($_GET['ticket']) ? absint($_GET['ticket']) : 0;

        if ($ticket_id) {
            $this->render_customer_ticket_detail($ticket_id, $user_id);
        } else {
            $this->render_customer_ticket_list($user_id);
        }
    }

    /* Müşteri: Talep Listesi */
    private function render_customer_ticket_list($user_id) {
        $tickets = get_posts(array(
            'post_type' => self::POST_TYPE,
            'meta_key' => '_wy_support_user_id',
            'meta_value' => $user_id,
            'posts_per_page' => 30,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        $sl = self::status_labels();
        $sc = self::status_colors();

        echo '<div style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">';
        echo '<h3 style="margin:0;font-size:18px;color:#333;">🎧 Destek Taleplerim</h3>';
        echo '<button type="button" id="wyNewTicketBtn" style="background:var(--webyaz-primary,#446084);color:#fff;border:none;padding:10px 22px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">+ Yeni Destek Oluştur</button>';
        echo '</div>';

        // Yeni talep formu
        echo '<div id="wyNewTicketForm" style="display:none;background:#f8f9fa;border:1px solid #e0e0e0;border-radius:10px;padding:20px;margin-bottom:20px;">';
        echo '<h4 style="margin:0 0 15px;font-size:15px;color:#333;">Yeni Destek Talebi</h4>';
        echo '<div style="margin-bottom:12px;"><label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:5px;">Konu</label>';
        echo '<input type="text" id="wySupportSubject" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;" placeholder="Destek talebinizin konusu"></div>';
        echo '<div style="margin-bottom:12px;"><label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:5px;">Öncelik</label>';
        echo '<select id="wySupportPriority" style="padding:10px;border:1px solid #ddd;border-radius:6px;"><option value="normal">Normal</option><option value="urgent">Acil</option></select></div>';
        echo '<div style="margin-bottom:12px;"><label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:5px;">Mesajınız</label>';
        echo '<textarea id="wySupportMessage" rows="4" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;resize:vertical;box-sizing:border-box;" placeholder="Sorununuzu veya sorunuzu detaylı olarak yazın..."></textarea></div>';
        echo '<div style="display:flex;gap:8px;align-items:center;">';
        echo '<button type="button" id="wySupportSubmit" style="background:var(--webyaz-primary,#446084);color:#fff;border:none;padding:10px 24px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">Gönder</button>';
        echo '<button type="button" onclick="document.getElementById(\'wyNewTicketForm\').style.display=\'none\';" style="background:#e0e0e0;color:#666;border:none;padding:10px 20px;border-radius:8px;font-size:13px;cursor:pointer;">İptal</button>';
        echo '</div>';
        echo '<div id="wySupportMsg" style="display:none;margin-top:10px;padding:10px;border-radius:6px;font-size:13px;"></div>';
        echo '</div>';

        if (empty($tickets)) {
            echo '<div style="background:#f8f9fa;padding:30px;text-align:center;border-radius:10px;color:#999;">';
            echo '<span style="font-size:36px;">🎧</span>';
            echo '<p style="margin:10px 0 0;font-size:14px;">Henüz destek talebiniz bulunmamaktadır.</p></div>';
        } else {
            echo '<table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e0e0e0;border-radius:10px;overflow:hidden;">';
            echo '<thead><tr style="background:#f8f9fa;"><th style="padding:12px 14px;text-align:left;font-size:13px;color:#666;">#</th><th style="padding:12px 14px;text-align:left;font-size:13px;color:#666;">Konu</th><th style="padding:12px 14px;text-align:left;font-size:13px;color:#666;">Tarih</th><th style="padding:12px 14px;text-align:left;font-size:13px;color:#666;">Durum</th></tr></thead><tbody>';
            foreach ($tickets as $t) {
                $status = get_post_meta($t->ID, '_wy_support_status', true);
                $date = get_post_meta($t->ID, '_wy_support_date', true);
                $color = $sc[$status] ?? '#999';
                $url = wc_get_endpoint_url('webyaz-support') . '?ticket=' . $t->ID;
                echo '<tr style="border-bottom:1px solid #f0f0f0;cursor:pointer;" onclick="location.href=\'' . esc_url($url) . '\'">';
                echo '<td style="padding:12px 14px;font-size:13px;color:#999;">' . $t->ID . '</td>';
                echo '<td style="padding:12px 14px;font-size:13px;font-weight:600;color:#333;">' . esc_html($t->post_title) . '</td>';
                echo '<td style="padding:12px 14px;font-size:12px;color:#999;">' . esc_html(date_i18n('d.m.Y H:i', strtotime($date))) . '</td>';
                echo '<td style="padding:12px 14px;"><span style="background:' . $color . '22;color:' . $color . ';padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;">' . ($sl[$status] ?? $status) . '</span></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        // JavaScript
        ?>
        <script>
        document.getElementById('wyNewTicketBtn').addEventListener('click', function(){
            document.getElementById('wyNewTicketForm').style.display = 'block';
            this.style.display = 'none';
        });
        document.getElementById('wySupportSubmit').addEventListener('click', function(){
            var btn = this;
            var subject = document.getElementById('wySupportSubject').value.trim();
            var message = document.getElementById('wySupportMessage').value.trim();
            var priority = document.getElementById('wySupportPriority').value;
            if (!subject || !message) {
                var msg = document.getElementById('wySupportMsg');
                msg.style.display = 'block'; msg.style.background = '#fde8e8'; msg.style.color = '#c62828';
                msg.textContent = 'Konu ve mesaj alanlarını doldurun.';
                return;
            }
            btn.disabled = true; btn.textContent = 'Gönderiliyor...';
            jQuery.post(webyaz_ajax.ajax_url, {
                action: 'webyaz_submit_support',
                nonce: webyaz_ajax.nonce,
                subject: subject,
                message: message,
                priority: priority
            }, function(r){
                var msg = document.getElementById('wySupportMsg');
                msg.style.display = 'block';
                if(r.success){
                    msg.style.background = '#e8f5e9'; msg.style.color = '#2e7d32';
                    msg.innerHTML = '✅ ' + r.data;
                    setTimeout(function(){ location.reload(); }, 1500);
                } else {
                    msg.style.background = '#fde8e8'; msg.style.color = '#c62828';
                    msg.innerHTML = '❌ ' + r.data;
                    btn.disabled = false; btn.textContent = 'Gönder';
                }
            });
        });
        </script>
        <?php
    }

    /* Müşteri: Talep Detayı (Mesaj Thread) */
    private function render_customer_ticket_detail($ticket_id, $user_id) {
        $ticket = get_post($ticket_id);
        if (!$ticket || $ticket->post_type !== self::POST_TYPE) {
            echo '<p style="color:#c62828;">Talep bulunamadı.</p>';
            return;
        }
        $ticket_user = get_post_meta($ticket_id, '_wy_support_user_id', true);
        if (intval($ticket_user) !== $user_id) {
            echo '<p style="color:#c62828;">Bu talebe erişim yetkiniz yok.</p>';
            return;
        }

        $status = get_post_meta($ticket_id, '_wy_support_status', true);
        $messages = get_post_meta($ticket_id, '_wy_support_messages', true);
        if (!is_array($messages)) $messages = array();
        $sl = self::status_labels();
        $sc = self::status_colors();
        $color = $sc[$status] ?? '#999';

        echo '<div style="margin-bottom:16px;">';
        echo '<a href="' . esc_url(wc_get_endpoint_url('webyaz-support')) . '" style="font-size:13px;color:var(--webyaz-primary,#446084);text-decoration:none;">← Tüm Talepler</a>';
        echo '</div>';

        echo '<div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px;margin-bottom:20px;">';
        echo '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:10px;">';
        echo '<h3 style="margin:0;font-size:16px;color:#333;">' . esc_html($ticket->post_title) . '</h3>';
        echo '<span style="background:' . $color . '22;color:' . $color . ';padding:4px 14px;border-radius:20px;font-size:12px;font-weight:600;">' . ($sl[$status] ?? $status) . '</span>';
        echo '</div>';
        echo '<div style="font-size:12px;color:#999;">Talep #' . $ticket_id . ' · ' . esc_html(date_i18n('d.m.Y H:i', strtotime(get_post_meta($ticket_id, '_wy_support_date', true)))) . '</div>';
        echo '</div>';

        // Mesajlar
        echo '<div style="margin-bottom:20px;">';
        foreach ($messages as $msg) {
            $is_admin = !empty($msg['is_admin']);
            $bg = $is_admin ? '#e3f2fd' : '#f5f5f5';
            $border = $is_admin ? '#1976d2' : '#e0e0e0';
            $label = $is_admin ? '👨‍💼 Destek Ekibi' : '👤 Siz';
            $label_color = $is_admin ? '#1565c0' : '#555';

            echo '<div style="background:' . $bg . ';border:1px solid ' . $border . ';border-radius:10px;padding:16px;margin-bottom:10px;">';
            echo '<div style="display:flex;justify-content:space-between;margin-bottom:8px;">';
            echo '<strong style="font-size:13px;color:' . $label_color . ';">' . $label . '</strong>';
            echo '<span style="font-size:11px;color:#999;">' . esc_html(date_i18n('d.m.Y H:i', strtotime($msg['date']))) . '</span>';
            echo '</div>';
            echo '<div style="font-size:13px;color:#333;line-height:1.7;white-space:pre-wrap;">' . esc_html($msg['text']) . '</div>';
            echo '</div>';
        }
        echo '</div>';

        // Yanıt formu
        if ($status !== 'closed') {
            echo '<div style="background:#f8f9fa;border:1px solid #e0e0e0;border-radius:10px;padding:16px;">';
            echo '<textarea id="wyReplyText" rows="3" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;resize:vertical;box-sizing:border-box;margin-bottom:10px;" placeholder="Yanıtınızı yazın..."></textarea>';
            echo '<button type="button" id="wyReplyBtn" style="background:var(--webyaz-primary,#446084);color:#fff;border:none;padding:10px 22px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">Yanıt Gönder</button>';
            echo '<div id="wyReplyMsg" style="display:none;margin-top:10px;padding:10px;border-radius:6px;font-size:13px;"></div>';
            echo '</div>';
            ?>
            <script>
            document.getElementById('wyReplyBtn').addEventListener('click', function(){
                var btn = this;
                var text = document.getElementById('wyReplyText').value.trim();
                if(!text){return;}
                btn.disabled = true; btn.textContent = 'Gönderiliyor...';
                jQuery.post(webyaz_ajax.ajax_url, {
                    action: 'webyaz_reply_support',
                    nonce: webyaz_ajax.nonce,
                    ticket_id: <?php echo $ticket_id; ?>,
                    message: text
                }, function(r){
                    var msg = document.getElementById('wyReplyMsg');
                    msg.style.display = 'block';
                    if(r.success){
                        msg.style.background = '#e8f5e9'; msg.style.color = '#2e7d32';
                        msg.textContent = '✅ Yanıtınız gönderildi.';
                        setTimeout(function(){ location.reload(); }, 1000);
                    } else {
                        msg.style.background = '#fde8e8'; msg.style.color = '#c62828';
                        msg.textContent = '❌ ' + r.data;
                        btn.disabled = false; btn.textContent = 'Yanıt Gönder';
                    }
                });
            });
            </script>
            <?php
        } else {
            echo '<div style="background:#f8f9fa;padding:16px;border-radius:10px;text-align:center;color:#999;font-size:13px;">Bu destek talebi kapatılmıştır.</div>';
        }
    }

    /* ═══════════════════════════════════════════════════════
       AJAX İŞLEMLERİ
       ═══════════════════════════════════════════════════════ */

    /* Yeni destek talebi oluştur */
    public function ajax_submit() {
        check_ajax_referer('webyaz_nonce', 'nonce');
        $user_id = get_current_user_id();
        if (!$user_id) wp_send_json_error('Giriş yapmanız gerekiyor.');

        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $priority = sanitize_text_field($_POST['priority'] ?? 'normal');

        if (empty($subject) || empty($message)) {
            wp_send_json_error('Konu ve mesaj alanlarını doldurun.');
        }

        $user = get_userdata($user_id);
        $ticket_id = wp_insert_post(array(
            'post_type' => self::POST_TYPE,
            'post_title' => $subject,
            'post_status' => 'publish',
        ));

        if ($ticket_id && !is_wp_error($ticket_id)) {
            update_post_meta($ticket_id, '_wy_support_user_id', $user_id);
            update_post_meta($ticket_id, '_wy_support_email', $user->user_email);
            update_post_meta($ticket_id, '_wy_support_status', 'open');
            update_post_meta($ticket_id, '_wy_support_priority', $priority);
            update_post_meta($ticket_id, '_wy_support_date', current_time('mysql'));

            $messages = array(array(
                'text' => $message,
                'date' => current_time('mysql'),
                'author' => $user->display_name,
                'is_admin' => false,
            ));
            update_post_meta($ticket_id, '_wy_support_messages', $messages);

            // Admin'e e-posta
            $admin_email = get_option('admin_email');
            $site_name = get_bloginfo('name');
            $subj = $site_name . ' - Yeni Destek Talebi #' . $ticket_id;
            $body = '<h2>Yeni Destek Talebi</h2>';
            $body .= '<p><strong>Talep #:</strong> ' . $ticket_id . '</p>';
            $body .= '<p><strong>Müşteri:</strong> ' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')</p>';
            $body .= '<p><strong>Konu:</strong> ' . esc_html($subject) . '</p>';
            $body .= '<p><strong>Öncelik:</strong> ' . (self::priority_labels()[$priority] ?? 'Normal') . '</p>';
            $body .= '<p><strong>Mesaj:</strong></p><p>' . nl2br(esc_html($message)) . '</p>';
            $body .= '<p><a href="' . admin_url('admin.php?page=webyaz-support&ticket=' . $ticket_id) . '">Talebe Git</a></p>';
            wp_mail($admin_email, $subj, $body, array('Content-Type: text/html; charset=UTF-8'));

            wp_send_json_success('Destek talebiniz oluşturuldu. En kısa sürede yanıt verilecektir.');
        } else {
            wp_send_json_error('Talep oluşturulurken hata oluştu.');
        }
    }

    /* Mesaj yanıtla (müşteri + admin) */
    public function ajax_reply() {
        check_ajax_referer('webyaz_nonce', 'nonce');
        $user_id = get_current_user_id();
        if (!$user_id) wp_send_json_error('Giriş gerekli.');

        $ticket_id = absint($_POST['ticket_id'] ?? 0);
        $text = sanitize_textarea_field($_POST['message'] ?? '');
        if (!$ticket_id || empty($text)) wp_send_json_error('Geçersiz istek.');

        $ticket = get_post($ticket_id);
        if (!$ticket || $ticket->post_type !== self::POST_TYPE) wp_send_json_error('Talep bulunamadı.');

        $is_admin = current_user_can('manage_options');
        $ticket_user = get_post_meta($ticket_id, '_wy_support_user_id', true);

        // Yetki kontrolü
        if (!$is_admin && intval($ticket_user) !== $user_id) {
            wp_send_json_error('Yetki yok.');
        }

        $messages = get_post_meta($ticket_id, '_wy_support_messages', true);
        if (!is_array($messages)) $messages = array();

        $user = get_userdata($user_id);
        $messages[] = array(
            'text' => $text,
            'date' => current_time('mysql'),
            'author' => $user->display_name,
            'is_admin' => $is_admin,
        );
        update_post_meta($ticket_id, '_wy_support_messages', $messages);

        // Durum güncelle
        if ($is_admin) {
            update_post_meta($ticket_id, '_wy_support_status', 'replied');
            // Müşteriye e-posta
            $cust_email = get_post_meta($ticket_id, '_wy_support_email', true);
            if ($cust_email) {
                $site_name = get_bloginfo('name');
                $subj = $site_name . ' - Destek Talebiniz Yanıtlandı #' . $ticket_id;
                $body = '<h2>Destek Talebiniz Yanıtlandı</h2>';
                $body .= '<p><strong>Konu:</strong> ' . esc_html($ticket->post_title) . '</p>';
                $body .= '<p><strong>Yanıt:</strong></p><p>' . nl2br(esc_html($text)) . '</p>';
                $body .= '<p>Yanıtınızı görmek için hesabınızdaki Destek Taleplerim sayfasını ziyaret edin.</p>';
                wp_mail($cust_email, $subj, $body, array('Content-Type: text/html; charset=UTF-8'));
            }
        } else {
            // Müşteri yanıt verince tekrar "Bekliyor" yap
            update_post_meta($ticket_id, '_wy_support_status', 'waiting');
            // Admin'e bildirim
            $admin_email = get_option('admin_email');
            $site_name = get_bloginfo('name');
            $subj = $site_name . ' - Destek Talebine Yeni Yanıt #' . $ticket_id;
            $body = '<h2>Yeni Müşteri Yanıtı</h2>';
            $body .= '<p><strong>Konu:</strong> ' . esc_html($ticket->post_title) . '</p>';
            $body .= '<p><strong>Müşteri:</strong> ' . esc_html($user->display_name) . '</p>';
            $body .= '<p><strong>Mesaj:</strong></p><p>' . nl2br(esc_html($text)) . '</p>';
            $body .= '<p><a href="' . admin_url('admin.php?page=webyaz-support&ticket=' . $ticket_id) . '">Talebe Git</a></p>';
            wp_mail($admin_email, $subj, $body, array('Content-Type: text/html; charset=UTF-8'));
        }

        wp_send_json_success('Yanıt gönderildi.');
    }

    /* Durum güncelle (admin) */
    public function ajax_update_status() {
        if (!current_user_can('manage_options')) wp_send_json_error('Yetki yok.');
        check_ajax_referer('webyaz_nonce', 'nonce');

        $ticket_id = absint($_POST['ticket_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');

        if (!$ticket_id || !in_array($status, array('open', 'waiting', 'replied', 'closed'))) {
            wp_send_json_error('Geçersiz parametre.');
        }

        update_post_meta($ticket_id, '_wy_support_status', $status);

        // Kapatıldıysa müşteriye bildir
        if ($status === 'closed') {
            $cust_email = get_post_meta($ticket_id, '_wy_support_email', true);
            $ticket = get_post($ticket_id);
            if ($cust_email && $ticket) {
                $site_name = get_bloginfo('name');
                $subj = $site_name . ' - Destek Talebiniz Kapatıldı #' . $ticket_id;
                $body = '<h2>Destek Talebi Kapatıldı</h2>';
                $body .= '<p><strong>Konu:</strong> ' . esc_html($ticket->post_title) . '</p>';
                $body .= '<p>Destek talebiniz çözüme ulaştığı için kapatılmıştır. Yeni bir sorunuz olduğunda yeni talep oluşturabilirsiniz.</p>';
                wp_mail($cust_email, $subj, $body, array('Content-Type: text/html; charset=UTF-8'));
            }
        }

        wp_send_json_success('Durum güncellendi.');
    }

    /* ═══════════════════════════════════════════════════════
       ADMİN TARAFI
       ═══════════════════════════════════════════════════════ */
    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Destek Talepleri', 'Destek Talepleri', 'manage_options', 'webyaz-support', array($this, 'render_admin'));
    }

    public function admin_styles($hook) {
        if (strpos($hook, 'webyaz-support') === false) return;
        $css = '.wy-sup-thread{max-height:500px;overflow-y:auto;padding:4px 2px;}';
        wp_add_inline_style('wp-admin', $css);
    }

    public function render_admin() {
        $ticket_id = isset($_GET['ticket']) ? absint($_GET['ticket']) : 0;

        if ($ticket_id) {
            $this->render_admin_ticket_detail($ticket_id);
        } else {
            $this->render_admin_ticket_list();
        }
    }

    /* Admin: Talep Listesi */
    private function render_admin_ticket_list() {
        $tickets = get_posts(array(
            'post_type' => self::POST_TYPE,
            'posts_per_page' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        $sl = self::status_labels();
        $sc = self::status_colors();
        $pl = self::priority_labels();

        $stats = array('open' => 0, 'waiting' => 0, 'replied' => 0, 'closed' => 0);
        foreach ($tickets as $t) {
            $s = get_post_meta($t->ID, '_wy_support_status', true);
            if (isset($stats[$s])) $stats[$s]++;
        }
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>🎧 Destek Talepleri</h1><p>Müşteri destek taleplerini yönetin ve yanıtlayın</p></div>

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:25px;">
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #2196f3;">
                    <div style="font-size:24px;font-weight:700;color:#2196f3;"><?php echo $stats['open']; ?></div>
                    <div style="font-size:12px;color:#666;">Açık</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #ff9800;">
                    <div style="font-size:24px;font-weight:700;color:#ff9800;"><?php echo $stats['waiting']; ?></div>
                    <div style="font-size:12px;color:#666;">Bekliyor</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #4caf50;">
                    <div style="font-size:24px;font-weight:700;color:#4caf50;"><?php echo $stats['replied']; ?></div>
                    <div style="font-size:12px;color:#666;">Cevaplandı</div>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;text-align:center;border-top:3px solid #9e9e9e;">
                    <div style="font-size:24px;font-weight:700;color:#9e9e9e;"><?php echo $stats['closed']; ?></div>
                    <div style="font-size:12px;color:#666;">Kapatıldı</div>
                </div>
            </div>

            <?php if (empty($tickets)): ?>
                <div style="background:#f8f9fa;padding:40px;text-align:center;border-radius:12px;color:#999;">
                    <span style="font-size:48px;">🎧</span>
                    <p style="font-size:16px;margin-top:12px;">Henüz destek talebi bulunmuyor.</p>
                </div>
            <?php else: ?>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;overflow:hidden;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f8f9fa;">
                                <th style="padding:14px 16px;text-align:left;font-size:13px;color:#666;">#</th>
                                <th style="padding:14px 16px;text-align:left;font-size:13px;color:#666;">Konu</th>
                                <th style="padding:14px 16px;text-align:left;font-size:13px;color:#666;">Müşteri</th>
                                <th style="padding:14px 16px;text-align:left;font-size:13px;color:#666;">Öncelik</th>
                                <th style="padding:14px 16px;text-align:left;font-size:13px;color:#666;">Tarih</th>
                                <th style="padding:14px 16px;text-align:left;font-size:13px;color:#666;">Mesaj</th>
                                <th style="padding:14px 16px;text-align:left;font-size:13px;color:#666;">Durum</th>
                                <th style="padding:14px 16px;text-align:left;font-size:13px;color:#666;">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $t):
                                $status = get_post_meta($t->ID, '_wy_support_status', true);
                                $priority = get_post_meta($t->ID, '_wy_support_priority', true);
                                $date = get_post_meta($t->ID, '_wy_support_date', true);
                                $email = get_post_meta($t->ID, '_wy_support_email', true);
                                $user_id = get_post_meta($t->ID, '_wy_support_user_id', true);
                                $user = get_userdata($user_id);
                                $customer = $user ? $user->display_name : '-';
                                $messages = get_post_meta($t->ID, '_wy_support_messages', true);
                                $msg_count = is_array($messages) ? count($messages) : 0;
                                $color = $sc[$status] ?? '#999';
                                $pri_color = $priority === 'urgent' ? '#f44336' : '#4caf50';
                                $detail_url = admin_url('admin.php?page=webyaz-support&ticket=' . $t->ID);
                            ?>
                            <tr style="border-bottom:1px solid #f0f0f0;">
                                <td style="padding:14px 16px;font-size:12px;color:#999;"><?php echo $t->ID; ?></td>
                                <td style="padding:14px 16px;"><a href="<?php echo esc_url($detail_url); ?>" style="font-weight:600;font-size:13px;color:#333;text-decoration:none;"><?php echo esc_html($t->post_title); ?></a></td>
                                <td style="padding:14px 16px;font-size:13px;"><?php echo esc_html($customer); ?><br><span style="font-size:11px;color:#999;"><?php echo esc_html($email); ?></span></td>
                                <td style="padding:14px 16px;"><span style="background:<?php echo $pri_color; ?>22;color:<?php echo $pri_color; ?>;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;"><?php echo $pl[$priority] ?? 'Normal'; ?></span></td>
                                <td style="padding:14px 16px;font-size:12px;color:#999;"><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($date))); ?></td>
                                <td style="padding:14px 16px;font-size:12px;color:#999;"><?php echo $msg_count; ?> mesaj</td>
                                <td style="padding:14px 16px;"><span style="background:<?php echo $color; ?>22;color:<?php echo $color; ?>;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;"><?php echo $sl[$status] ?? $status; ?></span></td>
                                <td style="padding:14px 16px;"><a href="<?php echo esc_url($detail_url); ?>" class="webyaz-btn webyaz-btn-outline" style="padding:6px 14px;font-size:12px;">Görüntüle</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /* Admin: Talep Detayı + Cevaplama */
    private function render_admin_ticket_detail($ticket_id) {
        $ticket = get_post($ticket_id);
        if (!$ticket || $ticket->post_type !== self::POST_TYPE) {
            echo '<div class="webyaz-admin-wrap"><p style="color:#c62828;">Talep bulunamadı.</p></div>';
            return;
        }

        $status = get_post_meta($ticket_id, '_wy_support_status', true);
        $priority = get_post_meta($ticket_id, '_wy_support_priority', true);
        $date = get_post_meta($ticket_id, '_wy_support_date', true);
        $email = get_post_meta($ticket_id, '_wy_support_email', true);
        $user_id = get_post_meta($ticket_id, '_wy_support_user_id', true);
        $user = get_userdata($user_id);
        $messages = get_post_meta($ticket_id, '_wy_support_messages', true);
        if (!is_array($messages)) $messages = array();

        $sl = self::status_labels();
        $sc = self::status_colors();
        $color = $sc[$status] ?? '#999';
        $nonce = wp_create_nonce('webyaz_nonce');
        ?>
        <div class="webyaz-admin-wrap">
            <div style="margin-bottom:16px;">
                <a href="<?php echo admin_url('admin.php?page=webyaz-support'); ?>" style="font-size:13px;color:#446084;text-decoration:none;">← Tüm Talepler</a>
            </div>

            <div class="webyaz-admin-header">
                <h1>🎧 <?php echo esc_html($ticket->post_title); ?></h1>
                <p>Talep #<?php echo $ticket_id; ?> · <?php echo esc_html($user ? $user->display_name : '-'); ?> (<?php echo esc_html($email); ?>)</p>
            </div>

            <!-- Bilgi Kartları -->
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:14px;text-align:center;">
                    <div style="font-size:11px;color:#999;margin-bottom:4px;">Durum</div>
                    <span style="background:<?php echo $color; ?>22;color:<?php echo $color; ?>;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:600;"><?php echo $sl[$status] ?? $status; ?></span>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:14px;text-align:center;">
                    <div style="font-size:11px;color:#999;margin-bottom:4px;">Öncelik</div>
                    <strong style="color:<?php echo $priority === 'urgent' ? '#f44336' : '#4caf50'; ?>;font-size:13px;"><?php echo self::priority_labels()[$priority] ?? 'Normal'; ?></strong>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:14px;text-align:center;">
                    <div style="font-size:11px;color:#999;margin-bottom:4px;">Tarih</div>
                    <strong style="font-size:13px;color:#333;"><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($date))); ?></strong>
                </div>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:14px;text-align:center;">
                    <div style="font-size:11px;color:#999;margin-bottom:4px;">Mesaj Sayısı</div>
                    <strong style="font-size:13px;color:#333;"><?php echo count($messages); ?></strong>
                </div>
            </div>

            <!-- Durum Değiştir -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <span style="font-size:13px;font-weight:600;color:#333;">Durum Değiştir:</span>
                <select id="wyAdminStatus" style="padding:8px 14px;border-radius:6px;border:1px solid #ddd;font-size:13px;">
                    <?php foreach ($sl as $sk => $slabel): ?>
                        <option value="<?php echo $sk; ?>" <?php selected($status, $sk); ?>><?php echo $slabel; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="wyStatusBtn" class="webyaz-btn webyaz-btn-primary" style="padding:8px 20px;font-size:13px;">Güncelle</button>
                <span id="wyStatusMsg" style="font-size:12px;display:none;"></span>
            </div>

            <!-- Mesaj Thread -->
            <div class="webyaz-section-title">Mesajlar</div>
            <div class="wy-sup-thread" style="margin-bottom:20px;">
                <?php foreach ($messages as $msg):
                    $is_admin = !empty($msg['is_admin']);
                    $bg = $is_admin ? '#e3f2fd' : '#f5f5f5';
                    $border = $is_admin ? '#1976d2' : '#e0e0e0';
                    $label = $is_admin ? '👨‍💼 Destek Ekibi (' . esc_html($msg['author']) . ')' : '👤 ' . esc_html($msg['author']);
                    $label_color = $is_admin ? '#1565c0' : '#555';
                ?>
                <div style="background:<?php echo $bg; ?>;border:1px solid <?php echo $border; ?>;border-radius:10px;padding:16px;margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                        <strong style="font-size:13px;color:<?php echo $label_color; ?>;"><?php echo $label; ?></strong>
                        <span style="font-size:11px;color:#999;"><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($msg['date']))); ?></span>
                    </div>
                    <div style="font-size:13px;color:#333;line-height:1.7;white-space:pre-wrap;"><?php echo esc_html($msg['text']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Admin Yanıt -->
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px;">
                <h3 style="margin:0 0 12px;font-size:15px;color:#333;">Yanıt Yaz</h3>
                <textarea id="wyAdminReply" rows="4" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:6px;resize:vertical;box-sizing:border-box;font-size:14px;margin-bottom:12px;" placeholder="Müşteriye yanıtınızı yazın..."></textarea>
                <button type="button" id="wyAdminReplyBtn" class="webyaz-btn webyaz-btn-primary" style="padding:10px 28px;font-size:14px;">📩 Yanıt Gönder</button>
                <div id="wyAdminReplyMsg" style="display:none;margin-top:10px;padding:10px;border-radius:6px;font-size:13px;"></div>
            </div>
        </div>

        <script>
        // Durum güncelle
        document.getElementById('wyStatusBtn').addEventListener('click', function(){
            var btn = this;
            btn.disabled = true;
            jQuery.post(ajaxurl, {
                action: 'webyaz_update_support_status',
                nonce: '<?php echo $nonce; ?>',
                ticket_id: <?php echo $ticket_id; ?>,
                status: document.getElementById('wyAdminStatus').value
            }, function(r){
                var msg = document.getElementById('wyStatusMsg');
                msg.style.display = 'inline';
                if(r.success){
                    msg.style.color = '#4caf50';
                    msg.textContent = '✅ Güncellendi';
                    setTimeout(function(){ location.reload(); }, 1000);
                } else {
                    msg.style.color = '#f44336';
                    msg.textContent = '❌ ' + r.data;
                    btn.disabled = false;
                }
            });
        });

        // Yanıt gönder
        document.getElementById('wyAdminReplyBtn').addEventListener('click', function(){
            var btn = this;
            var text = document.getElementById('wyAdminReply').value.trim();
            if(!text) return;
            btn.disabled = true; btn.textContent = 'Gönderiliyor...';
            jQuery.post(ajaxurl, {
                action: 'webyaz_reply_support',
                nonce: '<?php echo $nonce; ?>',
                ticket_id: <?php echo $ticket_id; ?>,
                message: text
            }, function(r){
                var msg = document.getElementById('wyAdminReplyMsg');
                msg.style.display = 'block';
                if(r.success){
                    msg.style.background = '#e8f5e9'; msg.style.color = '#2e7d32';
                    msg.textContent = '✅ Yanıt gönderildi.';
                    setTimeout(function(){ location.reload(); }, 1000);
                } else {
                    msg.style.background = '#fde8e8'; msg.style.color = '#c62828';
                    msg.textContent = '❌ ' + r.data;
                    btn.disabled = false; btn.textContent = '📩 Yanıt Gönder';
                }
            });
        });
        </script>
        <?php
    }
}

new Webyaz_Support();
