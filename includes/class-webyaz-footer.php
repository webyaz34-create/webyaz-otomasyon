<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Footer
{

    public function __construct()
    {
        add_action('wp_footer', array($this, 'render_footer'), 5);
        add_action('admin_menu', array($this, 'add_submenu'));
    }

    public function add_submenu()
    {
        $hook = add_submenu_page(
            'webyaz-dashboard',
            'Footer Ayarlari',
            'Footer',
            'manage_options',
            'webyaz-footer',
            array($this, 'render_admin')
        );
        add_action('admin_enqueue_scripts', function ($h) use ($hook) {
            if ($h === $hook) {
                wp_enqueue_media();
                wp_enqueue_script('jquery');
            }
        });
    }

    private static function get_defaults()
    {
        return array(
            'enabled' => '0',
            'bg_color' => '#2d2d2d',
            'text_color' => '#cccccc',
            'heading_color' => '#ffffff',
            'link_color' => '#ffffff',
            'link_hover_color' => '',
            'border_color' => '#444444',
            'bottom_bg' => '#222222',
            'bg_image' => '',
            'bg_overlay_color' => '#000000',
            'bg_overlay_opacity' => '75',
            'bg_image_size' => 'cover',
            'col1_title' => 'Hakkimizda',
            'col1_type' => 'about',
            'col1_content' => '',
            'col1_show_social' => '1',
            'col2_title' => 'Kurumsal',
            'col2_type' => 'legal',
            'col2_links' => array(),
            'col3_title' => 'Musteri Hizmetleri',
            'col3_type' => 'customer_service',
            'col3_links' => array(),
            'col4_title' => 'Iletisim',
            'col4_type' => 'contact',
            'col4_content' => '',
            'bottom_left' => '',
            'bottom_right' => 'payment',
            'show_payment_icons' => '1',
            'copyright_text' => '',
        );
    }

    public static function get_opts()
    {
        return wp_parse_args(get_option('webyaz_footer', array()), self::get_defaults());
    }

    private static function get_legal_pages()
    {
        $pages = array();
        $slugs = array('mesafeli-satis-sozlesmesi', 'site-kullanim-kurallari', 'kvkk-aydinlatma-metni', 'gizlilik-politikasi', 'iade-ve-degisim-kosullari', 'teslimat-ve-kargo', 'odeme-guvenligi', 'hakkimizda');
        foreach ($slugs as $slug) {
            $page = get_page_by_path($slug);
            if ($page) {
                $pages[] = array('title' => $page->post_title, 'url' => get_permalink($page->ID));
            }
        }
        return $pages;
    }

    public function render_footer()
    {
        if (is_admin()) return;
        $opts = self::get_opts();
        if ($opts['enabled'] !== '1') return;

        $site = Webyaz_Settings::get('company_name');
        if (empty($site)) $site = get_bloginfo('name');
        $phone = Webyaz_Settings::get('company_phone');
        $email = Webyaz_Settings::get('company_email');
        $address = Webyaz_Settings::get('company_address');
        $socials = array(
            'facebook' => Webyaz_Settings::get('social_facebook'),
            'instagram' => Webyaz_Settings::get('social_instagram'),
            'twitter' => Webyaz_Settings::get('social_twitter'),
            'youtube' => Webyaz_Settings::get('social_youtube'),
            'tiktok' => Webyaz_Settings::get('social_tiktok'),
        );

        $link_hover = !empty($opts['link_hover_color']) ? $opts['link_hover_color'] : $opts['link_color'];

        $legal_pages = self::get_legal_pages();
        $year = date('Y');
        $copyright = !empty($opts['copyright_text']) ? $opts['copyright_text'] : 'Copyright ' . $year . ' &copy; ' . esc_html($site) . '. Tum haklari saklidir.';
        $bg_image = !empty($opts['bg_image']) ? $opts['bg_image'] : '';
        $overlay_color = !empty($opts['bg_overlay_color']) ? $opts['bg_overlay_color'] : '#000000';
        $overlay_opacity = isset($opts['bg_overlay_opacity']) ? intval($opts['bg_overlay_opacity']) : 75;
        $bg_size = !empty($opts['bg_image_size']) ? $opts['bg_image_size'] : 'cover';
?>
        <style>
            .webyaz-footer {
                background: <?php echo esc_attr($opts['bg_color']); ?>;
                color: <?php echo esc_attr($opts['text_color']); ?>;
                font-family: 'Roboto', sans-serif;
                font-size: 14px;
                line-height: 1.8;
                position: relative;
                <?php if ($bg_image): ?>background-image: url('<?php echo esc_url($bg_image); ?>');
                background-size: <?php echo esc_attr($bg_size); ?>;
                background-position: center center;
                background-repeat: no-repeat;
                <?php endif; ?>
            }

            <?php if ($bg_image): ?>.webyaz-footer:before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: <?php echo esc_attr($overlay_color); ?>;
                opacity: <?php echo ($overlay_opacity / 100); ?>;
                z-index: 1;
            }

            .webyaz-footer-main,
            .webyaz-footer-bottom {
                position: relative;
                z-index: 2;
            }

            <?php endif; ?>.webyaz-footer a {
                color: <?php echo esc_attr($opts['link_color']); ?>;
                text-decoration: none;
                transition: color 0.2s;
            }

            .webyaz-footer a:hover {
                color: <?php echo esc_attr($link_hover); ?>;
            }

            .webyaz-footer h4 {
                color: <?php echo esc_attr($opts['heading_color']); ?>;
                font-size: 16px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 1px;
                margin: 0 0 20px;
                padding-bottom: 12px;
                border-bottom: 2px solid <?php echo esc_attr($opts['border_color']); ?>;
                position: relative;
            }

            .webyaz-footer-main {
                max-width: 1200px;
                margin: 0 auto;
                padding: 50px 20px 30px;
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 40px;
            }

            .webyaz-footer-bottom {
                <?php if ($bg_image): ?>background: transparent;
                border-top: none;
                <?php else: ?>background: <?php echo esc_attr($opts['bottom_bg']); ?>;
                border-top: 1px solid <?php echo esc_attr($opts['border_color']); ?>;
                <?php endif; ?>
            }

            .webyaz-footer-bottom-inner {
                max-width: 1200px;
                margin: 0 auto;
                padding: 18px 20px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 10px;
            }

            .webyaz-footer-bottom p {
                margin: 0;
                font-size: 13px;
                color: <?php echo esc_attr($opts['text_color']); ?>;
                opacity: 0.7;
            }

            .webyaz-footer ul {
                list-style: none;
                margin: 0;
                padding: 0;
            }

            .webyaz-footer ul li {
                margin-bottom: 8px;
            }

            .webyaz-footer ul li a {
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .webyaz-footer ul li a:before {
                content: '›';
                font-size: 16px;
                font-weight: 700;
                opacity: 0.5;
            }

            .webyaz-footer .webyaz-ft-social {
                display: flex;
                gap: 10px;
                margin-top: 18px;
            }

            .webyaz-footer .webyaz-ft-social a {
                width: 38px;
                height: 38px;
                border-radius: 50%;
                border: 1px solid <?php echo esc_attr($opts['border_color']); ?>;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.25s;
            }

            .webyaz-footer .webyaz-ft-social a:hover {
                background: <?php echo esc_attr($opts['link_color']); ?>;
                color: <?php echo esc_attr($opts['bg_color']); ?>;
                border-color: <?php echo esc_attr($opts['link_color']); ?>;
            }

            .webyaz-footer .webyaz-ft-contact li {
                margin-bottom: 12px;
                display: flex;
                align-items: flex-start;
                gap: 10px;
            }

            .webyaz-footer .webyaz-ft-contact li:before {
                display: none;
            }

            .webyaz-footer .webyaz-ft-contact a:before {
                display: none;
            }

            .webyaz-footer .webyaz-ft-contact svg {
                flex-shrink: 0;
                margin-top: 3px;
                opacity: 0.6;
            }

            .webyaz-ft-payment {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .webyaz-ft-payment span {
                background: rgba(255, 255, 255, 0.1);
                border-radius: 4px;
                padding: 6px 12px;
                font-size: 12px;
                font-weight: 700;
                color: #fff;
                letter-spacing: 0.5px;
            }

            @media(max-width:768px) {
                .webyaz-footer-main {
                    grid-template-columns: 1fr 1fr;
                    gap: 30px;
                }

                .webyaz-footer-bottom-inner {
                    flex-direction: column;
                    text-align: center;
                }
            }

            @media(max-width:480px) {
                .webyaz-footer-main {
                    grid-template-columns: 1fr;
                    gap: 25px;
                }
            }
        </style>

        <footer class="webyaz-footer" id="webyazFooter">
            <div class="webyaz-footer-main">
                <?php
                for ($c = 1; $c <= 4; $c++) {
                    $title = $opts['col' . $c . '_title'];
                    $type = $opts['col' . $c . '_type'];
                    echo '<div class="webyaz-ft-col">';
                    if ($title) echo '<h4>' . esc_html($title) . '</h4>';

                    switch ($type) {
                        case 'about':
                            $about = !empty($opts['col' . $c . '_content']) ? $opts['col' . $c . '_content'] : get_bloginfo('description');
                            echo '<p>' . esc_html($about) . '</p>';
                            if (!empty($opts['col' . $c . '_show_social']) && $opts['col' . $c . '_show_social'] === '1') {
                                $this->render_social($socials);
                            }
                            break;

                        case 'links':
                            $links = !empty($opts['col' . $c . '_links']) ? $opts['col' . $c . '_links'] : array();
                            if (!empty($links)) {
                                echo '<ul>';
                                foreach ($links as $link) {
                                    if (empty($link['label'])) continue;
                                    echo '<li><a href="' . esc_url($link['url']) . '">' . esc_html($link['label']) . '</a></li>';
                                }
                                echo '</ul>';
                            }
                            break;

                        case 'legal':
                            echo '<ul style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;">';
                            foreach ($legal_pages as $lp) {
                                echo '<li><a href="' . esc_url($lp['url']) . '">' . esc_html($lp['title']) . '</a></li>';
                            }
                            $custom_links = !empty($opts['col' . $c . '_links']) ? $opts['col' . $c . '_links'] : array();
                            foreach ($custom_links as $link) {
                                if (empty($link['label'])) continue;
                                echo '<li><a href="' . esc_url($link['url']) . '">' . esc_html($link['label']) . '</a></li>';
                            }
                            echo '</ul>';
                            break;

                        case 'customer_service':
                            echo '<ul>';
                            if (function_exists('wc_get_page_permalink')) {
                                echo '<li><a href="' . esc_url(wc_get_page_permalink('myaccount')) . '">Hesabim</a></li>';
                                $track_page = get_page_by_path('siparis-takip');
                                if ($track_page) {
                                    echo '<li><a href="' . esc_url(get_permalink($track_page->ID)) . '">Siparis Takip</a></li>';
                                } else {
                                    echo '<li><a href="' . esc_url(wc_get_page_permalink('myaccount')) . '">Siparis Takip</a></li>';
                                }
                            }
                            $iade_page = get_page_by_path('iade-ve-degisim-kosullari');
                            if ($iade_page) echo '<li><a href="' . esc_url(get_permalink($iade_page->ID)) . '">Iade ve Degisim</a></li>';
                            $teslimat_page = get_page_by_path('teslimat-ve-kargo');
                            if ($teslimat_page) echo '<li><a href="' . esc_url(get_permalink($teslimat_page->ID)) . '">Kargo Bilgileri</a></li>';
                            $odeme_page = get_page_by_path('odeme-guvenligi');
                            if ($odeme_page) echo '<li><a href="' . esc_url(get_permalink($odeme_page->ID)) . '">Odeme Guvenligi</a></li>';
                            $compare_page = get_page_by_path('urun-karsilastir');
                            if ($compare_page) echo '<li><a href="' . esc_url(get_permalink($compare_page->ID)) . '">Urun Karsilastir</a></li>';
                            $custom_links = !empty($opts['col' . $c . '_links']) ? $opts['col' . $c . '_links'] : array();
                            foreach ($custom_links as $link) {
                                if (empty($link['label'])) continue;
                                echo '<li><a href="' . esc_url($link['url']) . '">' . esc_html($link['label']) . '</a></li>';
                            }
                            echo '</ul>';
                            break;

                        case 'contact':
                            echo '<ul class="webyaz-ft-contact">';
                            if ($address) {
                                echo '<li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><span>' . esc_html($address) . '</span></li>';
                            }
                            if ($phone) {
                                echo '<li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.11 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg><a href="tel:' . esc_attr($phone) . '">' . esc_html($phone) . '</a></li>';
                            }
                            if ($email) {
                                echo '<li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></li>';
                            }
                            echo '</ul>';
                            if (!empty($opts['col' . $c . '_show_social']) && $opts['col' . $c . '_show_social'] === '1') {
                                $this->render_social($socials);
                            }
                            break;

                        case 'custom':
                            $html = !empty($opts['col' . $c . '_content']) ? $opts['col' . $c . '_content'] : '';
                            echo wp_kses_post($html);
                            break;
                    }
                    echo '</div>';
                }
                ?>
            </div>

            <div class="webyaz-footer-bottom">
                <div class="webyaz-footer-bottom-inner">
                    <p><?php echo wp_kses_post($copyright); ?></p>
                    <?php if ($opts['show_payment_icons'] === '1'): ?>
                        <div class="webyaz-ft-payment">
                            <span>VISA</span>
                            <span>MasterCard</span>
                            <span>Troy</span>
                            <span>Havale/EFT</span>
                            <span>Kapida Odeme</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </footer>
    <?php
    }

    private function render_social($socials)
    {
        $icons = array(
            'facebook' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>',
            'instagram' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>',
            'twitter' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
            'youtube' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
            'tiktok' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1v-3.51a6.37 6.37 0 0 0-.79-.05A6.34 6.34 0 0 0 3.15 15.2a6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.34-6.34V8.75a8.18 8.18 0 0 0 4.76 1.52v-3.4a4.85 4.85 0 0 1-1-.18z"/></svg>',
        );
        $has = false;
        foreach ($socials as $v) {
            if ($v) {
                $has = true;
                break;
            }
        }
        if (!$has) return;
        echo '<div class="webyaz-ft-social">';
        foreach ($socials as $key => $url) {
            if (!$url) continue;
            echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . $icons[$key] . '</a>';
        }
        echo '</div>';
    }

    public function render_admin()
    {
        if (isset($_POST['webyaz_footer_save']) && check_admin_referer('webyaz_footer_nonce')) {
            $save = array(
                'enabled' => isset($_POST['wf_enabled']) ? '1' : '0',
                'bg_color' => sanitize_hex_color($_POST['wf_bg_color']),
                'text_color' => sanitize_hex_color($_POST['wf_text_color']),
                'heading_color' => sanitize_hex_color($_POST['wf_heading_color']),
                'link_color' => sanitize_hex_color($_POST['wf_link_color']),
                'link_hover_color' => sanitize_hex_color(isset($_POST['wf_link_hover_color']) ? $_POST['wf_link_hover_color'] : ''),
                'border_color' => sanitize_hex_color($_POST['wf_border_color']),
                'bottom_bg' => sanitize_hex_color($_POST['wf_bottom_bg']),
                'bg_image' => esc_url_raw(isset($_POST['wf_bg_image']) ? $_POST['wf_bg_image'] : ''),
                'bg_overlay_color' => sanitize_hex_color(isset($_POST['wf_overlay_color']) ? $_POST['wf_overlay_color'] : '#000000'),
                'bg_overlay_opacity' => intval(isset($_POST['wf_overlay_opacity']) ? $_POST['wf_overlay_opacity'] : 75),
                'bg_image_size' => sanitize_text_field(isset($_POST['wf_bg_size']) ? $_POST['wf_bg_size'] : 'cover'),
                'show_payment_icons' => isset($_POST['wf_show_payment']) ? '1' : '0',
                'copyright_text' => sanitize_text_field(isset($_POST['wf_copyright']) ? $_POST['wf_copyright'] : ''),
            );

            for ($c = 1; $c <= 4; $c++) {
                $save['col' . $c . '_title'] = sanitize_text_field($_POST['wf_col' . $c . '_title']);
                $save['col' . $c . '_type'] = sanitize_text_field($_POST['wf_col' . $c . '_type']);
                $save['col' . $c . '_content'] = wp_kses_post(isset($_POST['wf_col' . $c . '_content']) ? $_POST['wf_col' . $c . '_content'] : '');
                $save['col' . $c . '_show_social'] = isset($_POST['wf_col' . $c . '_show_social']) ? '1' : '0';
                $save['col' . $c . '_links'] = array();
                if (isset($_POST['wf_col' . $c . '_links']) && is_array($_POST['wf_col' . $c . '_links'])) {
                    foreach ($_POST['wf_col' . $c . '_links'] as $link) {
                        if (empty($link['label']) && empty($link['url'])) continue;
                        $save['col' . $c . '_links'][] = array(
                            'label' => sanitize_text_field($link['label']),
                            'url' => esc_url_raw($link['url']),
                        );
                    }
                }
            }

            update_option('webyaz_footer', $save);
            echo '<div class="webyaz-notice success">Footer ayarlari kaydedildi!</div>';
        }

        $opts = self::get_opts();
        $col_types = array(
            'about' => 'Hakkimizda + Aciklama',
            'links' => 'Ozel Linkler',
            'legal' => 'Kurumsal Sayfalar (Otomatik)',
            'customer_service' => 'Musteri Hizmetleri (Otomatik)',
            'contact' => 'Iletisim Bilgileri',
            'custom' => 'Ozel HTML',
        );
        $all_pages = get_pages(array('sort_column' => 'post_title'));
    ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Footer Ayarlari</h1>
                <p>Site alt bilgisini ozellestirebileceginiz panel. 4 sutunlu footer tasarimi.</p>
            </div>

            <form method="post">
                <?php wp_nonce_field('webyaz_footer_nonce'); ?>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Genel</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label><input type="checkbox" name="wf_enabled" value="1" <?php checked($opts['enabled'], '1'); ?>> Footer Aktif</label>
                            <small style="color:#999;display:block;margin-top:4px;">Flatsome footer yerine Webyaz footer kullanilir</small>
                        </div>
                        <div class="webyaz-field">
                            <label><input type="checkbox" name="wf_show_payment" value="1" <?php checked($opts['show_payment_icons'], '1'); ?>> Odeme Yontemleri Goster</label>
                        </div>
                        <div class="webyaz-field" style="grid-column:1/-1;">
                            <label>Copyright Yazisi (bos = otomatik)</label>
                            <input type="text" name="wf_copyright" value="<?php echo esc_attr($opts['copyright_text']); ?>" placeholder="Copyright 2025 &copy; Firma Adi. Tum haklari saklidir.">
                        </div>
                    </div>
                </div>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Renk Ayarlari</h2>
                    <div class="webyaz-settings-grid" style="grid-template-columns:repeat(4,1fr);">
                        <?php
                        $color_fields = array(
                            'bg_color' => 'Arka Plan',
                            'text_color' => 'Yazi Rengi',
                            'heading_color' => 'Baslik Rengi',
                            'link_color' => 'Link Rengi',
                            'link_hover_color' => 'Link Hover',
                            'border_color' => 'Cizgi Rengi',
                            'bottom_bg' => 'Alt Bar Arka Plan',
                        );
                        foreach ($color_fields as $key => $label):
                            $val = $opts[$key];
                        ?>
                            <div class="webyaz-field">
                                <label><?php echo $label; ?></label>
                                <div style="display:flex;gap:6px;align-items:center;">
                                    <input type="color" name="wf_<?php echo $key; ?>" value="<?php echo esc_attr($val ? $val : '#333333'); ?>" style="width:44px;height:34px;padding:1px;border:1px solid #ddd;border-radius:6px;cursor:pointer;">
                                    <span style="font-size:12px;color:#888;"><?php echo esc_html($val); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Arka Plan Gorseli</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:15px;">Resim eklerseniz uzerine transparan bir katman eklenir, linkler ve yazilar gorunur kalir.</p>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field" style="grid-column:1/-1;">
                            <label>Gorsel URL</label>
                            <div style="display:flex;gap:8px;">
                                <input type="url" name="wf_bg_image" id="wfBgImage" value="<?php echo esc_attr($opts['bg_image']); ?>" placeholder="https://siteadi.com/gorsel.jpg" style="flex:1;">
                                <button type="button" class="button" id="wfBgImageBtn" style="white-space:nowrap;">Medyadan Sec</button>
                            </div>
                            <small style="color:#999;display:block;margin-top:4px;">Bos birakilirsa duz renk arka plan kullanilir. Ideal boyut: 1920x600 px</small>
                        </div>
                        <?php if (!empty($opts['bg_image'])): ?>
                            <div class="webyaz-field" style="grid-column:1/-1;">
                                <div style="border-radius:8px;overflow:hidden;max-height:150px;position:relative;">
                                    <img src="<?php echo esc_url($opts['bg_image']); ?>" style="width:100%;height:150px;object-fit:cover;display:block;">
                                    <div style="position:absolute;top:0;left:0;right:0;bottom:0;background:<?php echo esc_attr($opts['bg_overlay_color']); ?>;opacity:<?php echo (intval($opts['bg_overlay_opacity']) / 100); ?>;"></div>
                                    <div style="position:absolute;bottom:8px;right:8px;background:rgba(0,0,0,0.6);color:#fff;padding:4px 10px;border-radius:4px;font-size:11px;">Onizleme (overlay ile)</div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="webyaz-field">
                            <label>Overlay Rengi</label>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <input type="color" name="wf_overlay_color" value="<?php echo esc_attr($opts['bg_overlay_color']); ?>" style="width:44px;height:34px;padding:1px;border:1px solid #ddd;border-radius:6px;cursor:pointer;">
                                <span style="font-size:12px;color:#888;"><?php echo esc_html($opts['bg_overlay_color']); ?></span>
                            </div>
                        </div>
                        <div class="webyaz-field">
                            <label>Overlay Saydamlik (<?php echo intval($opts['bg_overlay_opacity']); ?>%)</label>
                            <input type="range" name="wf_overlay_opacity" min="0" max="100" value="<?php echo intval($opts['bg_overlay_opacity']); ?>" style="width:100%;" oninput="this.previousElementSibling.textContent='Overlay Saydamlik ('+this.value+'%)'">
                            <small style="color:#999;">0% = tamamen saydam, 100% = tamamen opak</small>
                        </div>
                        <div class="webyaz-field">
                            <label>Gorsel Boyutlandirma</label>
                            <select name="wf_bg_size">
                                <option value="cover" <?php selected($opts['bg_image_size'], 'cover'); ?>>Kapla (Cover)</option>
                                <option value="contain" <?php selected($opts['bg_image_size'], 'contain'); ?>>Sigdir (Contain)</option>
                                <option value="100% auto" <?php selected($opts['bg_image_size'], '100% auto'); ?>>Tam Genislik</option>
                            </select>
                        </div>
                    </div>
                </div>

                <script>
                    jQuery(document).ready(function($) {
                        var btn = document.getElementById('wfBgImageBtn');
                        if (btn) {
                            btn.addEventListener('click', function(e) {
                                e.preventDefault();
                                if (typeof wp === 'undefined' || !wp.media) {
                                    alert('Medya kutuphanesi yuklenemedi. Sayfayi yenileyin.');
                                    return;
                                }
                                var frame = wp.media({
                                    title: 'Footer Arka Plan Gorseli',
                                    multiple: false,
                                    library: {
                                        type: 'image'
                                    }
                                });
                                frame.on('select', function() {
                                    var attachment = frame.state().get('selection').first().toJSON();
                                    document.getElementById('wfBgImage').value = attachment.url;
                                });
                                frame.open();
                            });
                        }
                    });
                </script>

                <?php for ($c = 1; $c <= 4; $c++):
                    $col_title = $opts['col' . $c . '_title'];
                    $col_type = $opts['col' . $c . '_type'];
                    $col_content = isset($opts['col' . $c . '_content']) ? $opts['col' . $c . '_content'] : '';
                    $col_social = isset($opts['col' . $c . '_show_social']) ? $opts['col' . $c . '_show_social'] : '0';
                    $col_links = isset($opts['col' . $c . '_links']) ? $opts['col' . $c . '_links'] : array();
                    if (empty($col_links)) $col_links[] = array('label' => '', 'url' => '');
                ?>
                    <div class="webyaz-settings-section">
                        <h2 class="webyaz-section-title">Sutun <?php echo $c; ?></h2>
                        <div class="webyaz-settings-grid">
                            <div class="webyaz-field">
                                <label>Baslik</label>
                                <input type="text" name="wf_col<?php echo $c; ?>_title" value="<?php echo esc_attr($col_title); ?>">
                            </div>
                            <div class="webyaz-field">
                                <label>Icerik Tipi</label>
                                <select name="wf_col<?php echo $c; ?>_type" onchange="webyazToggleCol(<?php echo $c; ?>,this.value)">
                                    <?php foreach ($col_types as $tk => $tl): ?>
                                        <option value="<?php echo $tk; ?>" <?php selected($col_type, $tk); ?>><?php echo $tl; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="webyaz-field">
                                <label><input type="checkbox" name="wf_col<?php echo $c; ?>_show_social" value="1" <?php checked($col_social, '1'); ?>> Sosyal medya ikonlari goster</label>
                            </div>
                        </div>

                        <div id="webyazCol<?php echo $c; ?>About" style="<?php echo $col_type === 'about' || $col_type === 'custom' ? '' : 'display:none;'; ?>margin-top:12px;">
                            <div class="webyaz-field">
                                <label><?php echo $col_type === 'custom' ? 'HTML Icerik' : 'Aciklama Metni'; ?></label>
                                <textarea name="wf_col<?php echo $c; ?>_content" rows="3" style="width:100%;"><?php echo esc_textarea($col_content); ?></textarea>
                            </div>
                        </div>

                        <div id="webyazCol<?php echo $c; ?>Links" style="<?php echo $col_type === 'links' ? '' : 'display:none;'; ?>margin-top:12px;">
                            <label style="font-weight:600;margin-bottom:8px;display:block;">Linkler</label>
                            <div id="webyazCol<?php echo $c; ?>LinkList">
                                <?php foreach ($col_links as $li => $link): ?>
                                    <div style="display:flex;gap:8px;margin-bottom:6px;align-items:center;">
                                        <input type="text" name="wf_col<?php echo $c; ?>_links[<?php echo $li; ?>][label]" value="<?php echo esc_attr($link['label']); ?>" placeholder="Yazi" style="flex:1;">
                                        <select onchange="this.nextElementSibling.value=this.value" style="flex:1;">
                                            <option value="">-- Sayfa secin veya yazin --</option>
                                            <?php foreach ($all_pages as $pg): ?>
                                                <option value="<?php echo get_permalink($pg->ID); ?>" <?php selected(get_permalink($pg->ID), $link['url']); ?>><?php echo esc_html($pg->post_title); ?></option>
                                            <?php endforeach; ?>
                                            <?php if (function_exists('wc_get_page_permalink')): ?>
                                                <option value="<?php echo wc_get_page_permalink('shop'); ?>" <?php selected(wc_get_page_permalink('shop'), $link['url']); ?>>Magaza</option>
                                                <option value="<?php echo wc_get_page_permalink('cart'); ?>" <?php selected(wc_get_page_permalink('cart'), $link['url']); ?>>Sepet</option>
                                                <option value="<?php echo wc_get_page_permalink('myaccount'); ?>" <?php selected(wc_get_page_permalink('myaccount'), $link['url']); ?>>Hesabim</option>
                                            <?php endif; ?>
                                        </select>
                                        <input type="url" name="wf_col<?php echo $c; ?>_links[<?php echo $li; ?>][url]" value="<?php echo esc_attr($link['url']); ?>" placeholder="URL" style="flex:1;">
                                        <button type="button" onclick="this.parentElement.remove();" style="background:#d32f2f;color:#fff;border:none;border-radius:4px;padding:6px 10px;cursor:pointer;">&times;</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" onclick="webyazAddFooterLink(<?php echo $c; ?>);" style="font-size:12px;padding:6px 14px;background:#446084;color:#fff;border:none;border-radius:4px;cursor:pointer;margin-top:4px;">+ Link Ekle</button>
                        </div>
                    </div>
                <?php endfor; ?>

                <div style="margin-top:20px;">
                    <button type="submit" name="webyaz_footer_save" class="button button-primary" style="padding:8px 24px;font-size:14px;">Kaydet</button>
                </div>
            </form>
        </div>

        <script>
            function webyazToggleCol(c, type) {
                var about = document.getElementById('webyazCol' + c + 'About');
                var links = document.getElementById('webyazCol' + c + 'Links');
                about.style.display = (type === 'about' || type === 'custom') ? '' : 'none';
                links.style.display = (type === 'links') ? '' : 'none';
            }
            var webyazLinkIdx = {};
            var webyazPageOpts = <?php
                                    $popts = '<option value="">-- Sayfa secin --</option>';
                                    foreach ($all_pages as $pg) {
                                        $popts .= '<option value="' . esc_url(get_permalink($pg->ID)) . '">' . esc_html($pg->post_title) . '</option>';
                                    }
                                    if (function_exists('wc_get_page_permalink')) {
                                        $popts .= '<option value="' . esc_url(wc_get_page_permalink('shop')) . '">Magaza</option>';
                                        $popts .= '<option value="' . esc_url(wc_get_page_permalink('cart')) . '">Sepet</option>';
                                        $popts .= '<option value="' . esc_url(wc_get_page_permalink('myaccount')) . '">Hesabim</option>';
                                    }
                                    echo wp_json_encode($popts);
                                    ?>;

            function webyazAddFooterLink(c) {
                if (!webyazLinkIdx[c]) webyazLinkIdx[c] = document.querySelectorAll('#webyazCol' + c + 'LinkList > div').length;
                var i = webyazLinkIdx[c]++;
                var html = '<div style="display:flex;gap:8px;margin-bottom:6px;align-items:center;">';
                html += '<input type="text" name="wf_col' + c + '_links[' + i + '][label]" placeholder="Yazi" style="flex:1;">';
                html += '<select onchange="this.nextElementSibling.value=this.value" style="flex:1;">' + webyazPageOpts + '</select>';
                html += '<input type="url" name="wf_col' + c + '_links[' + i + '][url]" placeholder="URL" style="flex:1;">';
                html += '<button type="button" onclick="this.parentElement.remove();" style="background:#d32f2f;color:#fff;border:none;border-radius:4px;padding:6px 10px;cursor:pointer;">&times;</button>';
                html += '</div>';
                document.getElementById('webyazCol' + c + 'LinkList').insertAdjacentHTML('beforeend', html);
            }
        </script>
<?php
    }
}

new Webyaz_Footer();
