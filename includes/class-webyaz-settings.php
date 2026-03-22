<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Settings
{

    private static $defaults = array(
        'company_name' => '',
        'company_address' => '',
        'company_phone' => '',
        'company_email' => '',
        'company_logo' => '',
        'company_tax_office' => '',
        'company_tax_no' => '',
        'company_mersis' => '',
        'whatsapp_number' => '',
        'whatsapp_message' => 'Merhaba, bilgi almak istiyorum.',
        'whatsapp_online_text' => 'Size nasil yardimci olabiliriz?',
        'whatsapp_offline_text' => 'Su an mesai disiyiz.',
        'whatsapp_start_hour' => '09:00',
        'whatsapp_end_hour' => '18:00',
        'social_facebook' => '',
        'social_instagram' => '',
        'social_twitter' => '',
        'social_youtube' => '',
        'social_tiktok' => '',
        'social_linkedin' => '',
    );

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_styles'));
        add_action('admin_head', array($this, 'menu_scroll_css'));
        add_action('wp_ajax_webyaz_toggle_module', array($this, 'ajax_toggle_module'));
    }

    // AJAX: Tek toggle kaydet
    public function ajax_toggle_module() {
        check_ajax_referer('webyaz_toggle_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error();
        $key = sanitize_text_field($_POST['module_key'] ?? '');
        $val = sanitize_text_field($_POST['module_val'] ?? '0');
        if (strpos($key, 'webyaz_mod_') !== 0) wp_send_json_error();
        update_option($key, $val);
        update_option('webyaz_installed', '1');
        // Setup tamamla
        if (get_option('webyaz_setup_complete', '1') !== '1') {
            update_option('webyaz_setup_complete', '1');
        }
        wp_send_json_success(array('key' => $key, 'val' => $val));
    }

    /**
     * Müşteri Kilidi: Rol Yönetimi modülü aktifken ve
     * webyaz_mode = active_only ise, admin olmayan kullanıcılar
     * sadece açık modülleri görür.
     */
    public static function is_client_locked() {
        // Admin hiçbir zaman kilitlenmez
        if (current_user_can('administrator')) {
            return false;
        }
        // Rol Yönetimi modülü ayarlarını kontrol et
        if (class_exists('Webyaz_Role_Manager')) {
            $role_opts = Webyaz_Role_Manager::get_opts();
            if ($role_opts['active'] === '1') {
                $mode = isset($role_opts['webyaz_mode']) ? $role_opts['webyaz_mode'] : 'active_only';
                return $mode === 'active_only';
            }
        }
        return false;
    }

    public function add_admin_menus()
    {
        // Mağaza yöneticileri de menüyü görebilsin
        $capability = 'manage_options';
        if (class_exists('Webyaz_Role_Manager')) {
            $rm_opts = Webyaz_Role_Manager::get_opts();
            if ($rm_opts['active'] === '1') {
                $rm_mode = isset($rm_opts['webyaz_mode']) ? $rm_opts['webyaz_mode'] : 'active_only';
                if ($rm_mode === 'active_only') {
                    $capability = 'manage_woocommerce';
                }
            }
        }

        add_menu_page(
            'Webyaz',
            'Webyaz',
            $capability,
            'webyaz-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-shield-alt',
            58
        );

        add_submenu_page(
            'webyaz-dashboard',
            'Kontrol Paneli',
            'Kontrol Paneli',
            $capability,
            'webyaz-dashboard',
            array($this, 'render_dashboard')
        );

        // Ayarlar sayfası — active_only modunda user_has_cap filtresi ile erisilebilir
        add_submenu_page(
            'webyaz-dashboard',
            'Ayarlar',
            'Ayarlar',
            'manage_options',
            'webyaz-settings',
            array($this, 'render_settings')
        );
    }

    public function register_settings()
    {
        register_setting('webyaz_settings_group', 'webyaz_settings', array($this, 'sanitize'));
    }

    public function sanitize($input)
    {
        $clean = array();
        foreach (self::$defaults as $key => $default) {
            if (isset($input[$key])) {
                if ($key === 'company_logo') {
                    $clean[$key] = esc_url_raw($input[$key]);
                } else {
                    $clean[$key] = sanitize_text_field($input[$key]);
                }
            } else {
                $clean[$key] = $default;
            }
        }
        return $clean;
    }

    public static function get($key)
    {
        $opts = get_option('webyaz_settings', array());
        if (isset($opts[$key]) && $opts[$key] !== '') {
            return $opts[$key];
        }
        return isset(self::$defaults[$key]) ? self::$defaults[$key] : '';
    }

    public static function get_all()
    {
        return wp_parse_args(get_option('webyaz_settings', array()), self::$defaults);
    }

    public function menu_scroll_css()
    {
        echo '<style>
        #adminmenu .wp-submenu-wrap{max-height:70vh;overflow-y:auto;overflow-x:hidden;}
        #adminmenu .wp-submenu-wrap::-webkit-scrollbar{width:4px;}
        #adminmenu .wp-submenu-wrap::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.2);border-radius:4px;}
        </style>';
    }

    public function admin_styles($hook)
    {
        if (strpos($hook, 'webyaz') === false) return;
        wp_enqueue_media();

        $primary = '#446084';
        $secondary = '#d26e4b';
        if (class_exists('Webyaz_Colors')) {
            $colors = Webyaz_Colors::get_theme_colors();
            $primary = $colors['primary'];
            $secondary = $colors['secondary'];
        }

        $css = "
        .webyaz-admin-wrap{max-width:900px;margin:20px 0;font-family:-apple-system,BlinkMacSystemFont,sans-serif;}
        .webyaz-admin-header{background:linear-gradient(135deg,{$primary},{$secondary});color:#fff;padding:30px 35px;border-radius:12px;margin-bottom:25px;position:relative;overflow:hidden;}
        .webyaz-admin-header::before{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background:linear-gradient(135deg,rgba(0,0,0,0.3),rgba(0,0,0,0.1));z-index:0;}
        .webyaz-admin-header h1{margin:0 0 5px;font-size:26px;font-weight:700;color:#fff;position:relative;z-index:1;text-shadow:0 1px 4px rgba(0,0,0,0.4);}
        .webyaz-admin-header p{margin:0;opacity:.95;font-size:14px;color:#fff;position:relative;z-index:1;text-shadow:0 1px 3px rgba(0,0,0,0.3);}
        .webyaz-card{background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:0;margin-bottom:15px;overflow:hidden;transition:box-shadow .2s;}
        .webyaz-card:hover{box-shadow:0 2px 12px rgba(0,0,0,.08);}
        .webyaz-card-inner{display:flex;align-items:center;justify-content:space-between;padding:20px 25px;}
        .webyaz-card-left{display:flex;align-items:center;gap:15px;}
        .webyaz-card-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;}
        .webyaz-card-icon.green{background:#e6f9e6;color:#22863a;}
        .webyaz-card-icon.red{background:#fde8e8;color:#d32f2f;}
        .webyaz-card-title{font-size:15px;font-weight:600;color:#1a1a1a;margin:0 0 3px;}
        .webyaz-card-desc{font-size:13px;color:#666;margin:0;}
        .webyaz-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;}
        .webyaz-badge.active{background:#e6f9e6;color:#22863a;}
        .webyaz-badge.missing{background:#fde8e8;color:#d32f2f;}
        .webyaz-card-actions{display:flex;align-items:center;gap:10px;}
        .webyaz-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;border:none;cursor:pointer;transition:all .2s;}
        .webyaz-btn-primary{background:{$primary};color:#fff;}
        .webyaz-btn-primary:hover{opacity:.85;color:#fff;}
        .webyaz-btn-secondary{background:{$secondary};color:#fff;}
        .webyaz-btn-secondary:hover{opacity:.85;color:#fff;}
        .webyaz-btn-outline{background:transparent;border:1px solid #ccc;color:#555;}
        .webyaz-btn-outline:hover{border-color:{$primary};color:{$primary};}
        .webyaz-section-title{font-size:16px;font-weight:700;color:#1a1a1a;margin:25px 0 12px;padding-bottom:8px;border-bottom:2px solid {$secondary};}
        .webyaz-notice{padding:12px 18px;border-radius:8px;margin-bottom:15px;font-size:14px;}
        .webyaz-notice.success{background:#e6f9e6;color:#22863a;border:1px solid #b7e4c7;}
        .webyaz-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-bottom:25px;}
        .webyaz-stat{background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px;text-align:center;}
        .webyaz-stat-num{font-size:28px;font-weight:700;color:{$primary};}
        .webyaz-stat-label{font-size:13px;color:#666;margin-top:4px;}
        .webyaz-settings-section{background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:25px;margin-bottom:20px;}
        .webyaz-settings-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:15px;}
        .webyaz-field{display:flex;flex-direction:column;}
        .webyaz-field label{font-size:13px;font-weight:600;color:#333;margin-bottom:5px;}
        .webyaz-field input{border:1px solid #ddd;border-radius:6px;padding:10px 12px;font-size:14px;transition:border-color .2s;}
        .webyaz-field input:focus{border-color:{$primary};outline:none;box-shadow:0 0 0 2px rgba(68,96,132,0.1);}
        ";
        wp_add_inline_style('wp-admin', $css);
    }

    public function render_dashboard()
    {
        // Guvenli mod: Kurulum tamamlama islemi
        $setup_complete = get_option('webyaz_setup_complete', '1');
        if (isset($_POST['webyaz_complete_setup']) && wp_verify_nonce($_POST['_wpnonce_setup'], 'webyaz_setup')) {
            update_option('webyaz_setup_complete', '1');
            $setup_complete = '1';
            echo '<div class="webyaz-notice success">Kurulum tamamlandi! Sectiginiz moduller artik aktif.</div>';
        }
        $features = class_exists('Webyaz_Pages') ? Webyaz_Pages::get_features_status() : array();
        $active = count(array_filter($features, function ($f) {
            return $f['status'] === 'active';
        }));
        $missing = count($features) - $active;
        $groups = array(
            'pages' => 'Yasal Sayfalar',
            'checkout' => 'Odeme Sayfasi',
            'product' => 'Urun Sayfalari',
            'security' => 'Guvenlik',
        );

        $modules = array(
            // -- Urun Yonetimi --
            array('key' => 'webyaz_mod_product_style', 'title' => 'Urun Sayfasi Tasarim', 'desc' => 'Tek urun sayfasi gorsel ozellestirme', 'icon' => 'dashicons-admin-appearance', 'group' => 'urun'),
            array('key' => 'webyaz_mod_product_video', 'title' => 'Urun Videosu', 'desc' => 'YouTube/Vimeo video ekleme', 'icon' => 'dashicons-video-alt3', 'group' => 'urun'),
            array('key' => 'webyaz_mod_product_desc', 'title' => 'Urun Aciklama', 'desc' => 'Premium urun aciklama tasarimi', 'icon' => 'dashicons-text-page', 'group' => 'urun'),
            array('key' => 'webyaz_mod_product_tabs', 'title' => 'Urun Tablari', 'desc' => 'Teslimat, iade ve degisim tablari', 'icon' => 'dashicons-list-view', 'group' => 'urun'),
            array('key' => 'webyaz_mod_badges', 'title' => 'Urun Rozetleri', 'desc' => 'Yeni, Indirimde, Tukeniyor etiketleri', 'icon' => 'dashicons-star-filled', 'group' => 'urun'),
            array('key' => 'webyaz_mod_size_guide', 'title' => 'Beden Tablosu', 'desc' => 'Urun sayfasi beden rehberi', 'icon' => 'dashicons-editor-table', 'group' => 'urun'),
            array('key' => 'webyaz_mod_attributes', 'title' => 'Beden & Renk', 'desc' => 'Ozel nitelik sistemi', 'icon' => 'dashicons-art', 'group' => 'urun'),
            array('key' => 'webyaz_mod_photo_reviews', 'title' => 'Fotografli Yorum', 'desc' => 'Yorumlara fotograf ekleme', 'icon' => 'dashicons-camera', 'group' => 'urun'),
            array('key' => 'webyaz_mod_qa', 'title' => 'Soru-Cevap', 'desc' => 'Urun sayfasi soru-cevap', 'icon' => 'dashicons-format-chat', 'group' => 'urun'),
            array('key' => 'webyaz_mod_compare', 'title' => 'Urun Karsilastirma', 'desc' => 'Urunleri yan yana karsilastir', 'icon' => 'dashicons-columns', 'group' => 'urun'),
            array('key' => 'webyaz_mod_wishlist', 'title' => 'Favoriler', 'desc' => 'Istek listesi', 'icon' => 'dashicons-heart', 'group' => 'urun'),
            array('key' => 'webyaz_mod_social_proof', 'title' => 'Sosyal Kanit', 'desc' => 'Goruntulenme ve satin alma sayaci', 'icon' => 'dashicons-groups', 'group' => 'urun'),
            array('key' => 'webyaz_mod_recently_viewed', 'title' => 'Son Bakilan Urunler', 'desc' => 'Ziyaret edilen urunleri goster', 'icon' => 'dashicons-visibility', 'group' => 'urun'),
            array('key' => 'webyaz_mod_previously_bought', 'title' => 'Onceki Alinanlar', 'desc' => 'Daha once alinan urunleri goster', 'icon' => 'dashicons-update', 'group' => 'urun'),
            array('key' => 'webyaz_mod_cross_sell', 'title' => 'Birlikte Satin Alinanlar', 'desc' => 'Urun onerileri', 'icon' => 'dashicons-networking', 'group' => 'urun'),
            array('key' => 'webyaz_mod_extra_services', 'title' => 'Ekstra Hizmetler', 'desc' => 'Urune opsiyonel ucretli hizmet ekle', 'icon' => 'dashicons-hammer', 'group' => 'urun'),
            array('key' => 'webyaz_mod_stock_counter', 'title' => 'Stok Sayaci', 'desc' => 'Dusuk stok uyarisi', 'icon' => 'dashicons-warning', 'group' => 'urun'),
            array('key' => 'webyaz_mod_stock_alert', 'title' => 'Stok Bildirimi', 'desc' => 'Stok gelince e-posta bildirimi', 'icon' => 'dashicons-bell', 'group' => 'urun'),
            array('key' => 'webyaz_mod_auto_tags', 'title' => 'Otomatik Etiketler', 'desc' => 'Urunlere otomatik etiket', 'icon' => 'dashicons-tag', 'group' => 'urun'),
            array('key' => 'webyaz_mod_countdown', 'title' => 'Geri Sayim', 'desc' => 'Kampanya zamanlayici', 'icon' => 'dashicons-clock', 'group' => 'urun'),
            array('key' => 'webyaz_mod_quick_product', 'title' => 'Hizli Urun Ekle', 'desc' => 'Tek sayfada hizli urun olusturma', 'icon' => 'dashicons-plus-alt', 'group' => 'urun'),
            array('key' => 'webyaz_mod_bulk_product', 'title' => 'Toplu Urun Ekle', 'desc' => 'Coklu urun ekleme formu', 'icon' => 'dashicons-grid-view', 'group' => 'urun'),
            array('key' => 'webyaz_mod_bulk_edit', 'title' => 'Toplu Duzenle', 'desc' => 'Toplu fiyat, kategori, stok degistir', 'icon' => 'dashicons-editor-table', 'group' => 'urun'),
            array('key' => 'webyaz_mod_preorder', 'title' => 'On Siparis', 'desc' => 'Stoga gelmeden on siparis alma', 'icon' => 'dashicons-calendar-alt', 'group' => 'urun'),
            array('key' => 'webyaz_mod_delivery_date', 'title' => 'Tahmini Teslimat', 'desc' => 'Urun sayfasinda tahmini teslimat tarihi', 'icon' => 'dashicons-calendar', 'group' => 'urun'),
            array('key' => 'webyaz_mod_compatible', 'title' => 'Uyumlu Urunler', 'desc' => 'Aksesuar ve uyumlu urun onerileri', 'icon' => 'dashicons-clipboard', 'group' => 'urun'),
            // -- Siparis & Kargo --
            array('key' => 'webyaz_mod_checkout', 'title' => 'Odeme Formu', 'desc' => 'Bireysel/Kurumsal checkout formu', 'icon' => 'dashicons-cart', 'group' => 'siparis'),
            array('key' => 'webyaz_mod_order_status', 'title' => 'Siparis Durumlari', 'desc' => 'Ozel siparis durumlari olustur', 'icon' => 'dashicons-flag', 'group' => 'siparis'),
            array('key' => 'webyaz_mod_order_track', 'title' => 'Siparis Takip', 'desc' => 'Kargo takip numarasi ekleme', 'icon' => 'dashicons-location-alt', 'group' => 'siparis'),
            array('key' => 'webyaz_mod_order_note', 'title' => 'Siparis Notu', 'desc' => 'Musteriye ozel mesaj gonder', 'icon' => 'dashicons-testimonial', 'group' => 'siparis'),
            array('key' => 'webyaz_mod_order_whatsapp', 'title' => 'Siparis WhatsApp', 'desc' => 'Siparis durumu degisince WhatsApp mesaji', 'icon' => 'dashicons-whatsapp', 'group' => 'siparis'),
            array('key' => 'webyaz_mod_cargo', 'title' => 'Kargo Entegrasyonu', 'desc' => 'Kargo firmalari entegrasyonu', 'icon' => 'dashicons-car', 'group' => 'siparis'),
            array('key' => 'webyaz_mod_free_shipping_bar', 'title' => 'Kargo Bari', 'desc' => 'Ucretsiz kargo icin progress bar goster', 'icon' => 'dashicons-car', 'group' => 'siparis'),
            array('key' => 'webyaz_mod_invoice', 'title' => 'E-Fatura', 'desc' => 'Otomatik fatura olusturma ve yazdirma', 'icon' => 'dashicons-media-text', 'group' => 'siparis'),
            array('key' => 'webyaz_mod_returns', 'title' => 'Iade Yonetimi', 'desc' => 'Musteri iade talebi ve admin onay sistemi', 'icon' => 'dashicons-undo', 'group' => 'siparis'),
            array('key' => 'webyaz_mod_gift', 'title' => 'Hediye Secenekleri', 'desc' => 'Hediye paketi ve not ekleme', 'icon' => 'dashicons-awards', 'group' => 'siparis'),
            // -- Pazarlama & Satis --
            array('key' => 'webyaz_mod_seo', 'title' => 'SEO', 'desc' => 'Schema, Open Graph, meta etiketleri', 'icon' => 'dashicons-search', 'group' => 'pazarlama'),
            array('key' => 'webyaz_mod_analytics', 'title' => 'Analytics & Izleme', 'desc' => 'GA4, GTM, Search Console, Facebook Pixel', 'icon' => 'dashicons-chart-bar', 'group' => 'pazarlama'),
            array('key' => 'webyaz_mod_popup', 'title' => 'Kupon Popup', 'desc' => 'Indirim kuponu popup', 'icon' => 'dashicons-megaphone', 'group' => 'pazarlama'),
            array('key' => 'webyaz_mod_cart_reminder', 'title' => 'Sepet Hatirlatma', 'desc' => 'Terk edilen sepet popup', 'icon' => 'dashicons-dismiss', 'group' => 'pazarlama'),
            array('key' => 'webyaz_mod_upsell', 'title' => 'Upsell Popup', 'desc' => 'Sepete ekleme onerisi', 'icon' => 'dashicons-money-alt', 'group' => 'pazarlama'),
            array('key' => 'webyaz_mod_bulk_discount', 'title' => 'Toplu Indirim', 'desc' => 'Coklu alis indirimi', 'icon' => 'dashicons-tickets-alt', 'group' => 'pazarlama'),
            array('key' => 'webyaz_mod_auto_discount', 'title' => 'Otomatik Indirim', 'desc' => 'Sepet tutari, adet, BOGO, X Al Y Ode kurallari', 'icon' => 'dashicons-tag', 'group' => 'pazarlama'),
            array('key' => 'webyaz_mod_coupon_manager', 'title' => 'Kupon Yonetici', 'desc' => 'Toplu kupon olustur, CSV aktar, istatistik', 'icon' => 'dashicons-tickets-alt', 'group' => 'pazarlama'),
            array('key' => 'webyaz_mod_loyalty', 'title' => 'Sadakat Puani', 'desc' => 'Alisveriste puan kazan, puanla indirim kullan', 'icon' => 'dashicons-star-filled', 'group' => 'pazarlama'),
            array('key' => 'webyaz_mod_referral', 'title' => 'Referans Sistemi', 'desc' => 'Arkadasini davet et, ikisi de kazansin', 'icon' => 'dashicons-groups', 'group' => 'pazarlama'),
            array('key' => 'webyaz_mod_cost_price', 'title' => 'Kar Analizi', 'desc' => 'Alis fiyati ve kar/zarar takibi', 'icon' => 'dashicons-chart-area', 'group' => 'pazarlama'),
            array('key' => 'webyaz_mod_sms', 'title' => 'SMS Bildirim', 'desc' => 'Siparis durumunda otomatik SMS', 'icon' => 'dashicons-email-alt', 'group' => 'pazarlama'),
            array('key' => 'webyaz_mod_ticker', 'title' => 'Kayan Yazi', 'desc' => 'Kayan duyuru/promosyon seridi', 'icon' => 'dashicons-megaphone', 'group' => 'pazarlama'),
            array('key' => 'webyaz_mod_live_search', 'title' => 'Canli Arama', 'desc' => 'AJAX arama + filtre', 'icon' => 'dashicons-search', 'group' => 'pazarlama'),
            // -- Tasarim & Icerik --
            array('key' => 'webyaz_mod_branded_pages', 'title' => 'Kurumsal Sayfalar', 'desc' => 'Premium tasarimli kurumsal sayfa olustur', 'icon' => 'dashicons-welcome-add-page', 'group' => 'tasarim'),
            array('key' => 'webyaz_mod_story_menu', 'title' => 'Story Menu', 'desc' => 'Instagram tarzi kategori menusu', 'icon' => 'dashicons-format-gallery', 'group' => 'tasarim'),
            array('key' => 'webyaz_mod_footer', 'title' => 'Footer', 'desc' => 'Ozel footer tasarimi', 'icon' => 'dashicons-editor-insertmore', 'group' => 'tasarim'),
            array('key' => 'webyaz_mod_mobile_bar', 'title' => 'Mobil Bar', 'desc' => 'Mobil alt navigasyon', 'icon' => 'dashicons-smartphone', 'group' => 'tasarim'),
            array('key' => 'webyaz_mod_floating_contact', 'title' => 'Iletisim Butonlari', 'desc' => 'Yuzen sosyal medya & iletisim butonlari', 'icon' => 'dashicons-phone', 'group' => 'tasarim'),
            array('key' => 'webyaz_mod_whatsapp', 'title' => 'WhatsApp Butonu', 'desc' => 'Canli destek butonu', 'icon' => 'dashicons-phone', 'group' => 'tasarim'),
            array('key' => 'webyaz_mod_live_support', 'title' => 'Canli Destek', 'desc' => 'Coklu kanal destek butonu', 'icon' => 'dashicons-format-status', 'group' => 'tasarim'),
            array('key' => 'webyaz_mod_email_templates', 'title' => 'E-posta Sablonlari', 'desc' => 'Ozel WooCommerce e-posta tasarimi', 'icon' => 'dashicons-email', 'group' => 'tasarim'),
            array('key' => 'webyaz_mod_custom_css', 'title' => 'Ozel CSS', 'desc' => 'Siteye ozel CSS kodlari ekleyin', 'icon' => 'dashicons-editor-code', 'group' => 'tasarim'),
            array('key' => 'webyaz_mod_cookie', 'title' => 'Cerez Uyarisi', 'desc' => 'KVKK cerez bildirimi', 'icon' => 'dashicons-info-outline', 'group' => 'tasarim'),
            array('key' => 'webyaz_mod_maintenance', 'title' => 'Bakim Modu', 'desc' => 'Siteyi bakima al', 'icon' => 'dashicons-hammer', 'group' => 'tasarim'),
            array('key' => 'webyaz_mod_shop_turkish', 'title' => 'Magaza Turkcesi', 'desc' => 'WooCommerce bilesenleri Turkce + tasarim', 'icon' => 'dashicons-translation', 'group' => 'tasarim'),
            // -- Kullanici & Uyelik --
            array('key' => 'webyaz_mod_b2b', 'title' => 'B2B Bayi Sistemi', 'desc' => 'Bayi yonetimi, bakiye ve ozel fiyatlandirma', 'icon' => 'dashicons-building', 'group' => 'kullanici'),
            array('key' => 'webyaz_mod_membership', 'title' => 'Uyelik Sistemi', 'desc' => 'Sayfa ve yazi kisitlama, uyelik planlari', 'icon' => 'dashicons-lock', 'group' => 'kullanici'),
            array('key' => 'webyaz_mod_partner', 'title' => 'Partner Sistemi', 'desc' => 'Ortaklik ve komisyon yonetimi', 'icon' => 'dashicons-businessperson', 'group' => 'kullanici'),
            array('key' => 'webyaz_mod_role_manager', 'title' => 'Rol Yonetimi', 'desc' => 'Kullanici rolu menu kisitlamalari', 'icon' => 'dashicons-admin-users', 'group' => 'kullanici'),
            array('key' => 'webyaz_mod_customer_ledger', 'title' => 'Musteri Cari', 'desc' => 'Toplam harcama takibi ve otomatik kupon', 'icon' => 'dashicons-money-alt', 'group' => 'kullanici'),
            // -- Guvenlik --
            array('key' => 'webyaz_mod_antibot', 'title' => 'Anti-Bot', 'desc' => 'Sahte kayitlari engelle', 'icon' => 'dashicons-shield', 'group' => 'guvenlik'),
            array('key' => 'webyaz_mod_brute_force', 'title' => 'Brute Force Koruma', 'desc' => 'Giris denemesi sinirla', 'icon' => 'dashicons-lock', 'group' => 'guvenlik'),
            array('key' => 'webyaz_mod_security_shield', 'title' => 'Guvenlik Kalkani', 'desc' => 'Kaynak kod, sag tik ve gelistirici araclarini engelle', 'icon' => 'dashicons-shield', 'group' => 'guvenlik'),
            // -- Araclar & Entegrasyon --
            array('key' => 'webyaz_mod_backup', 'title' => 'Yedekleme', 'desc' => 'Tam site yedek al & geri yukle', 'icon' => 'dashicons-database-export', 'group' => 'araclar'),
            array('key' => 'webyaz_mod_xml', 'title' => 'XML Yonetimi', 'desc' => 'XML urun iceri/disari aktarma', 'icon' => 'dashicons-download', 'group' => 'araclar'),
            array('key' => 'webyaz_mod_marketplace', 'title' => 'Pazaryeri', 'desc' => 'Trendyol & Hepsiburada entegrasyonu', 'icon' => 'dashicons-store', 'group' => 'araclar'),
            array('key' => 'webyaz_mod_webp', 'title' => 'WebP Donusturme', 'desc' => 'Otomatik resim optimizasyonu', 'icon' => 'dashicons-format-image', 'group' => 'araclar'),
            array('key' => 'webyaz_mod_bulk_webp', 'title' => 'Toplu WebP', 'desc' => 'Mevcut resimleri topluca WebP cevir', 'icon' => 'dashicons-images-alt2', 'group' => 'araclar'),
            array('key' => 'webyaz_mod_big_upload', 'title' => 'Buyuk Dosya Yukleme', 'desc' => 'Medya yukleme limitini artir', 'icon' => 'dashicons-upload', 'group' => 'araclar'),
            array('key' => 'webyaz_mod_duplicate_post', 'title' => 'Yazi Kopyala', 'desc' => 'Yazi ve sayfalari tek tikla kopyala', 'icon' => 'dashicons-admin-page', 'group' => 'araclar'),
            array('key' => 'webyaz_mod_updater', 'title' => 'Otomatik Guncelleme', 'desc' => 'Eklenti guncelleme bildirimi ve tek tik guncelleme', 'icon' => 'dashicons-update', 'group' => 'araclar'),
        );

        // Grup icerisinde alfabetik sirala
        usort($modules, function($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });

        // Grup tanimlari
        $group_labels = array(
            'urun'      => array('label' => 'Urun Yonetimi', 'icon' => '📦'),
            'siparis'   => array('label' => 'Siparis & Kargo', 'icon' => '🚚'),
            'pazarlama' => array('label' => 'Pazarlama & Satis', 'icon' => '📈'),
            'tasarim'   => array('label' => 'Tasarim & Icerik', 'icon' => '🎨'),
            'kullanici' => array('label' => 'Kullanici & Uyelik', 'icon' => '👥'),
            'guvenlik'  => array('label' => 'Guvenlik', 'icon' => '🛡️'),
            'araclar'   => array('label' => 'Araclar & Entegrasyon', 'icon' => '🔧'),
        );

        // Gruplara ayir
        $grouped = array();
        foreach ($modules as $m) {
            $g = isset($m['group']) ? $m['group'] : 'araclar';
            $grouped[$g][] = $m;
        }

        if (isset($_POST['webyaz_save_modules']) && wp_verify_nonce($_POST['_wpnonce_modules'], 'webyaz_modules')) {
            if ($_POST['webyaz_save_modules'] === 'all') {
                foreach ($modules as $m) {
                    update_option($m['key'], '1');
                }
                echo '<div class="webyaz-notice success">Tum moduller aktif edildi!</div>';
            } else {
                foreach ($modules as $m) {
                    update_option($m['key'], isset($_POST[$m['key']]) ? '1' : '0');
                }
                echo '<div class="webyaz-notice success">Modul ayarlari kaydedildi!</div>';
            }
            update_option('webyaz_installed', '1');
            // Modul kaydedildiginde setup da tamamlanir
            if ($setup_complete !== '1') {
                update_option('webyaz_setup_complete', '1');
                $setup_complete = '1';
            }
        }
?>
        <div class="webyaz-admin-wrap">
            <?php if ($setup_complete !== '1'): ?>
                <div style="background:linear-gradient(135deg,#ff9800,#f57c00);color:#fff;padding:25px 30px;border-radius:12px;margin-bottom:25px;box-shadow:0 4px 15px rgba(255,152,0,0.3);">
                    <div style="display:flex;align-items:center;gap:15px;flex-wrap:wrap;">
                        <span style="font-size:36px;">🔒</span>
                        <div style="flex:1;">
                            <h2 style="margin:0 0 5px;color:#fff;font-size:20px;">Guvenli Kurulum Modu</h2>
                            <p style="margin:0;opacity:0.95;font-size:14px;">Eklenti henuz hicbir degisiklik yapmadi. Asagidan kullanmak istediginiz modulleri toggle ile acin, sonra kurulumu tamamlayin.</p>
                        </div>
                        <form method="post" style="margin:0;">
                            <?php wp_nonce_field('webyaz_setup', '_wpnonce_setup'); ?>
                            <button type="submit" name="webyaz_complete_setup" value="1" style="background:#fff;color:#f57c00;border:none;padding:12px 28px;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;white-space:nowrap;box-shadow:0 2px 8px rgba(0,0,0,0.15);">
                                ✅ Kurulumu Tamamla
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            <div class="webyaz-admin-header">
                <h1>Webyaz Kontrol Paneli</h1>
                <p>Flatsome e-ticaret eklentisi - <?php echo count($modules); ?> modul</p>
            </div>

            <?php do_action('webyaz_dashboard_before_modules'); ?>

            <?php if (get_option('webyaz_installed', '0') !== '1'): ?>
                <div style="background:linear-gradient(135deg,#fff3e0,#ffe0b2);border-radius:12px;padding:24px;margin-bottom:24px;border-left:5px solid #e65100;">
                    <h2 style="margin:0 0 12px;font-size:18px;color:#e65100;">Hosgeldiniz! Kuruluma Baslayin</h2>
                    <p style="margin:0 0 16px;font-size:14px;color:#333;line-height:1.8;">
                        Webyaz eklentisi basariyla yuklendi. Asagidaki adimlari takip edin:
                    </p>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
                        <div style="background:#fff;border-radius:10px;padding:16px;text-align:center;">
                            <div style="width:40px;height:40px;background:#e65100;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-weight:900;font-size:18px;">1</div>
                            <strong style="font-size:13px;">Ayarlari Girin</strong>
                            <p style="font-size:12px;color:#666;margin:6px 0 0;">Webyaz > Ayarlar sayfasindan firma bilgileri, telefon, sosyal medya ve sozlesme bilgilerini girin.</p>
                        </div>
                        <div style="background:#fff;border-radius:10px;padding:16px;text-align:center;">
                            <div style="width:40px;height:40px;background:#e65100;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-weight:900;font-size:18px;">2</div>
                            <strong style="font-size:13px;">Modulleri Acin</strong>
                            <p style="font-size:12px;color:#666;margin:6px 0 0;">Asagidaki modul listesinden ihtiyaciniz olanlari aktif edin ve "Kaydet" butonuna basin.</p>
                        </div>
                        <div style="background:#fff;border-radius:10px;padding:16px;text-align:center;">
                            <div style="width:40px;height:40px;background:#e65100;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-weight:900;font-size:18px;">3</div>
                            <strong style="font-size:13px;">Sayfalari Olusturun</strong>
                            <p style="font-size:12px;color:#666;margin:6px 0 0;">"Tum Eksik Sayfalari Olustur" butonuna basarak KVKK, Sozlesme vb. sayfalari olusturun.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['webyaz_done'])): ?>
                <div class="webyaz-notice success">Islem basarili!</div>
            <?php endif; ?>

            <div class="webyaz-stats">
                <div class="webyaz-stat">
                    <div class="webyaz-stat-num"><?php echo count($features); ?></div>
                    <div class="webyaz-stat-label">Yasal Sayfa</div>
                </div>
                <div class="webyaz-stat">
                    <div class="webyaz-stat-num"><?php echo $active; ?></div>
                    <div class="webyaz-stat-label">Aktif Sayfa</div>
                </div>
                <div class="webyaz-stat">
                    <div class="webyaz-stat-num"><?php echo count($modules); ?></div>
                    <div class="webyaz-stat-label">Toplam Modul</div>
                </div>
            </div>

            <?php if (self::is_client_locked()): ?>
                <!-- MÜŞTERİ KİLİDİ AKTİF: Sadece açık modüller gösterilir -->
                <div class="webyaz-section-title">Aktif Modüller</div>
                <p style="color:#666;font-size:13px;margin:-8px 0 16px;">Sitenizde aktif olan modüllerin listesi. Ayarlar için sol menüden ilgili modülü seçin.</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;margin-bottom:20px;">
                    <?php foreach ($modules as $m):
                        $enabled = get_option($m['key'], '0');
                        if ($enabled !== '1') continue;
                    ?>
                        <div class="webyaz-card" style="border-left:4px solid #4caf50;">
                            <div style="padding:16px 18px;display:flex;align-items:center;gap:12px;">
                                <span class="dashicons <?php echo esc_attr($m['icon']); ?>" style="font-size:20px;color:#4caf50;"></span>
                                <div>
                                    <div style="font-size:14px;font-weight:600;color:#333;"><?php echo esc_html($m['title']); ?></div>
                                    <div style="font-size:11px;color:#999;"><?php echo esc_html($m['desc']); ?></div>
                                </div>
                                <span style="margin-left:auto;background:#4caf5022;color:#4caf50;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;">Aktif</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <div class="webyaz-section-title">Modul Yonetimi</div>
                <p style="color:#666;font-size:13px;margin:-8px 0 16px;">Modulleri acip kapatarak eklentinin hangi ozelliklerinin aktif oldugunu kontrol edin. <strong>Degisiklikler aninda kaydedilir.</strong></p>

                    <?php foreach ($group_labels as $gkey => $ginfo):
                        if (!isset($grouped[$gkey]) || empty($grouped[$gkey])) continue;
                    ?>
                    <div style="margin-bottom:24px;" data-group="<?php echo $gkey; ?>">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;padding:8px 14px;background:linear-gradient(135deg,#f5f7fa,#e4e9f0);border-radius:8px;flex-wrap:wrap;">
                            <span style="font-size:20px;"><?php echo $ginfo['icon']; ?></span>
                            <span style="font-size:15px;font-weight:700;color:#333;"><?php echo $ginfo['label']; ?></span>
                            <span style="font-size:11px;color:#999;"><?php echo count($grouped[$gkey]); ?> modul</span>
                            <div style="margin-left:auto;display:flex;gap:4px;">
                                <button type="button" onclick="wzToggleGroup('<?php echo $gkey; ?>', true)" style="background:#4caf50;color:#fff;border:none;padding:4px 10px;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;">✅ Hepsini Ac</button>
                                <button type="button" onclick="wzToggleGroup('<?php echo $gkey; ?>', false)" style="background:#e0e0e0;color:#666;border:none;padding:4px 10px;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;">Hepsini Kapat</button>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;">
                        <?php foreach ($grouped[$gkey] as $m):
                            $first_install = get_option('webyaz_installed', '0') !== '1';
                            $enabled = $first_install ? '0' : get_option($m['key'], '0');
                        ?>
                            <div class="webyaz-card" id="card_<?php echo esc_attr($m['key']); ?>" style="border-left:4px solid <?php echo $enabled === '1' ? '#4caf50' : '#e0e0e0'; ?>;position:relative;">
                                <div style="padding:16px 18px;display:flex;align-items:center;justify-content:space-between;">
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <span class="dashicons <?php echo esc_attr($m['icon']); ?>" style="font-size:20px;color:<?php echo $enabled === '1' ? '#4caf50' : '#999'; ?>;"></span>
                                        <div>
                                            <div style="font-size:14px;font-weight:600;color:#333;"><?php echo esc_html($m['title']); ?></div>
                                            <div style="font-size:11px;color:#999;"><?php echo esc_html($m['desc']); ?></div>
                                        </div>
                                    </div>
                                    <label style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer;">
                                        <input type="checkbox" data-module="<?php echo esc_attr($m['key']); ?>" value="1" <?php checked($enabled, '1'); ?> style="display:none;" onchange="wzToggleModule(this)">
                                        <span style="position:absolute;top:0;left:0;right:0;bottom:0;background:<?php echo $enabled === '1' ? '#4caf50' : '#ccc'; ?>;border-radius:24px;transition:0.3s;"></span>
                                        <span style="position:absolute;top:3px;left:<?php echo $enabled === '1' ? '23px' : '3px'; ?>;width:18px;height:18px;background:#fff;border-radius:50%;transition:0.3s;box-shadow:0 1px 3px rgba(0,0,0,0.2);"></span>
                                    </label>
                                </div>
                                <div class="wz-save-badge" style="display:none;position:absolute;top:8px;right:8px;background:#4caf50;color:#fff;font-size:10px;padding:2px 8px;border-radius:10px;font-weight:600;">Kaydedildi ✓</div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
                        <button type="button" onclick="wzToggleAll(true)" class="webyaz-btn" style="padding:12px 28px;font-size:14px;background:linear-gradient(135deg,#2e7d32,#4caf50);color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;">Tumunu Ac</button>
                        <button type="button" onclick="wzToggleAll(false)" class="webyaz-btn webyaz-btn-outline" style="padding:12px 28px;font-size:14px;">Tumunu Kapat</button>
                    </div>
                <script>
                var wzToggleNonce = '<?php echo wp_create_nonce('webyaz_toggle_nonce'); ?>';
                function wzToggleModule(cb) {
                    var key = cb.getAttribute('data-module');
                    var val = cb.checked ? '1' : '0';
                    var card = cb.closest('.webyaz-card');
                    var spans = cb.parentElement.querySelectorAll('span');
                    card.style.borderLeftColor = cb.checked ? '#4caf50' : '#e0e0e0';
                    card.querySelector('.dashicons').style.color = cb.checked ? '#4caf50' : '#999';
                    spans[0].style.background = cb.checked ? '#4caf50' : '#ccc';
                    spans[1].style.left = cb.checked ? '23px' : '3px';
                    var fd = new FormData();
                    fd.append('action', 'webyaz_toggle_module');
                    fd.append('nonce', wzToggleNonce);
                    fd.append('module_key', key);
                    fd.append('module_val', val);
                    var badge = card.querySelector('.wz-save-badge');
                    fetch(ajaxurl, {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(r){
                        if(r.success){
                            badge.style.display='block';
                            setTimeout(function(){badge.style.display='none';},1500);
                        }
                    });
                }
                function wzToggleAll(state) {
                    document.querySelectorAll('.webyaz-card input[data-module]').forEach(function(cb){
                        if(cb.checked !== state) { cb.checked = state; wzToggleModule(cb); }
                    });
                }
                function wzToggleGroup(groupKey, state) {
                    var container = document.querySelector('[data-group="' + groupKey + '"]');
                    if (!container) return;
                    container.querySelectorAll('.webyaz-card input[data-module]').forEach(function(cb){
                        if(cb.checked !== state) { cb.checked = state; wzToggleModule(cb); }
                    });
                }
                </script>
            <?php endif; ?>

            <?php
            $pages_enabled = get_option('webyaz_pages_enabled', '0');
            // Eğer tüm sayfalar zaten varsa, toggle'ı otomatik açık göster
            $all_pages_exist = true;
            $page_defs_check = class_exists('Webyaz_Pages') ? Webyaz_Pages::get_page_definitions() : array();
            foreach ($page_defs_check as $slug_check => $def_check) {
                if (!get_page_by_path($slug_check)) {
                    $all_pages_exist = false;
                    break;
                }
            }
            if ($all_pages_exist && !empty($page_defs_check)) {
                $pages_enabled = '1';
            }
            ?>
            <form method="post" style="margin-bottom:20px;">
                <?php wp_nonce_field('webyaz_action', 'webyaz_nonce'); ?>
                <input type="hidden" name="webyaz_action" value="toggle_pages">
                <div style="background:<?php echo $pages_enabled === '1' ? 'linear-gradient(135deg,#e8f5e9,#c8e6c9)' : 'linear-gradient(135deg,#fce4ec,#f8bbd0)'; ?>;border-radius:12px;padding:20px 25px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:15px;border:2px solid <?php echo $pages_enabled === '1' ? '#4caf50' : '#e57373'; ?>;transition:all 0.3s;">
                    <div style="display:flex;align-items:center;gap:15px;">
                        <span style="font-size:32px;"><?php echo $pages_enabled === '1' ? '📄' : '📄'; ?></span>
                        <div>
                            <div style="font-size:16px;font-weight:700;color:#333;">Yasal Sayfalar</div>
                            <div style="font-size:13px;color:#666;margin-top:2px;">
                                <?php echo $pages_enabled === '1'
                                    ? 'Yasal sayfalar aktif. Aşağıdan yüklemek istediğiniz sayfaları seçip "Seçilenleri Yükle" butonuna basın.'
                                    : 'Yasal sayfalar kapalı. Açtığınızda sayfa seçim alanı görünür olacak.'; ?>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="font-size:13px;font-weight:600;color:<?php echo $pages_enabled === '1' ? '#2e7d32' : '#c62828'; ?>;">
                            <?php echo $pages_enabled === '1' ? 'AKTİF' : 'KAPALI'; ?>
                        </span>
                        <label style="position:relative;display:inline-block;width:52px;height:28px;cursor:pointer;">
                            <input type="hidden" name="webyaz_pages_toggle" value="0">
                            <input type="checkbox" name="webyaz_pages_toggle" value="1" <?php checked($pages_enabled, '1'); ?> onchange="this.form.submit();" style="display:none;">
                            <span style="position:absolute;top:0;left:0;right:0;bottom:0;background:<?php echo $pages_enabled === '1' ? '#4caf50' : '#ccc'; ?>;border-radius:28px;transition:0.3s;box-shadow:inset 0 1px 3px rgba(0,0,0,0.2);"></span>
                            <span style="position:absolute;top:3px;left:<?php echo $pages_enabled === '1' ? '27px' : '3px'; ?>;width:22px;height:22px;background:#fff;border-radius:50%;transition:0.3s;box-shadow:0 2px 4px rgba(0,0,0,0.2);"></span>
                        </label>
                    </div>
                </div>
            </form>

            <div id="wzPagesArea" style="<?php echo $pages_enabled !== '1' ? 'display:none;' : ''; ?>">
                <form method="post" id="wzPagesForm">
                    <?php wp_nonce_field('webyaz_action', 'webyaz_nonce'); ?>
                    <input type="hidden" name="webyaz_action" value="create_selected_pages">

                    <?php foreach ($groups as $group_key => $group_title): ?>
                        <div class="webyaz-section-title"><?php echo esc_html($group_title); ?></div>
                        <?php foreach ($features as $key => $f): if ($f['group'] !== $group_key) continue; ?>
                            <div class="webyaz-card">
                                <div class="webyaz-card-inner">
                                    <div class="webyaz-card-left">
                                        <?php if ($f['group'] === 'pages' && $f['status'] === 'missing'): ?>
                                            <label style="display:flex;align-items:center;cursor:pointer;margin-right:4px;">
                                                <input type="checkbox" name="webyaz_pages[]" value="<?php echo esc_attr($key); ?>" class="wz-page-checkbox" checked style="width:18px;height:18px;accent-color:#4caf50;cursor:pointer;">
                                            </label>
                                        <?php endif; ?>
                                        <div class="webyaz-card-icon <?php echo $f['status'] === 'active' ? 'green' : 'red'; ?>">
                                            <span class="dashicons <?php echo $f['status'] === 'active' ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                                        </div>
                                        <div>
                                            <div class="webyaz-card-title"><?php echo esc_html($f['title']); ?></div>
                                            <div class="webyaz-card-desc"><?php echo esc_html($f['desc']); ?></div>
                                        </div>
                                    </div>
                                    <div class="webyaz-card-actions">
                                        <span class="webyaz-badge <?php echo $f['status'] === 'active' ? 'active' : 'missing'; ?>">
                                            <?php echo $f['status'] === 'active' ? 'Aktif' : 'Eksik'; ?>
                                        </span>
                                        <?php if ($f['group'] === 'pages' && $f['status'] === 'active'): ?>
                                            <a href="<?php echo esc_url($f['view_url']); ?>" target="_blank" class="webyaz-btn webyaz-btn-outline">Goruntule</a>
                                            <a href="<?php echo esc_url($f['edit_url']); ?>" class="webyaz-btn webyaz-btn-outline">Duzenle</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>

                    <?php
                    // Eksik sayfa sayisi
                    $missing_pages = array_filter($features, function ($f) {
                        return $f['group'] === 'pages' && $f['status'] === 'missing';
                    });
                    if (!empty($missing_pages)):
                    ?>
                        <div style="margin-top:20px;background:#f8f9fa;border:1px solid #e0e0e0;border-radius:10px;padding:18px 22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <span style="font-size:20px;">📋</span>
                                <div>
                                    <div style="font-size:14px;font-weight:600;color:#333;" id="wzPageCount"><?php echo count($missing_pages); ?> eksik sayfa secili</div>
                                    <div style="font-size:12px;color:#888;">Olusturmak istediginiz sayfalari isaretleyin</div>
                                </div>
                            </div>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <button type="button" onclick="document.querySelectorAll('.wz-page-checkbox').forEach(function(c){c.checked=true;});wzUpdateCount();" class="webyaz-btn webyaz-btn-outline" style="padding:8px 16px;font-size:12px;">Tumunu Sec</button>
                                <button type="button" onclick="document.querySelectorAll('.wz-page-checkbox').forEach(function(c){c.checked=false;});wzUpdateCount();" class="webyaz-btn webyaz-btn-outline" style="padding:8px 16px;font-size:12px;">Tumunu Kaldir</button>
                                <button type="submit" class="webyaz-btn webyaz-btn-secondary" style="padding:10px 24px;font-size:14px;">
                                    <span class="dashicons dashicons-welcome-add-page" style="vertical-align:middle;margin-right:4px;font-size:16px;"></span>Seçilenleri Yükle
                                </button>
                            </div>
                        </div>
                        <script>
                            function wzUpdateCount() {
                                var c = document.querySelectorAll('.wz-page-checkbox:checked').length;
                                var t = document.querySelectorAll('.wz-page-checkbox').length;
                                document.getElementById('wzPageCount').textContent = c + ' / ' + t + ' eksik sayfa secili';
                            }
                            document.querySelectorAll('.wz-page-checkbox').forEach(function(cb) {
                                cb.addEventListener('change', wzUpdateCount);
                            });
                        </script>
                    <?php endif; ?>
                </form>
            </div>


            <div class="webyaz-section-title" style="margin-top:35px;">Kullanim Rehberi &amp; Notlar</div>
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;margin-bottom:20px;">
                <!-- Tab Basliklari -->
                <div id="wzGuideTabs" style="display:flex;border-bottom:2px solid #e0e0e0;background:#fafafa;overflow-x:auto;">
                    <button type="button" class="wz-guide-tab wz-guide-tab-active" data-tab="tab-xml" style="flex:1;min-width:0;padding:14px 10px;border:none;background:transparent;font-size:13px;font-weight:600;color:#666;cursor:pointer;position:relative;transition:all .2s;display:flex;flex-direction:column;align-items:center;gap:4px;white-space:nowrap;">
                        <span style="font-size:18px;">📦</span> XML / Urun
                    </button>
                    <button type="button" class="wz-guide-tab" data-tab="tab-attr" style="flex:1;min-width:0;padding:14px 10px;border:none;background:transparent;font-size:13px;font-weight:600;color:#666;cursor:pointer;position:relative;transition:all .2s;display:flex;flex-direction:column;align-items:center;gap:4px;white-space:nowrap;">
                        <span style="font-size:18px;">🎨</span> Nitelikler
                    </button>
                    <button type="button" class="wz-guide-tab" data-tab="tab-shortcode" style="flex:1;min-width:0;padding:14px 10px;border:none;background:transparent;font-size:13px;font-weight:600;color:#666;cursor:pointer;position:relative;transition:all .2s;display:flex;flex-direction:column;align-items:center;gap:4px;white-space:nowrap;">
                        <span style="font-size:18px;">⚡</span> Shortcode
                    </button>
                    <button type="button" class="wz-guide-tab" data-tab="tab-notes" style="flex:1;min-width:0;padding:14px 10px;border:none;background:transparent;font-size:13px;font-weight:600;color:#666;cursor:pointer;position:relative;transition:all .2s;display:flex;flex-direction:column;align-items:center;gap:4px;white-space:nowrap;">
                        <span style="font-size:18px;">⚠️</span> Notlar
                    </button>
                    <button type="button" class="wz-guide-tab" data-tab="tab-backup" style="flex:1;min-width:0;padding:14px 10px;border:none;background:transparent;font-size:13px;font-weight:600;color:#666;cursor:pointer;position:relative;transition:all .2s;display:flex;flex-direction:column;align-items:center;gap:4px;white-space:nowrap;">
                        <span style="font-size:18px;">💾</span> Yedekleme
                    </button>
                    <button type="button" class="wz-guide-tab" data-tab="tab-start" style="flex:1;min-width:0;padding:14px 10px;border:none;background:transparent;font-size:13px;font-weight:600;color:#666;cursor:pointer;position:relative;transition:all .2s;display:flex;flex-direction:column;align-items:center;gap:4px;white-space:nowrap;">
                        <span style="font-size:18px;">🚀</span> Baslangiç
                    </button>
                </div>

                <!-- Tab Icerikleri -->
                <div id="tab-xml" class="wz-guide-panel" style="padding:22px 25px;">
                    <h3 style="margin:0 0 12px;font-size:16px;color:#1565c0;display:flex;align-items:center;gap:8px;"><span style="background:#e3f2fd;padding:6px 10px;border-radius:8px;font-size:18px;">📦</span> XML / Excel ile Urun Yukleme</h3>
                    <ul style="margin:0;padding-left:18px;font-size:13px;color:#333;line-height:2.2;">
                        <li><strong>XML Import:</strong> Webyaz > XML Yonetimi > Toptanci Ekle > Link yapistir > Analiz Et > Alan eslestir > Iceri Aktar</li>
                        <li><strong>Excel/CSV:</strong> XML sayfasinda "Excel / CSV" tabina tikla > Dosya sec > Otomatik analiz > Eslestir > Aktar</li>
                        <li><strong>Gorsel URL:</strong> Excel'de "Gorsel URL" sutununa resim linkini yaz, sistem otomatik indirir</li>
                        <li><strong>Galeri:</strong> Birden fazla gorseli virgul ile ayir: <code>link1.jpg,link2.jpg</code></li>
                        <li><strong>Sablon:</strong> "Sablon Indir" butonu ile ornek CSV dosyasini indirip doldurun</li>
                        <li><strong>Otomatik Cekme:</strong> Toptanci satirinda "Gunde 1/2 Kez" secip saat belirleyin, sistem gece otomatik ceker</li>
                    </ul>
                </div>

                <div id="tab-attr" class="wz-guide-panel" style="padding:22px 25px;display:none;">
                    <h3 style="margin:0 0 12px;font-size:16px;color:#2e7d32;display:flex;align-items:center;gap:8px;"><span style="background:#e8f5e9;padding:6px 10px;border-radius:8px;font-size:18px;">🎨</span> Urun Nitelikleri (Beden/Renk/Numara)</h3>
                    <ul style="margin:0;padding-left:18px;font-size:13px;color:#333;line-height:2.2;">
                        <li><strong>Beden:</strong> Urun duzenle > "Webyaz Nitelikler" kutusunda "Bedenler" aktif et, bedenleri sec</li>
                        <li><strong>Renk:</strong> "Renkler" aktif et, hazir renklerden sec veya palet ile ozel renk olustur</li>
                        <li><strong>Ayakkabi No:</strong> "Ayakkabi Numaralari" aktif et, numaralari sec</li>
                        <li><strong>Satis Birimi:</strong> Duzine, gross, paket gibi birimleri sec veya + ile ozel ekle</li>
                        <li><strong>Ilave Beden:</strong> 5XL sonrasi + simgesiyle yeni beden ekleyebilirsiniz</li>
                    </ul>
                </div>

                <div id="tab-shortcode" class="wz-guide-panel" style="padding:22px 25px;display:none;">
                    <h3 style="margin:0 0 12px;font-size:16px;color:#e65100;display:flex;align-items:center;gap:8px;"><span style="background:#fff3e0;padding:6px 10px;border-radius:8px;font-size:18px;">⚡</span> Shortcode'lar</h3>
                    <ul style="margin:0;padding-left:18px;font-size:13px;color:#333;line-height:2.2;">
                        <li><code>[webyaz_story]</code> - Ana sayfada story carousel gosterir</li>
                        <li><code>[webyaz_marquee]</code> - Kayan yazi seridi (UX Builder veya sayfa icine ekleyin)</li>
                        <li><code>[webyaz_recently_viewed]</code> - Son goruntulenen urunler</li>
                        <li><strong>Footer:</strong> Webyaz > Footer Ayarlari'ndan yonetin</li>
                        <li><strong>Mobil Menu:</strong> Webyaz > Mobil Menu'den butonlari ayarlayin</li>
                        <li><strong>Iletisim Butonlari:</strong> Webyaz > Canli Destek'ten aktif edin, konum ayarlayin</li>
                    </ul>
                </div>

                <div id="tab-notes" class="wz-guide-panel" style="padding:22px 25px;display:none;">
                    <h3 style="margin:0 0 12px;font-size:16px;color:#c62828;display:flex;align-items:center;gap:8px;"><span style="background:#fce4ec;padding:6px 10px;border-radius:8px;font-size:18px;">⚠️</span> Onemli Notlar</h3>
                    <ul style="margin:0;padding-left:18px;font-size:13px;color:#333;line-height:2.2;">
                        <li><strong>Renkler:</strong> Tema renklerinden otomatik alinir (Flatsome > Tema Ayarlari)</li>
                        <li><strong>Yazitipi:</strong> Eklenti aktifken tum site Roboto kullanir</li>
                        <li><strong>Sozlesmeler:</strong> Webyaz > Ayarlar'dan firma bilgilerini girin, sozlesmelere otomatik yazilir</li>
                        <li><strong>Musteri Paneli:</strong> Magaza yoneticisi rolundeki kullanicilar Webyaz menulerini goremez</li>
                        <li><strong>WebP:</strong> Yuklenen gorseller otomatik WebP'ye cevrilir, eski gorselleri "WebP Resim" sayfasindan topluca cevirin</li>
                        <li><strong>Sepet Hatirlatma:</strong> E-posta sablonunu ve kupon kodunu Webyaz > Sepet Hatirlatma'dan ayarlayin</li>
                        <li><strong>XML Feed:</strong> <?php echo esc_url(home_url('/webyaz-xml-feed/')); ?> linkini pazaryerlerine verin</li>
                    </ul>
                </div>

                <div id="tab-backup" class="wz-guide-panel" style="padding:22px 25px;display:none;">
                    <h3 style="margin:0 0 12px;font-size:16px;color:#4527a0;display:flex;align-items:center;gap:8px;"><span style="background:#ede7f6;padding:6px 10px;border-radius:8px;font-size:18px;">💾</span> Yedekleme &amp; Geri Yukleme</h3>
                    <ul style="margin:0;padding-left:18px;font-size:13px;color:#333;line-height:2.2;">
                        <li><strong>Yedek Al:</strong> Webyaz > Yedek & Geri Yukle > "Yedek Olustur" butonuna tiklayin</li>
                        <li><strong>Icerdigi:</strong> Veritabani + Temalar + Eklentiler + Uploads + wp-config.php + .htaccess</li>
                        <li><strong>Format:</strong> .wbak (ZIP arsivi) - bilgisayariniza indirebilirsiniz</li>
                        <li><strong>Geri Yukle:</strong> "Geri Yukle" tabinda yedek secin veya dosya yukleyin</li>
                        <li><strong>URL Degistirme:</strong> Farkli domaine tasirken URL'ler otomatik degistirilir</li>
                        <li><strong>Admin Hesabi:</strong> Geri yuklerken yeni admin kullanici adi/sifre belirleyebilirsiniz</li>
                        <li><strong>FTP Yukleme:</strong> <code>wp-content/webyaz-backups/</code> klasorune .wbak dosyasi FTP ile de yuklenebilir</li>
                        <li><strong>Guvenlik:</strong> Yedek dizini .htaccess ile korunur, disaridan erisilemez</li>
                    </ul>
                </div>

                <div id="tab-start" class="wz-guide-panel" style="padding:22px 25px;display:none;">
                    <h3 style="margin:0 0 12px;font-size:16px;color:#7b1fa2;display:flex;align-items:center;gap:8px;"><span style="background:#f3e5f5;padding:6px 10px;border-radius:8px;font-size:18px;">🚀</span> Hizli Baslangiç Kontrol Listesi</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;font-size:13px;color:#333;">
                        <div style="background:#f9f5ff;border-radius:10px;padding:16px;">
                            <p style="font-weight:700;margin:0 0 8px;color:#7b1fa2;">1. Temel Ayarlar</p>
                            <ul style="margin:0;padding-left:16px;line-height:2;">
                                <li>Webyaz > Ayarlar > Firma bilgileri gir</li>
                                <li>WhatsApp numarasi gir</li>
                                <li>Sosyal medya linkleri gir</li>
                            </ul>
                        </div>
                        <div style="background:#f9f5ff;border-radius:10px;padding:16px;">
                            <p style="font-weight:700;margin:0 0 8px;color:#7b1fa2;">2. Sayfalari Olustur</p>
                            <ul style="margin:0;padding-left:16px;line-height:2;">
                                <li>"Tum Eksik Sayfalari Olustur" tikla</li>
                                <li>Footer ayarlarini yap</li>
                                <li>Mobil menu ayarla</li>
                            </ul>
                        </div>
                        <div style="background:#f9f5ff;border-radius:10px;padding:16px;">
                            <p style="font-weight:700;margin:0 0 8px;color:#7b1fa2;">3. Urun Ekle</p>
                            <ul style="margin:0;padding-left:16px;line-height:2;">
                                <li>Hizli Urun Ekle veya Toplu Ekle</li>
                                <li>XML/Excel'den import</li>
                                <li>Etiketleri ayarla</li>
                                <li>Pazaryeri: Trendyol/HB API bagla</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab JavaScript -->
            <script>
                (function() {
                    var tabs = document.querySelectorAll('.wz-guide-tab');
                    var panels = document.querySelectorAll('.wz-guide-panel');
                    var primary = '<?php
                                    $tab_primary = "#446084";
                                    if (class_exists("Webyaz_Colors")) {
                                        $tc = Webyaz_Colors::get_theme_colors();
                                        $tab_primary = $tc["primary"];
                                    }
                                    echo esc_js($tab_primary);
                                    ?>';

                    function activateTab(btn) {
                        tabs.forEach(function(t) {
                            t.classList.remove('wz-guide-tab-active');
                            t.style.color = '#666';
                            t.style.background = 'transparent';
                            t.style.borderBottom = 'none';
                        });
                        panels.forEach(function(p) {
                            p.style.display = 'none';
                        });

                        btn.classList.add('wz-guide-tab-active');
                        btn.style.color = primary;
                        btn.style.background = '#fff';
                        btn.style.borderBottom = '3px solid ' + primary;
                        var target = document.getElementById(btn.dataset.tab);
                        if (target) target.style.display = 'block';
                    }

                    tabs.forEach(function(tab) {
                        tab.addEventListener('click', function() {
                            activateTab(this);
                        });
                    });

                    // Ilk tab'i aktif et
                    if (tabs.length > 0) activateTab(tabs[0]);
                })();
            </script>
            <style>
                .wz-guide-tab {
                    outline: none;
                }

                .wz-guide-tab:hover {
                    background: #f5f5f5 !important;
                    color: #333 !important;
                }

                .wz-guide-tab-active:hover {
                    background: #fff !important;
                }

                .wz-guide-panel ul li {
                    padding: 2px 0;
                }

                .wz-guide-panel ul li strong {
                    color: #1a1a1a;
                }

                .wz-guide-panel code {
                    background: #f0f0f0;
                    padding: 2px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                }
            </style>
        </div>
    <?php
    }

    public function render_settings()
    {
        $opts = self::get_all();
    ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Webyaz Ayarlar</h1>
                <p>Sirket bilgileri, WhatsApp ve sosyal medya ayarlari</p>
            </div>

            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="webyaz-notice success">Ayarlar kaydedildi!</div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('webyaz_settings_group'); ?>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Sirket Bilgileri</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:15px;">Bu bilgiler Mesafeli Satis Sozlesmesi ve diger yasal sayfalarda kullanilir.</p>
                    <div class="webyaz-settings-grid">
                        <?php
                        self::render_field('company_name', 'Sirket / Magaza Adi', $opts, 'text', 'Ornek: Webyaz Yazilim');
                        self::render_field('company_address', 'Adres', $opts, 'text', 'Tam adresiniz');
                        self::render_field('company_phone', 'Telefon', $opts, 'text', '0XXX XXX XX XX');
                        self::render_field('company_email', 'E-posta', $opts, 'email', 'info@siteadi.com');
                        self::render_field('company_tax_office', 'Vergi Dairesi', $opts, 'text', 'Vergi dairesi adi');
                        self::render_field('company_tax_no', 'Vergi No', $opts, 'text', 'Vergi numarasi');
                        self::render_field('company_mersis', 'MERSIS No (opsiyonel)', $opts, 'text', 'MERSIS numarasi');
                        ?>
                    </div>
                    <div style="grid-column:span 2;border-top:1px solid #eee;padding-top:15px;">
                        <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:8px;">Firma Logosu</label>
                        <div style="display:flex;align-items:center;gap:15px;">
                            <input type="text" id="webyaz_company_logo" name="webyaz_settings[company_logo]" value="<?php echo esc_attr($opts['company_logo']); ?>" placeholder="Logo URL" style="flex:1;border:1px solid #ddd;border-radius:6px;padding:10px 12px;font-size:14px;">
                            <button type="button" id="webyaz_logo_btn" style="background:#446084;color:#fff;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;font-weight:600;font-size:13px;white-space:nowrap;">Medya Sec</button>
                        </div>
                        <?php if (!empty($opts['company_logo'])): ?>
                            <div style="margin-top:10px;"><img src="<?php echo esc_url($opts['company_logo']); ?>" style="max-height:60px;border-radius:6px;border:1px solid #eee;padding:5px;background:#fff;" alt="Logo"></div>
                        <?php endif; ?>
                    </div>
                </div>
                <script>
                    jQuery(document).ready(function($) {
                        $('#webyaz_logo_btn').on('click', function(e) {
                            e.preventDefault();
                            var frame = wp.media({
                                title: 'Logo Sec',
                                button: {
                                    text: 'Logo Olarak Kullan'
                                },
                                multiple: false
                            });
                            frame.on('select', function() {
                                var att = frame.state().get('selection').first().toJSON();
                                $('#webyaz_company_logo').val(att.url);
                            });
                            frame.open();
                        });
                    });
                </script>

                <div class="webyaz-settings-section" style="margin-top:30px;">
                    <h2 class="webyaz-section-title">WhatsApp Canli Destek</h2>
                    <div class="webyaz-settings-grid">
                        <?php
                        self::render_field('whatsapp_number', 'WhatsApp Telefon No', $opts, 'text', '905XXXXXXXXX (basinda 90)');
                        self::render_field('whatsapp_message', 'Varsayilan Mesaj', $opts, 'text', 'Merhaba, bilgi almak istiyorum.');
                        self::render_field('whatsapp_online_text', 'Online Metin', $opts, 'text', 'Size nasil yardimci olabiliriz?');
                        self::render_field('whatsapp_offline_text', 'Offline Metin', $opts, 'text', 'Su an mesai disiyiz.');
                        self::render_field('whatsapp_start_hour', 'Mesai Baslangic', $opts, 'time', '09:00');
                        self::render_field('whatsapp_end_hour', 'Mesai Bitis', $opts, 'time', '18:00');
                        ?>
                    </div>
                </div>

                <div class="webyaz-settings-section" style="margin-top:30px;">
                    <h2 class="webyaz-section-title">Sosyal Medya</h2>
                    <div class="webyaz-settings-grid">
                        <?php
                        self::render_field('social_facebook', 'Facebook', $opts, 'url', 'https://facebook.com/sayfaniz');
                        self::render_field('social_instagram', 'Instagram', $opts, 'url', 'https://instagram.com/hesabiniz');
                        self::render_field('social_twitter', 'X (Twitter)', $opts, 'url', 'https://x.com/hesabiniz');
                        self::render_field('social_youtube', 'YouTube', $opts, 'url', 'https://youtube.com/kanaliniz');
                        self::render_field('social_tiktok', 'TikTok', $opts, 'url', 'https://tiktok.com/@hesabiniz');
                        self::render_field('social_linkedin', 'LinkedIn', $opts, 'url', 'https://linkedin.com/company/sirketiniz');
                        ?>
                    </div>
                </div>

                <div style="margin-top:25px;">
                    <?php submit_button('Ayarlari Kaydet', 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>
<?php
    }

    private static function render_field($key, $label, $opts, $type, $placeholder)
    {
        $val = isset($opts[$key]) ? esc_attr($opts[$key]) : '';
        echo '<div class="webyaz-field">';
        echo '<label for="webyaz_' . $key . '">' . $label . '</label>';
        echo '<input type="' . $type . '" id="webyaz_' . $key . '" name="webyaz_settings[' . $key . ']" value="' . $val . '" placeholder="' . esc_attr($placeholder) . '">';
        echo '</div>';
    }
}

new Webyaz_Settings();
