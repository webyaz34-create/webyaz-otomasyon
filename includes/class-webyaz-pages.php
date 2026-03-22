<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Pages
{

    public function __construct()
    {
        add_action('admin_init', array($this, 'handle_actions'));
        add_filter('the_content', array($this, 'legal_page_wrapper'), 20);
        add_filter('woocommerce_get_privacy_policy_text', array($this, 'checkout_privacy_text'), 10, 2);
    }

    /**
     * WooCommerce checkout sayfasındaki gizlilik metnini Türkçe yapma
     */
    public function checkout_privacy_text($text, $type)
    {
        $colors = Webyaz_Colors::get_theme_colors();
        $primary = $colors['primary'];

        $link_style = 'color:' . $primary . ';font-weight:600;text-decoration:underline;';

        if ($type === 'checkout') {
            $gizlilik = get_page_by_path('gizlilik-politikasi');
            $kvkk = get_page_by_path('kvkk-aydinlatma-metni');
            $mesafeli = get_page_by_path('mesafeli-satis-sozlesmesi');

            $new_text = 'Kisisel verileriniz, siparisinizin islenmesi ve bu web sitesindeki deneyiminizi desteklemek amaciyla kullanilacaktir.';

            $links = array();
            if ($gizlilik) {
                $links[] = '<a href="' . get_permalink($gizlilik->ID) . '" target="_blank" style="' . $link_style . '">Gizlilik Politikamizi</a>';
            }
            if ($kvkk) {
                $links[] = '<a href="' . get_permalink($kvkk->ID) . '" target="_blank" style="' . $link_style . '">KVKK Aydinlatma Metnimizi</a>';
            }
            if ($mesafeli) {
                $links[] = '<a href="' . get_permalink($mesafeli->ID) . '" target="_blank" style="' . $link_style . '">Mesafeli Satis Sozlesmesini</a>';
            }

            if (!empty($links)) {
                $new_text .= ' Detayli bilgi icin ';
                if (count($links) === 1) {
                    $new_text .= $links[0];
                } elseif (count($links) === 2) {
                    $new_text .= $links[0] . ' ve ' . $links[1];
                } else {
                    $last = array_pop($links);
                    $new_text .= implode(', ', $links) . ' ve ' . $last;
                }
                $new_text .= ' inceleyebilirsiniz.';
            }

            return $new_text;
        }

        if ($type === 'registration') {
            $kvkk = get_page_by_path('kvkk-aydinlatma-metni');
            $gizlilik = get_page_by_path('gizlilik-politikasi');

            $new_text = 'Kisisel verileriniz 6698 sayili KVKK kapsaminda korunmaktadir.';
            if ($kvkk) {
                $new_text .= ' <a href="' . get_permalink($kvkk->ID) . '" target="_blank" style="' . $link_style . '">KVKK Aydinlatma Metni</a>';
            }
            if ($gizlilik) {
                $new_text .= ' ve <a href="' . get_permalink($gizlilik->ID) . '" target="_blank" style="' . $link_style . '">Gizlilik Politikasi</a>';
            }

            return $new_text;
        }

        return $text;
    }

    private static function legal_page_slugs()
    {
        return array(
            'mesafeli-satis-sozlesmesi',
            'on-bilgilendirme-formu',
            'site-kullanim-kurallari',
            'kvkk-aydinlatma-metni',
            'acik-riza-metni',
            'gizlilik-politikasi',
            'iade-ve-degisim-kosullari',
            'teslimat-ve-kargo',
            'odeme-guvenligi',
            'uyelik-sozlesmesi',
            'hakkimizda',
            'sikca-sorulan-sorular',
        );
    }

    public function legal_page_wrapper($content)
    {
        if (!is_page() || is_admin()) return $content;

        global $post;
        $slug = $post->post_name;
        $is_legal = in_array($slug, self::legal_page_slugs());
        $is_branded = get_post_meta($post->ID, '_webyaz_branded', true) === '1';
        if (!$is_legal && !$is_branded) return $content;

        // Ayarlardan bilgiler
        $site = Webyaz_Settings::get('company_name');
        if (empty($site)) $site = get_bloginfo('name');
        $logo = Webyaz_Settings::get('company_logo');
        $address = Webyaz_Settings::get('company_address');
        $phone = Webyaz_Settings::get('company_phone');
        $email = Webyaz_Settings::get('company_email');
        $tax_office = Webyaz_Settings::get('company_tax_office');
        $tax_no = Webyaz_Settings::get('company_tax_no');
        $mersis = Webyaz_Settings::get('company_mersis');

        $socials = array(
            'instagram' => array(Webyaz_Settings::get('social_instagram'), 'fab fa-instagram'),
            'facebook' => array(Webyaz_Settings::get('social_facebook'), 'fab fa-facebook-f'),
            'youtube' => array(Webyaz_Settings::get('social_youtube'), 'fab fa-youtube'),
            'twitter' => array(Webyaz_Settings::get('social_twitter'), 'fab fa-x-twitter'),
            'tiktok' => array(Webyaz_Settings::get('social_tiktok'), 'fab fa-tiktok'),
            'linkedin' => array(Webyaz_Settings::get('social_linkedin'), 'fab fa-linkedin-in'),
        );

        // Renkler
        $colors = Webyaz_Colors::get_theme_colors();
        $primary = $colors['primary'];
        $secondary = $colors['secondary'];

        // Kontrast
        $is_primary_dark = $this->is_dark_color($primary);
        $on_primary = $is_primary_dark ? '#ffffff' : '#111111';
        $is_secondary_dark = $this->is_dark_color($secondary);
        $on_secondary = $is_secondary_dark ? '#ffffff' : '#111111';
        $dark_class = $is_primary_dark ? 'wz-dark-theme' : 'wz-light-theme';

        ob_start();
?>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            .wz-legal-wrap {
                background-color: #FDFBF7;
                padding: 40px 15px;
                font-family: 'Roboto', 'Segoe UI', sans-serif;
            }

            .wz-legal-container {
                max-width: 900px;
                margin: 0 auto;
                background: #fff;
                border: 1px solid <?php echo $secondary; ?>;
                border-radius: 8px;
                box-shadow: 0 20px 50px rgba(0, 0, 0, 0.06);
                overflow: hidden;
            }

            .wz-legal-topbar {
                height: 6px;
                background: <?php echo $secondary; ?>;
                width: 100%;
            }

            .wz-legal-inner {
                padding: 50px;
            }

            .wz-legal-header {
                text-align: center;
                margin-bottom: 40px;
            }

            .wz-legal-logo {
                max-height: 80px;
                margin-bottom: 20px;
            }

            .wz-legal-title {
                color: <?php echo $primary; ?>;
                font-size: 28px;
                font-weight: 800;
                letter-spacing: 1px;
                margin: 0;
                line-height: 1.2;
                text-transform: uppercase;
            }

            .wz-legal-divider {
                width: 70px;
                height: 3px;
                background: <?php echo $secondary; ?>;
                margin: 20px auto;
            }

            .wz-legal-subtitle {
                color: #666;
                font-size: 14px;
                font-weight: bold;
                letter-spacing: 2px;
                text-transform: uppercase;
                margin: 0;
            }

            .wz-legal-body {
                color: #111;
                font-size: 16px;
                line-height: 1.8;
                text-align: justify;
            }

            .wz-legal-body h2,
            .wz-legal-body h3,
            .wz-legal-body h4 {
                color: <?php echo $primary; ?>;
                font-weight: 800;
                margin-top: 30px;
            }

            .wz-legal-body h2 {
                font-size: 22px;
                border-bottom: 2px solid <?php echo $secondary; ?>;
                padding-bottom: 8px;
            }

            .wz-legal-body h3 {
                font-size: 18px;
            }

            .wz-legal-body strong,
            .wz-legal-body b {
                color: <?php echo $primary; ?>;
            }

            .wz-legal-body p {
                margin-bottom: 16px;
                color: #111;
            }

            .wz-legal-body .webyaz-warning-box h3 {
                color: #fff !important;
            }

            .wz-legal-body .webyaz-legal-page {
                /* mevcut wrapper'i sifirla */
            }

            .wz-legal-highlight {
                background: #FDFBF7;
                border-left: 5px solid <?php echo $secondary; ?>;
                padding: 20px 25px;
                margin: 30px 0;
                border-radius: 0 8px 8px 0;
            }

            .wz-legal-highlight h4 {
                color: <?php echo $primary; ?>;
                margin: 0 0 8px;
                font-weight: 800;
                font-size: 17px;
            }

            .wz-legal-footer {
                margin-top: 40px;
                padding-top: 30px;
                border-top: 1px solid #eee;
            }

            .wz-legal-footer-grid {
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                align-items: flex-start;
                gap: 20px;
            }

            .wz-legal-footer-left {
                flex: 1;
                min-width: 250px;
            }

            .wz-legal-footer-left strong {
                color: <?php echo $primary; ?>;
                font-size: 17px;
                display: block;
                margin-bottom: 4px;
            }

            .wz-legal-footer-left .wz-legal-badge {
                color: <?php echo $secondary; ?>;
                font-size: 12px;
                font-weight: bold;
                letter-spacing: 1px;
            }

            .wz-legal-footer-right {
                flex: 1;
                min-width: 250px;
                text-align: right;
                font-size: 14px;
                color: #555;
                line-height: 1.8;
            }

            .wz-legal-footer-right strong {
                color: <?php echo $primary; ?>;
            }

            .wz-legal-social {
                text-align: center;
                margin-top: 25px;
                padding-top: 20px;
                border-top: 1px solid #eee;
            }

            .wz-legal-social a {
                display: inline-flex;
                width: 40px;
                height: 40px;
                background: <?php echo $primary; ?>;
                color: <?php echo $on_primary; ?>;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                margin: 0 5px;
                text-decoration: none;
                transition: 0.3s;
                font-size: 1.1rem;
            }

            .wz-legal-social a:hover {
                background: <?php echo $secondary; ?>;
                color: <?php echo $on_secondary; ?>;
                transform: translateY(-3px);
            }

            .wz-legal-bottombar {
                height: 8px;
                background: <?php echo $primary; ?>;
                width: 100%;
            }

            @media(max-width:768px) {
                .wz-legal-inner {
                    padding: 25px 20px;
                }

                .wz-legal-title {
                    font-size: 22px;
                }

                .wz-legal-footer-grid {
                    flex-direction: column;
                }

                .wz-legal-footer-right {
                    text-align: left;
                }
            }
        </style>
        <div class="wz-legal-wrap <?php echo $dark_class; ?>">
            <div class="wz-legal-container">
                <div class="wz-legal-topbar"></div>
                <div class="wz-legal-inner">

                    <div class="wz-legal-header">
                        <?php if ($logo): ?>
                            <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($site); ?>" class="wz-legal-logo">
                        <?php endif; ?>
                        <h1 class="wz-legal-title"><?php echo esc_html($site); ?></h1>
                        <div class="wz-legal-divider"></div>
                        <p class="wz-legal-subtitle"><?php echo esc_html(get_the_title()); ?></p>
                    </div>

                    <div class="wz-legal-body">
                        <?php echo $content; ?>
                    </div>

                    <div class="wz-legal-footer">
                        <div class="wz-legal-footer-grid">
                            <div class="wz-legal-footer-left">
                                <strong><?php echo esc_html($site); ?></strong>
                                <span class="wz-legal-badge">Kurumsal Bilgiler</span>
                                <?php if ($tax_office || $tax_no): ?>
                                    <p style="font-size:13px;color:#888;margin-top:8px;">
                                        <?php if ($tax_office) echo 'V.D: ' . esc_html($tax_office); ?>
                                        <?php if ($tax_no) echo ' &bull; V.No: ' . esc_html($tax_no); ?>
                                        <?php if ($mersis) echo '<br>MERSIS: ' . esc_html($mersis); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="wz-legal-footer-right">
                                <?php if ($address): ?>
                                    <p style="margin:0 0 6px;"><strong>Adres:</strong><br><?php echo esc_html($address); ?></p>
                                <?php endif; ?>
                                <?php if ($phone): ?>
                                    <p style="margin:0 0 4px;"><strong>Tel:</strong> <a href="tel:<?php echo esc_attr($phone); ?>" style="color:inherit;text-decoration:none;"><?php echo esc_html($phone); ?></a></p>
                                <?php endif; ?>
                                <?php if ($email): ?>
                                    <p style="margin:0;"><strong>E-posta:</strong> <a href="mailto:<?php echo esc_attr($email); ?>" style="color:inherit;text-decoration:none;"><?php echo esc_html($email); ?></a></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php
                        $has_social = false;
                        foreach ($socials as $s) {
                            if ($s[0]) {
                                $has_social = true;
                                break;
                            }
                        }
                        if ($has_social):
                        ?>
                            <div class="wz-legal-social">
                                <?php foreach ($socials as $key => $s):
                                    if (empty($s[0])) continue;
                                ?>
                                    <a href="<?php echo esc_url($s[0]); ?>" target="_blank" rel="noopener"><i class="<?php echo $s[1]; ?>"></i></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
                <div class="wz-legal-bottombar"></div>
            </div>
        </div>
<?php
        return ob_get_clean();
    }

    private function is_dark_color($hex)
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return $luminance < 0.5;
    }



    public static function get_features_status()
    {
        $features = array();

        $page_defs = self::get_page_definitions();
        foreach ($page_defs as $slug => $def) {
            $page = get_page_by_path($slug);
            $features[$slug] = array(
                'title' => $def['title'],
                'desc' => $def['desc'],
                'group' => 'pages',
                'status' => $page ? 'active' : 'missing',
                'view_url' => $page ? get_permalink($page->ID) : '',
                'edit_url' => $page ? get_edit_post_link($page->ID, 'raw') : '',
            );
        }

        $features['checkout_form'] = array(
            'title' => 'Bireysel / Kurumsal Form',
            'desc' => 'Odeme sayfasinda TC Kimlik, Firma, Vergi bilgileri',
            'group' => 'checkout',
            'status' => 'active',
        );

        $features['cargo'] = array(
            'title' => 'Kargo Takip',
            'desc' => 'Siparis detayinda kargo takip bilgisi ve link',
            'group' => 'checkout',
            'status' => 'active',
        );

        $features['gift'] = array(
            'title' => 'Hediye Paketi & Siparis Notu',
            'desc' => 'Checkout\'ta hediye secenekleri',
            'group' => 'checkout',
            'status' => 'active',
        );

        $features['tab_teslimat'] = array(
            'title' => 'Teslimat Bilgileri Sekmesi',
            'desc' => 'Urun sayfasinda teslimat bilgileri tab\'i',
            'group' => 'product',
            'status' => 'active',
        );

        $features['tab_iade'] = array(
            'title' => 'Iade ve Degisim Sekmesi',
            'desc' => 'Urun sayfasinda iade ve degisim tab\'i',
            'group' => 'product',
            'status' => 'active',
        );

        $features['stock_alert'] = array(
            'title' => 'Stok Alarm Bildirimi',
            'desc' => 'Stokta olmayan urunlerde e-posta bildirimi',
            'group' => 'product',
            'status' => 'active',
        );

        $features['antibot'] = array(
            'title' => 'Anti-Bot Koruma',
            'desc' => 'Honeypot + zaman dogrulamasi ile sahte kayitlari engeller',
            'group' => 'security',
            'status' => 'active',
        );

        $features['whatsapp'] = array(
            'title' => 'WhatsApp Canli Destek',
            'desc' => 'Sag alt kosede WhatsApp destek butonu',
            'group' => 'security',
            'status' => Webyaz_Settings::get('whatsapp_number') ? 'active' : 'missing',
        );

        $features['cross_sell'] = array(
            'title' => 'Birlikte Satin Alinanlar',
            'desc' => 'Urun sayfasinda otomatik urun onerileri',
            'group' => 'product',
            'status' => 'active',
        );

        $features['popup'] = array(
            'title' => 'Kupon / Hosgeldin Popup',
            'desc' => 'Ziyaretcilere indirim kodu gosterimi',
            'group' => 'product',
            'status' => 'active',
        );

        $features['seo'] = array(
            'title' => 'SEO (Open Graph & Schema)',
            'desc' => 'Sosyal medya paylasim ve arama motoru optimizasyonu',
            'group' => 'product',
            'status' => 'active',
        );

        $features['order_status'] = array(
            'title' => 'Siparis Durum Bildirimleri',
            'desc' => 'Siparis durumu degistiginde otomatik e-posta',
            'group' => 'checkout',
            'status' => 'active',
        );

        $features['compare'] = array(
            'title' => 'Urun Karsilastirma',
            'desc' => 'Urunleri yan yana karsilastirma',
            'group' => 'product',
            'status' => 'active',
        );

        $features['wishlist'] = array(
            'title' => 'Favoriler (Istek Listesi)',
            'desc' => 'Kullanicilarin urunleri favorilere eklemesi',
            'group' => 'product',
            'status' => 'active',
        );

        $features['brute_force'] = array(
            'title' => 'Brute Force Koruma',
            'desc' => 'Login denemelerini sinirlandirma ve IP engelleme',
            'group' => 'security',
            'status' => 'active',
        );

        $features['mobile_bar'] = array(
            'title' => 'Mobil Alt Menu',
            'desc' => 'Telefonda altta gorunen sabit navigasyon cubugu',
            'group' => 'product',
            'status' => 'active',
        );

        $features['auto_tags'] = array(
            'title' => 'Otomatik Urun Etiketleri',
            'desc' => 'Yeni urunlere otomatik etiket atama',
            'group' => 'product',
            'status' => 'active',
        );

        $features['footer'] = array(
            'title' => 'Ozel Footer',
            'desc' => '4 sutunlu, renk ayarli profesyonel alt bilgi',
            'group' => 'pages',
            'status' => 'active',
        );

        $features['cookie'] = array(
            'title' => 'Cerez Bildirimi',
            'desc' => 'KVKK/GDPR uyumlu cerez kabul banner',
            'group' => 'security',
            'status' => 'active',
        );

        $features['recently_viewed'] = array(
            'title' => 'Son Goruntulenen Urunler',
            'desc' => 'Urun sayfasinda son bakilan urunleri gosterir',
            'group' => 'product',
            'status' => 'active',
        );

        $features['qa'] = array(
            'title' => 'Urun Soru & Cevap',
            'desc' => 'Urun sayfasinda soru sorma ve yanitlama',
            'group' => 'product',
            'status' => 'active',
        );

        $features['size_guide'] = array(
            'title' => 'Beden Tablosu',
            'desc' => 'Giyim urunlerinde beden rehberi',
            'group' => 'product',
            'status' => 'active',
        );

        $features['attributes'] = array(
            'title' => 'Ozel Beden & Renk',
            'desc' => 'WooCommerce disinda hafif beden ve renk secenekleri',
            'group' => 'product',
            'status' => 'active',
        );

        $features['quick_product'] = array(
            'title' => 'Hizli Urun Ekleme',
            'desc' => 'Tek sayfada hizlica urun olusturma',
            'group' => 'product',
            'status' => 'active',
        );

        return $features;
    }

    public static function get_page_definitions()
    {
        return array(
            'mesafeli-satis-sozlesmesi' => array(
                'title' => 'Mesafeli Satis Sozlesmesi',
                'desc' => '6502 sayili kanun geregi zorunlu sozlesme sayfasi',
                'content_fn' => 'mesafeli_satis_content',
            ),
            'on-bilgilendirme-formu' => array(
                'title' => 'On Bilgilendirme Formu',
                'desc' => '6502 sayili kanun geregi mesafeli satista zorunlu on bilgilendirme',
                'content_fn' => 'on_bilgilendirme_content',
            ),
            'site-kullanim-kurallari' => array(
                'title' => 'Site Kullanim Kurallari',
                'desc' => 'Site kullanim sartlari ve kosullari sayfasi',
                'content_fn' => 'kullanim_kurallari_content',
            ),
            'kvkk-aydinlatma-metni' => array(
                'title' => 'KVKK Aydinlatma Metni',
                'desc' => '6698 sayili KVKK kapsaminda zorunlu aydinlatma metni',
                'content_fn' => 'kvkk_content',
            ),
            'acik-riza-metni' => array(
                'title' => 'Acik Riza Metni',
                'desc' => 'KVKK kapsaminda pazarlama ve iletisim icin acik riza',
                'content_fn' => 'acik_riza_content',
            ),
            'gizlilik-politikasi' => array(
                'title' => 'Gizlilik Politikasi',
                'desc' => 'Kisisel verilerin toplanmasi ve kullanimi hakkinda bilgilendirme',
                'content_fn' => 'gizlilik_content',
            ),
            'iade-ve-degisim-kosullari' => array(
                'title' => 'Iade ve Degisim Kosullari',
                'desc' => '14 gun cayma hakki ve iade/degisim proseduru',
                'content_fn' => 'iade_content',
            ),
            'teslimat-ve-kargo' => array(
                'title' => 'Teslimat ve Kargo Politikasi',
                'desc' => 'Kargo sureleri, ucretleri ve teslimat kosullari',
                'content_fn' => 'teslimat_content',
            ),
            'odeme-guvenligi' => array(
                'title' => 'Odeme Guvenligi',
                'desc' => 'SSL, 3D Secure ve guvenli odeme bilgilendirmesi',
                'content_fn' => 'odeme_guvenligi_content',
            ),
            'uyelik-sozlesmesi' => array(
                'title' => 'Uyelik Sozlesmesi',
                'desc' => 'Siteye uye olurken kabul edilen uyelik kosullari',
                'content_fn' => 'uyelik_sozlesmesi_content',
            ),
            'hakkimizda' => array(
                'title' => 'Hakkimizda',
                'desc' => 'Firma tanitimi ve kurumsal bilgiler',
                'content_fn' => 'hakkimizda_content',
            ),
            'sikca-sorulan-sorular' => array(
                'title' => 'Sikca Sorulan Sorular',
                'desc' => 'Musterilerin en cok sordugu sorular ve yanitlari',
                'content_fn' => 'sss_content',
            ),
            'siparis-takip' => array(
                'title' => 'Siparis Takip',
                'desc' => 'Musteri siparis numarasi ve e-posta ile siparis durumunu sorgular',
                'content_fn' => 'siparis_takip_content',
            ),
            'urun-karsilastir' => array(
                'title' => 'Urun Karsilastir',
                'desc' => 'Urunleri yan yana karsilastirma sayfasi',
                'content_fn' => 'compare_content',
            ),
        );
    }

    public function handle_actions()
    {
        if (!isset($_POST['webyaz_action'])) return;
        if (!wp_verify_nonce($_POST['webyaz_nonce'], 'webyaz_action')) return;
        if (!current_user_can('manage_options')) return;

        $action = sanitize_text_field($_POST['webyaz_action']);

        if ($action === 'create_page') {
            $key = sanitize_text_field($_POST['webyaz_page_key']);
            $defs = self::get_page_definitions();
            if (isset($defs[$key]) && !get_page_by_path($key)) {
                $fn = $defs[$key]['content_fn'];
                wp_insert_post(array(
                    'post_title' => $defs[$key]['title'],
                    'post_content' => call_user_func(array(__CLASS__, $fn)),
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $key,
                ));
            }
            wp_redirect(admin_url('admin.php?page=webyaz-dashboard&webyaz_done=1'));
            exit;
        }

        if ($action === 'create_all_pages') {
            self::create_pages();
            wp_redirect(admin_url('admin.php?page=webyaz-dashboard&webyaz_done=1'));
            exit;
        }

        if ($action === 'toggle_pages') {
            $state = sanitize_text_field($_POST['webyaz_pages_toggle']);
            if ($state === '1') {
                update_option('webyaz_pages_enabled', '1');
            } else {
                update_option('webyaz_pages_enabled', '0');
                self::trash_pages();
            }
            wp_redirect(admin_url('admin.php?page=webyaz-dashboard&webyaz_done=1'));
            exit;
        }

        if ($action === 'create_selected_pages') {
            $selected = isset($_POST['webyaz_pages']) ? array_map('sanitize_text_field', $_POST['webyaz_pages']) : array();
            if (!empty($selected)) {
                $defs = self::get_page_definitions();
                foreach ($selected as $slug) {
                    if (isset($defs[$slug]) && !get_page_by_path($slug)) {
                        $fn = $defs[$slug]['content_fn'];
                        wp_insert_post(array(
                            'post_title' => $defs[$slug]['title'],
                            'post_content' => call_user_func(array(__CLASS__, $fn)),
                            'post_status' => 'publish',
                            'post_type' => 'page',
                            'post_name' => $slug,
                        ));
                    }
                }
            }
            wp_redirect(admin_url('admin.php?page=webyaz-dashboard&webyaz_done=1'));
            exit;
        }

        if ($action === 'create_branded_page') {
            $title = sanitize_text_field($_POST['webyaz_branded_title']);
            $content = wp_kses_post($_POST['webyaz_branded_content']);
            if (!empty($title)) {
                $page_id = wp_insert_post(array(
                    'post_title' => $title,
                    'post_content' => $content,
                    'post_status' => 'publish',
                    'post_type' => 'page',
                ));
                if ($page_id && !is_wp_error($page_id)) {
                    update_post_meta($page_id, '_webyaz_branded', '1');
                }
            }
            wp_redirect(admin_url('admin.php?page=webyaz-dashboard&webyaz_done=1'));
            exit;
        }
    }

    public static function create_pages()
    {
        $defs = self::get_page_definitions();
        foreach ($defs as $slug => $def) {
            if (!get_page_by_path($slug)) {
                $fn = $def['content_fn'];
                wp_insert_post(array(
                    'post_title' => $def['title'],
                    'post_content' => call_user_func(array(__CLASS__, $fn)),
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $slug,
                ));
            }
        }
    }

    public static function trash_pages()
    {
        $defs = self::get_page_definitions();
        foreach ($defs as $slug => $def) {
            $page = get_page_by_path($slug);
            if ($page) {
                wp_trash_post($page->ID);
            }
        }
    }

    public static function mesafeli_satis_content()
    {
        $site = Webyaz_Settings::get('company_name');
        if (empty($site)) $site = get_bloginfo('name');
        $url = home_url();
        $address = Webyaz_Settings::get('company_address');
        $phone = Webyaz_Settings::get('company_phone');
        $email = Webyaz_Settings::get('company_email');
        $tax_office = Webyaz_Settings::get('company_tax_office');
        $tax_no = Webyaz_Settings::get('company_tax_no');
        $mersis = Webyaz_Settings::get('company_mersis');

        $company_info = 'Ticari Unvan: ' . $site . '<br>Web Sitesi: ' . $url;
        if ($address) $company_info .= '<br>Adres: ' . $address;
        if ($phone) $company_info .= '<br>Telefon: ' . $phone;
        if ($email) $company_info .= '<br>E-posta: ' . $email;
        if ($tax_office) $company_info .= '<br>Vergi Dairesi: ' . $tax_office;
        if ($tax_no) $company_info .= '<br>Vergi No: ' . $tax_no;
        if ($mersis) $company_info .= '<br>MERSIS No: ' . $mersis;

        return '<div class="webyaz-legal-page">
<h2>Mesafeli Satis Sozlesmesi</h2>
<h3>1. TARAFLAR</h3>
<p><strong>SATICI:</strong><br>' . $company_info . '</p>
<p><strong>ALICI:</strong><br>Siparis sirasinda beyan edilen bilgiler gecerlidir.</p>
<h3>2. KONU</h3>
<p>Isbu sozlesmenin konusu, ALICI nin SATICI ya ait web sitesinden elektronik ortamda siparis verdigi urun/urunlerin satisi ve teslimi ile ilgili olarak 6502 sayili Tuketicinin Korunmasi Hakkinda Kanun ve Mesafeli Sozlesmelere Dair Yonetmelik hukumleri geregince taraflarin hak ve yukumluluklerinin belirlenmesidir.</p>
<h3>3. SOZLESME KONUSU URUN BILGILERI</h3>
<p>Urunun temel nitelikleri, satis fiyati, odeme sekli ve teslimat bilgileri siparis sayfasinda belirtilmektedir.</p>
<h3>4. GENEL HUKUMLER</h3>
<p>ALICI, SATICI ya ait web sitesinde urunun temel nitelikleri, satis fiyati ve odeme sekli ile teslimat ve iade kosullarina iliskin on bilgileri okuyup bilgi sahibi oldugunu ve elektronik ortamda onay verdigini kabul eder.</p>
<h3>5. TESLIMAT</h3>
<p>Sozlesme konusu urun, siparis tarihinden itibaren yasal 30 gunluk sureyi asmamak kosulu ile teslim edilir.</p>
<h3>6. CAYMA HAKKI</h3>
<p>ALICI, urunu teslim aldigi tarihten itibaren 14 gun icinde herhangi bir gerekce gostermeksizin cayma hakkina sahiptir.</p>
<h3>7. CAYMA HAKKI KULLANILAMAYACAK URUNLER</h3>
<p>Fiyati finansal piyasalardaki dalgalanmalara bagli olan, kisiye ozel uretilen, cabuk bozulabilen, kullanilmis kozmetik urunleri gibi niteligi itibariyle iade edilemeyecek urunlerde cayma hakki kullanilamaz.</p>
<h3>8. ODEME VE TESLIMAT</h3>
<p>Siparis onaylandiktan sonra urunler anlasmali kargo firmasi araciligiyla gonderilir.</p>
<h3>9. YETKILI MAHKEME</h3>
<p>Bu sozlesmeden dogabilecek uyusmazliklarda Tuketici Hakem Heyetleri ve Tuketici Mahkemeleri yetkilidir.</p>
<p><em>Isbu sozlesme siparis tarihinde elektronik ortamda akdedilmistir.</em></p>
</div>';
    }

    public static function kullanim_kurallari_content()
    {
        $site = Webyaz_Settings::get('company_name');
        if (empty($site)) $site = get_bloginfo('name');
        $url = home_url();

        return '<div class="webyaz-legal-page">
<h2>Site Kullanim Kurallari</h2>

<div class="webyaz-warning-box" style="background:linear-gradient(135deg,#d32f2f 0%,#b71c1c 100%);color:#fff;padding:28px 30px;border-radius:12px;margin:25px 0;font-family:Roboto,sans-serif;box-shadow:0 4px 20px rgba(211,47,47,0.3);">
<h3 style="color:#fff;font-size:20px;font-weight:900;margin:0 0 12px;text-transform:uppercase;letter-spacing:1px;">&#9888; ONEMLI UYARI - CALINTI / KOPYALANMIS KART KULLANIMI</h3>
<p style="font-size:16px;line-height:1.8;margin:0 0 12px;font-weight:500;">Sitemiz uzerinden yapilan tum alisverislerde <strong style="text-decoration:underline;">calinti, kopyalanmis, sahte veya yetkisiz kullanilan kredi karti / banka karti</strong> ile yapilan islemlerden firmamiz <strong>hicbir sekilde sorumlu degildir.</strong></p>
<p style="font-size:15px;line-height:1.8;margin:0 0 12px;">Bu tur islemler tespit edildigi takdirde;</p>
<ul style="font-size:15px;line-height:2;margin:0 0 12px;padding-left:20px;">
<li>Siparis derhal iptal edilir ve ilgili birimlere bildirilir.</li>
<li>Kart sahibinin ve bankanin talep ettigi tum bilgiler yasal mercilerle paylasilir.</li>
<li>5237 sayili Turk Ceza Kanunu kapsaminda hukuki islem baslatilir.</li>
<li>IP adresi, cihaz bilgileri ve islem detaylari kayit altina alinmaktadir.</li>
</ul>
<p style="font-size:14px;margin:0;opacity:0.9;font-style:italic;">TCK Madde 245 - Baskasina ait banka veya kredi kartini kullanan kisi, 3 yildan 6 yila kadar hapis ve adli para cezasi ile cezalandirilir.</p>
</div>

<h3>1. GENEL</h3>
<p>' . $site . ' web sitesini (' . $url . ') kullanarak asagidaki kullanim kosullarini kabul etmis sayilirsiniz.</p>
<h3>2. FIKRI MULKIYET HAKLARI</h3>
<p>Bu sitede yer alan tum icerikler (gorseller, metinler, logolar, tasarimlar) ' . $site . ' a aittir ve 5846 sayili Fikir ve Sanat Eserleri Kanunu ile korunmaktadir. Izinsiz kopyalama, dagitma veya kullanim yasaktir.</p>
<h3>3. KULLANICI SORUMLULUKLARI</h3>
<ul>
<li>Siteye kayit sirasinda dogru ve guncel bilgi vermekle yukumlusunuz.</li>
<li>Hesap bilgilerinizin gizliliginden siz sorumlusunuz.</li>
<li>Siteyi yasa disi amaclarla kullanmak yasaktir.</li>
<li>Zararli yazilim veya bot kullanarak siteye erisim saglamak yasaktir.</li>
<li>Baskasina ait kimlik, kart veya hesap bilgilerini kullanmak yasaktir ve suc teskil eder.</li>
</ul>
<h3>4. ODEME GUVENLIGI</h3>
<p>Siteden yapilan tum odemeler 256-bit SSL sertifikasi ile sifrelenmektedir. Kredi karti bilgileriniz hicbir sekilde sunucularimizda saklanmaz. 3D Secure dogrulama sistemi kullanilmaktadir.</p>
<p><strong>Yetkisiz kart kullanimi veya dolandiricilik tespit edilmesi halinde siparis iptal edilir, odeme iade edilmez ve gerekli yasal islemler baslatilir.</strong></p>
<h3>5. GIZLILIK VE KISISEL VERILER</h3>
<p>Kisisel verileriniz 6698 sayili Kisisel Verilerin Korunmasi Kanunu (KVKK) kapsaminda korunmaktadir. Detayli bilgi icin KVKK Aydinlatma Metni sayfamizi inceleyiniz.</p>
<h3>6. SIPARIS VE TESLIMAT</h3>
<p>Siteden yapilan siparisler guvenli odeme yontemleri ile gerceklestirilir. Siparisler yasal 30 gunluk sure icerisinde teslim edilir.</p>
<h3>7. SORUMLULUK SINIRLAMASI</h3>
<p>' . $site . ' bilgi hatalarindan, teknik aksakliklardan ve ucuncu taraflarin eylemlerinden kaynaklanan zararlardan sorumlu tutulamaz.</p>
<h3>8. DEGISIKLIKLER</h3>
<p>Bu kullanim kurallari onceden bildirimsiz olarak guncellenebilir. Guncel versiyonu daima bu sayfada yayinlanir.</p>
<h3>9. YETKILI MAHKEME</h3>
<p>Bu kosullardan dogabilecek uyusmazliklarda Turkiye Cumhuriyeti mahkemeleri yetkilidir.</p>
</div>';
    }

    public static function kvkk_content()
    {
        $site = Webyaz_Settings::get('company_name');
        if (empty($site)) $site = get_bloginfo('name');
        $url = home_url();
        $address = Webyaz_Settings::get('company_address');
        $phone = Webyaz_Settings::get('company_phone');
        $email = Webyaz_Settings::get('company_email');

        $contact = '';
        if ($address) $contact .= 'Adres: ' . $address . '<br>';
        if ($phone) $contact .= 'Telefon: ' . $phone . '<br>';
        if ($email) $contact .= 'E-posta: ' . $email . '<br>';
        $contact .= 'Web: ' . $url;

        return '<div class="webyaz-legal-page">
<h2>KVKK Aydinlatma Metni</h2>
<h4>Kisisel Verilerin Korunmasi Kanunu (6698 Sayili Kanun) Kapsaminda Aydinlatma Metni</h4>

<h3>1. VERI SORUMLUSU</h3>
<p>6698 sayili Kisisel Verilerin Korunmasi Kanunu ("KVKK") uyarinca, kisisel verileriniz; veri sorumlusu olarak <strong>' . $site . '</strong> tarafindan asagida aciklanan amaclar kapsaminda islenebilecektir.</p>
<p>' . $contact . '</p>

<h3>2. KISISEL VERILERIN ISLENME AMACI</h3>
<p>Toplanan kisisel verileriniz, KVKK nin 5. ve 6. maddelerinde belirtilen kisisel veri isleme sart ve amaclarına uygun olarak asagidaki amaclarla islenebilecektir:</p>
<ul>
<li>Uyelik islemlerinin gerceklestirilmesi</li>
<li>Siparis ve odeme islemlerinin yurutulmesi</li>
<li>Kargo ve teslimat sureclerinin yonetimi</li>
<li>Musteri hizmetleri ve destek taleplerinin karsilanmasi</li>
<li>Yasal yukumluluklerin yerine getirilmesi (fatura, e-arsiv vb.)</li>
<li>Kampanya, promosyon ve pazarlama faaliyetlerinin yurutulmesi (onayiniz dahilinde)</li>
<li>Site guvenliginin saglanmasi ve dolandiricilik onleme</li>
<li>Istatistiksel analizler ve hizmet kalitesinin arttirilmasi</li>
</ul>

<h3>3. ISLENEN KISISEL VERILER</h3>
<ul>
<li><strong>Kimlik Bilgileri:</strong> Ad, soyad, T.C. kimlik numarasi</li>
<li><strong>Iletisim Bilgileri:</strong> E-posta adresi, telefon numarasi, adres</li>
<li><strong>Musteri Islem Bilgileri:</strong> Siparis bilgileri, odeme gecmisi, iade talepleri</li>
<li><strong>Pazarlama Bilgileri:</strong> Alisveris tercihleri, cerez kayitlari</li>
<li><strong>Islem Guvenligi Bilgileri:</strong> IP adresi, tarayici bilgileri, giris/cikis kayitlari</li>
<li><strong>Finansal Bilgiler:</strong> Fatura bilgileri, vergi numarasi (kurumsal uyeler icin)</li>
</ul>

<h3>4. KISISEL VERILERIN AKTARILMASI</h3>
<p>Toplanan kisisel verileriniz; yukarida belirtilen amaclarla ve KVKK nin 8. ve 9. maddelerine uygun olarak:</p>
<ul>
<li>Is ortaklari ve tedarikci firmalar (kargo, odeme kuruluslari)</li>
<li>Yasal zorunluluk halinde yetkili kamu kurum ve kuruluslari</li>
<li>Hukuki sureclerin yurutulmesi icin avukatlar ve danismanlar</li>
</ul>
<p>ile paylasilabilecektir.</p>

<h3>5. KISISEL VERI TOPLAMANIN YONTEMI VE HUKUKI SEBEBI</h3>
<p>Kisisel verileriniz; web sitemiz, mobil uygulamalarimiz, sosyal medya hesaplarimiz, musteri hizmetleri ve fiziki ortamlar araciligiyla toplanmaktadir.</p>
<p>Hukuki sebepler: Sozlesmenin kurulmasi ve ifasi, yasal yukumluluk, meşru menfaat ve acik rizaniz.</p>

<h3>6. VERI SAHIBI OLARAK HAKLARINIZ</h3>
<p>KVKK nun 11. maddesi uyarinca asagidaki haklara sahipsiniz:</p>
<ul>
<li>Kisisel verilerinizin islenip islenmedigini ogrenme</li>
<li>Kisisel verileriniz islenmisse buna iliskin bilgi talep etme</li>
<li>Kisisel verilerinizin islenme amacini ve bunlarin amacina uygun kullanilip kullanilmadigini ogrenme</li>
<li>Yurt icinde veya yurt disinda kisisel verilerin aktarildigi ucuncu kisileri bilme</li>
<li>Kisisel verilerin eksik veya yanlis islenmis olmasi halinde bunlarin duzeltilmesini isteme</li>
<li>KVKK nun 7. maddesinde ongorulen sartlar cercevesinde kisisel verilerin silinmesini veya yok edilmesini isteme</li>
<li>Islenen verilerin munhasiran otomatik sistemler vasitasiyla analiz edilmesi suretiyle aleyhinize bir sonucun ortaya cikmasina itiraz etme</li>
<li>Kisisel verilerin kanuna aykiri olarak islenmesi sebebiyle zarara ugramaniz halinde zararin giderilmesini talep etme</li>
</ul>

<h3>7. BASVURU YONTEMI</h3>
<p>Yukaridaki haklarinizi kullanmak icin kimliginizi tespit edici belgeler ile birlikte asagidaki yontemlerden birini kullanarak bize basvurabilirsiniz:</p>
<ul>
<li>E-posta ile: ' . ($email ? $email : '[E-posta adresiniz]') . '</li>
<li>Posta ile: ' . ($address ? $address : '[Adresiniz]') . '</li>
</ul>
<p>Basvurular en gec 30 gun icinde ucretsiz olarak sonuclandirilir.</p>

<p style="margin-top:30px;padding:15px 20px;background:rgba(68,96,132,0.06);border-radius:8px;font-size:13px;color:#666;"><em>Bu aydinlatma metni 6698 sayili Kisisel Verilerin Korunmasi Kanunu nun 10. maddesi uyarinca hazirlanmis olup, gerekli hallerde guncellenebilir.</em></p>
</div>';
    }

    public static function compare_content()
    {
        return '[webyaz_compare]';
    }

    public static function siparis_takip_content()
    {
        return '[webyaz_order_tracking]';
    }

    public static function gizlilik_content()
    {
        $site = Webyaz_Settings::get('company_name');
        if (empty($site)) $site = get_bloginfo('name');
        $url = home_url();
        $email = Webyaz_Settings::get('company_email');

        return '<div class="webyaz-legal-page">
<h2>Gizlilik Politikasi</h2>
<p><strong>' . $site . '</strong> olarak kisisel verilerinizin gizliligine onem veriyoruz. Bu sayfa, hangi bilgilerin nasil toplandigi, kullanildigi ve korunduguyla ilgili sizi bilgilendirmek amaciyla hazirlanmistir.</p>

<h3>1. TOPLANAN BILGILER</h3>
<p>Sitemizi kullandiginizda asagidaki bilgiler toplanabilir:</p>
<ul>
<li><strong>Kimlik Bilgileri:</strong> Ad, soyad, T.C. kimlik numarasi (yalnizca fatura islemleri icin)</li>
<li><strong>Iletisim Bilgileri:</strong> E-posta, telefon, adres</li>
<li><strong>Hesap Bilgileri:</strong> Kullanici adi, sifre (sifrelenmis olarak saklanir)</li>
<li><strong>Siparis Bilgileri:</strong> Satin alma gecmisi, sepet icerigi, favori urunler</li>
<li><strong>Teknik Bilgiler:</strong> IP adresi, tarayici turu, isletim sistemi, erisim zamanlari</li>
<li><strong>Cerez Bilgileri:</strong> Oturum cerezleri, tercih cerezleri, analitik cerezler</li>
</ul>

<h3>2. BILGILERIN KULLANIM AMACLARI</h3>
<ul>
<li>Siparis islemlerinin gerceklestirilmesi ve takibi</li>
<li>Musteri hizmetleri destegi saglanmasi</li>
<li>Fatura ve yasal belge duzenlenmesi</li>
<li>Site deneyiminin kisisellestirilmesi</li>
<li>Guvenlik onlemlerinin alinmasi ve dolandiricilik onleme</li>
<li>Kampanya ve promosyon bildirimlerinin gonderilmesi (izniniz dahilinde)</li>
<li>Yasal yukumluluklerin yerine getirilmesi</li>
</ul>

<h3>3. CEREZ POLITIKASI</h3>
<p>Sitemiz, kullanici deneyimini iyilestirmek icin cerezler (cookies) kullanmaktadir:</p>
<ul>
<li><strong>Zorunlu Cerezler:</strong> Sitenin duzgun calismasi icin gerekli (oturum yonetimi, sepet)</li>
<li><strong>Performans Cerezleri:</strong> Site performansini olcmek icin (Google Analytics vb.)</li>
<li><strong>Islevsellik Cerezleri:</strong> Tercihlerinizi hatirlamak icin (dil, para birimi)</li>
<li><strong>Pazarlama Cerezleri:</strong> Size uygun reklamlar gostermek icin (izniniz dahilinde)</li>
</ul>
<p>Tarayici ayarlarindan cerezleri reddedebilir veya silebilirsiniz. Ancak bu durumda bazi site ozellikleri duzgun calismayabilir.</p>

<h3>4. BILGI PAYLASIMI</h3>
<p>Kisisel bilgileriniz asagidaki durumlar disinda ucuncu taraflarla paylasilmaz:</p>
<ul>
<li>Kargo ve lojistik firmalari (teslimat icin)</li>
<li>Odeme kuruluslari (guvenli odeme islemi icin)</li>
<li>Yasal zorunluluk halinde yetkili makamlar</li>
<li>Hukuki sureclerde avukat ve danismanlar</li>
</ul>

<h3>5. BILGI GUVENLIGI</h3>
<p>Tum kisisel verileriniz 256-bit SSL sertifikasi ile sifrelenmis baglanti uzerinden iletilir. Veritabanlarimiz guvenlik duvari ve sifreli depolama ile korunmaktadir. Duzenli guvenlik denetimleri yapilmaktadir.</p>

<h3>6. HAKLARINIZ</h3>
<p>6698 sayili KVKK kapsaminda kisisel verilerinizle ilgili su haklara sahipsiniz:</p>
<ul>
<li>Verilerinize erisim ve bilgi talep etme</li>
<li>Yanlis verilerin duzeltilmesini isteme</li>
<li>Verilerin silinmesini veya yok edilmesini talep etme</li>
<li>Veri islemesine itiraz etme</li>
</ul>
<p>Bu haklarinizi kullanmak icin ' . ($email ? $email : 'iletisim sayfamiz') . ' uzerinden bize ulasabilirsiniz.</p>

<h3>7. DEGISIKLIKLER</h3>
<p>Bu gizlilik politikasi gerekli goruldugunde guncellenebilir. Guncel versiyon her zaman bu sayfada yayinlanir.</p>

<p style="margin-top:30px;padding:15px 20px;background:rgba(68,96,132,0.06);border-radius:8px;font-size:13px;color:#666;"><em>Son guncelleme: ' . date('d.m.Y') . '</em></p>
</div>';
    }

    public static function iade_content()
    {
        $site = Webyaz_Settings::get('company_name');
        if (empty($site)) $site = get_bloginfo('name');
        $email = Webyaz_Settings::get('company_email');
        $phone = Webyaz_Settings::get('company_phone');
        $address = Webyaz_Settings::get('company_address');

        return '<div class="webyaz-legal-page">
<h2>Iade ve Degisim Kosullari</h2>
<p><strong>' . $site . '</strong> olarak musterilerimizin memnuniyetini on planda tutuyoruz. 6502 sayili Tuketicinin Korunmasi Hakkinda Kanun ve Mesafeli Sozlesmelere Dair Yonetmelik cercevesinde iade ve degisim haklariniz asagida belirtilmistir.</p>

<h3>1. CAYMA HAKKI</h3>
<p>Mesafeli satis sozlesmesine gore, urunu teslim aldiginiz tarihten itibaren <strong>14 (on dort) gun</strong> icinde herhangi bir gerekce gostermeksizin ve cezai sart odemeksizin cayma hakkinizi kullanabilirsiniz.</p>

<h3>2. IADE SARTLARI</h3>
<ul>
<li>Urun, teslim aldiginiz sekliyle, kullanilmamis ve hasarsiz olmalidir</li>
<li>Orijinal ambalaji, etiketi ve tum aksesuarlari eksiksiz olmalidir</li>
<li>Fatura veya fatura kopyasi ile birlikte iade edilmelidir</li>
<li>Urun, <strong>14 gun</strong> icinde kargo ile gonderilmelidir</li>
</ul>

<h3>3. IADE EDILEMEYECEK URUNLER</h3>
<p>Asagidaki urunlerde cayma hakki kullanilamaz:</p>
<ul>
<li>Kisiye ozel uretilen/hazirlanan urunler</li>
<li>Cabuk bozulabilen veya son kullanma tarihi gecebilecek urunler</li>
<li>Kullanildiktan sonra iade edilemeyecek kozmetik ve kisisel bakim urunleri</li>
<li>Ambalaji acilmis ses ve goruntu kayitlari, yazilim ve bilgisayar sarflari</li>
<li>Gazete, dergi gibi sureli yayinlar</li>
<li>Elektronik ortamda aninda ifa edilen hizmetler ve dijital icerikler</li>
<li>Fiyati piyasa kosullarina gore degisen urunler</li>
</ul>

<h3>4. DEGISIM ISLEMI</h3>
<p>Beden, renk veya model degisimi icin:</p>
<ul>
<li>Urun kullanilmamis ve orijinal ambalajinda olmalidir</li>
<li>Degisim talebi <strong>14 gun</strong> icinde yapilmalidir</li>
<li>Istenen urun stokta mevcut olmalidir</li>
<li>Degisim urunleri 3-5 is gunu icinde kargoya verilir</li>
</ul>

<h3>5. ARIZALI/KUSURLU URUN</h3>
<p>Urunun ayipli (kusurlu) olmasi halinde, teslim tarihinden itibaren <strong>30 gun</strong> icinde ucretsiz onarim, urun degisimi veya iade talep edebilirsiniz. Garanti kapsamindaki urunler icin garanti belgesi sartlari gecerlidir.</p>

<h3>6. IADE SURECI</h3>
<ol>
<li>Iade talebinizi ' . ($email ? $email : 'e-posta') . ' adresine veya ' . ($phone ? $phone : 'telefon') . ' numarasina bildirin</li>
<li>Iade onay kodu ve kargo bilgileri tarafiniza iletilecektir</li>
<li>Urunu kargo ile gonderin (anlasmali kargo ile ucretsiz iade imkani)</li>
<li>Urun tarafimiza ulastiktan sonra <strong>3 is gunu</strong> icinde kontrol edilir</li>
<li>Onaylanan iadeler <strong>14 gun</strong> icinde ayni odeme yontemine iade edilir</li>
</ol>

<h3>7. KARGO MASRAFLARI</h3>
<ul>
<li><strong>Cayma hakki:</strong> Iade kargo ucreti aliciya aittir (anlasmali kargo haric)</li>
<li><strong>Ayipli/kusurlu urun:</strong> Kargo ucreti satici tarafindan karsilanir</li>
<li><strong>Yanlis urun gonderimi:</strong> Kargo ucreti satici tarafindan karsilanir</li>
</ul>

<h3>8. PARA IADESI</h3>
<p>Iade islemi onaylandiktan sonra:</p>
<ul>
<li><strong>Kredi karti ile yapilan odemelerde:</strong> Iadeniz 14 gun icinde kartiniza yansitilir. Banka islem sureleri nedeniyle 2-3 fatura donemi surebilir.</li>
<li><strong>Havale/EFT ile yapilan odemelerde:</strong> Belirttiginiz banka hesabina 14 gun icinde aktarilir.</li>
<li><strong>Kapida odeme:</strong> Belirttiginiz banka hesabina 14 gun icinde havale yapilir.</li>
</ul>

<div style="margin-top:20px;padding:20px;background:linear-gradient(135deg,rgba(68,96,132,0.06),rgba(68,96,132,0.02));border-radius:10px;border-left:4px solid #446084;">
<p style="margin:0;font-size:14px;"><strong>Iletisim:</strong><br>
' . ($email ? 'E-posta: ' . $email . '<br>' : '') . '
' . ($phone ? 'Telefon: ' . $phone . '<br>' : '') . '
' . ($address ? 'Adres: ' . $address : '') . '</p>
</div>
</div>';
    }

    public static function teslimat_content()
    {
        $site = Webyaz_Settings::get('company_name');
        if (empty($site)) $site = get_bloginfo('name');

        return '<div class="webyaz-legal-page">
<h2>Teslimat ve Kargo Politikasi</h2>

<h3>1. TESLIMAT SURESI</h3>
<ul>
<li>Siparisler, odeme onayindan sonra <strong>1-3 is gunu</strong> icinde kargoya verilir</li>
<li>Kargo teslimat suresi ortalama <strong>1-5 is gunu</strong> arasindadir (bolgeye gore degisiklik gosterebilir)</li>
<li>Yasal teslimat suresi odeme tarihinden itibaren en fazla <strong>30 gun</strong>dur</li>
<li>Kampanya ve ozel gun donemlerinde teslimat suresi uzayabilir</li>
</ul>

<h3>2. KARGO UCRETLERI</h3>
<ul>
<li>Kargo ucreti siparis ozetinde belirtilir</li>
<li>Belirli tutarin uzerindeki siparislerde <strong>ucretsiz kargo</strong> uygulanabilir</li>
<li>Kampanya kosullarina gore kargo ucreti degisebilir</li>
</ul>

<h3>3. KARGO TAKIP</h3>
<p>Siparisler kargoya verildikten sonra takip numarasi e-posta ve/veya SMS ile bildirilir. Siparis durumunuzu hesabinizdan veya kargo firmasinin web sitesinden takip edebilirsiniz.</p>

<h3>4. TESLIMAT ADRESI</h3>
<ul>
<li>Siparisler yalnizca belirtilen teslimat adresine gonderilir</li>
<li>Adres degisikligi, siparis kargoya verilmeden once yapilabilir</li>
<li>Yanlis veya eksik adres nedeniyle yasanan gecikmelerde sorumluluk aliciya aittir</li>
</ul>

<h3>5. TESLIMAT SIRASINDA KONTROL</h3>
<p>Kargo teslimi sirasinda lutfen asagidaki kontrolleri yapin:</p>
<ul>
<li>Paketin dis gorunumunde hasar olup olmadigini kontrol edin</li>
<li>Hasarli paketleri <strong>tutanak tutturarak</strong> teslim alin veya reddedin</li>
<li>Paket icindeki urunlerin eksiksiz oldugunu kontrol edin</li>
<li>Herhangi bir sorun varsa <strong>24 saat</strong> icinde bize bildirin</li>
</ul>

<h3>6. TESLIM EDILEMEYEN SIPARISLER</h3>
<p>Kargo firmasinin 3 teslimat denemesine ragmen teslim edilemeyen siparisler iade edilir. Bu durumda kargo ucreti aliciya ait olup, urun bedeli iade edilir.</p>

<h3>7. ANLASMALI KARGO FIRMALARI</h3>
<p>' . $site . ' anlasmali kargo firmalari araciligiyla teslimat yapmaktadir. Kargo firmasi tercihi siparis yogunlugu ve bolgenize gore belirlenir.</p>

<p style="margin-top:30px;padding:15px 20px;background:rgba(68,96,132,0.06);border-radius:8px;font-size:13px;color:#666;"><em>Bu politika gerekli goruldugunde guncellenebilir. Guncel versiyon daima bu sayfada yayinlanir.</em></p>
</div>';
    }

    public static function odeme_guvenligi_content()
    {
        $site = Webyaz_Settings::get('company_name');
        if (empty($site)) $site = get_bloginfo('name');

        return '<div class="webyaz-legal-page">
<h2>Odeme Guvenligi</h2>
<p><strong>' . $site . '</strong> olarak alisveris guvenliginiz bizim icin en onemli onceliktir.</p>

<h3>1. SSL SERTIFIKASI</h3>
<p>Tum sayfalarimiz <strong>256-bit SSL (Secure Sockets Layer)</strong> sertifikasi ile korunmaktadir. Tarayicinizin adres cubugundaki kilit simgesi ve "https://" ifadesi, baglantinizin guvenli oldugunu gosterir. Butun kisisel ve finansal bilgileriniz sifreli kanal uzerinden iletilir.</p>

<h3>2. 3D SECURE DOGRULAMA</h3>
<p>Kredi karti ile yapilan tum odemelerde <strong>3D Secure</strong> (Verified by Visa / MasterCard SecureCode) dogrulama sistemi kullanilmaktadir. Bu sistem, kart sahibinin kimligini dogrulayarak yetkisiz islemleri engeller.</p>

<h3>3. KART BILGISI GUVENLIGI</h3>
<ul>
<li>Kredi karti bilgileriniz <strong>hicbir sekilde</strong> sunucularimizda saklanmaz</li>
<li>Odeme islemleri PCI-DSS uyumlu odeme kuruluslari tarafindan gerceklestirilir</li>
<li>Kart bilgileriniz yalnizca odeme aninda sifreli olarak islenir</li>
</ul>

<h3>4. ODEME YONTEMLERI</h3>
<ul>
<li><strong>Kredi Karti / Banka Karti:</strong> Visa, MasterCard, Troy ile guvenli odeme</li>
<li><strong>Havale / EFT:</strong> Banka havalesi ile odeme imkani</li>
<li><strong>Kapida Odeme:</strong> Nakit veya kapida kredi karti ile odeme (varsa)</li>
</ul>

<h3>5. DOLANDIRICILIK ONLEME</h3>
<p>Supheli islemleri tespit etmek icin gelismis guvenlik sistemleri kullanilmaktadir:</p>
<ul>
<li>IP adresi ve konum kontrolu</li>
<li>Coklu basarisiz islem takibi</li>
<li>Anormal siparis desenleri analizi</li>
<li>Yetkisiz kart kullanimi tespitinde derhal islem iptali ve yasal bildirim</li>
</ul>

<h3>6. GUVENLI ALISVERIS IPUCLARI</h3>
<ul>
<li>Hesap sifrenizi duzenli olarak degistirin</li>
<li>Sifrenizi baskalariyla paylasmayın</li>
<li>Ortak kullanilan bilgisayarlarda oturumu kapatmayi unutmayin</li>
<li>Tarayicinizi guncel tutun</li>
<li>Supheli e-postalardaki linklere tiklamayin</li>
</ul>

<div style="margin-top:20px;padding:20px;background:linear-gradient(135deg,#e8f5e9,#f1f8e9);border-radius:10px;border-left:4px solid #4caf50;">
<p style="margin:0;font-size:14px;color:#2e7d32;"><strong>&#128274; Guvenle Alisveris Yapin</strong><br>Tum odemeleriniz 256-bit SSL sifreleme ve 3D Secure ile korunmaktadir.</p>
</div>
</div>';
    }

    public static function hakkimizda_content()
    {
        $site = Webyaz_Settings::get('company_name');
        if (empty($site)) $site = get_bloginfo('name');
        $url = home_url();
        $address = Webyaz_Settings::get('company_address');
        $phone = Webyaz_Settings::get('company_phone');
        $email = Webyaz_Settings::get('company_email');

        $colors = Webyaz_Colors::get_theme_colors();
        $primary = $colors['primary'];

        return '<div class="webyaz-legal-page">
<h2>Hakkimizda</h2>
<p><strong>' . $site . '</strong> olarak, musterilerimize en kaliteli urunleri ve en iyi alisveris deneyimini sunmayi hedefliyoruz.</p>

<h3>Misyonumuz</h3>
<p>Musterilerimizin ihtiyaclarina en uygun urunleri, en uygun fiyatlarla, en guvenli sekilde sunmak ve satis sonrasi da yanlarinda olmaktir.</p>

<h3>Vizyonumuz</h3>
<p>Sektorumuzde guvenilirlik, kalite ve musteri memnuniyeti ile taninan, surdurulebilir buyumeyi hedefleyen bir marka olmaktir.</p>

<h3>Degerlerimiz</h3>
<ul>
<li><strong>Guvenilirlik:</strong> Tum islemlerimizde seffaflik ve durustluk</li>
<li><strong>Kalite:</strong> En iyi urunleri en iyi hizmetle sunma</li>
<li><strong>Musteri Odaklilik:</strong> Musterilerimizin memnuniyeti her seyin ustunde</li>
<li><strong>Yenilikcilik:</strong> Surekli gelisim ve teknolojik ilerleme</li>
</ul>

<h3>Neden Bizi Tercih Etmelisiniz?</h3>
<ul>
<li>&#10004; Guvenli ve kolay alisveris deneyimi</li>
<li>&#10004; Hizli ve guvenilir kargo</li>
<li>&#10004; 14 gun kosulsuz iade garantisi</li>
<li>&#10004; Musteri hizmetleri destegi</li>
<li>&#10004; Guvenli odeme altyapisi (SSL + 3D Secure)</li>
<li>&#10004; Genis urun yelpazesi</li>
</ul>

<div style="margin-top:20px;padding:20px;background:linear-gradient(135deg,rgba(68,96,132,0.06),rgba(68,96,132,0.02));border-radius:10px;border-left:4px solid ' . $primary . ';">
<h3 style="margin:0 0 10px;color:' . $primary . ';">Iletisim Bilgilerimiz</h3>
<p style="margin:0;font-size:14px;line-height:2;">
' . ($address ? '<strong>Adres:</strong> ' . $address . '<br>' : '') . '
' . ($phone ? '<strong>Telefon:</strong> ' . $phone . '<br>' : '') . '
' . ($email ? '<strong>E-posta:</strong> ' . $email . '<br>' : '') . '
<strong>Web:</strong> ' . $url . '
</p>
</div>
</div>';
    }

    public static function on_bilgilendirme_content()
    {
        $site = Webyaz_Settings::get('company_name');
        if (empty($site)) $site = get_bloginfo('name');
        $url = home_url();
        $address = Webyaz_Settings::get('company_address');
        $phone = Webyaz_Settings::get('company_phone');
        $email = Webyaz_Settings::get('company_email');
        $tax_office = Webyaz_Settings::get('company_tax_office');
        $tax_no = Webyaz_Settings::get('company_tax_no');
        $mersis = Webyaz_Settings::get('company_mersis');

        $company_info = 'Ticari Unvan: ' . $site . '<br>Web Sitesi: ' . $url;
        if ($address) $company_info .= '<br>Adres: ' . $address;
        if ($phone) $company_info .= '<br>Telefon: ' . $phone;
        if ($email) $company_info .= '<br>E-posta: ' . $email;
        if ($tax_office) $company_info .= '<br>Vergi Dairesi: ' . $tax_office;
        if ($tax_no) $company_info .= '<br>Vergi No: ' . $tax_no;
        if ($mersis) $company_info .= '<br>MERSIS No: ' . $mersis;

        return '<div class="webyaz-legal-page">
<h2>On Bilgilendirme Formu</h2>
<p><em>6502 sayili Tuketicinin Korunmasi Hakkinda Kanun ve Mesafeli Sozlesmelere Dair Yonetmelik uyarinca duzenlenmistir.</em></p>

<h3>1. SATICI BILGILERI</h3>
<p>' . $company_info . '</p>

<h3>2. URUN / HIZMET BILGILERI</h3>
<p>Satin alacaginiz urun/urunlerin temel ozellikleri, satis fiyati (KDV dahil), odeme sekli ve diger detaylari siparis ozet sayfasinda belirtilmektedir. Urun fiyatlarina kargo ucreti dahil degildir; kargo ucreti siparis asamasinda ayrica gosterilmektedir.</p>

<h3>3. TESLIMAT BILGILERI</h3>
<p>Siparis onaylanmasinin ardindan, urun/urunler en gec <strong>30 (otuz) is gunu</strong> icerisinde teslim edilecektir. Teslimat, anlasmali kargo firmasi araciligiyla ALICI nin belirttigi adrese yapilacaktir.</p>
<p>Teslimat sirasinda ALICI nin adresinde bulunamamasi halinde, kargo firmasi bilgilendirme birakacaktir. Kargo firmasinin teslim edemedigi urunler <strong>7 (yedi) gun</strong> sube de bekletilir.</p>

<h3>4. ODEME VE FIYAT</h3>
<p>Urun bedeli, siparis sirasinda secilen odeme yontemiyle tahsil edilir. Kredi karti ile yapilan odemelerde taksit secenekleri ilgili banka ve kampanya kosullarina gore degisiklik gosterebilir. Tum fiyatlar Turk Lirasi (TL) cinsindendir ve KDV dahildir.</p>

<h3>5. CAYMA HAKKI</h3>
<p>ALICI, urunun kendisine veya gosterdigi adresteki kisiye teslim edilmesinden itibaren <strong>14 (on dort) gun</strong> icerisinde herhangi bir gerekce gostermeksizin ve cezai sart odemeksizin cayma hakkina sahiptir.</p>
<p>Cayma hakkinin kullanilmasi halinde:</p>
<ul>
<li>Cayma bildiriminin SATICI ya yoneltildigi tarihten itibaren <strong>10 (on) gun</strong> icerisinde urun iade edilmelidir.</li>
<li>SATICI, cayma bildiriminin kendisine ulastigi tarihten itibaren <strong>14 (on dort) gun</strong> icerisinde tum odemeleri iade edecektir.</li>
<li>Iade kargo ucreti ALICI ya aittir (SATICI nin iade kargo ucretsiz kampanyasi haric).</li>
</ul>

<h3>6. CAYMA HAKKI KULLANILAMAYACAK URUNLER</h3>
<p>Asagidaki hallerde cayma hakki kullanilamaz:</p>
<ul>
<li>ALICI nin istekleri veya acikca kisisel ihtiyaclari dogrultusunda hazirlanan urunler</li>
<li>Cabuk bozulabilen veya son kullanma tarihi gecebilecek urunler</li>
<li>Tesliminden sonra ambalaj, bant, muhur, paket gibi koruyucu unsurlari acilan urunler</li>
<li>Kopyalanabilir yazilim ve programlar, DVD, VCD, CD ve kasetler</li>
<li>Gazete, dergi gibi sureli yayinlar</li>
<li>Elektronik ortamda aninda ifa edilen hizmetler</li>
<li>Fiyati finansal piyasalardaki dalgalanmalara bagli olarak degisen urunler</li>
</ul>

<h3>7. UYUSMAZLIK</h3>
<p>Bu sozlesmeden dogan uyusmazliklarda Ticaret Bakanligi tarafindan her yil belirlenen parasal sinirlar dahilinde Il ve Ilce Tuketici Hakem Heyetleri, bu sinirlari asan durumlarda Tuketici Mahkemeleri gorevli ve yetkilidir.</p>

<p><em>ALICI, isbu on bilgilendirme formunu elektronik ortamda okuyup bilgi edindigini kabul ve beyan eder.</em></p>
</div>';
    }

    public static function uyelik_sozlesmesi_content()
    {
        $site = Webyaz_Settings::get('company_name');
        if (empty($site)) $site = get_bloginfo('name');
        $url = home_url();
        $email = Webyaz_Settings::get('company_email');

        return '<div class="webyaz-legal-page">
<h2>Uyelik Sozlesmesi</h2>
<p>Bu uyelik sozlesmesi, <strong>' . $site . '</strong> (' . $url . ') web sitesine uye olan kullanicilar ile site yonetimi arasindaki hak ve yukumlulukleri duzenler.</p>

<h3>1. TARAFLAR</h3>
<p><strong>HIZMET SAGLAYICI:</strong> ' . $site . ' (' . $url . ')</p>
<p><strong>UYE:</strong> Web sitesine uyelik formunu doldurarak uye olan gercek veya tuzel kisi.</p>

<h3>2. SOZLESMENIN KONUSU</h3>
<p>Isbu sozlesmenin konusu, web sitesinde sunulan hizmetlerden yararlanma kosullarinin ve taraflarin karsilikli hak ve yukumluluklerinin belirlenmesidir.</p>

<h3>3. UYELIK KOSULLARI</h3>
<ul>
<li>Uyelik icin 18 yasini doldurmus olmak gerekmektedir.</li>
<li>Uye, kayit sirasinda verdigi bilgilerin dogru ve guncel oldugunu kabul ve taahhut eder.</li>
<li>Uyelik bilgilerinin guvenliginden ve gizliliginden UYE sorumludur.</li>
<li>Bir kisi birden fazla uyelik hesabi olusturamaz.</li>
<li>UYE, hesabini ucuncu sahislara devredemez.</li>
</ul>

<h3>4. UYENIN HAK VE YUKUMLULUKLERI</h3>
<ul>
<li>UYE, siteyi yasa disi amaclarla kullanamaz.</li>
<li>UYE, diger uyelerin kisisel bilgilerine izinsiz erismeye calisamaz.</li>
<li>UYE, sitenin isleyisini engelleyecek veya bozan faaliyetlerde bulunamaz.</li>
<li>UYE, satin aldigi urunler/hizmetler icin zamaninda odeme yapmakla yukumludur.</li>
<li>UYE, siparis gecmisini ve hesap bilgilerini uye panelindan goruntuleyebilir.</li>
</ul>

<h3>5. HIZMET SAGLAYICININ HAK VE YUKUMLULUKLERI</h3>
<ul>
<li>' . $site . ', uyelik hizmetini 7/24 sunmayi hedefler ancak teknik bakim nedeniyle kesinti olabilir.</li>
<li>Kurallara aykiri davranan uyelerin hesaplari bildirimli veya bildirimsiz olarak askiya alinabilir veya silinebilir.</li>
<li>Uye bilgileri, KVKK ve Gizlilik Politikasi kapsaminda korunur ve ucuncu sahislarla paylasılmaz.</li>
<li>Hizmet iceriginde ve fiyatlarinda onceden bildirim yaparak degisiklik yapma hakkini sakli tutar.</li>
</ul>

<h3>6. KISISEL VERILERIN KORUNMASI</h3>
<p>UYE nin kisisel verileri, 6698 sayili Kisisel Verilerin Korunmasi Kanunu (KVKK) kapsaminda islenmekte ve korunmaktadir. Detayli bilgi icin <strong>KVKK Aydinlatma Metni</strong> ve <strong>Gizlilik Politikasi</strong> sayfalarimizi inceleyiniz.</p>

<h3>7. FIKRI MULKIYET HAKLARI</h3>
<p>Web sitesindeki tum icerikler (tasarimlar, metinler, gorseller, yazilim kodu, logolar) ' . $site . ' a aittir ve 5846 sayili Fikir ve Sanat Eserleri Kanunu ile korunmaktadir. Izinsiz kopyala, dagitma veya kullanim yasaktir.</p>

<h3>8. SOZLESMENIN FESHI</h3>
<ul>
<li>UYE, dilediginde uyelik hesabini kapatabilir. Hesap kapatma islemi icin ' . ($email ? '<a href="mailto:' . $email . '">' . $email . '</a>' : 'iletisim sayfamiz') . ' uzerinden talepte bulunabilirsiniz.</li>
<li>' . $site . ', kosullara uymayan uyeleri onceden bildirimde bulunarak veya bulunmaksizin uyelikten cikarma hakkini sakli tutar.</li>
</ul>

<h3>9. UYGULANACAK HUKUK VE YETKILI MAHKEME</h3>
<p>Isbu sozlesme Turk Hukukuna tabidir. Uyusmazliklarda Tuketici Hakem Heyetleri ve Tuketici Mahkemeleri yetkilidir.</p>

<p><em>UYE, isbu sozlesmeyi okumus, anlams ve kabul etmis sayilir. Uyelik isleminin tamamlanmasi ile sozlesme yururluge girer.</em></p>
</div>';
    }

    public static function acik_riza_content()
    {
        $site = Webyaz_Settings::get('company_name');
        if (empty($site)) $site = get_bloginfo('name');
        $email = Webyaz_Settings::get('company_email');

        return '<div class="webyaz-legal-page">
<h2>Acik Riza Metni</h2>
<p><em>6698 Sayili Kisisel Verilerin Korunmasi Kanunu (KVKK) kapsaminda duzenlenmistir.</em></p>

<h3>Veri Sorumlusu</h3>
<p><strong>' . $site . '</strong></p>

<h3>Acik Rizanin Konusu</h3>
<p>' . $site . ' olarak, asagida belirtilen amaclar dogrultusunda kisisel verilerinizin islenmesine iliskin acik rizanizi talep etmekteyiz:</p>

<h3>1. Pazarlama ve Tanitim Faaliyetleri</h3>
<p>Kisisel verilerinizin (ad, soyad, e-posta adresi, telefon numarasi) asagidaki amaclarla islenmesine acik riza veriyorum:</p>
<ul>
<li>Kampanya, indirim ve promosyon bilgilendirmeleri gonderilmesi</li>
<li>Yeni urun ve hizmetlere iliskin tanitim yapilmasi</li>
<li>Ozel gun kutlamalari ve kisisellestirilmis teklifler sunulmasi</li>
<li>E-posta, SMS veya arama yoluyla pazarlama iletisimleri gonderilmesi</li>
</ul>

<h3>2. Profil Olusturma ve Analiz</h3>
<p>Alisveris gecmisim, site uzerindeki davranislarim ve tercihlerimin analiz edilerek kisiye ozel urun ve hizmet onerileri sunulmasi amaciyla islenmesine acik riza veriyorum.</p>

<h3>3. Ucuncu Taraflarla Paylasim</h3>
<p>Kisisel verilerimin, pazarlama hizmetleri kapsaminda is ortaklari, reklam aglari ve analiz servisleri ile paylasılmasina acik riza veriyorum.</p>

<h3>Haklariniz</h3>
<p>KVKK nin 11. maddesi uyarinca asagidaki haklara sahipsiniz:</p>
<ul>
<li>Kisisel verilerinizin islenip islenmedigini ogrenme</li>
<li>Islenmisse buna iliskin bilgi talep etme</li>
<li>Islenme amacini ve amacina uygun kullanilip kullanilmadigini ogrenme</li>
<li>Eksik veya yanlis islenmisse duzeltilmesini isteme</li>
<li>KVKK nin 7. maddesi kapsaminda silinmesini veya yok edilmesini isteme</li>
<li>Islenen verilerin ucuncu kisilere bildirilmesini isteme</li>
<li>Verilerinizin otomatik analiz yoluyla aleyhinize bir sonuc dogurmasi halinde itiraz etme</li>
</ul>

<h3>Rizanin Geri Alinmasi</h3>
<p>Verdiginiz acik rizayi, herhangi bir gerekce gostermeksizin her zaman geri alabilirsiniz. Bunun icin ' . ($email ? '<a href="mailto:' . $email . '">' . $email . '</a>' : 'iletisim sayfamiz') . ' uzerinden basvuruda bulunabilirsiniz. Rizanizin geri alinmasi, geri alma tarihine kadar yapilan islemlerin hukuka uygunlugunu etkilemez.</p>

<p><strong>Onemli:</strong> Acik riza vermek tamamen gonulludur. Riza vermemeniz durumunda temel hizmetlerimizden yararlanmaya devam edebilirsiniz; yalnizca yukarida belirtilen pazarlama faaliyetleri gerceklestirilmeyecektir.</p>
</div>';
    }

    public static function sss_content()
    {
        $site = Webyaz_Settings::get('company_name');
        if (empty($site)) $site = get_bloginfo('name');
        $phone = Webyaz_Settings::get('company_phone');
        $email = Webyaz_Settings::get('company_email');

        $colors = Webyaz_Colors::get_theme_colors();
        $primary = $colors['primary'];
        $secondary = $colors['secondary'];

        return '<div class="webyaz-legal-page">
<h2>Sikca Sorulan Sorular (SSS)</h2>

<style>
.wz-faq-section{margin-bottom:28px;}
.wz-faq-section-title{font-size:18px;font-weight:800;color:' . $primary . ';margin:0 0 14px;padding:10px 16px;background:linear-gradient(135deg,rgba(' . implode(',', array_map('hexdec', str_split(ltrim($primary, '#'), 2))) . ',0.08),rgba(' . implode(',', array_map('hexdec', str_split(ltrim($primary, '#'), 2))) . ',0.03));border-radius:10px;display:flex;align-items:center;gap:10px;}
.wz-faq-item{border:1px solid #e8e8e8;border-radius:10px;margin-bottom:8px;overflow:hidden;transition:all 0.3s ease;background:#fff;}
.wz-faq-item:hover{border-color:' . $secondary . ';box-shadow:0 2px 12px rgba(0,0,0,0.06);}
.wz-faq-item[open]{border-color:' . $primary . ';box-shadow:0 4px 16px rgba(0,0,0,0.08);}
.wz-faq-item summary{padding:16px 20px;font-size:15px;font-weight:600;color:#333;cursor:pointer;list-style:none;display:flex;align-items:center;justify-content:space-between;gap:12px;transition:all 0.2s;user-select:none;}
.wz-faq-item summary::-webkit-details-marker{display:none;}
.wz-faq-item summary::after{content:"+";font-size:22px;font-weight:300;color:' . $secondary . ';transition:transform 0.3s ease;flex-shrink:0;width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:rgba(' . implode(',', array_map('hexdec', str_split(ltrim($secondary, '#'), 2))) . ',0.1);}
.wz-faq-item[open] summary::after{content:"−";background:' . $primary . ';color:#fff;}
.wz-faq-item[open] summary{color:' . $primary . ';background:rgba(' . implode(',', array_map('hexdec', str_split(ltrim($primary, '#'), 2))) . ',0.04);}
.wz-faq-item summary:hover{background:rgba(' . implode(',', array_map('hexdec', str_split(ltrim($primary, '#'), 2))) . ',0.04);}
.wz-faq-answer{padding:0 20px 18px;font-size:14px;line-height:1.8;color:#555;border-top:1px solid #f0f0f0;margin-top:0;animation:wzFaqFade 0.3s ease;}
@keyframes wzFaqFade{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}
</style>

<div class="wz-faq-section">
<div class="wz-faq-section-title"><span style="font-size:20px;">🛒</span> Siparis ve Odeme</div>

<details class="wz-faq-item">
<summary>Nasil siparis verebilirim?</summary>
<div class="wz-faq-answer">Begendiginiz urunu sepete ekleyin, odeme sayfasinda teslimat ve fatura bilgilerinizi girin, odeme yontemini secin ve siparisinizi tamamlayin. Siparis onay e-postasi tarafiniza iletilecektir.</div>
</details>

<details class="wz-faq-item">
<summary>Hangi odeme yontemlerini kabul ediyorsunuz?</summary>
<div class="wz-faq-answer">Kredi karti, banka karti, havale/EFT ve kapida odeme seceneklerini sunmaktayiz. Kredi karti ile taksitli alisveris imkani da mevcuttur.</div>
</details>

<details class="wz-faq-item">
<summary>Siparisimi verdikten sonra degistirebilir miyim?</summary>
<div class="wz-faq-answer">Siparisiniz kargoya verilmeden once degisiklik yapabilirsiniz. Bunun icin musteri hizmetlerimizle iletisime gecmeniz yeterlidir.</div>
</details>

<details class="wz-faq-item">
<summary>Faturami nasil alabilirim?</summary>
<div class="wz-faq-answer">E-faturaniz siparis tamamlandiktan sonra kayitli e-posta adresinize gonderilir. Ayrica hesabinizin "Siparislerim" bolumunden de erisebilirsiniz.</div>
</details>
</div>

<div class="wz-faq-section">
<div class="wz-faq-section-title"><span style="font-size:20px;">📦</span> Kargo ve Teslimat</div>

<details class="wz-faq-item">
<summary>Kargo ucreti ne kadar?</summary>
<div class="wz-faq-answer">Kargo ucreti siparis tutarina ve teslimat bolgesine gore degisiklik gosterebilir. Belirli tutar ustundeki siparislerde ucretsiz kargo avantajimiz vardir.</div>
</details>

<details class="wz-faq-item">
<summary>Siparisim ne zaman teslim edilir?</summary>
<div class="wz-faq-answer">Siparisler genellikle 1-3 is gunu icerisinde kargoya verilir. Toplam teslimat suresi bulundugunuz bolgeye gore 2-5 is gunu arasinda degismektedir.</div>
</details>

<details class="wz-faq-item">
<summary>Kargo takip numarami nasil ogrenebilirim?</summary>
<div class="wz-faq-answer">Siparisiniz kargoya verildiginde takip numarasi e-posta ve/veya SMS ile tarafiniza iletilir. Hesabinizdaki "Siparislerim" bolumunden de takip edebilirsiniz.</div>
</details>
</div>

<div class="wz-faq-section">
<div class="wz-faq-section-title"><span style="font-size:20px;">🔄</span> Iade ve Degisim</div>

<details class="wz-faq-item">
<summary>Urunu iade edebilir miyim?</summary>
<div class="wz-faq-answer">Evet, urunu teslim aldiktan sonra <strong>14 gun</strong> icerisinde kosulsuz iade hakkina sahipsiniz. Urunun kullanilmamis, hasarsiz ve orijinal ambalajinda olmasi gerekmektedir.</div>
</details>

<details class="wz-faq-item">
<summary>Iade sureci nasil isliyor?</summary>
<div class="wz-faq-answer">Hesabinizdan veya musteri hizmetlerinden iade talebi olusturun, urunu orijinal ambalajinda kargo ile gonderin. Urun elimize ulastiktan sonra inceleme yapilir ve onay halinde odemeniz 3-7 is gunu icerisinde iade edilir.</div>
</details>

<details class="wz-faq-item">
<summary>Degisim yapabilir miyim?</summary>
<div class="wz-faq-answer">Beden, renk veya model degisimi icin musteri hizmetlerimizle iletisime gecebilirsiniz. Degisim islemi, urunun stok durumuna bagli olarak gerceklestirilir.</div>
</details>
</div>

<div class="wz-faq-section">
<div class="wz-faq-section-title"><span style="font-size:20px;">👤</span> Hesap ve Uyelik</div>

<details class="wz-faq-item">
<summary>Uye olmadan alisveris yapabilir miyim?</summary>
<div class="wz-faq-answer">Evet, misafir olarak da alisveris yapabilirsiniz. Ancak uye olarak siparis takibi, adres kaydetme ve ozel kampanyalardan yararlanma gibi avantajlardan faydalanabilirsiniz.</div>
</details>

<details class="wz-faq-item">
<summary>Sifremi unuttum, ne yapmaliyim?</summary>
<div class="wz-faq-answer">Giris sayfasindaki "Sifremi Unuttum" linkine tiklayarak kayitli e-posta adresinize sifre sifirlama baglantisi gonderebilirsiniz.</div>
</details>
</div>

<div class="wz-faq-section">
<div class="wz-faq-section-title"><span style="font-size:20px;">🔒</span> Guvenlik</div>

<details class="wz-faq-item">
<summary>Kisisel bilgilerim guvenli mi?</summary>
<div class="wz-faq-answer">Evet, tum kisisel verileriniz 256-bit SSL sertifikasi ile sifrelenmektedir. KVKK kapsaminda verileriniz korunmakta ve ucuncu sahislarla paylasilmamaktadir.</div>
</details>

<details class="wz-faq-item">
<summary>Kredi karti bilgilerim saklaniyor mu?</summary>
<div class="wz-faq-answer">Hayir, kredi karti bilgileriniz sunucularimizda kesinlikle saklanmaz. Odemeler 3D Secure dogrulama ile gerceklestirilmektedir.</div>
</details>
</div>

<div style="margin-top:25px;padding:20px;background:linear-gradient(135deg,rgba(' . implode(',', array_map('hexdec', str_split(ltrim($primary, '#'), 2))) . ',0.06),rgba(' . implode(',', array_map('hexdec', str_split(ltrim($primary, '#'), 2))) . ',0.02));border-radius:12px;border-left:4px solid ' . $primary . ';">
<h3 style="margin:0 0 10px;color:' . $primary . ';">Baska Sorulariniz mi Var?</h3>
<p style="margin:0;font-size:14px;line-height:1.8;">
' . ($phone ? '<strong>Telefon:</strong> <a href="tel:' . $phone . '" style="color:' . $primary . ';text-decoration:none;">' . $phone . '</a><br>' : '') . '
' . ($email ? '<strong>E-posta:</strong> <a href="mailto:' . $email . '" style="color:' . $primary . ';text-decoration:none;">' . $email . '</a><br>' : '') . '
Musteri hizmetlerimiz hafta ici 09:00 - 18:00 saatleri arasinda hizmet vermektedir.
</p>
</div>
</div>';
    }
}

new Webyaz_Pages();
