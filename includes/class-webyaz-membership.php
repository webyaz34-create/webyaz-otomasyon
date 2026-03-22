<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Membership
{

    private static $table_name;

    public function __construct()
    {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'webyaz_membership_plans';

        register_activation_hook(WEBYAZ_PATH . 'webyaz-otomasyon.php', array($this, 'create_tables'));
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box'));
        add_action('template_redirect', array($this, 'check_access'));
        add_filter('the_content', array($this, 'filter_content'), 999);
        add_action('wp_ajax_webyaz_membership_save_plan', array($this, 'ajax_save_plan'));
        add_action('wp_ajax_webyaz_membership_delete_plan', array($this, 'ajax_delete_plan'));
        add_action('wp_ajax_webyaz_membership_assign_user', array($this, 'ajax_assign_user'));
        add_action('wp_ajax_webyaz_membership_remove_user', array($this, 'ajax_remove_user'));
        add_action('wp_ajax_webyaz_membership_search_users', array($this, 'ajax_search_users'));
        add_action('show_user_profile', array($this, 'user_profile_fields'));
        add_action('edit_user_profile', array($this, 'user_profile_fields'));
        add_action('personal_options_update', array($this, 'save_user_profile'));
        add_action('edit_user_profile_update', array($this, 'save_user_profile'));
        add_action('wp_ajax_webyaz_membership_apply', array($this, 'ajax_membership_apply'));
        add_action('wp_ajax_nopriv_webyaz_membership_apply', array($this, 'ajax_membership_apply'));
        add_action('wp_ajax_webyaz_membership_approve_app', array($this, 'ajax_approve_application'));
        add_action('wp_ajax_webyaz_membership_reject_app', array($this, 'ajax_reject_application'));

        // WooCommerce Hesabim sekmesi
        add_action('init', array($this, 'add_my_account_endpoint'));
        add_filter('query_vars', array($this, 'my_account_query_vars'));
        add_filter('woocommerce_account_menu_items', array($this, 'my_account_menu_item'));
        add_action('woocommerce_account_membership_endpoint', array($this, 'my_account_content'));

        // Sureleri kontrol et (gunluk)
        if (!wp_next_scheduled('webyaz_membership_check_expiry')) {
            wp_schedule_event(time(), 'daily', 'webyaz_membership_check_expiry');
        }
        add_action('webyaz_membership_check_expiry', array($this, 'check_expiry'));

        $this->maybe_create_tables();
    }

    private function maybe_create_tables()
    {
        if (get_option('webyaz_membership_db_ver', '0') !== '1.3') {
            $this->create_tables();
        }
    }

    public function create_tables()
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        // dbDelta requires CREATE TABLE without IF NOT EXISTS to detect missing columns
        $sql = "CREATE TABLE " . self::$table_name . " (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT,
            color VARCHAR(7) DEFAULT '#446084',
            duration INT DEFAULT 0,
            monthly_price DECIMAL(10,2) DEFAULT 0,
            yearly_price DECIMAL(10,2) DEFAULT 0,
            status TINYINT(1) DEFAULT 1,
            priority INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) $charset;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Fallback: ensure columns exist even if dbDelta didn't add them
        $table = self::$table_name;
        $cols = $wpdb->get_col("SHOW COLUMNS FROM `$table`");
        if (!in_array('monthly_price', $cols)) {
            $wpdb->query("ALTER TABLE `$table` ADD COLUMN monthly_price DECIMAL(10,2) DEFAULT 0");
        }
        if (!in_array('yearly_price', $cols)) {
            $wpdb->query("ALTER TABLE `$table` ADD COLUMN yearly_price DECIMAL(10,2) DEFAULT 0");
        }

        update_option('webyaz_membership_db_ver', '1.3');
    }

    // === AYARLAR ===
    public function register_settings()
    {
        register_setting('webyaz_membership_group', 'webyaz_membership_opts');
    }

    private static function get_defaults()
    {
        return array(
            'restrict_mode'    => 'message',
            'redirect_url'     => '',
            'message_title'    => 'Bu icerik kisitlidir',
            'message_text'     => 'Bu icerigi goruntulemek icin uygun uyelik planina sahip olmaniz gerekmektedir.',
            'message_bg'       => '#fff3e0',
            'message_color'    => '#e65100',
            'show_login'       => '1',
            'teaser_mode'      => '0',
            'teaser_length'    => '200',
            'restrict_categories' => array(),
            'restrict_posts'   => array(),
            'restrict_pages'   => array(),
            'bank_name'        => '',
            'bank_iban'        => '',
            'bank_holder'      => '',
        );
    }

    public static function get_opts()
    {
        return wp_parse_args(get_option('webyaz_membership_opts', array()), self::get_defaults());
    }

    // === PLANLAR ===
    public static function get_plans($active_only = false)
    {
        global $wpdb;
        $where = $active_only ? "WHERE status = 1" : "";
        return $wpdb->get_results("SELECT * FROM " . self::$table_name . " $where ORDER BY priority ASC, name ASC");
    }

    public static function get_plan($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::$table_name . " WHERE id = %d", $id));
    }

    // === META BOX ===
    public function add_meta_box()
    {
        $screens = array('post', 'page');
        if (class_exists('WooCommerce')) $screens[] = 'product';
        foreach ($screens as $screen) {
            add_meta_box('webyaz_membership_access', 'Uyelik Kisitlama', array($this, 'render_meta_box'), $screen, 'side', 'high');
        }
    }

    public function render_meta_box($post)
    {
        wp_nonce_field('webyaz_membership_meta', '_webyaz_membership_nonce');
        $selected = get_post_meta($post->ID, '_webyaz_membership_plans', true);
        if (!is_array($selected)) $selected = array();
        $plans = self::get_plans(true);
        echo '<p style="margin-bottom:8px;font-size:12px;color:#666;">Erisim icin gerekli planlari secin. Bos birakirsaniz herkes erisebilir.</p>';
        if (empty($plans)) {
            echo '<p style="color:#999;font-style:italic;">Henuz plan olusturulmanis. Webyaz > Uyelik Sistemi\'nden plan ekleyin.</p>';
            return;
        }
        foreach ($plans as $plan) {
            $checked = in_array($plan->id, $selected) ? 'checked' : '';
            echo '<label style="display:flex;align-items:center;gap:6px;padding:4px 0;cursor:pointer;">';
            echo '<input type="checkbox" name="webyaz_membership_plans[]" value="' . esc_attr($plan->id) . '" ' . $checked . '>';
            echo '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' . esc_attr($plan->color) . ';"></span>';
            echo esc_html($plan->name);
            echo '</label>';
        }
    }

    public function save_meta_box($post_id)
    {
        if (!isset($_POST['_webyaz_membership_nonce'])) return;
        if (!wp_verify_nonce($_POST['_webyaz_membership_nonce'], 'webyaz_membership_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $plans = isset($_POST['webyaz_membership_plans']) ? array_map('intval', $_POST['webyaz_membership_plans']) : array();
        update_post_meta($post_id, '_webyaz_membership_plans', $plans);
    }

    // === FRONTEND ERISIM KONTROLU ===
    public function check_access()
    {
        if (is_admin() || wp_doing_ajax()) return;
        if (current_user_can('manage_options')) return;

        $opts = self::get_opts();

        // Kategori kisitlama
        if (is_single() && !empty($opts['restrict_categories'])) {
            $cats = wp_get_post_categories(get_the_ID());
            $restricted_cats = array_map('intval', $opts['restrict_categories']);
            if (array_intersect($cats, $restricted_cats) && !$this->user_has_access(get_the_ID())) {
                if ($opts['restrict_mode'] === 'redirect' && !empty($opts['redirect_url'])) {
                    wp_redirect($opts['redirect_url']);
                    exit;
                }
                return;
            }
        }

        if (!is_singular()) return;
        $post_id = get_queried_object_id();

        // Ayarlardan yazi/sayfa kisitlama
        $is_settings_restricted = false;
        if (!empty($opts['restrict_posts']) && in_array($post_id, array_map('intval', $opts['restrict_posts']))) $is_settings_restricted = true;
        if (!empty($opts['restrict_pages']) && in_array($post_id, array_map('intval', $opts['restrict_pages']))) $is_settings_restricted = true;

        $required = get_post_meta($post_id, '_webyaz_membership_plans', true);
        if ((empty($required) || !is_array($required)) && !$is_settings_restricted) return;

        if ($is_settings_restricted && $this->user_has_any_plan()) return;
        if (!$is_settings_restricted && $this->user_has_access($post_id)) return;

        if ($opts['restrict_mode'] === 'redirect' && !empty($opts['redirect_url'])) {
            wp_redirect($opts['redirect_url']);
            exit;
        }
    }

    public function filter_content($content)
    {
        if (is_admin() || !is_singular()) return $content;
        if (current_user_can('manage_options')) return $content;

        $post_id = get_the_ID();
        $required = get_post_meta($post_id, '_webyaz_membership_plans', true);
        $opts = self::get_opts();

        // Kategori kisitlama
        $is_cat_restricted = false;
        if (is_single() && !empty($opts['restrict_categories'])) {
            $cats = wp_get_post_categories($post_id);
            $restricted_cats = array_map('intval', $opts['restrict_categories']);
            if (array_intersect($cats, $restricted_cats)) $is_cat_restricted = true;
        }

        // Ayarlardan yazi/sayfa kisitlama
        $is_settings_restricted = false;
        if (!empty($opts['restrict_posts']) && in_array($post_id, array_map('intval', $opts['restrict_posts']))) $is_settings_restricted = true;
        if (!empty($opts['restrict_pages']) && in_array($post_id, array_map('intval', $opts['restrict_pages']))) $is_settings_restricted = true;

        $has_meta = !empty($required) && is_array($required);
        if (!$has_meta && !$is_cat_restricted && !$is_settings_restricted) return $content;
        if ($has_meta && $this->user_has_access($post_id)) return $content;
        if (($is_cat_restricted || $is_settings_restricted) && $this->user_has_any_plan()) return $content;

        return $this->get_restricted_message($content);
    }

    private function user_has_access($post_id)
    {
        if (!is_user_logged_in()) return false;
        $user_id = get_current_user_id();
        $required = get_post_meta($post_id, '_webyaz_membership_plans', true);
        if (empty($required) || !is_array($required)) return true;
        $user_plans = get_user_meta($user_id, '_webyaz_membership_user_plans', true);
        if (!is_array($user_plans)) return false;
        foreach ($user_plans as $up) {
            if (in_array($up['plan_id'], $required)) {
                if (empty($up['expires']) || strtotime($up['expires']) > time()) return true;
            }
        }
        return false;
    }

    private function user_has_any_plan()
    {
        if (!is_user_logged_in()) return false;
        $user_plans = get_user_meta(get_current_user_id(), '_webyaz_membership_user_plans', true);
        if (!is_array($user_plans) || empty($user_plans)) return false;
        foreach ($user_plans as $up) {
            if (empty($up['expires']) || strtotime($up['expires']) > time()) return true;
        }
        return false;
    }

    private function get_restricted_message($content = '')
    {
        $opts = self::get_opts();
        $teaser = '';
        if ($opts['teaser_mode'] === '1' && !empty($content)) {
            $len = intval($opts['teaser_length']);
            $teaser = '<div class="webyaz-membership-teaser">' . wp_trim_words(strip_tags($content), $len) . '</div>';
        }
        $login_form = '';
        if ($opts['show_login'] === '1' && !is_user_logged_in()) {
            $login_form = '<div style="margin-top:16px;text-align:center;">
                <a href="' . esc_url(wp_login_url(get_permalink())) . '" style="display:inline-block;padding:10px 28px;background:' . esc_attr($opts['message_color']) . ';color:#fff;text-decoration:none;border-radius:8px;font-weight:600;font-size:14px;">Giris Yap</a>
            </div>';
        }
        // Plan fiyat listesi
        $plan_cards = '';
        $plans = self::get_plans(true);
        if (!empty($plans)) {
            $plan_cards .= '<div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:18px;">';
            foreach ($plans as $p) {
                $prices = '';
                if ($p->monthly_price > 0) $prices .= '<div style="font-size:13px;color:#555;">Aylik: <strong>' . number_format($p->monthly_price, 2, ',', '.') . ' ₺</strong></div>';
                if ($p->yearly_price > 0) $prices .= '<div style="font-size:13px;color:#555;">Yillik: <strong>' . number_format($p->yearly_price, 2, ',', '.') . ' ₺</strong></div>';
                $plan_cards .= '<div style="background:#fff;border:2px solid ' . esc_attr($p->color) . ';border-radius:10px;padding:14px 20px;min-width:140px;text-align:center;">';
                $plan_cards .= '<div style="font-weight:700;font-size:14px;color:' . esc_attr($p->color) . ';margin-bottom:6px;">' . esc_html($p->name) . '</div>';
                $plan_cards .= $prices;
                $plan_cards .= '</div>';
            }
            $plan_cards .= '</div>';
        }
        $msg = $teaser . '
        <div style="background:' . esc_attr($opts['message_bg']) . ';border-left:4px solid ' . esc_attr($opts['message_color']) . ';border-radius:12px;padding:28px 32px;margin:20px 0;text-align:center;">
            <div style="font-size:36px;margin-bottom:12px;">🔒</div>
            <h3 style="margin:0 0 10px;color:' . esc_attr($opts['message_color']) . ';font-size:18px;font-weight:700;">' . esc_html($opts['message_title']) . '</h3>
            <p style="margin:0;color:#555;font-size:14px;line-height:1.7;">' . esc_html($opts['message_text']) . '</p>
            ' . $plan_cards . '
            ' . $login_form . '
        </div>';
        return $msg;
    }

    // === SURESI DOLAN UYELIKLERI KONTROL ===
    public function check_expiry()
    {
        $users = get_users(array('meta_key' => '_webyaz_membership_user_plans'));
        foreach ($users as $user) {
            $plans = get_user_meta($user->ID, '_webyaz_membership_user_plans', true);
            if (!is_array($plans)) continue;
            $updated = false;
            $send_expiry_email = false;
            foreach ($plans as $k => $p) {
                if (!empty($p['expires']) && strtotime($p['expires']) < time() && (!isset($p['status']) || $p['status'] !== 'expired')) {
                    $plans[$k]['status'] = 'expired';
                    $updated = true;
                    $send_expiry_email = true;
                }
            }
            if ($updated) {
                update_user_meta($user->ID, '_webyaz_membership_user_plans', $plans);

                // === E-POSTA: Süre doldu / ödeme yapılmadı bildirimi ===
                if ($send_expiry_email && class_exists('Webyaz_Email_Templates')) {
                    $body = '<p style="font-size:15px;margin:0 0 16px;">Merhaba <strong>' . esc_html($user->display_name) . '</strong>,</p>';
                    $body .= '<p>Üyelik süreniz <strong>sona ermiştir</strong>. Ödeme yapılmadığı için üyelik avantajlarınız askıya alınmıştır.</p>';
                    $body .= '<div style="background:#fffbeb;border:1px solid #fed7aa;border-radius:12px;padding:18px;margin:16px 0;text-align:center;">';
                    $body .= '<div style="font-size:32px;margin-bottom:8px;">⏰</div>';
                    $body .= '<div style="font-size:15px;font-weight:700;color:#92400e;">Üyeliğiniz sona erdi!</div>';
                    $body .= '<div style="font-size:13px;color:#b45309;margin-top:4px;">Kısıtlı içeriklere erişiminiz durdurulmuştur.</div>';
                    $body .= '</div>';
                    $body .= '<p>Üyeliğinizi yenilemek ve avantajlardan yararlanmaya devam etmek için aşağıdaki butona tıklayın:</p>';
                    $body .= '<p style="text-align:center;margin:20px 0;"><a href="' . wc_get_account_endpoint_url('membership') . '" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;text-decoration:none;border-radius:8px;font-weight:700;font-size:15px;">Üyeliğimi Yenile →</a></p>';
                    Webyaz_Email_Templates::send_branded_email($user->user_email, '⏰ Üyelik Süreniz Doldu — Yenileme Gerekli', 'Üyelik Süreniz Doldu', $body, '⏰ Süre Doldu', '#f59e0b');
                }
            }

            // === 3 GÜN KALA UYARI E-POSTASI ===
            foreach ($plans as $k => $p) {
                if (!empty($p['expires']) && (!isset($p['status']) || $p['status'] !== 'expired')) {
                    $days_left = ceil((strtotime($p['expires']) - time()) / 86400);
                    $warn_key = '_webyaz_membership_warned_' . md5($p['plan_id'] . '_' . $p['expires']);
                    if ($days_left <= 3 && $days_left > 0 && !get_user_meta($user->ID, $warn_key, true)) {
                        // 3 gun kala bir kez uyari gonder
                        update_user_meta($user->ID, $warn_key, '1');
                        if (class_exists('Webyaz_Email_Templates')) {
                            $plan_info = self::get_plan($p['plan_id']);
                            $plan_name = $plan_info ? $plan_info->name : 'Plan #' . $p['plan_id'];
                            $expire_date = date('d.m.Y', strtotime($p['expires']));
                            $mopts = self::get_options();

                            $body = '<p style="font-size:15px;margin:0 0 16px;">Merhaba <strong>' . esc_html($user->display_name) . '</strong>,</p>';
                            $body .= '<p><strong>' . esc_html($plan_name) . '</strong> üyelik planınızın süresi <strong>' . $days_left . ' gün içinde</strong> sona erecektir.</p>';

                            $body .= '<div style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:2px solid #f59e0b;border-radius:12px;padding:20px;margin:16px 0;text-align:center;">';
                            $body .= '<div style="font-size:36px;margin-bottom:8px;">⚠️</div>';
                            $body .= '<div style="font-size:17px;font-weight:800;color:#92400e;">Aboneliğinizi Yenileyin!</div>';
                            $body .= '<div style="font-size:13px;color:#b45309;margin-top:6px;">Bitiş tarihi: <strong>' . $expire_date . '</strong> — ' . $days_left . ' gün kaldı</div>';
                            $body .= '<div style="font-size:12px;color:#92400e;margin-top:8px;">Ödeme yapılmazsa kısıtlı içeriklere erişiminiz durdurulacaktır.</div>';
                            $body .= '</div>';

                            // Banka bilgileri
                            if (!empty($mopts['bank_name']) || !empty($mopts['bank_iban'])) {
                                $body .= '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin:16px 0;">';
                                $body .= '<div style="font-size:13px;font-weight:700;color:#334155;margin-bottom:8px;">🏦 Ödeme Bilgileri</div>';
                                if (!empty($mopts['bank_name'])) $body .= '<div style="font-size:13px;color:#555;margin-bottom:4px;">Banka: <strong>' . esc_html($mopts['bank_name']) . '</strong></div>';
                                if (!empty($mopts['bank_holder'])) $body .= '<div style="font-size:13px;color:#555;margin-bottom:4px;">Hesap Sahibi: <strong>' . esc_html($mopts['bank_holder']) . '</strong></div>';
                                if (!empty($mopts['bank_iban'])) $body .= '<div style="font-size:13px;color:#555;">IBAN: <strong style="font-family:monospace;letter-spacing:1px;">' . esc_html($mopts['bank_iban']) . '</strong></div>';
                                $body .= '</div>';
                            }

                            $body .= '<p style="text-align:center;margin:20px 0;"><a href="' . wc_get_account_endpoint_url('membership') . '" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;text-decoration:none;border-radius:8px;font-weight:700;font-size:15px;">Aboneliğimi Yenile →</a></p>';
                            Webyaz_Email_Templates::send_branded_email($user->user_email, '⚠️ Aboneliğiniz ' . $days_left . ' Gün İçinde Sona Erecek!', 'Abonelik Yenileme Hatırlatması', $body, '⚠️ ' . $days_left . ' Gün Kaldı', '#f59e0b');
                        }
                    }
                }
            }
        }
    }

    // === KULLANICI PROFIL ALANLARI ===
    public function user_profile_fields($user)
    {
        if (!current_user_can('manage_options')) return;
        $plans = self::get_plans();
        $user_plans = get_user_meta($user->ID, '_webyaz_membership_user_plans', true);
        if (!is_array($user_plans)) $user_plans = array();
?>
        <h2>Uyelik Planlari</h2>
        <table class="form-table">
            <tr>
                <th>Aktif Planlar</th>
                <td>
                    <?php if (empty($user_plans)): ?>
                        <p style="color:#999;">Atanmis plan yok.</p>
                    <?php else: ?>
                        <?php foreach ($user_plans as $up):
                            $plan = self::get_plan($up['plan_id']);
                            if (!$plan) continue;
                            $exp = !empty($up['expires']) ? date('d.m.Y', strtotime($up['expires'])) : 'Sinirsiz';
                            $status = (!empty($up['expires']) && strtotime($up['expires']) < time()) ? 'Suresi doldu' : 'Aktif';
                        ?>
                            <span style="display:inline-flex;align-items:center;gap:6px;background:#f0f4f8;padding:5px 14px;border-radius:20px;margin:3px;font-size:13px;">
                                <span style="width:8px;height:8px;border-radius:50%;background:<?php echo esc_attr($plan->color); ?>;"></span>
                                <?php echo esc_html($plan->name); ?> - <?php echo $exp; ?> (<?php echo $status; ?>)
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    <?php
    }

    public function save_user_profile($user_id)
    {
        if (!current_user_can('manage_options')) return;
    }

    // === WOOCOMMERCE HESABIM SEKMESI ===
    public function add_my_account_endpoint()
    {
        add_rewrite_endpoint('membership', EP_ROOT | EP_PAGES);
    }

    public function my_account_query_vars($vars)
    {
        $vars[] = 'membership';
        return $vars;
    }

    public function my_account_menu_item($items)
    {
        $new = array();
        foreach ($items as $k => $v) {
            $new[$k] = $v;
            if ($k === 'orders') {
                $new['membership'] = 'Uyeligim';
            }
        }
        if (!isset($new['membership'])) $new['membership'] = 'Uyeligim';
        return $new;
    }

    public function my_account_content()
    {
        $user_id = get_current_user_id();
        $user_plans = get_user_meta($user_id, '_webyaz_membership_user_plans', true);
        if (!is_array($user_plans)) $user_plans = array();
        $active_plans = array();
        $expired_plans = array();
        foreach ($user_plans as $up) {
            $plan = self::get_plan($up['plan_id']);
            if (!$plan) continue;
            $is_active = empty($up['expires']) || strtotime($up['expires']) > time();
            $item = array('plan' => $plan, 'data' => $up, 'active' => $is_active);
            if ($is_active) $active_plans[] = $item;
            else $expired_plans[] = $item;
        }
        $opts = self::get_opts();
        $all_plans = self::get_plans(true);
        // Kullanicinin bekleyen basvurularini getir
        $applications = get_option('webyaz_membership_applications', array());
        $my_apps = array();
        foreach ($applications as $idx => $app) {
            if (isset($app['user_id']) && $app['user_id'] == $user_id) {
                $app['_index'] = $idx;
                $my_apps[] = $app;
            }
        }
    ?>
        <div style="font-family:-apple-system,BlinkMacSystemFont,'Roboto',sans-serif;">
            <h3 style="margin:0 0 18px;font-size:20px;color:#333;">Uyelik Bilgilerim</h3>

            <?php if (!empty($active_plans)): ?>
                <div style="margin-bottom:24px;">
                    <?php foreach ($active_plans as $item): ?>
                        <div style="background:linear-gradient(135deg,<?php echo esc_attr($item['plan']->color); ?>15,<?php echo esc_attr($item['plan']->color); ?>08);border:2px solid <?php echo esc_attr($item['plan']->color); ?>;border-radius:12px;padding:22px;margin-bottom:12px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                                <div>
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                                        <span style="width:12px;height:12px;border-radius:50%;background:<?php echo esc_attr($item['plan']->color); ?>;"></span>
                                        <strong style="font-size:17px;color:#333;"><?php echo esc_html($item['plan']->name); ?></strong>
                                    </div>
                                    <?php if ($item['plan']->description): ?><p style="font-size:13px;color:#666;margin:0;"><?php echo esc_html($item['plan']->description); ?></p><?php endif; ?>
                                </div>
                                <span style="background:#e6f9e6;color:#22863a;padding:6px 16px;border-radius:20px;font-size:13px;font-weight:700;">✅ Aktif</span>
                            </div>
                            <div style="display:flex;gap:20px;margin-top:14px;font-size:13px;color:#555;flex-wrap:wrap;">
                                <?php if (!empty($item['data']['assigned_at'])): ?>
                                    <div>Baslangic: <strong><?php echo date('d.m.Y', strtotime($item['data']['assigned_at'])); ?></strong></div>
                                <?php endif; ?>
                                <div>Bitis: <strong><?php echo !empty($item['data']['expires']) ? date('d.m.Y', strtotime($item['data']['expires'])) : 'Sinirsiz'; ?></strong></div>
                                <?php if ($item['plan']->monthly_price > 0): ?><div>Aylik: <strong><?php echo number_format($item['plan']->monthly_price, 2, ',', '.'); ?> ₺</strong></div><?php endif; ?>
                                <?php if ($item['plan']->yearly_price > 0): ?><div>Yillik: <strong><?php echo number_format($item['plan']->yearly_price, 2, ',', '.'); ?> ₺</strong></div><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php
                // Yakinda sureleri dolacak planlari kontrol et (3 gun icinde)
                $soon_expiring = array();
                foreach ($active_plans as $item) {
                    if (!empty($item['data']['expires'])) {
                        $days_left = ceil((strtotime($item['data']['expires']) - time()) / 86400);
                        if ($days_left <= 3 && $days_left > 0) {
                            $soon_expiring[] = array('plan' => $item['plan'], 'days' => $days_left, 'expires' => $item['data']['expires']);
                        }
                    }
                }
                if (!empty($soon_expiring)):
                ?>
                    <div style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:2px solid #f59e0b;border-radius:14px;padding:20px 24px;margin-bottom:20px;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                            <span style="font-size:28px;">⚠️</span>
                            <div>
                                <div style="font-size:15px;font-weight:700;color:#92400e;">Aboneliğiniz Yakında Sona Eriyor!</div>
                                <div style="font-size:12px;color:#b45309;margin-top:2px;">Ödeme yapılmazsa kısıtlı içeriklere erişiminiz durdurulacaktır.</div>
                            </div>
                        </div>
                        <?php foreach ($soon_expiring as $se): ?>
                            <div style="display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,0.7);border-radius:8px;padding:10px 14px;margin-top:8px;">
                                <div style="font-size:14px;font-weight:600;color:#333;">
                                    <span style="width:8px;height:8px;border-radius:50%;background:<?php echo esc_attr($se['plan']->color); ?>;display:inline-block;margin-right:6px;"></span>
                                    <?php echo esc_html($se['plan']->name); ?>
                                </div>
                                <div>
                                    <span style="background:#dc2626;color:#fff;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:700;">
                                        <?php echo $se['days']; ?> gün kaldı!
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <p style="margin:12px 0 0;font-size:12px;color:#92400e;">Aşağıdaki hesap bilgilerine ödemenizi yaparak üyeliğinizi yenileyebilirsiniz.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- HESAP BİLGİLERİ (BANKA) -->
            <?php if (!empty($opts['bank_name']) || !empty($opts['bank_iban'])): ?>
                <div style="background:linear-gradient(135deg,#f8fafc,#eef2ff);border:1px solid #c7d2fe;border-radius:14px;padding:22px 24px;margin-bottom:24px;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                        <span style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#4f46e5,#7c3aed);display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;">🏦</span>
                        <div>
                            <div style="font-size:16px;font-weight:700;color:#1e1b4b;">Hesap Bilgileri</div>
                            <div style="font-size:12px;color:#6366f1;">Ödeme yapacağınız banka bilgileri</div>
                        </div>
                    </div>
                    <div style="background:#fff;border-radius:10px;padding:16px 18px;">
                        <table style="width:100%;border-collapse:collapse;">
                            <?php if (!empty($opts['bank_name'])): ?>
                                <tr>
                                    <td style="padding:8px 0;width:120px;font-size:13px;color:#888;border-bottom:1px solid #f3f4f6;">Banka Adı</td>
                                    <td style="padding:8px 0;font-size:14px;font-weight:600;color:#333;border-bottom:1px solid #f3f4f6;"><?php echo esc_html($opts['bank_name']); ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($opts['bank_holder'])): ?>
                                <tr>
                                    <td style="padding:8px 0;font-size:13px;color:#888;border-bottom:1px solid #f3f4f6;">Hesap Sahibi</td>
                                    <td style="padding:8px 0;font-size:14px;font-weight:600;color:#333;border-bottom:1px solid #f3f4f6;"><?php echo esc_html($opts['bank_holder']); ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($opts['bank_iban'])): ?>
                                <tr>
                                    <td style="padding:8px 0;font-size:13px;color:#888;">IBAN</td>
                                    <td style="padding:8px 0;font-size:14px;font-weight:700;color:#4f46e5;font-family:monospace;letter-spacing:1px;"><?php echo esc_html($opts['bank_iban']); ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <p style="margin:12px 0 0;font-size:12px;color:#6366f1;">💡 Havale/EFT yaptıktan sonra aşağıdan plan seçip dekont referans numaranızı girin.</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($expired_plans)): ?>
                <h4 style="margin:20px 0 10px;font-size:14px;color:#999;">Suresi Dolmus Planlar</h4>
                <?php foreach ($expired_plans as $item): ?>
                    <div style="background:#fafafa;border:1px solid #eee;border-radius:10px;padding:14px 18px;margin-bottom:8px;opacity:0.7;">
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <div style="display:flex;align-items:center;gap:6px;">
                                <span style="width:8px;height:8px;border-radius:50%;background:#ccc;"></span>
                                <span style="font-size:14px;color:#666;"><?php echo esc_html($item['plan']->name); ?></span>
                            </div>
                            <span style="font-size:12px;color:#d32f2f;">Bitis: <?php echo date('d.m.Y', strtotime($item['data']['expires'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- BEKLEYEN BASVURULAR -->
            <?php
            $pending_apps = array_filter($my_apps, function ($a) {
                return $a['status'] === 'pending';
            });
            if (!empty($pending_apps)):
            ?>
                <div style="margin-top:20px;margin-bottom:20px;">
                    <h4 style="margin:0 0 12px;font-size:15px;color:#e65100;">⏳ Onay Bekleyen Basvurulariniz</h4>
                    <?php foreach ($pending_apps as $app):
                        $app_plan = self::get_plan($app['plan_id']);
                        if (!$app_plan) continue;
                        $period_label = ($app['period'] === 'yearly') ? 'Yillik' : 'Aylik';
                        $price = ($app['period'] === 'yearly') ? $app_plan->yearly_price : $app_plan->monthly_price;
                    ?>
                        <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:16px 20px;margin-bottom:10px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                                <div>
                                    <strong style="color:#333;"><?php echo esc_html($app_plan->name); ?></strong>
                                    <span style="font-size:12px;color:#666;margin-left:8px;"><?php echo $period_label; ?> — <?php echo number_format($price, 2, ',', '.'); ?> ₺</span>
                                </div>
                                <span style="background:#fff3e0;color:#e65100;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;">⏳ Onay Bekleniyor</span>
                            </div>
                            <?php if (!empty($app['referans_no'])): ?>
                                <div style="margin-top:8px;font-size:12px;color:#555;">Dekont Ref: <strong><?php echo esc_html($app['referans_no']); ?></strong></div>
                            <?php endif; ?>
                            <div style="margin-top:4px;font-size:11px;color:#999;">Basvuru: <?php echo date('d.m.Y H:i', strtotime($app['date'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- REDDEDILEN BASVURULAR -->
            <?php
            $rejected_apps = array_filter($my_apps, function ($a) {
                return $a['status'] === 'rejected';
            });
            if (!empty($rejected_apps)):
            ?>
                <div style="margin-top:10px;margin-bottom:20px;">
                    <h4 style="margin:0 0 12px;font-size:15px;color:#d32f2f;">❌ Reddedilen Basvurular</h4>
                    <?php foreach ($rejected_apps as $app):
                        $app_plan = self::get_plan($app['plan_id']);
                        if (!$app_plan) continue;
                    ?>
                        <div style="background:#ffeef0;border:1px solid #ffc1c1;border-radius:10px;padding:14px 18px;margin-bottom:8px;opacity:0.8;">
                            <div style="display:flex;align-items:center;justify-content:space-between;">
                                <span style="font-size:13px;color:#666;"><?php echo esc_html($app_plan->name); ?></span>
                                <span style="font-size:12px;color:#d32f2f;">Reddedildi</span>
                            </div>
                            <?php if (!empty($app['admin_note'])): ?>
                                <div style="margin-top:6px;font-size:12px;color:#888;">Not: <?php echo esc_html($app['admin_note']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- ÜYE OL FORMU (PREMIUM KARTLAR) -->
            <?php if (!empty($all_plans)): ?>
                <div class="wm-apply-wrap">
                    <h4 class="wm-apply-title">🎯 Üye Ol / Plan Başvurusu</h4>
                    <p class="wm-apply-desc">Planınızı ve döneminizi seçin, ödemenizi yapın, dekont bilgisini girin.</p>

                    <?php if (!empty($opts['bank_name']) || !empty($opts['bank_iban'])): ?>
                        <div class="wm-bank-box">
                            <h5 style="margin:0 0 10px;font-size:14px;color:#333;">🏦 Banka / Havale Bilgileri</h5>
                            <p style="font-size:12px;color:#888;margin:0 0 10px;">Aşağıdaki hesaba havale/EFT yapın, dekont referans numarasını forma girin.</p>
                            <table style="width:100%;font-size:13px;color:#555;">
                                <?php if (!empty($opts['bank_name'])): ?>
                                    <tr><td style="padding:4px 0;font-weight:600;width:120px;">Banka:</td><td style="padding:4px 0;"><?php echo esc_html($opts['bank_name']); ?></td></tr>
                                <?php endif; ?>
                                <?php if (!empty($opts['bank_holder'])): ?>
                                    <tr><td style="padding:4px 0;font-weight:600;">Hesap Sahibi:</td><td style="padding:4px 0;"><?php echo esc_html($opts['bank_holder']); ?></td></tr>
                                <?php endif; ?>
                                <?php if (!empty($opts['bank_iban'])): ?>
                                    <tr><td style="padding:4px 0;font-weight:600;">IBAN:</td><td style="padding:4px 0;font-family:monospace;letter-spacing:1px;"><?php echo esc_html($opts['bank_iban']); ?></td></tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    <?php endif; ?>

                    <!-- DÖNEM TOGGLE -->
                    <div class="wm-period-toggle">
                        <button type="button" class="wm-period-btn active" data-period="monthly">📅 Aylık</button>
                        <button type="button" class="wm-period-btn" data-period="yearly">📆 Yıllık <span class="wm-save-badge">Tasarruf</span></button>
                    </div>
                    <input type="hidden" id="wm-selected-period" value="monthly">

                    <!-- PLAN KARTLARI -->
                    <div class="wm-plan-cards">
                        <?php
                        $plan_colors = array('#4f46e5', '#0891b2', '#7c3aed', '#059669', '#dc2626', '#d97706');
                        $pi = 0;
                        foreach ($all_plans as $p):
                            $color = !empty($p->color) ? $p->color : ($plan_colors[$pi % count($plan_colors)]);
                            $mp = floatval($p->monthly_price);
                            $yp = floatval($p->yearly_price);
                            $monthly_per_month = $mp;
                            $yearly_per_month = ($yp > 0) ? round($yp / 12, 2) : 0;
                            $save_pct = ($mp > 0 && $yp > 0) ? round((1 - ($yp / ($mp * 12))) * 100) : 0;
                            $pi++;
                        ?>
                            <label class="wm-plan-card" data-plan-id="<?php echo esc_attr($p->id); ?>">
                                <input type="radio" name="wm_plan_select" value="<?php echo esc_attr($p->id); ?>" data-monthly="<?php echo esc_attr($mp); ?>" data-yearly="<?php echo esc_attr($yp); ?>" <?php echo $pi === 1 ? 'checked' : ''; ?>>
                                <div class="wm-plan-card-inner" style="--plan-color:<?php echo $color; ?>">
                                    <?php if ($pi === 2): ?><div class="wm-plan-badge">Popüler</div><?php endif; ?>
                                    <div class="wm-plan-dot" style="background:<?php echo $color; ?>;"></div>
                                    <h4 class="wm-plan-name"><?php echo esc_html($p->name); ?></h4>
                                    <?php if ($p->description): ?>
                                        <p class="wm-plan-desc"><?php echo esc_html($p->description); ?></p>
                                    <?php endif; ?>

                                    <!-- Aylık Fiyat -->
                                    <div class="wm-plan-price wm-price-monthly">
                                        <?php if ($mp > 0): ?>
                                            <span class="wm-plan-amount"><?php echo number_format($mp, 2, ',', '.'); ?> ₺</span>
                                            <span class="wm-plan-period">/ ay</span>
                                        <?php else: ?>
                                            <span class="wm-plan-amount wm-na">—</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Yıllık Fiyat -->
                                    <div class="wm-plan-price wm-price-yearly" style="display:none;">
                                        <?php if ($yp > 0): ?>
                                            <span class="wm-plan-amount"><?php echo number_format($yp, 2, ',', '.'); ?> ₺</span>
                                            <span class="wm-plan-period">/ yıl</span>
                                            <?php if ($yearly_per_month > 0): ?>
                                                <div class="wm-plan-permonth">aylık ~<?php echo number_format($yearly_per_month, 0, ',', '.'); ?> ₺</div>
                                            <?php endif; ?>
                                            <?php if ($save_pct > 0): ?>
                                                <div class="wm-plan-save">%<?php echo $save_pct; ?> tasarruf</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="wm-plan-amount wm-na">—</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="wm-plan-features">
                                        <div class="wm-plan-feature">✓ Üyelere özel içerikler</div>
                                        <div class="wm-plan-feature">✓ Kısıtlı sayfa erişimi</div>
                                        <?php if ($p->duration > 0): ?>
                                            <div class="wm-plan-feature">✓ <?php echo $p->duration; ?> gün süre</div>
                                        <?php else: ?>
                                            <div class="wm-plan-feature">✓ Süresiz erişim</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- REFERANS & NOT -->
                    <div class="wm-apply-fields">
                        <div class="wm-apply-field">
                            <label>🧾 Dekont / Referans Numarası <span style="color:#d32f2f;">*</span></label>
                            <input type="text" id="wm-apply-referans" placeholder="Havale/EFT dekont referans numaranızı girin">
                            <small>Ödemenizi yaptıktan sonra banka dekontundaki referans numarasını buraya yazın.</small>
                        </div>
                        <div class="wm-apply-field">
                            <label>Not (isteğe bağlı)</label>
                            <textarea id="wm-apply-note" rows="2" placeholder="Eklemek istediğiniz bir not varsa yazabilirsiniz..."></textarea>
                        </div>
                    </div>

                    <div id="wm-apply-result" style="display:none;padding:12px 16px;border-radius:8px;margin-bottom:14px;font-size:14px;"></div>

                    <button type="button" id="wm-apply-btn" class="wm-apply-submit">
                        🚀 Başvuruyu Gönder
                    </button>
                </div>

                <script>
                    (function() {
                        // Period toggle
                        var periodBtns = document.querySelectorAll('.wm-period-btn');
                        var periodHidden = document.getElementById('wm-selected-period');
                        var priceMonthly = document.querySelectorAll('.wm-price-monthly');
                        var priceYearly = document.querySelectorAll('.wm-price-yearly');

                        periodBtns.forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                periodBtns.forEach(function(b) { b.classList.remove('active'); });
                                this.classList.add('active');
                                var period = this.getAttribute('data-period');
                                periodHidden.value = period;
                                priceMonthly.forEach(function(el) { el.style.display = (period === 'monthly') ? '' : 'none'; });
                                priceYearly.forEach(function(el) { el.style.display = (period === 'yearly') ? '' : 'none'; });
                            });
                        });

                        // Card selection
                        var cards = document.querySelectorAll('.wm-plan-card');
                        cards.forEach(function(card) {
                            card.addEventListener('click', function() {
                                cards.forEach(function(c) { c.classList.remove('selected'); });
                                this.classList.add('selected');
                                this.querySelector('input[type=radio]').checked = true;
                            });
                            if (card.querySelector('input[type=radio]').checked) card.classList.add('selected');
                        });

                        // Submit
                        document.getElementById('wm-apply-btn').addEventListener('click', function() {
                            var btn = this;
                            var planRadio = document.querySelector('input[name="wm_plan_select"]:checked');
                            var period = periodHidden.value;
                            var referans = document.getElementById('wm-apply-referans').value.trim();
                            var note = document.getElementById('wm-apply-note').value;
                            var result = document.getElementById('wm-apply-result');

                            if (!planRadio) {
                                result.style.display = 'block';
                                result.style.background = '#fff3e0';
                                result.style.color = '#e65100';
                                result.textContent = 'Lütfen bir plan seçiniz.';
                                return;
                            }
                            if (!referans) {
                                result.style.display = 'block';
                                result.style.background = '#fff3e0';
                                result.style.color = '#e65100';
                                result.textContent = 'Lütfen dekont / referans numarasını giriniz.';
                                return;
                            }
                            btn.disabled = true;
                            btn.style.opacity = '0.6';
                            btn.textContent = 'Gönderiliyor...';
                            var fd = new FormData();
                            fd.append('action', 'webyaz_membership_apply');
                            fd.append('plan_id', planRadio.value);
                            fd.append('period', period);
                            fd.append('referans_no', referans);
                            fd.append('note', note);
                            fd.append('_ajax_nonce', '<?php echo wp_create_nonce("webyaz_membership_apply"); ?>');
                            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                                    method: 'POST',
                                    body: fd
                                })
                                .then(function(r) { return r.json(); })
                                .then(function(d) {
                                    result.style.display = 'block';
                                    if (d.success) {
                                        result.style.background = '#e6f9e6';
                                        result.style.color = '#22863a';
                                        result.textContent = d.data.message || 'Başvurunuz alındı!';
                                        document.getElementById('wm-apply-referans').value = '';
                                        document.getElementById('wm-apply-note').value = '';
                                    } else {
                                        result.style.background = '#ffeef0';
                                        result.style.color = '#d32f2f';
                                        result.textContent = d.data.message || 'Bir hata oluştu.';
                                    }
                                    btn.disabled = false;
                                    btn.style.opacity = '1';
                                    btn.textContent = '🚀 Başvuruyu Gönder';
                                });
                        });
                    })();
                </script>
            <?php endif; ?>

            <!-- ÜYELERE ÖZEL İÇERİKLER -->
            <?php
            // Tüm kısıtlı içerikleri topla
            $restricted_items = array();

            // 1. Post meta ile kısıtlanan içerikler
            global $wpdb;
            $meta_results = $wpdb->get_results(
                "SELECT p.ID, p.post_title, p.post_type, pm.meta_value 
             FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE pm.meta_key = '_webyaz_membership_plans' 
             AND p.post_status = 'publish'"
            );
            foreach ($meta_results as $mr) {
                $plans = maybe_unserialize($mr->meta_value);
                if (!empty($plans) && is_array($plans)) {
                    $restricted_items[$mr->ID] = array(
                        'id' => $mr->ID,
                        'title' => $mr->post_title,
                        'type' => $mr->post_type,
                        'url' => get_permalink($mr->ID),
                        'source' => 'plan',
                        'plans' => $plans,
                    );
                }
            }

            // 2. Ayarlardan kısıtlanan yazılar
            if (!empty($opts['restrict_posts'])) {
                foreach ($opts['restrict_posts'] as $pid) {
                    $pid = intval($pid);
                    if (!isset($restricted_items[$pid])) {
                        $p = get_post($pid);
                        if ($p && $p->post_status === 'publish') {
                            $restricted_items[$pid] = array(
                                'id' => $pid,
                                'title' => $p->post_title,
                                'type' => 'post',
                                'url' => get_permalink($pid),
                                'source' => 'settings',
                            );
                        }
                    }
                }
            }

            // 3. Ayarlardan kısıtlanan sayfalar
            if (!empty($opts['restrict_pages'])) {
                foreach ($opts['restrict_pages'] as $pid) {
                    $pid = intval($pid);
                    if (!isset($restricted_items[$pid])) {
                        $p = get_post($pid);
                        if ($p && $p->post_status === 'publish') {
                            $restricted_items[$pid] = array(
                                'id' => $pid,
                                'title' => $p->post_title,
                                'type' => 'page',
                                'url' => get_permalink($pid),
                                'source' => 'settings',
                            );
                        }
                    }
                }
            }

            // 4. Kısıtlı kategorilerdeki yazılar
            if (!empty($opts['restrict_categories'])) {
                $cat_posts = get_posts(array(
                    'numberposts' => -1,
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'category__in' => array_map('intval', $opts['restrict_categories']),
                ));
                foreach ($cat_posts as $cp) {
                    if (!isset($restricted_items[$cp->ID])) {
                        $restricted_items[$cp->ID] = array(
                            'id' => $cp->ID,
                            'title' => $cp->post_title,
                            'type' => 'post',
                            'url' => get_permalink($cp->ID),
                            'source' => 'category',
                        );
                    }
                }
            }

            // Kullanıcının aktif plan ID'lerini topla
            $user_active_plan_ids = array();
            foreach ($active_plans as $ap) {
                $user_active_plan_ids[] = intval($ap['data']['plan_id']);
            }

            // İçerikleri erişilebilir/kilitli olarak ayır
            $accessible_items = array();
            $locked_items = array();
            foreach ($restricted_items as $ri) {
                $can_access = false;
                if (isset($ri['plans']) && is_array($ri['plans'])) {
                    // Plan bazlı kısıtlama - kullanıcının planı eşleşiyor mu?
                    foreach ($user_active_plan_ids as $upid) {
                        if (in_array($upid, $ri['plans'])) {
                            $can_access = true;
                            break;
                        }
                    }
                } elseif ($ri['source'] === 'settings' || $ri['source'] === 'category') {
                    // Ayarlardan/kategoriden kısıtlama - herhangi bir aktif plan yeterli
                    $can_access = !empty($user_active_plan_ids);
                }

                // Plan isimlerini ekle
                $plan_names = array();
                if (isset($ri['plans']) && is_array($ri['plans'])) {
                    foreach ($ri['plans'] as $rpid) {
                        $rp = self::get_plan($rpid);
                        if ($rp) $plan_names[] = $rp->name;
                    }
                }
                $ri['plan_names'] = $plan_names;
                $ri['can_access'] = $can_access;

                if ($can_access) {
                    $accessible_items[] = $ri;
                } else {
                    $locked_items[] = $ri;
                }
            }
            ?>

            <?php if (!empty($restricted_items)): ?>
                <div style="margin-top:28px;">
                    <h3 style="margin:0 0 14px;font-size:17px;color:#333;display:flex;align-items:center;gap:8px;">
                        📚 Uyelere Ozel Icerikler
                        <span style="background:#eef;color:#4f46e5;font-size:12px;padding:3px 10px;border-radius:12px;font-weight:600;"><?php echo count($restricted_items); ?> icerik</span>
                    </h3>

                    <?php if (!empty($accessible_items)): ?>
                        <div style="background:#e6f9e6;border:1px solid #c8e6c9;border-radius:10px;padding:14px 18px;margin-bottom:14px;font-size:13px;color:#2e7d32;">
                            🔓 Planınızla erisebileceginiz icerikler (<?php echo count($accessible_items); ?> adet)
                        </div>
                        <div style="display:grid;gap:8px;margin-bottom:20px;">
                            <?php foreach ($accessible_items as $ri):
                                $type_label = ($ri['type'] === 'page') ? '📄 Sayfa' : '📝 Yazi';
                            ?>
                                <a href="<?php echo esc_url($ri['url']); ?>" style="display:flex;align-items:center;gap:12px;padding:14px 18px;background:#f0faf0;border:1px solid #c8e6c9;border-radius:10px;text-decoration:none;color:#333;transition:all .2s;"
                                    onmouseover="this.style.background='#e6f9e6';this.style.borderColor='#22863a'"
                                    onmouseout="this.style.background='#f0faf0';this.style.borderColor='#c8e6c9'">
                                    <span style="font-size:18px;">🔓</span>
                                    <div style="flex:1;">
                                        <div style="font-weight:600;font-size:14px;"><?php echo esc_html($ri['title']); ?></div>
                                        <div style="font-size:11px;color:#999;margin-top:2px;">
                                            <?php echo $type_label; ?>
                                            <?php if (!empty($ri['plan_names'])): ?>
                                                <?php foreach ($ri['plan_names'] as $pn): ?>
                                                    <span style="background:#e6f9e6;color:#22863a;padding:1px 6px;border-radius:4px;margin-left:4px;font-size:10px;"><?php echo esc_html($pn); ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span style="font-size:18px;color:#22863a;">→</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($locked_items)): ?>
                        <div style="background:#fff3e0;border:1px solid #ffe0b2;border-radius:10px;padding:14px 18px;margin-bottom:14px;font-size:13px;color:#e65100;">
                            🔒 Asagidaki iceriklere erisebilmek icin ilgili plana abone olmaniz gerekmektedir.
                        </div>
                        <div style="display:grid;gap:8px;">
                            <?php foreach ($locked_items as $ri):
                                $type_label = ($ri['type'] === 'page') ? '📄 Sayfa' : '📝 Yazi';
                            ?>
                                <div style="display:flex;align-items:center;gap:12px;padding:14px 18px;background:#fafafa;border:1px solid #eee;border-radius:10px;opacity:0.7;">
                                    <span style="font-size:18px;">🔒</span>
                                    <div style="flex:1;">
                                        <div style="font-weight:600;font-size:14px;color:#888;"><?php echo esc_html($ri['title']); ?></div>
                                        <div style="font-size:11px;color:#bbb;margin-top:2px;">
                                            <?php echo $type_label; ?>
                                            <?php if (!empty($ri['plan_names'])): ?>
                                                <?php foreach ($ri['plan_names'] as $pn): ?>
                                                    <span style="background:#fff3e0;color:#e65100;padding:1px 6px;border-radius:4px;margin-left:4px;font-size:10px;"><?php echo esc_html($pn); ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <style>
            /* ===== MEMBERSHIP PREMIUM PLAN KARTLARI ===== */
            .wm-apply-wrap {
                margin-top: 30px;
                background: linear-gradient(135deg, #f8f9ff 0%, #eef1ff 100%);
                border: 2px solid #d4daff;
                border-radius: 16px;
                padding: 32px;
            }

            .wm-apply-title {
                margin: 0 0 6px;
                font-size: 20px;
                color: #1a1a2e;
                font-weight: 800;
            }

            .wm-apply-desc {
                margin: 0 0 22px;
                font-size: 13px;
                color: #777;
            }

            .wm-bank-box {
                margin-bottom: 22px;
                background: #fff;
                border: 1px solid #e0e6ff;
                border-radius: 12px;
                padding: 18px;
            }

            /* PERIOD TOGGLE */
            .wm-period-toggle {
                display: flex;
                gap: 0;
                background: #e8ecf4;
                border-radius: 12px;
                padding: 4px;
                margin-bottom: 20px;
            }

            .wm-period-btn {
                flex: 1;
                padding: 12px 20px;
                border: none;
                background: transparent;
                border-radius: 10px;
                font-size: 14px;
                font-weight: 700;
                color: #666;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
            }

            .wm-period-btn.active {
                background: #fff;
                color: #4f46e5;
                box-shadow: 0 2px 8px rgba(79, 70, 229, 0.15);
            }

            .wm-save-badge {
                display: inline-block;
                background: linear-gradient(135deg, #059669, #10b981);
                color: #fff;
                font-size: 10px;
                padding: 2px 8px;
                border-radius: 10px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }

            /* PLAN CARDS GRID */
            .wm-plan-cards {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 16px;
                margin-bottom: 24px;
            }

            .wm-plan-card {
                cursor: pointer;
                position: relative;
            }

            .wm-plan-card input[type=radio] {
                position: absolute;
                opacity: 0;
                pointer-events: none;
            }

            .wm-plan-card-inner {
                background: #fff;
                border: 2px solid #e0e0e0;
                border-radius: 16px;
                padding: 24px 20px;
                text-align: center;
                transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
                position: relative;
                overflow: hidden;
                height: 100%;
                box-sizing: border-box;
            }

            .wm-plan-card-inner::before {
                content: '';
                position: absolute;
                top: 0; left: 0; right: 0;
                height: 4px;
                background: var(--plan-color);
                opacity: 0.4;
                transition: all 0.3s ease;
            }

            .wm-plan-card:hover .wm-plan-card-inner {
                border-color: var(--plan-color);
                transform: translateY(-4px);
                box-shadow: 0 12px 32px rgba(0,0,0,0.1);
            }

            .wm-plan-card:hover .wm-plan-card-inner::before {
                height: 6px;
                opacity: 1;
            }

            .wm-plan-card.selected .wm-plan-card-inner {
                border-color: var(--plan-color);
                box-shadow: 0 0 0 3px color-mix(in srgb, var(--plan-color) 20%, transparent),
                            0 12px 32px rgba(0,0,0,0.12);
                transform: translateY(-6px);
            }

            .wm-plan-card.selected .wm-plan-card-inner::before {
                height: 6px;
                opacity: 1;
            }

            .wm-plan-badge {
                position: absolute;
                top: 14px;
                right: -28px;
                background: linear-gradient(135deg, #4f46e5, #7c3aed);
                color: #fff;
                font-size: 10px;
                font-weight: 700;
                padding: 4px 32px;
                transform: rotate(45deg);
                letter-spacing: 0.5px;
                text-transform: uppercase;
            }

            .wm-plan-dot {
                width: 14px;
                height: 14px;
                border-radius: 50%;
                margin: 0 auto 10px;
            }

            .wm-plan-name {
                margin: 0 0 6px;
                font-size: 18px;
                font-weight: 800;
                color: #1a1a2e;
            }

            .wm-plan-desc {
                margin: 0 0 14px;
                font-size: 12px;
                color: #888;
                line-height: 1.5;
            }

            .wm-plan-price {
                margin-bottom: 6px;
            }

            .wm-plan-amount {
                font-size: 26px;
                font-weight: 800;
                color: var(--plan-color);
            }

            .wm-plan-amount.wm-na {
                font-size: 20px;
                color: #ccc;
            }

            .wm-plan-period {
                font-size: 13px;
                color: #999;
                font-weight: 500;
            }

            .wm-plan-permonth {
                font-size: 11px;
                color: #888;
                margin-top: 2px;
            }

            .wm-plan-save {
                display: inline-block;
                background: linear-gradient(135deg, #059669, #10b981);
                color: #fff;
                font-size: 11px;
                font-weight: 700;
                padding: 3px 10px;
                border-radius: 12px;
                margin-top: 6px;
            }

            .wm-plan-features {
                margin-top: 14px;
                padding-top: 14px;
                border-top: 1px solid #f0f0f0;
                text-align: left;
            }

            .wm-plan-feature {
                font-size: 12px;
                color: #666;
                padding: 3px 0;
            }

            /* FORM FIELDS */
            .wm-apply-fields {
                display: grid;
                gap: 14px;
                margin-bottom: 14px;
            }

            .wm-apply-field label {
                display: block;
                font-size: 13px;
                font-weight: 600;
                color: #444;
                margin-bottom: 6px;
            }

            .wm-apply-field input,
            .wm-apply-field textarea {
                width: 100%;
                padding: 10px 12px;
                border: 1px solid #ddd;
                border-radius: 8px;
                font-size: 14px;
                box-sizing: border-box;
                resize: vertical;
            }

            .wm-apply-field small {
                font-size: 11px;
                color: #999;
                display: block;
                margin-top: 4px;
            }

            /* SUBMIT */
            .wm-apply-submit {
                width: 100%;
                padding: 16px;
                background: linear-gradient(135deg, #4f46e5, #7c3aed);
                color: #fff;
                border: none;
                border-radius: 12px;
                font-size: 16px;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
                letter-spacing: 0.3px;
            }

            .wm-apply-submit:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
            }

            /* RESPONSIVE */
            @media (max-width: 600px) {
                .wm-apply-wrap {
                    padding: 18px;
                }
                .wm-plan-cards {
                    grid-template-columns: 1fr;
                }
                .wm-plan-card-inner {
                    padding: 18px 14px;
                }
                .wm-plan-amount {
                    font-size: 22px;
                }
                .wm-period-toggle {
                    flex-direction: column;
                }
            }
        </style>

        </div>
    <?php
    }

    // === AJAX: Uyelik Basvurusu (Odeme Bildirimi) ===
    public function ajax_membership_apply()
    {
        check_ajax_referer('webyaz_membership_apply');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Giris yapmaniz gerekmektedir.'));

        $plan_id    = intval($_POST['plan_id'] ?? 0);
        $period     = sanitize_text_field($_POST['period'] ?? 'monthly');
        $referans   = sanitize_text_field($_POST['referans_no'] ?? '');
        $note       = sanitize_textarea_field($_POST['note'] ?? '');
        $plan       = self::get_plan($plan_id);
        if (!$plan) wp_send_json_error(array('message' => 'Gecersiz plan.'));
        if (empty($referans)) wp_send_json_error(array('message' => 'Dekont / referans numarasi zorunludur.'));

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        $price = ($period === 'yearly') ? $plan->yearly_price : $plan->monthly_price;

        $applications = get_option('webyaz_membership_applications', array());
        $applications[] = array(
            'user_id'     => $user_id,
            'user_name'   => $user ? $user->display_name : '',
            'user_email'  => $user ? $user->user_email : '',
            'plan_id'     => $plan_id,
            'plan_name'   => $plan->name,
            'period'      => $period,
            'price'       => $price,
            'referans_no' => $referans,
            'note'        => $note,
            'status'      => 'pending',
            'date'        => current_time('mysql'),
        );
        update_option('webyaz_membership_applications', $applications);

        // === E-POSTA: Admin'e yeni basvuru bildirimi ===
        if (class_exists('Webyaz_Email_Templates')) {
            $admin_email = get_option('admin_email');
            $period_label = ($period === 'yearly') ? 'Yıllık' : 'Aylık';
            $body = '<p style="font-size:15px;margin:0 0 16px;">Merhaba,</p>';
            $body .= '<p>Yeni bir üyelik başvurusu alındı. Detaylar:</p>';
            $body .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f8f9ff;border-radius:12px;overflow:hidden;margin:16px 0;">';
            $body .= '<tr><td style="padding:12px 16px;border-bottom:1px solid #eee;color:#888;font-size:12px;width:120px;">Kullanıcı</td><td style="padding:12px 16px;border-bottom:1px solid #eee;font-weight:600;">' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')</td></tr>';
            $body .= '<tr><td style="padding:12px 16px;border-bottom:1px solid #eee;color:#888;font-size:12px;">Plan</td><td style="padding:12px 16px;border-bottom:1px solid #eee;font-weight:600;">' . esc_html($plan->name) . ' (' . $period_label . ')</td></tr>';
            $body .= '<tr><td style="padding:12px 16px;border-bottom:1px solid #eee;color:#888;font-size:12px;">Tutar</td><td style="padding:12px 16px;border-bottom:1px solid #eee;font-weight:700;color:#d26e4b;">' . number_format($price, 2, ',', '.') . ' ₺</td></tr>';
            $body .= '<tr><td style="padding:12px 16px;border-bottom:1px solid #eee;color:#888;font-size:12px;">Referans No</td><td style="padding:12px 16px;border-bottom:1px solid #eee;font-family:monospace;">' . esc_html($referans) . '</td></tr>';
            if (!empty($note)) {
                $body .= '<tr><td style="padding:12px 16px;color:#888;font-size:12px;">Not</td><td style="padding:12px 16px;">' . esc_html($note) . '</td></tr>';
            }
            $body .= '</table>';
            $body .= '<p style="margin:16px 0 0;"><a href="' . admin_url('admin.php?page=webyaz-membership&tab=applications') . '" style="display:inline-block;padding:12px 28px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:8px;font-weight:700;">Başvuruları İncele →</a></p>';
            Webyaz_Email_Templates::send_branded_email($admin_email, '📋 Yeni Üyelik Başvurusu — ' . esc_html($user->display_name), 'Yeni Üyelik Başvurusu', $body, '📋 Yeni Başvuru', '#4f46e5');
        }

        wp_send_json_success(array('message' => 'Odeme bildiriminiz alindi! Dekont kontrolu yapildiktan sonra uyeliginiz aktif edilecektir.'));
    }

    // === AJAX: Admin Basvuru Onayla ===
    public function ajax_approve_application()
    {
        check_ajax_referer('webyaz_nonce');
        if (!current_user_can('manage_options')) wp_die('Yetkiniz yok.');

        $index = intval($_POST['app_index'] ?? -1);
        $applications = get_option('webyaz_membership_applications', array());
        if (!isset($applications[$index])) wp_send_json_error(array('message' => 'Basvuru bulunamadi.'));

        $app = $applications[$index];
        $plan = self::get_plan($app['plan_id']);
        if (!$plan) wp_send_json_error(array('message' => 'Plan bulunamadi.'));

        // Plani kullaniciya ata
        $user_id = $app['user_id'];
        $period = isset($app['period']) ? $app['period'] : 'monthly';
        $duration = $plan->duration;
        if ($period === 'yearly') {
            $duration = ($duration > 0) ? $duration * 12 : 365;
        } elseif ($duration <= 0) {
            $duration = 30;
        }

        $plans = get_user_meta($user_id, '_webyaz_membership_user_plans', true);
        if (!is_array($plans)) $plans = array();
        $plans[] = array(
            'plan_id'     => $app['plan_id'],
            'assigned_at' => current_time('mysql'),
            'expires'     => date('Y-m-d H:i:s', strtotime('+' . $duration . ' days')),
        );
        update_user_meta($user_id, '_webyaz_membership_user_plans', $plans);

        // Basvuruyu onayli olarak isaretle
        $applications[$index]['status'] = 'approved';
        $applications[$index]['approved_at'] = current_time('mysql');
        update_option('webyaz_membership_applications', $applications);

        // === E-POSTA: Kullaniciya onay bildirimi ===
        if (class_exists('Webyaz_Email_Templates') && !empty($app['user_email'])) {
            $user_name = $app['user_name'] ?? 'Değerli Üyemiz';
            $expire_date = date('d.m.Y', strtotime('+' . $duration . ' days'));
            $body = '<p style="font-size:15px;margin:0 0 16px;">Merhaba <strong>' . esc_html($user_name) . '</strong>,</p>';
            $body .= '<p>Üyelik başvurunuz <strong>onaylanmıştır</strong>! 🎉 Artık tüm üyelik avantajlarından yararlanabilirsiniz.</p>';
            $body .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;overflow:hidden;margin:16px 0;">';
            $body .= '<tr><td style="padding:12px 16px;border-bottom:1px solid #dcfce7;color:#15803d;font-size:12px;width:120px;">Plan</td><td style="padding:12px 16px;border-bottom:1px solid #dcfce7;font-weight:700;color:#15803d;">' . esc_html($plan->name) . '</td></tr>';
            $body .= '<tr><td style="padding:12px 16px;border-bottom:1px solid #dcfce7;color:#15803d;font-size:12px;">Süre</td><td style="padding:12px 16px;border-bottom:1px solid #dcfce7;font-weight:600;">' . $duration . ' gün</td></tr>';
            $body .= '<tr><td style="padding:12px 16px;color:#15803d;font-size:12px;">Bitiş Tarihi</td><td style="padding:12px 16px;font-weight:600;">' . $expire_date . '</td></tr>';
            $body .= '</table>';
            $body .= '<p style="margin:16px 0 0;"><a href="' . wc_get_account_endpoint_url('membership') . '" style="display:inline-block;padding:12px 28px;background:#22c55e;color:#fff;text-decoration:none;border-radius:8px;font-weight:700;">Üyeliğimi Görüntüle →</a></p>';
            Webyaz_Email_Templates::send_branded_email($app['user_email'], '✅ Üyeliğiniz Aktif Edildi!', 'Üyeliğiniz Onaylandı', $body, '✅ Onaylandı', '#22c55e');
        }

        wp_send_json_success(array('message' => 'Basvuru onaylandi ve plan aktif edildi.'));
    }

    // === AJAX: Admin Basvuru Reddet ===
    public function ajax_reject_application()
    {
        check_ajax_referer('webyaz_nonce');
        if (!current_user_can('manage_options')) wp_die('Yetkiniz yok.');

        $index = intval($_POST['app_index'] ?? -1);
        $admin_note = sanitize_text_field($_POST['admin_note'] ?? '');
        $applications = get_option('webyaz_membership_applications', array());
        if (!isset($applications[$index])) wp_send_json_error(array('message' => 'Basvuru bulunamadi.'));

        $applications[$index]['status'] = 'rejected';
        $applications[$index]['admin_note'] = $admin_note;
        $applications[$index]['rejected_at'] = current_time('mysql');
        update_option('webyaz_membership_applications', $applications);

        // === E-POSTA: Kullaniciya red bildirimi ===
        $app = $applications[$index];
        if (class_exists('Webyaz_Email_Templates') && !empty($app['user_email'])) {
            $user_name = $app['user_name'] ?? 'Değerli Kullanıcımız';
            $body = '<p style="font-size:15px;margin:0 0 16px;">Merhaba <strong>' . esc_html($user_name) . '</strong>,</p>';
            $body .= '<p>Üyelik başvurunuz maalesef <strong>reddedilmiştir</strong>.</p>';
            if (!empty($admin_note)) {
                $body .= '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 18px;margin:16px 0;">';
                $body .= '<div style="font-size:12px;font-weight:700;color:#991b1b;margin-bottom:4px;">Red Sebebi:</div>';
                $body .= '<div style="font-size:13px;color:#dc2626;">' . esc_html($admin_note) . '</div>';
                $body .= '</div>';
            }
            $body .= '<p>Yeniden başvuru yapabilirsiniz. Sorularınız için bizimle iletişime geçmekten çekinmeyin.</p>';
            $body .= '<p style="margin:16px 0 0;"><a href="' . wc_get_account_endpoint_url('membership') . '" style="display:inline-block;padding:12px 28px;background:#dc2626;color:#fff;text-decoration:none;border-radius:8px;font-weight:700;">Yeniden Başvur →</a></p>';
            Webyaz_Email_Templates::send_branded_email($app['user_email'], '❌ Üyelik Başvurunuz Reddedildi', 'Başvurunuz Reddedildi', $body, '❌ Reddedildi', '#dc2626');
        }

        wp_send_json_success(array('message' => 'Basvuru reddedildi.'));
    }

    // === AJAX ISLEMLERI ===
    public function ajax_save_plan()
    {
        check_ajax_referer('webyaz_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Yetkiniz yok');
        global $wpdb;
        $id = intval($_POST['plan_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $base_slug = sanitize_title($name);
        if (empty($name)) {
            wp_send_json_error(array('message' => 'Plan adi bos olamaz'));
            return;
        }

        // Slug benzersizligini sagla
        $slug = $base_slug;
        $counter = 2;
        while (true) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM " . self::$table_name . " WHERE slug = %s AND id != %d",
                $slug,
                $id
            ));
            if (!$existing) break;
            $slug = $base_slug . '-' . $counter;
            $counter++;
        }

        $data = array(
            'name'          => $name,
            'slug'          => $slug,
            'description'   => sanitize_textarea_field($_POST['description'] ?? ''),
            'color'         => sanitize_hex_color($_POST['color'] ?? '#446084') ?: '#446084',
            'duration'      => intval($_POST['duration'] ?? 0),
            'monthly_price' => floatval($_POST['monthly_price'] ?? 0),
            'yearly_price'  => floatval($_POST['yearly_price'] ?? 0),
            'status'        => intval($_POST['status'] ?? 1),
            'priority'      => intval($_POST['priority'] ?? 0),
        );
        if ($id > 0) {
            $result = $wpdb->update(self::$table_name, $data, array('id' => $id));
        } else {
            $result = $wpdb->insert(self::$table_name, $data);
            $id = $wpdb->insert_id;
        }
        if ($result === false) {
            wp_send_json_error(array('message' => 'Veritabani hatasi: ' . $wpdb->last_error));
            return;
        }
        wp_send_json_success(array('id' => $id, 'message' => 'Plan kaydedildi'));
    }

    public function ajax_delete_plan()
    {
        check_ajax_referer('webyaz_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Yetkiniz yok');
        global $wpdb;
        $id = intval($_POST['plan_id'] ?? 0);
        $wpdb->delete(self::$table_name, array('id' => $id));
        wp_send_json_success(array('message' => 'Plan silindi'));
    }

    public function ajax_assign_user()
    {
        check_ajax_referer('webyaz_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Yetkiniz yok');
        $user_id = intval($_POST['user_id'] ?? 0);
        $plan_id = intval($_POST['plan_id'] ?? 0);
        $duration = intval($_POST['duration'] ?? 0);
        if (!$user_id || !$plan_id) wp_send_json_error('Gecersiz veri');
        $plans = get_user_meta($user_id, '_webyaz_membership_user_plans', true);
        if (!is_array($plans)) $plans = array();
        // Ayni plan varsa guncelle
        $found = false;
        foreach ($plans as $k => $p) {
            if ($p['plan_id'] == $plan_id) {
                $plans[$k]['expires'] = $duration > 0 ? date('Y-m-d H:i:s', strtotime("+{$duration} days")) : '';
                $plans[$k]['status'] = 'active';
                $plans[$k]['assigned_at'] = current_time('mysql');
                $found = true;
                break;
            }
        }
        if (!$found) {
            $plans[] = array(
                'plan_id'     => $plan_id,
                'expires'     => $duration > 0 ? date('Y-m-d H:i:s', strtotime("+{$duration} days")) : '',
                'status'      => 'active',
                'assigned_at' => current_time('mysql'),
            );
        }
        update_user_meta($user_id, '_webyaz_membership_user_plans', $plans);
        wp_send_json_success(array('message' => 'Kullaniciya plan atandi'));
    }

    public function ajax_remove_user()
    {
        check_ajax_referer('webyaz_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Yetkiniz yok');
        $user_id = intval($_POST['user_id'] ?? 0);
        $plan_id = intval($_POST['plan_id'] ?? 0);
        $plans = get_user_meta($user_id, '_webyaz_membership_user_plans', true);
        if (!is_array($plans)) $plans = array();
        $plans = array_filter($plans, function ($p) use ($plan_id) {
            return $p['plan_id'] != $plan_id;
        });
        update_user_meta($user_id, '_webyaz_membership_user_plans', array_values($plans));
        wp_send_json_success(array('message' => 'Plan kaldirildi'));
    }

    public function ajax_search_users()
    {
        check_ajax_referer('webyaz_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die('Yetkiniz yok');
        $q = sanitize_text_field($_POST['q'] ?? '');
        $users = get_users(array('search' => "*{$q}*", 'number' => 10));
        $result = array();
        foreach ($users as $u) {
            $up = get_user_meta($u->ID, '_webyaz_membership_user_plans', true);
            $result[] = array('id' => $u->ID, 'name' => $u->display_name, 'email' => $u->user_email, 'plans' => is_array($up) ? $up : array());
        }
        wp_send_json_success($result);
    }

    // === ADMIN MENU ===
    public function add_submenu()
    {
        add_submenu_page('webyaz-dashboard', 'Uyelik Sistemi', 'Uyelik Sistemi', 'manage_options', 'webyaz-membership', array($this, 'render_admin'));
    }

    // === ADMIN SAYFA ===
    public function render_admin()
    {
        $opts = self::get_opts();
        $plans = self::get_plans();
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'plans';

        // Ayarlari kaydet
        if (isset($_POST['webyaz_membership_save_settings']) && wp_verify_nonce($_POST['_wpnonce_membership'], 'webyaz_membership_settings')) {
            $save = array(
                'restrict_mode'    => sanitize_text_field($_POST['restrict_mode'] ?? 'message'),
                'redirect_url'     => esc_url_raw($_POST['redirect_url'] ?? ''),
                'message_title'    => sanitize_text_field($_POST['message_title'] ?? ''),
                'message_text'     => sanitize_textarea_field($_POST['message_text'] ?? ''),
                'message_bg'       => sanitize_hex_color($_POST['message_bg'] ?? '#fff3e0'),
                'message_color'    => sanitize_hex_color($_POST['message_color'] ?? '#e65100'),
                'show_login'       => isset($_POST['show_login']) ? '1' : '0',
                'teaser_mode'      => isset($_POST['teaser_mode']) ? '1' : '0',
                'teaser_length'    => intval($_POST['teaser_length'] ?? 200),
                'restrict_categories' => isset($_POST['restrict_categories']) ? array_map('intval', $_POST['restrict_categories']) : array(),
                'restrict_posts'   => isset($_POST['restrict_posts']) ? array_map('intval', $_POST['restrict_posts']) : array(),
                'restrict_pages'   => isset($_POST['restrict_pages']) ? array_map('intval', $_POST['restrict_pages']) : array(),
                'bank_name'        => sanitize_text_field($_POST['bank_name'] ?? ''),
                'bank_iban'        => sanitize_text_field($_POST['bank_iban'] ?? ''),
                'bank_holder'      => sanitize_text_field($_POST['bank_holder'] ?? ''),
            );
            update_option('webyaz_membership_opts', $save);
            $opts = $save;
            echo '<div class="webyaz-notice success">Ayarlar kaydedildi!</div>';
        }
    ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Uyelik Sistemi</h1>
                <p>Sayfa ve yazi kisitlama, uyelik planlari yonetimi</p>
            </div>

            <div style="display:flex;gap:0;margin-bottom:20px;border-bottom:2px solid #e0e0e0;">
                <?php
                $tabs = array('plans' => 'Planlar', 'applications' => '📋 Basvurular', 'users' => 'Kullanicilar', 'settings' => 'Ayarlar', 'guide' => '📖 Rehber');
                $app_count = count(array_filter(get_option('webyaz_membership_applications', array()), function ($a) {
                    return $a['status'] === 'pending';
                }));
                foreach ($tabs as $k => $v):
                    $active = $tab === $k;
                    $badge = ($k === 'applications' && $app_count > 0) ? ' <span style="background:#d32f2f;color:#fff;font-size:11px;padding:2px 7px;border-radius:10px;margin-left:4px;">' . $app_count . '</span>' : '';
                ?>
                    <a href="?page=webyaz-membership&tab=<?php echo $k; ?>" style="padding:12px 24px;text-decoration:none;font-weight:600;font-size:14px;color:<?php echo $active ? '#446084' : '#666'; ?>;border-bottom:3px solid <?php echo $active ? '#446084' : 'transparent'; ?>;margin-bottom:-2px;transition:0.2s;"><?php echo $v . $badge; ?></a>
                <?php endforeach; ?>
            </div>

            <?php
            if ($tab === 'plans') $this->render_plans_tab($plans);
            elseif ($tab === 'applications') $this->render_applications_tab($plans);
            elseif ($tab === 'users') $this->render_users_tab($plans);
            elseif ($tab === 'settings') $this->render_settings_tab($opts);
            elseif ($tab === 'guide') $this->render_guide_tab();
            ?>
        </div>
    <?php
    }

    private function render_plans_tab($plans)
    {
        $active_count = 0;
        $min_price = PHP_INT_MAX;
        $max_price = 0;
        foreach ($plans as $p) {
            if ($p->status) $active_count++;
            if ($p->monthly_price > 0) { $min_price = min($min_price, $p->monthly_price); $max_price = max($max_price, $p->monthly_price); }
            if ($p->yearly_price > 0) { $min_price = min($min_price, $p->yearly_price); $max_price = max($max_price, $p->yearly_price); }
        }
        if ($min_price === PHP_INT_MAX) $min_price = 0;
    ?>
        <style>
            .wma-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:14px; margin-bottom:24px; }
            .wma-stat { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px 22px; display:flex; align-items:center; gap:14px; }
            .wma-stat-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:22px; }
            .wma-stat-num { font-size:24px; font-weight:800; color:#1a1a2e; }
            .wma-stat-label { font-size:12px; color:#888; margin-top:2px; }

            .wma-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
            .wma-header h3 { margin:0; font-size:18px; color:#1a1a2e; font-weight:700; }
            .wma-add-btn { display:inline-flex; align-items:center; gap:6px; padding:10px 22px; background:linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff; border:none; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; transition:all .3s; box-shadow:0 4px 12px rgba(79,70,229,0.25); }
            .wma-add-btn:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(79,70,229,0.35); }

            .wma-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:18px; }
            .wma-card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; overflow:hidden; transition:all .3s ease; position:relative; }
            .wma-card:hover { transform:translateY(-4px); box-shadow:0 12px 35px rgba(0,0,0,0.1); }
            .wma-card-top { padding:22px 22px 14px; position:relative; }
            .wma-card-top::before { content:''; position:absolute; top:0;left:0;right:0;height:5px; background:var(--card-color); }
            .wma-card-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:6px; }
            .wma-card-name { display:flex; align-items:center; gap:10px; }
            .wma-card-dot { width:14px; height:14px; border-radius:50%; flex-shrink:0; }
            .wma-card-title { font-size:17px; font-weight:700; color:#1a1a2e; margin:0; }
            .wma-card-badge { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700; }
            .wma-card-desc { font-size:12px; color:#888; margin:0 0 10px; line-height:1.5; }

            .wma-card-prices { display:grid; grid-template-columns:1fr 1fr; gap:10px; background:#f8f9ff; border-radius:10px; padding:14px; margin-bottom:12px; }
            .wma-price-box { text-align:center; }
            .wma-price-label { font-size:10px; text-transform:uppercase; letter-spacing:0.5px; color:#888; font-weight:600; margin-bottom:2px; }
            .wma-price-val { font-size:18px; font-weight:800; color:var(--card-color); }
            .wma-price-na { font-size:14px; color:#ccc; }

            .wma-card-pills { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:14px; }
            .wma-pill { padding:4px 10px; background:#f0f4f8; border-radius:8px; font-size:11px; color:#555; font-weight:600; }

            .wma-card-actions { display:flex; gap:8px; padding:14px 22px; border-top:1px solid #f0f0f0; background:#fafafa; }
            .wma-btn-edit { flex:1; padding:8px; background:#fff; border:1px solid #d4daff; color:#4f46e5; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; transition:all .2s; text-align:center; }
            .wma-btn-edit:hover { background:#eef1ff; border-color:#4f46e5; }
            .wma-btn-clone { padding:8px 12px; background:#fff; border:1px solid #d1fae5; color:#059669; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; transition:all .2s; }
            .wma-btn-clone:hover { background:#ecfdf5; border-color:#059669; }
            .wma-btn-del { padding:8px 12px; background:#fff; border:1px solid #fde8e8; color:#d32f2f; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; transition:all .2s; }
            .wma-btn-del:hover { background:#fef2f2; border-color:#d32f2f; }

            /* MODAL */
            .wma-modal-bg { display:none; position:fixed; top:0;left:0;right:0;bottom:0; background:rgba(0,0,0,0.55); z-index:999999; align-items:center; justify-content:center; backdrop-filter:blur(3px); }
            .wma-modal { background:#fff; border-radius:18px; width:560px; max-width:92vw; max-height:90vh; overflow-y:auto; box-shadow:0 24px 60px rgba(0,0,0,0.3); }
            .wma-modal-header { padding:22px 28px; border-bottom:1px solid #eee; display:flex; align-items:center; justify-content:space-between; }
            .wma-modal-header h3 { margin:0; font-size:19px; font-weight:700; color:#1a1a2e; }
            .wma-modal-close { width:32px; height:32px; border-radius:8px; border:none; background:#f3f4f6; color:#666; font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .2s; }
            .wma-modal-close:hover { background:#e5e7eb; }
            .wma-modal-body { padding:24px 28px; }
            .wma-modal-section { margin-bottom:20px; }
            .wma-modal-section-title { font-size:13px; text-transform:uppercase; letter-spacing:0.8px; color:#999; font-weight:700; margin:0 0 12px; display:flex; align-items:center; gap:6px; }
            .wma-modal-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
            .wma-modal-field { margin-bottom:14px; }
            .wma-modal-field label { display:block; font-size:12px; font-weight:600; color:#555; margin-bottom:5px; }
            .wma-modal-field input, .wma-modal-field textarea, .wma-modal-field select { width:100%; padding:10px 14px; border:1px solid #e5e7eb; border-radius:9px; font-size:14px; box-sizing:border-box; transition:border-color .2s; }
            .wma-modal-field input:focus, .wma-modal-field textarea:focus, .wma-modal-field select:focus { outline:none; border-color:#4f46e5; box-shadow:0 0 0 3px rgba(79,70,229,0.1); }
            .wma-modal-field textarea { resize:vertical; }
            .wma-modal-field input[type=color] { height:42px; padding:4px; cursor:pointer; }
            .wma-modal-footer { padding:16px 28px; border-top:1px solid #eee; display:flex; gap:10px; justify-content:flex-end; background:#fafafa; border-radius:0 0 18px 18px; }
            .wma-modal-cancel { padding:10px 22px; background:#fff; border:1px solid #ddd; color:#666; border-radius:9px; font-size:13px; font-weight:600; cursor:pointer; transition:all .2s; }
            .wma-modal-cancel:hover { background:#f3f4f6; }
            .wma-modal-save { padding:10px 26px; background:linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff; border:none; border-radius:9px; font-size:13px; font-weight:700; cursor:pointer; transition:all .2s; box-shadow:0 2px 8px rgba(79,70,229,0.25); }
            .wma-modal-save:hover { transform:translateY(-1px); box-shadow:0 4px 15px rgba(79,70,229,0.35); }

            .wma-empty { text-align:center; padding:60px 20px; background:linear-gradient(135deg,#f8f9ff,#eef1ff); border:2px dashed #d4daff; border-radius:16px; }
            .wma-empty-icon { font-size:56px; margin-bottom:14px; }
            .wma-empty-text { font-size:16px; color:#888; margin:0 0 6px; }
            .wma-empty-sub { font-size:13px; color:#bbb; margin:0 0 20px; }
        </style>

        <!-- STATS -->
        <div class="wma-stats">
            <div class="wma-stat">
                <div class="wma-stat-icon" style="background:#eef1ff;color:#4f46e5;">📦</div>
                <div><div class="wma-stat-num"><?php echo count($plans); ?></div><div class="wma-stat-label">Toplam Plan</div></div>
            </div>
            <div class="wma-stat">
                <div class="wma-stat-icon" style="background:#ecfdf5;color:#059669;">✅</div>
                <div><div class="wma-stat-num"><?php echo $active_count; ?></div><div class="wma-stat-label">Aktif Plan</div></div>
            </div>
            <div class="wma-stat">
                <div class="wma-stat-icon" style="background:#fef3c7;color:#d97706;">💰</div>
                <div>
                    <div class="wma-stat-num" style="font-size:16px;">
                        <?php if ($max_price > 0): ?>
                            <?php echo number_format($min_price, 0, ',', '.'); ?> — <?php echo number_format($max_price, 0, ',', '.'); ?> ₺
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                    <div class="wma-stat-label">Fiyat Aralığı</div>
                </div>
            </div>
        </div>

        <!-- HEADER -->
        <div class="wma-header">
            <h3>📋 Üyelik Planları</h3>
            <button type="button" onclick="webyazMembershipShowPlanModal()" class="wma-add-btn">➕ Yeni Plan Oluştur</button>
        </div>

        <?php if (empty($plans)): ?>
            <div class="wma-empty">
                <div class="wma-empty-icon">📋</div>
                <p class="wma-empty-text">Henüz üyelik planı oluşturulmamış</p>
                <p class="wma-empty-sub">İlk planınızı oluşturarak başlayın. Aylık ve yıllık fiyatlandırma belirleyin.</p>
                <button type="button" onclick="webyazMembershipShowPlanModal()" class="wma-add-btn">➕ İlk Planı Oluştur</button>
            </div>
        <?php else: ?>
            <div class="wma-grid">
                <?php foreach ($plans as $plan): ?>
                    <div class="wma-card" style="--card-color:<?php echo esc_attr($plan->color); ?>">
                        <div class="wma-card-top">
                            <div class="wma-card-head">
                                <div class="wma-card-name">
                                    <div class="wma-card-dot" style="background:<?php echo esc_attr($plan->color); ?>;"></div>
                                    <h4 class="wma-card-title"><?php echo esc_html($plan->name); ?></h4>
                                </div>
                                <span class="wma-card-badge" style="background:<?php echo $plan->status ? '#ecfdf5' : '#fef2f2'; ?>;color:<?php echo $plan->status ? '#059669' : '#d32f2f'; ?>;">
                                    <?php echo $plan->status ? '● Aktif' : '○ Pasif'; ?>
                                </span>
                            </div>
                            <?php if ($plan->description): ?>
                                <p class="wma-card-desc"><?php echo esc_html($plan->description); ?></p>
                            <?php endif; ?>

                            <div class="wma-card-prices">
                                <div class="wma-price-box">
                                    <div class="wma-price-label">📅 Aylık</div>
                                    <?php if ($plan->monthly_price > 0): ?>
                                        <div class="wma-price-val"><?php echo number_format($plan->monthly_price, 2, ',', '.'); ?> ₺</div>
                                    <?php else: ?>
                                        <div class="wma-price-na">—</div>
                                    <?php endif; ?>
                                </div>
                                <div class="wma-price-box">
                                    <div class="wma-price-label">📆 Yıllık</div>
                                    <?php if ($plan->yearly_price > 0): ?>
                                        <div class="wma-price-val"><?php echo number_format($plan->yearly_price, 2, ',', '.'); ?> ₺</div>
                                    <?php else: ?>
                                        <div class="wma-price-na">—</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="wma-card-pills">
                                <span class="wma-pill">⏱ <?php echo $plan->duration > 0 ? $plan->duration . ' gün' : 'Süresiz'; ?></span>
                                <span class="wma-pill">📊 Öncelik: <?php echo $plan->priority; ?></span>
                                <span class="wma-pill" style="background:#f0f4ff;color:#4f46e5;">#<?php echo $plan->id; ?></span>
                            </div>
                        </div>

                        <div class="wma-card-actions">
                            <button type="button" onclick='webyazMembershipShowPlanModal(<?php echo json_encode($plan); ?>)' class="wma-btn-edit">✏️ Düzenle</button>
                            <button type="button" onclick='webyazMembershipClonePlan(<?php echo json_encode($plan); ?>)' class="wma-btn-clone" title="Klonla">📋</button>
                            <button type="button" onclick="webyazMembershipDeletePlan(<?php echo $plan->id; ?>)" class="wma-btn-del" title="Sil">🗑️</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- PREMIUM MODAL -->
        <div id="webyaz-plan-modal" class="wma-modal-bg">
            <div class="wma-modal">
                <div class="wma-modal-header">
                    <h3 id="webyaz-plan-modal-title">Yeni Plan</h3>
                    <button type="button" class="wma-modal-close" onclick="document.getElementById('webyaz-plan-modal').style.display='none'">✕</button>
                </div>
                <div class="wma-modal-body">
                    <input type="hidden" id="wm-plan-id" value="0">

                    <div class="wma-modal-section">
                        <div class="wma-modal-section-title">📝 Temel Bilgiler</div>
                        <div class="wma-modal-field">
                            <label>Plan Adı *</label>
                            <input type="text" id="wm-plan-name" placeholder="Örn: Premium Üyelik">
                        </div>
                        <div class="wma-modal-field">
                            <label>Açıklama</label>
                            <textarea id="wm-plan-desc" rows="2" placeholder="Bu planın kısa açıklaması..."></textarea>
                        </div>
                    </div>

                    <div class="wma-modal-section">
                        <div class="wma-modal-section-title">💰 Fiyatlandırma</div>
                        <div class="wma-modal-row">
                            <div class="wma-modal-field">
                                <label>📅 Aylık Fiyat (₺)</label>
                                <input type="number" id="wm-plan-monthly" value="0" min="0" step="0.01" placeholder="0.00">
                            </div>
                            <div class="wma-modal-field">
                                <label>📆 Yıllık Fiyat (₺)</label>
                                <input type="number" id="wm-plan-yearly" value="0" min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <div class="wma-modal-section">
                        <div class="wma-modal-section-title">⚙️ Görünüm & Ayarlar</div>
                        <div class="wma-modal-row">
                            <div class="wma-modal-field">
                                <label>🎨 Renk</label>
                                <input type="color" id="wm-plan-color" value="#446084">
                            </div>
                            <div class="wma-modal-field">
                                <label>⏱ Süre (gün, 0=süresiz)</label>
                                <input type="number" id="wm-plan-duration" value="0" min="0">
                            </div>
                        </div>
                        <div class="wma-modal-row">
                            <div class="wma-modal-field">
                                <label>📊 Öncelik (sıralama)</label>
                                <input type="number" id="wm-plan-priority" value="0" min="0">
                            </div>
                            <div class="wma-modal-field">
                                <label>Durum</label>
                                <select id="wm-plan-status">
                                    <option value="1">✅ Aktif</option>
                                    <option value="0">⏸ Pasif</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="wma-modal-footer">
                    <button type="button" class="wma-modal-cancel" onclick="document.getElementById('webyaz-plan-modal').style.display='none'">İptal</button>
                    <button type="button" class="wma-modal-save" onclick="webyazMembershipSavePlan()">💾 Kaydet</button>
                </div>
            </div>
        </div>

        <script>
            function webyazMembershipShowPlanModal(plan) {
                var m = document.getElementById('webyaz-plan-modal');
                document.getElementById('webyaz-plan-modal-title').textContent = plan ? '✏️ Planı Düzenle' : '➕ Yeni Plan Oluştur';
                document.getElementById('wm-plan-id').value = plan ? plan.id : 0;
                document.getElementById('wm-plan-name').value = plan ? plan.name : '';
                document.getElementById('wm-plan-desc').value = plan ? (plan.description || '') : '';
                document.getElementById('wm-plan-monthly').value = plan ? (plan.monthly_price || 0) : 0;
                document.getElementById('wm-plan-yearly').value = plan ? (plan.yearly_price || 0) : 0;
                document.getElementById('wm-plan-color').value = plan ? plan.color : '#446084';
                document.getElementById('wm-plan-duration').value = plan ? plan.duration : 0;
                document.getElementById('wm-plan-priority').value = plan ? plan.priority : 0;
                document.getElementById('wm-plan-status').value = plan ? plan.status : 1;
                m.style.display = 'flex';
            }

            function webyazMembershipClonePlan(plan) {
                var clone = Object.assign({}, plan);
                clone.id = 0;
                clone.name = plan.name + ' (Kopya)';
                webyazMembershipShowPlanModal(clone);
            }

            function webyazMembershipSavePlan() {
                var name = document.getElementById('wm-plan-name').value.trim();
                if (!name) { alert('Plan adı boş olamaz!'); return; }
                var saveBtn = document.querySelector('.wma-modal-save');
                saveBtn.textContent = 'Kaydediliyor...';
                saveBtn.disabled = true;
                jQuery.post(ajaxurl, {
                    action: 'webyaz_membership_save_plan',
                    nonce: '<?php echo wp_create_nonce("webyaz_nonce"); ?>',
                    plan_id: document.getElementById('wm-plan-id').value,
                    name: name,
                    description: document.getElementById('wm-plan-desc').value,
                    monthly_price: document.getElementById('wm-plan-monthly').value,
                    yearly_price: document.getElementById('wm-plan-yearly').value,
                    color: document.getElementById('wm-plan-color').value,
                    duration: document.getElementById('wm-plan-duration').value,
                    priority: document.getElementById('wm-plan-priority').value,
                    status: document.getElementById('wm-plan-status').value,
                }, function(r) {
                    if (r.success) location.reload();
                    else {
                        alert('Hata: ' + (r.data && r.data.message ? r.data.message : 'Bilinmeyen hata'));
                        saveBtn.textContent = '💾 Kaydet';
                        saveBtn.disabled = false;
                    }
                });
            }

            function webyazMembershipDeletePlan(id) {
                if (!confirm('Bu planı silmek istediğinize emin misiniz?\nBu işlem geri alınamaz.')) return;
                jQuery.post(ajaxurl, {
                    action: 'webyaz_membership_delete_plan',
                    nonce: '<?php echo wp_create_nonce("webyaz_nonce"); ?>',
                    plan_id: id
                }, function(r) {
                    if (r.success) location.reload();
                });
            }

            // Backdrop click to close
            document.getElementById('webyaz-plan-modal').addEventListener('click', function(e) {
                if (e.target === this) this.style.display = 'none';
            });
        </script>
    <?php
    }

    // === BASVURULAR SEKMESI ===
    private function render_applications_tab($plans)
    {
        $applications = get_option('webyaz_membership_applications', array());
        $pending = array();
        $other = array();
        foreach ($applications as $idx => $app) {
            $app['_index'] = $idx;
            if ($app['status'] === 'pending') $pending[] = $app;
            else $other[] = $app;
        }
    ?>
        <div>
            <h3 style="margin:0 0 16px;color:#333;">📋 Üyelik Başvuruları</h3>

            <?php if (empty($pending) && empty($other)): ?>
                <div style="text-align:center;padding:40px 20px;color:#999;">
                    <div style="font-size:48px;margin-bottom:10px;">📭</div>
                    <p style="font-size:15px;">Henüz hiç başvuru yok.</p>
                </div>
            <?php else: ?>

                <!-- BEKLEYEN BASVURULAR -->
                <?php if (!empty($pending)): ?>
                    <h4 style="margin:0 0 12px;font-size:15px;color:#e65100;">⏳ Onay Bekleyenler (<?php echo count($pending); ?>)</h4>
                    <div style="overflow-x:auto;margin-bottom:30px;">
                        <table style="width:100%;border-collapse:collapse;font-size:13px;">
                            <thead>
                                <tr style="background:#fff8e1;border-bottom:2px solid #ffe082;">
                                    <th style="padding:10px 14px;text-align:left;font-weight:700;color:#555;">Kullanıcı</th>
                                    <th style="padding:10px 14px;text-align:left;font-weight:700;color:#555;">Plan</th>
                                    <th style="padding:10px 14px;text-align:left;font-weight:700;color:#555;">Dönem</th>
                                    <th style="padding:10px 14px;text-align:left;font-weight:700;color:#555;">Tutar</th>
                                    <th style="padding:10px 14px;text-align:left;font-weight:700;color:#555;">Dekont Ref</th>
                                    <th style="padding:10px 14px;text-align:left;font-weight:700;color:#555;">Not</th>
                                    <th style="padding:10px 14px;text-align:left;font-weight:700;color:#555;">Tarih</th>
                                    <th style="padding:10px 14px;text-align:center;font-weight:700;color:#555;">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending as $app):
                                    $plan = self::get_plan($app['plan_id']);
                                    $plan_name = $plan ? $plan->name : ('Plan #' . $app['plan_id']);
                                    $period_label = ($app['period'] === 'yearly') ? 'Yıllık' : 'Aylık';
                                    $price = isset($app['price']) ? number_format($app['price'], 2, ',', '.') . ' ₺' : '-';
                                ?>
                                    <tr style="border-bottom:1px solid #f0f0f0;" id="wm-app-row-<?php echo $app['_index']; ?>">
                                        <td style="padding:12px 14px;">
                                            <strong><?php echo esc_html($app['user_name'] ?? ''); ?></strong>
                                            <div style="font-size:11px;color:#999;"><?php echo esc_html($app['user_email'] ?? ''); ?></div>
                                        </td>
                                        <td style="padding:12px 14px;"><?php echo esc_html($plan_name); ?></td>
                                        <td style="padding:12px 14px;"><?php echo $period_label; ?></td>
                                        <td style="padding:12px 14px;font-weight:600;color:#333;"><?php echo $price; ?></td>
                                        <td style="padding:12px 14px;font-family:monospace;font-weight:700;color:#1565c0;"><?php echo esc_html($app['referans_no'] ?? '-'); ?></td>
                                        <td style="padding:12px 14px;font-size:12px;color:#666;max-width:150px;"><?php echo esc_html($app['note'] ?? ''); ?></td>
                                        <td style="padding:12px 14px;font-size:12px;color:#888;"><?php echo date('d.m.Y H:i', strtotime($app['date'])); ?></td>
                                        <td style="padding:12px 14px;text-align:center;white-space:nowrap;">
                                            <button type="button" onclick="wmApproveApp(<?php echo $app['_index']; ?>)" class="webyaz-btn webyaz-btn-primary" style="padding:6px 14px;font-size:12px;margin-right:4px;background:#22863a;border-color:#22863a;">✅ Onayla</button>
                                            <button type="button" onclick="wmRejectApp(<?php echo $app['_index']; ?>)" class="webyaz-btn" style="padding:6px 14px;font-size:12px;background:#d32f2f;color:#fff;border-color:#d32f2f;">❌ Reddet</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- GECMIS BASVURULAR -->
                <?php if (!empty($other)): ?>
                    <h4 style="margin:20px 0 12px;font-size:14px;color:#888;">📁 Geçmiş Başvurular</h4>
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:12px;opacity:0.85;">
                            <thead>
                                <tr style="background:#f5f5f5;border-bottom:1px solid #e0e0e0;">
                                    <th style="padding:8px 12px;text-align:left;color:#777;">Kullanıcı</th>
                                    <th style="padding:8px 12px;text-align:left;color:#777;">Plan</th>
                                    <th style="padding:8px 12px;text-align:left;color:#777;">Dekont Ref</th>
                                    <th style="padding:8px 12px;text-align:left;color:#777;">Durum</th>
                                    <th style="padding:8px 12px;text-align:left;color:#777;">Tarih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_reverse($other) as $app):
                                    $plan = self::get_plan($app['plan_id']);
                                    $plan_name = $plan ? $plan->name : ('Plan #' . $app['plan_id']);
                                    $status_label = $app['status'] === 'approved' ? '<span style="color:#22863a;font-weight:600;">✅ Onaylı</span>' : '<span style="color:#d32f2f;font-weight:600;">❌ Reddedildi</span>';
                                ?>
                                    <tr style="border-bottom:1px solid #f5f5f5;">
                                        <td style="padding:8px 12px;"><?php echo esc_html($app['user_name'] ?? ''); ?> <span style="color:#aaa;font-size:11px;">(<?php echo esc_html($app['user_email'] ?? ''); ?>)</span></td>
                                        <td style="padding:8px 12px;"><?php echo esc_html($plan_name); ?></td>
                                        <td style="padding:8px 12px;font-family:monospace;"><?php echo esc_html($app['referans_no'] ?? '-'); ?></td>
                                        <td style="padding:8px 12px;"><?php echo $status_label; ?></td>
                                        <td style="padding:8px 12px;color:#aaa;"><?php echo date('d.m.Y H:i', strtotime($app['date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

        <script>
            function wmApproveApp(index) {
                if (!confirm('Bu başvuruyu onaylamak ve üyeliği aktif etmek istediğinize emin misiniz?')) return;
                var fd = new FormData();
                fd.append('action', 'webyaz_membership_approve_app');
                fd.append('app_index', index);
                fd.append('_ajax_nonce', '<?php echo wp_create_nonce("webyaz_nonce"); ?>');
                fetch(ajaxurl, {
                        method: 'POST',
                        body: fd
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(d) {
                        if (d.success) {
                            alert('✅ ' + d.data.message);
                            location.reload();
                        } else {
                            alert('Hata: ' + (d.data.message || 'Bilinmeyen hata'));
                        }
                    });
            }

            function wmRejectApp(index) {
                var note = prompt('Red sebebi (isteğe bağlı):');
                if (note === null) return;
                var fd = new FormData();
                fd.append('action', 'webyaz_membership_reject_app');
                fd.append('app_index', index);
                fd.append('admin_note', note);
                fd.append('_ajax_nonce', '<?php echo wp_create_nonce("webyaz_nonce"); ?>');
                fetch(ajaxurl, {
                        method: 'POST',
                        body: fd
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(d) {
                        if (d.success) {
                            alert('❌ ' + d.data.message);
                            location.reload();
                        } else {
                            alert('Hata: ' + (d.data.message || 'Bilinmeyen hata'));
                        }
                    });
            }
        </script>
    <?php
    }

    private function render_users_tab($plans)
    {
    ?>
        <div style="margin-bottom:20px;">
            <h3 style="margin:0 0 12px;color:#333;">Kullanici Uyelik Yonetimi</h3>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="text" id="wm-user-search" placeholder="Kullanici ara (isim/e-posta)..." style="flex:1;min-width:200px;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                <button type="button" onclick="webyazMembershipSearchUsers()" class="webyaz-btn webyaz-btn-primary">Ara</button>
            </div>
        </div>
        <div id="wm-user-results" style="min-height:60px;"></div>

        <!-- Atama Modal -->
        <div id="webyaz-assign-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:999999;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:14px;padding:28px;width:380px;max-width:90vw;box-shadow:0 20px 50px rgba(0,0,0,0.3);">
                <h3 style="margin:0 0 18px;font-size:18px;">Plan Ata</h3>
                <input type="hidden" id="wm-assign-user-id" value="0">
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div><label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px;">Plan</label>
                        <select id="wm-assign-plan" style="width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:7px;font-size:14px;">
                            <?php foreach ($plans as $p): ?>
                                <option value="<?php echo $p->id; ?>"><?php echo esc_html($p->name); ?> (<?php echo $p->duration > 0 ? $p->duration . ' gun' : 'Sinirsiz'; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px;">Sure (gun, 0=plan varsayilani)</label><input type="number" id="wm-assign-duration" value="0" min="0" style="width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:7px;font-size:14px;box-sizing:border-box;"></div>
                </div>
                <div style="display:flex;gap:10px;margin-top:18px;justify-content:flex-end;">
                    <button type="button" onclick="document.getElementById('webyaz-assign-modal').style.display='none'" class="webyaz-btn webyaz-btn-outline">Iptal</button>
                    <button type="button" onclick="webyazMembershipAssignUser()" class="webyaz-btn webyaz-btn-primary">Ata</button>
                </div>
            </div>
        </div>

        <script>
            var wmPlans = <?php echo json_encode($plans); ?>;

            function webyazMembershipSearchUsers() {
                var q = document.getElementById('wm-user-search').value;
                jQuery.post(ajaxurl, {
                    action: 'webyaz_membership_search_users',
                    nonce: '<?php echo wp_create_nonce("webyaz_nonce"); ?>',
                    q: q
                }, function(r) {
                    if (!r.success) return;
                    var html = '';
                    if (r.data.length === 0) {
                        html = '<p style="color:#999;text-align:center;padding:20px;">Kullanici bulunamadi.</p>';
                    }
                    r.data.forEach(function(u) {
                        var planBadges = '';
                        u.plans.forEach(function(p) {
                            var info = wmPlans.find(function(x) {
                                return x.id == p.plan_id;
                            });
                            if (!info) return;
                            var exp = p.expires ? new Date(p.expires).toLocaleDateString('tr-TR') : 'Sinirsiz';
                            var st = (p.expires && new Date(p.expires) < new Date()) ? '⛔' : '✅';
                            planBadges += '<span style="display:inline-flex;align-items:center;gap:4px;background:#f0f4f8;padding:3px 10px;border-radius:12px;font-size:11px;margin:2px;">' + st + ' <span style="width:6px;height:6px;border-radius:50%;background:' + info.color + ';"></span> ' + info.name + ' (' + exp + ')<button type="button" onclick="webyazMembershipRemoveUser(' + u.id + ',' + p.plan_id + ')" style="background:none;border:none;color:#d32f2f;cursor:pointer;font-size:14px;padding:0 2px;" title="Kaldir">&times;</button></span>';
                        });
                        html += '<div class="webyaz-card" style="margin-bottom:8px;"><div style="padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;"><div><strong>' + u.name + '</strong> <span style="color:#999;font-size:12px;">(' + u.email + ')</span><div style="margin-top:4px;">' + planBadges + '</div></div><button type="button" onclick="webyazMembershipShowAssignModal(' + u.id + ')" class="webyaz-btn webyaz-btn-primary" style="padding:6px 14px;font-size:12px;">Plan Ata</button></div></div>';
                    });
                    document.getElementById('wm-user-results').innerHTML = html;
                });
            }

            function webyazMembershipShowAssignModal(uid) {
                document.getElementById('wm-assign-user-id').value = uid;
                document.getElementById('webyaz-assign-modal').style.display = 'flex';
            }

            function webyazMembershipAssignUser() {
                var planId = document.getElementById('wm-assign-plan').value;
                var plan = wmPlans.find(function(x) {
                    return x.id == planId;
                });
                var dur = parseInt(document.getElementById('wm-assign-duration').value);
                if (dur === 0 && plan) dur = plan.duration;
                jQuery.post(ajaxurl, {
                    action: 'webyaz_membership_assign_user',
                    nonce: '<?php echo wp_create_nonce("webyaz_nonce"); ?>',
                    user_id: document.getElementById('wm-assign-user-id').value,
                    plan_id: planId,
                    duration: dur
                }, function(r) {
                    if (r.success) {
                        document.getElementById('webyaz-assign-modal').style.display = 'none';
                        webyazMembershipSearchUsers();
                    }
                });
            }

            function webyazMembershipRemoveUser(uid, pid) {
                if (!confirm('Bu plani kaldirmak istediginize emin misiniz?')) return;
                jQuery.post(ajaxurl, {
                    action: 'webyaz_membership_remove_user',
                    nonce: '<?php echo wp_create_nonce("webyaz_nonce"); ?>',
                    user_id: uid,
                    plan_id: pid
                }, function(r) {
                    if (r.success) webyazMembershipSearchUsers();
                });
            }
            document.getElementById('wm-user-search').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') webyazMembershipSearchUsers();
            });
        </script>
    <?php
    }

    private function render_settings_tab($opts)
    {
        $categories = get_categories(array('hide_empty' => false));
        $all_posts = get_posts(array('numberposts' => -1, 'post_type' => 'post', 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC'));
        $all_pages = get_pages(array('sort_column' => 'post_title', 'sort_order' => 'ASC'));
    ?>
        <form method="post">
            <?php wp_nonce_field('webyaz_membership_settings', '_wpnonce_membership'); ?>
            <div class="webyaz-settings-section">
                <h3 style="margin:0 0 16px;font-size:16px;color:#333;">Kisitlama Ayarlari</h3>
                <div class="webyaz-settings-grid">
                    <div class="webyaz-field">
                        <label>Kisitlama Modu</label>
                        <select name="restrict_mode">
                            <option value="message" <?php selected($opts['restrict_mode'], 'message'); ?>>Mesaj Goster</option>
                            <option value="redirect" <?php selected($opts['restrict_mode'], 'redirect'); ?>>Sayfaya Yonlendir</option>
                        </select>
                    </div>
                    <div class="webyaz-field">
                        <label>Yonlendirme URL (redirect modunda)</label>
                        <input type="url" name="redirect_url" value="<?php echo esc_attr($opts['redirect_url']); ?>" placeholder="https://...">
                    </div>
                    <div class="webyaz-field">
                        <label>Mesaj Basligi</label>
                        <input type="text" name="message_title" value="<?php echo esc_attr($opts['message_title']); ?>">
                    </div>
                    <div class="webyaz-field">
                        <label>Mesaj Metni</label>
                        <textarea name="message_text" rows="3"><?php echo esc_textarea($opts['message_text']); ?></textarea>
                    </div>
                    <div class="webyaz-field">
                        <label>Mesaj Arkaplan</label>
                        <input type="color" name="message_bg" value="<?php echo esc_attr($opts['message_bg']); ?>">
                    </div>
                    <div class="webyaz-field">
                        <label>Mesaj Rengi</label>
                        <input type="color" name="message_color" value="<?php echo esc_attr($opts['message_color']); ?>">
                    </div>
                    <div class="webyaz-field">
                        <label><input type="checkbox" name="show_login" value="1" <?php checked($opts['show_login'], '1'); ?>> Giris butonu goster</label>
                    </div>
                    <div class="webyaz-field">
                        <label><input type="checkbox" name="teaser_mode" value="1" <?php checked($opts['teaser_mode'], '1'); ?>> Teaser modu (icerigin baslangicini goster)</label>
                    </div>
                    <div class="webyaz-field">
                        <label>Teaser kelime sayisi</label>
                        <input type="number" name="teaser_length" value="<?php echo esc_attr($opts['teaser_length']); ?>" min="10" max="1000">
                    </div>
                </div>
            </div>

            <div class="webyaz-settings-section" style="margin-top:20px;">
                <h3 style="margin:0 0 16px;font-size:16px;color:#333;">Kategori Kisitlama</h3>
                <p style="font-size:13px;color:#666;margin:0 0 12px;">Secilen kategorilerdeki tum yazilar sadece uyeligi olan kullanicilara gosterilir.</p>
                <div style="max-height:200px;overflow-y:auto;border:1px solid #eee;border-radius:8px;padding:10px;">
                    <?php foreach ($categories as $cat): ?>
                        <label style="display:flex;align-items:center;gap:6px;padding:4px 0;cursor:pointer;font-size:13px;">
                            <input type="checkbox" name="restrict_categories[]" value="<?php echo $cat->term_id; ?>" <?php echo in_array($cat->term_id, $opts['restrict_categories']) ? 'checked' : ''; ?>>
                            <?php echo esc_html($cat->name); ?> <span style="color:#999;font-size:11px;">(<?php echo $cat->count; ?> yazi)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="webyaz-settings-section" style="margin-top:20px;">
                <h3 style="margin:0 0 16px;font-size:16px;color:#333;">📝 Yazi Kisitlama</h3>
                <p style="font-size:13px;color:#666;margin:0 0 12px;">Secilen yazilar sadece uyeligi olan kullanicilara gosterilir.</p>
                <div style="max-height:200px;overflow-y:auto;border:1px solid #eee;border-radius:8px;padding:10px;">
                    <?php if (!empty($all_posts)): ?>
                        <?php foreach ($all_posts as $p): ?>
                            <label style="display:flex;align-items:center;gap:6px;padding:4px 0;cursor:pointer;font-size:13px;">
                                <input type="checkbox" name="restrict_posts[]" value="<?php echo $p->ID; ?>" <?php echo in_array($p->ID, $opts['restrict_posts']) ? 'checked' : ''; ?>>
                                <?php echo esc_html($p->post_title); ?>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#999;font-size:13px;margin:0;">Henuz yazi bulunmuyor.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="webyaz-settings-section" style="margin-top:20px;">
                <h3 style="margin:0 0 16px;font-size:16px;color:#333;">📄 Sayfa Kisitlama</h3>
                <p style="font-size:13px;color:#666;margin:0 0 12px;">Secilen sayfalar sadece uyeligi olan kullanicilara gosterilir.</p>
                <div style="max-height:200px;overflow-y:auto;border:1px solid #eee;border-radius:8px;padding:10px;">
                    <?php if (!empty($all_pages)): ?>
                        <?php foreach ($all_pages as $pg): ?>
                            <label style="display:flex;align-items:center;gap:6px;padding:4px 0;cursor:pointer;font-size:13px;">
                                <input type="checkbox" name="restrict_pages[]" value="<?php echo $pg->ID; ?>" <?php echo in_array($pg->ID, $opts['restrict_pages']) ? 'checked' : ''; ?>>
                                <?php echo esc_html($pg->post_title); ?>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#999;font-size:13px;margin:0;">Henuz sayfa bulunmuyor.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="webyaz-settings-section" style="margin-top:20px;">
                <h3 style="margin:0 0 16px;font-size:16px;color:#333;">🏦 Banka / Havale Bilgileri</h3>
                <p style="font-size:13px;color:#666;margin:0 0 12px;">Kullanicilara gosterilecek banka bilgilerini girin. Bu bilgiler Hesabim &gt; Uyeligim sayfasinda gorunur.</p>
                <div class="webyaz-settings-grid">
                    <div class="webyaz-field">
                        <label>Banka Adi</label>
                        <input type="text" name="bank_name" value="<?php echo esc_attr($opts['bank_name']); ?>" placeholder="ornegin: Ziraat Bankasi">
                    </div>
                    <div class="webyaz-field">
                        <label>Hesap Sahibi</label>
                        <input type="text" name="bank_holder" value="<?php echo esc_attr($opts['bank_holder']); ?>" placeholder="Ad Soyad / Sirket Adi">
                    </div>
                    <div class="webyaz-field">
                        <label>IBAN</label>
                        <input type="text" name="bank_iban" value="<?php echo esc_attr($opts['bank_iban']); ?>" placeholder="TR00 0000 0000 0000 0000 0000 00">
                    </div>
                </div>
            </div>

            <div style="margin-top:20px;">
                <button type="submit" name="webyaz_membership_save_settings" value="1" class="webyaz-btn webyaz-btn-primary" style="padding:12px 28px;font-size:14px;">Ayarlari Kaydet</button>
            </div>
        </form>
<?php
    }

    // === KULLANIM REHBERI ===
    private function render_guide_tab()
    {
    ?>
        <style>
            .wmg-wrap { max-width:900px; }
            .wmg-intro { background:linear-gradient(135deg,#f8f9ff,#eef1ff); border:2px solid #d4daff; border-radius:16px; padding:28px; margin-bottom:24px; }
            .wmg-intro h3 { margin:0 0 8px; font-size:20px; color:#1a1a2e; font-weight:800; }
            .wmg-intro p { margin:0; font-size:14px; color:#666; line-height:1.7; }
            .wmg-section { background:#fff; border:1px solid #e5e7eb; border-radius:14px; margin-bottom:14px; overflow:hidden; transition:all .3s; }
            .wmg-section:hover { border-color:#d4daff; box-shadow:0 4px 15px rgba(0,0,0,0.05); }
            .wmg-section-head { display:flex; align-items:center; gap:14px; padding:20px 24px; cursor:pointer; user-select:none; }
            .wmg-section-num { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:15px; color:#fff; flex-shrink:0; }
            .wmg-section-title { font-size:16px; font-weight:700; color:#1a1a2e; flex:1; }
            .wmg-section-arrow { font-size:18px; color:#999; transition:transform .3s; }
            .wmg-section.open .wmg-section-arrow { transform:rotate(180deg); }
            .wmg-section-body { display:none; padding:0 24px 22px 74px; }
            .wmg-section.open .wmg-section-body { display:block; }
            .wmg-step { display:flex; gap:12px; margin-bottom:14px; align-items:flex-start; }
            .wmg-step-icon { width:28px; height:28px; border-radius:8px; background:#f0f4ff; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; margin-top:2px; }
            .wmg-step-text { font-size:13px; color:#555; line-height:1.7; }
            .wmg-step-text strong { color:#1a1a2e; }
            .wmg-step-text code { background:#f3f4f6; padding:2px 7px; border-radius:4px; font-size:12px; color:#4f46e5; }
            .wmg-tip { background:#fefce8; border:1px solid #fef08a; border-radius:10px; padding:14px 18px; margin-top:10px; }
            .wmg-tip-title { font-size:12px; font-weight:700; color:#854d0e; margin-bottom:4px; }
            .wmg-tip-text { font-size:12px; color:#92400e; line-height:1.6; }
        </style>

        <div class="wmg-wrap">
            <div class="wmg-intro">
                <h3>📖 Üyelik Sistemi Kullanım Rehberi</h3>
                <p>Bu rehber, üyelik sistemi modülünü nasıl kuracağınızı ve yöneteceğinizi adım adım anlatmaktadır. Aşağıdaki bölümlere tıklayarak detaylara ulaşabilirsiniz.</p>
            </div>

            <!-- BÖLÜM 1 -->
            <div class="wmg-section open">
                <div class="wmg-section-head" onclick="this.parentElement.classList.toggle('open')">
                    <div class="wmg-section-num" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);">1</div>
                    <div class="wmg-section-title">Üyelik Planı Oluşturma</div>
                    <span class="wmg-section-arrow">▼</span>
                </div>
                <div class="wmg-section-body">
                    <div class="wmg-step">
                        <div class="wmg-step-icon">📋</div>
                        <div class="wmg-step-text"><strong>Planlar</strong> sekmesine gidin ve <strong>"➕ Yeni Plan Oluştur"</strong> butonuna tıklayın.</div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">✏️</div>
                        <div class="wmg-step-text">Açılan modalda plan bilgilerini doldurun:<br>
                            • <strong>Plan Adı:</strong> Kullanıcılara gösterilecek isim (Örn: "Premium Üyelik")<br>
                            • <strong>Açıklama:</strong> Planın kısa tanıtımı<br>
                            • <strong>Renk:</strong> Planı temsil eden renk (kart görselinde kullanılır)
                        </div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">💾</div>
                        <div class="wmg-step-text"><strong>"Kaydet"</strong> butonuyla planı oluşturun. Plan kartı otomatik olarak listeye eklenir.</div>
                    </div>
                    <div class="wmg-tip">
                        <div class="wmg-tip-title">💡 İpucu</div>
                        <div class="wmg-tip-text">Mevcut bir planı klonlamak için plan kartındaki 📋 butonunu kullanabilirsiniz. Bu, benzer planlar oluşturmayı hızlandırır.</div>
                    </div>
                </div>
            </div>

            <!-- BÖLÜM 2 -->
            <div class="wmg-section">
                <div class="wmg-section-head" onclick="this.parentElement.classList.toggle('open')">
                    <div class="wmg-section-num" style="background:linear-gradient(135deg,#059669,#10b981);">2</div>
                    <div class="wmg-section-title">Fiyatlandırma Ayarları</div>
                    <span class="wmg-section-arrow">▼</span>
                </div>
                <div class="wmg-section-body">
                    <div class="wmg-step">
                        <div class="wmg-step-icon">📅</div>
                        <div class="wmg-step-text"><strong>Aylık Fiyat:</strong> Kullanıcıların aylık ödeyeceği tutar. 0 bırakırsanız aylık seçenek gösterilmez.</div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">📆</div>
                        <div class="wmg-step-text"><strong>Yıllık Fiyat:</strong> Yıllık ödeme tutarı. Aylık fiyattan daha düşük belirlemeniz önerilir — tasarruf yüzdesi otomatik hesaplanır ve kullanıcıya gösterilir.</div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">⏱️</div>
                        <div class="wmg-step-text"><strong>Süre (gün):</strong> Üyeliğin kaç gün geçerli olacağı. <code>0</code> girilirse süresiz üyelik olur. Yıllık seçeneklerde süre otomatik olarak 12 ile çarpılır.</div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">📊</div>
                        <div class="wmg-step-text"><strong>Öncelik:</strong> Planların sıralama önceliğini belirler. Düşük sayı = daha önce gösterilir.</div>
                    </div>
                    <div class="wmg-tip">
                        <div class="wmg-tip-title">💡 Hesaplama Örneği</div>
                        <div class="wmg-tip-text">Aylık: 99₺, Yıllık: 899₺ belirlediğinizde → Tasarruf: <strong>%24</strong>, Aylık eşdeğeri: <strong>74,92₺</strong> şeklinde otomatik gösterilir.</div>
                    </div>
                </div>
            </div>

            <!-- BÖLÜM 3 -->
            <div class="wmg-section">
                <div class="wmg-section-head" onclick="this.parentElement.classList.toggle('open')">
                    <div class="wmg-section-num" style="background:linear-gradient(135deg,#d97706,#f59e0b);">3</div>
                    <div class="wmg-section-title">İçerik Kısıtlama</div>
                    <span class="wmg-section-arrow">▼</span>
                </div>
                <div class="wmg-section-body">
                    <div class="wmg-step">
                        <div class="wmg-step-icon">⚙️</div>
                        <div class="wmg-step-text"><strong>Ayarlar</strong> sekmesine gidin. Kısıtlama modunu seçin:<br>
                            • <strong>Mesaj Göster:</strong> Kısıtlı içeriğe erişmeye çalışan kullanıcıya uyarı mesajı gösterilir<br>
                            • <strong>Sayfaya Yönlendir:</strong> Kullanıcı belirlediğiniz URL'ye yönlendirilir
                        </div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">📂</div>
                        <div class="wmg-step-text"><strong>Kategori Kısıtlama:</strong> Seçtiğiniz kategorilerdeki tüm içerikler otomatik olarak kısıtlanır.</div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">📝</div>
                        <div class="wmg-step-text"><strong>Yazı/Sayfa Kısıtlama:</strong> Belirli yazıları veya sayfaları tek tek seçerek kısıtlayabilirsiniz.</div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">🔍</div>
                        <div class="wmg-step-text"><strong>Teaser Modu:</strong> Kısıtlı içeriğin ilk birkaç kelimesini gösterir, devamı için üyelik gerektirir. Kelime sayısını ayarlayabilirsiniz.</div>
                    </div>
                    <div class="wmg-tip">
                        <div class="wmg-tip-title">⚠️ Önemli</div>
                        <div class="wmg-tip-text">Yazı düzenlerken de "Üyelik Planı" meta kutusundan hangi planlara ait olacağını belirleyebilirsiniz. Bu sayede farklı planlar farklı içeriklere erişebilir.</div>
                    </div>
                </div>
            </div>

            <!-- BÖLÜM 4 -->
            <div class="wmg-section">
                <div class="wmg-section-head" onclick="this.parentElement.classList.toggle('open')">
                    <div class="wmg-section-num" style="background:linear-gradient(135deg,#2563eb,#3b82f6);">4</div>
                    <div class="wmg-section-title">Banka Bilgileri Yapılandırma</div>
                    <span class="wmg-section-arrow">▼</span>
                </div>
                <div class="wmg-section-body">
                    <div class="wmg-step">
                        <div class="wmg-step-icon">🏦</div>
                        <div class="wmg-step-text"><strong>Ayarlar</strong> sekmesindeki <strong>"Banka / Havale Bilgileri"</strong> bölümünden:</div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">📋</div>
                        <div class="wmg-step-text">
                            • <strong>Banka Adı:</strong> Ödemenin yapılacağı banka (Örn: Ziraat Bankası)<br>
                            • <strong>Hesap Sahibi:</strong> Alıcı ad/soyad veya firma adı<br>
                            • <strong>IBAN:</strong> Havale/EFT için IBAN numarası
                        </div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">👁️</div>
                        <div class="wmg-step-text">Bu bilgiler kullanıcılara <strong>Hesabım → Üyeliğim</strong> sayfasındaki başvuru formunda otomatik gösterilir.</div>
                    </div>
                </div>
            </div>

            <!-- BÖLÜM 5 -->
            <div class="wmg-section">
                <div class="wmg-section-head" onclick="this.parentElement.classList.toggle('open')">
                    <div class="wmg-section-num" style="background:linear-gradient(135deg,#dc2626,#ef4444);">5</div>
                    <div class="wmg-section-title">Başvuru Onay/Red Süreci</div>
                    <span class="wmg-section-arrow">▼</span>
                </div>
                <div class="wmg-section-body">
                    <div class="wmg-step">
                        <div class="wmg-step-icon">📬</div>
                        <div class="wmg-step-text">Kullanıcı başvuru yaptığında <strong>"📋 Başvurular"</strong> sekmesinde red badge ile bildirim görürsünüz.</div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">🔍</div>
                        <div class="wmg-step-text">Başvurunun detaylarını inceleyin: Kullanıcı adı, seçtiği plan, dönem, tutar, dekont referans numarası ve notu.</div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">✅</div>
                        <div class="wmg-step-text"><strong>Onayla:</strong> Dekont doğrulandıysa onaylayın. Üyelik otomatik olarak aktif edilir ve süre başlar.</div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">❌</div>
                        <div class="wmg-step-text"><strong>Reddet:</strong> Red sebebi yazarak reddedin. Kullanıcı tekrar başvuru yapabilir.</div>
                    </div>
                    <div class="wmg-tip">
                        <div class="wmg-tip-title">💡 İpucu</div>
                        <div class="wmg-tip-text">Onaylanan ve reddedilen başvurular "Geçmiş Başvurular" bölümünde arşivlenir.</div>
                    </div>
                </div>
            </div>

            <!-- BÖLÜM 6 -->
            <div class="wmg-section">
                <div class="wmg-section-head" onclick="this.parentElement.classList.toggle('open')">
                    <div class="wmg-section-num" style="background:linear-gradient(135deg,#7c3aed,#a855f7);">6</div>
                    <div class="wmg-section-title">Kullanıcı Yönetimi</div>
                    <span class="wmg-section-arrow">▼</span>
                </div>
                <div class="wmg-section-body">
                    <div class="wmg-step">
                        <div class="wmg-step-icon">🔎</div>
                        <div class="wmg-step-text"><strong>Kullanıcılar</strong> sekmesinden kullanıcıları isim veya e-posta ile arayın.</div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">📋</div>
                        <div class="wmg-step-text">Her kullanıcının aktif planları, süreleri ve durumları listelenir.</div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">➕</div>
                        <div class="wmg-step-text"><strong>"Plan Ata"</strong> butonu ile herhangi bir kullanıcıya manuel olarak plan atayabilirsiniz. Süreyi özel olarak belirleyebilirsiniz.</div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">🗑️</div>
                        <div class="wmg-step-text">Süresi dolan veya iptal edilmesi gereken üyelikleri kaldırabilirsiniz.</div>
                    </div>
                </div>
            </div>

            <!-- BÖLÜM 7 -->
            <div class="wmg-section">
                <div class="wmg-section-head" onclick="this.parentElement.classList.toggle('open')">
                    <div class="wmg-section-num" style="background:linear-gradient(135deg,#0891b2,#06b6d4);">7</div>
                    <div class="wmg-section-title">Ön Yüz (Kullanıcı Deneyimi)</div>
                    <span class="wmg-section-arrow">▼</span>
                </div>
                <div class="wmg-section-body">
                    <div class="wmg-step">
                        <div class="wmg-step-icon">🏠</div>
                        <div class="wmg-step-text">Kullanıcılar <strong>Hesabım → Üyeliğim</strong> sayfasından üyelik durumlarını görür.</div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">🎨</div>
                        <div class="wmg-step-text"><strong>Premium Plan Kartları:</strong> Planlar modern, profesyonel kartlar olarak gösterilir. Her kartın kendi renk kodu, açıklama ve fiyatı vardır.</div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">🔄</div>
                        <div class="wmg-step-text"><strong>Aylık/Yıllık Toggle:</strong> Kullanıcılar tek tıkla aylık ve yıllık fiyatlar arasında geçiş yapabilir. Yıllık tasarruf otomatik hesaplanır.</div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">📤</div>
                        <div class="wmg-step-text"><strong>Başvuru Formu:</strong> Plan seçildikten sonra banka bilgileri gösterilir, kullanıcı dekont/referans numarası ve not girerek başvurusunu tamamlar.</div>
                    </div>
                    <div class="wmg-step">
                        <div class="wmg-step-icon">🔒</div>
                        <div class="wmg-step-text"><strong>Kısıtlı İçerikler:</strong> Üyeliği olmayan kullanıcılar kısıtlı sayfa/yazılara erişmeye çalıştığında seçtiğiniz yöntemle (mesaj/yönlendirme) uyarılır.</div>
                    </div>
                    <div class="wmg-tip">
                        <div class="wmg-tip-title">💡 İpucu</div>
                        <div class="wmg-tip-text">Aktif üyeliği olan kullanıcılar, üyelik bilgileri ve bitiş tarihleriyle birlikte panellerinde plan durumlarını görebilir. Süresi dolan üyelikler için otomatik kontrol yapılmaktadır.</div>
                    </div>
                </div>
            </div>

        </div>
    <?php
    }
}

new Webyaz_Membership();
