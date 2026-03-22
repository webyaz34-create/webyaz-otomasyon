<?php
if (!defined('ABSPATH')) exit;

class Webyaz_SEO
{

    public function __construct()
    {
        // Meta output
        add_action('wp_head', array($this, 'output_meta'), 1);
        add_action('wp_head', array($this, 'output_schema'), 2);
        add_action('wp_head', array($this, 'output_canonical'), 3);

        // Meta box (yazi/urun bazli SEO)
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box'));

        // Admin sayfalar
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_actions'));

        // XML Sitemap
        add_action('init', array($this, 'sitemap_rewrite'));
        add_action('template_redirect', array($this, 'serve_sitemap'));

        // Robots.txt
        add_filter('robots_txt', array($this, 'custom_robots'), 10, 2);

        // Title tag override
        add_filter('pre_get_document_title', array($this, 'override_title'), 99);
        add_filter('document_title_parts', array($this, 'filter_title_parts'), 99);

        // Admin bar SEO skoru
        add_action('admin_bar_menu', array($this, 'admin_bar_seo'), 100);
    }

    // ─── AYARLAR ──────────────────────────────
    public function register_settings()
    {
        register_setting('webyaz_seo_group', 'webyaz_seo');
    }

    private static function get_defaults()
    {
        return array(
            'site_title'       => '',
            'slogan'           => '',
            'site_description' => '',
            'og_image'         => '',
            'twitter_card'     => 'summary_large_image',
            'robots_extra'     => '',
            'title_separator'  => '-',
        );
    }

    /**
     * Shortcode ve HTML etiketlerini temizle — OG description icin
     */
    private static function clean_content($content)
    {
        // WordPress shortcode'larini kaldir
        $text = strip_shortcodes($content);
        // Sayfa builder shortcode kalintilari ([tag ...] formatinda)
        $text = preg_replace('/\[\/?\w+[^\]]*\]/', '', $text);
        // HTML etiketlerini kaldir
        $text = wp_strip_all_tags($text);
        // Coklu bosluk/satir sonlarini tek bosluga indir
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    public static function get($key)
    {
        $opts = wp_parse_args(get_option('webyaz_seo', array()), self::get_defaults());
        return isset($opts[$key]) ? $opts[$key] : '';
    }

    // ─── CANONICAL URL ──────────────────────────
    public function output_canonical()
    {
        if (is_singular()) {
            $url = get_permalink();
            $custom = get_post_meta(get_the_ID(), '_webyaz_canonical', true);
            if (!empty($custom)) $url = $custom;
            echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
        } elseif (is_front_page()) {
            echo '<link rel="canonical" href="' . esc_url(home_url('/')) . '">' . "\n";
        } elseif (is_category() || is_tag() || is_tax()) {
            $term_link = get_term_link(get_queried_object());
            if (!is_wp_error($term_link)) {
                echo '<link rel="canonical" href="' . esc_url($term_link) . '">' . "\n";
            }
        }
    }

    // ─── TITLE OVERRIDE ──────────────────────────
    public function override_title($title)
    {
        if (is_singular()) {
            $custom = get_post_meta(get_the_ID(), '_webyaz_title', true);
            if (!empty($custom)) return $custom;
        }
        return $title;
    }

    public function filter_title_parts($parts)
    {
        if (is_singular()) {
            $custom = get_post_meta(get_the_ID(), '_webyaz_title', true);
            if (!empty($custom)) {
                $parts['title'] = $custom;
            }
        }
        $sep = self::get('title_separator');
        if (!empty($sep)) $parts['sep'] = $sep;
        return $parts;
    }

    // ─── META OUTPUT ──────────────────────────
    public function output_meta()
    {
        $opts = wp_parse_args(get_option('webyaz_seo', array()), self::get_defaults());

        $title = '';
        $description = '';
        $image = $opts['og_image'];
        $url = home_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');
        $site_name = Webyaz_Settings::get('company_name');
        if (empty($site_name)) $site_name = get_bloginfo('name');

        if (is_singular()) {
            global $post;

            // Ozel meta varsa kullan
            $custom_title = get_post_meta($post->ID, '_webyaz_title', true);
            $custom_desc = get_post_meta($post->ID, '_webyaz_description', true);

            if (is_singular('product') && function_exists('wc_get_product')) {
                $product = wc_get_product($post->ID);
                if ($product) {
                    $title = !empty($custom_title) ? $custom_title : $product->get_name() . ' ' . $opts['title_separator'] . ' ' . $site_name;
                    if (!empty($custom_desc)) {
                        $description = $custom_desc;
                    } else {
                        $desc_raw = $product->get_short_description();
                        if (empty($desc_raw)) $desc_raw = $product->get_description();
                        $description = wp_trim_words(self::clean_content($desc_raw), 30, '...');
                    }
                }
            } else {
                $title = !empty($custom_title) ? $custom_title : get_the_title() . ' ' . $opts['title_separator'] . ' ' . $site_name;
                $description = !empty($custom_desc) ? $custom_desc : wp_trim_words(self::clean_content($post->post_content), 30, '...');
            }

            $thumb = get_post_thumbnail_id($post->ID);
            if ($thumb) {
                $img = wp_get_attachment_image_src($thumb, 'large');
                if ($img) $image = $img[0];
            }
        } elseif (is_front_page() || is_home()) {
            $base_title = !empty($opts['site_title']) ? $opts['site_title'] : $site_name;
            // Slogan varsa basligi birlistir: "Site Adi - Slogan"
            if (!empty($opts['slogan'])) {
                $title = $base_title . ' ' . $opts['title_separator'] . ' ' . $opts['slogan'];
            } else {
                $title = $base_title;
            }
            $description = !empty($opts['site_description']) ? $opts['site_description'] : get_bloginfo('description');
        } elseif (is_product_category() || is_category() || is_tax()) {
            $term = get_queried_object();
            if ($term) {
                $title = $term->name . ' ' . $opts['title_separator'] . ' ' . $site_name;
                $description = !empty($term->description) ? wp_trim_words(self::clean_content($term->description), 30, '...') : '';
            }
        }

        if (empty($title)) $title = wp_title('-', false, 'right') . $site_name;
        if (empty($description)) $description = !empty($opts['site_description']) ? $opts['site_description'] : get_bloginfo('description');
        if (empty($image)) {
            $custom_logo = get_theme_mod('custom_logo');
            if ($custom_logo) {
                $img = wp_get_attachment_image_src($custom_logo, 'full');
                if ($img) $image = $img[0];
            }
        }

        echo "\n<!-- Webyaz SEO -->\n";
        if ($description) {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }

        echo '<meta property="og:locale" content="tr_TR">' . "\n";
        echo '<meta property="og:type" content="' . (is_singular('product') ? 'product' : 'website') . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";
        if ($image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
            echo '<meta property="og:image:alt" content="' . esc_attr($title) . '">' . "\n";
            echo '<meta property="og:image:width" content="1200">' . "\n";
            echo '<meta property="og:image:height" content="630">' . "\n";
        }

        echo '<meta name="twitter:card" content="' . esc_attr($opts['twitter_card']) . '">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
        if ($image) {
            echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
        }

        // Iletisim bilgileri (business contact)
        $phone = Webyaz_Settings::get('company_phone');
        $email = Webyaz_Settings::get('company_email');
        $address = Webyaz_Settings::get('company_address');
        if ($phone) {
            echo '<meta property="business:contact_data:phone_number" content="' . esc_attr($phone) . '">' . "\n";
        }
        if ($email) {
            echo '<meta property="business:contact_data:email" content="' . esc_attr($email) . '">' . "\n";
        }
        if ($address) {
            echo '<meta property="business:contact_data:street_address" content="' . esc_attr($address) . '">' . "\n";
        }

        echo "<!-- /Webyaz SEO -->\n";
    }

    // ─── SCHEMA OUTPUT ──────────────────────────
    public function output_schema()
    {
        $site_name = Webyaz_Settings::get('company_name');
        if (empty($site_name)) $site_name = get_bloginfo('name');

        $logo = '';
        $custom_logo = get_theme_mod('custom_logo');
        if ($custom_logo) {
            $img = wp_get_attachment_image_src($custom_logo, 'full');
            if ($img) $logo = $img[0];
        }

        // Organization schema
        if (is_front_page() || is_home()) {
            $schema = array(
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $site_name,
                'url' => home_url('/'),
            );
            if ($logo) $schema['logo'] = $logo;
            $phone = Webyaz_Settings::get('company_phone');
            if ($phone) $schema['telephone'] = $phone;
            $address = Webyaz_Settings::get('company_address');
            if ($address) {
                $schema['address'] = array(
                    '@type' => 'PostalAddress',
                    'streetAddress' => $address,
                );
            }
            // Sosyal medya profilleri
            $same_as = array();
            $social_keys = array(
                'social_facebook'  => 'https://facebook.com/',
                'social_instagram' => 'https://instagram.com/',
                'social_twitter'   => 'https://twitter.com/',
                'social_youtube'   => '',
                'social_tiktok'    => 'https://tiktok.com/@',
                'social_linkedin'  => '',
            );
            foreach ($social_keys as $key => $prefix) {
                $val = Webyaz_Settings::get($key);
                if (!empty($val)) {
                    // URL ise direkt kullan, degilse prefix ekle
                    if (filter_var($val, FILTER_VALIDATE_URL)) {
                        $same_as[] = $val;
                    } elseif (!empty($prefix)) {
                        $same_as[] = $prefix . ltrim($val, '@/');
                    }
                }
            }
            if (!empty($same_as)) $schema['sameAs'] = $same_as;
            echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        }

        // Product schema
        if (is_singular('product') && function_exists('wc_get_product')) {
            global $post;
            $product = wc_get_product($post->ID);
            if ($product) {
                $schema = array(
                    '@context' => 'https://schema.org',
                    '@type' => 'Product',
                    'name' => $product->get_name(),
                    'description' => wp_trim_words(wp_strip_all_tags($product->get_short_description()), 50),
                    'url' => get_permalink($post->ID),
                    'offers' => array(
                        '@type' => 'Offer',
                        'price' => $product->get_price(),
                        'priceCurrency' => get_woocommerce_currency(),
                        'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                        'seller' => array(
                            '@type' => 'Organization',
                            'name' => $site_name,
                        ),
                    ),
                );
                $thumb = get_post_thumbnail_id($post->ID);
                if ($thumb) {
                    $img = wp_get_attachment_image_src($thumb, 'large');
                    if ($img) $schema['image'] = $img[0];
                }
                if ($product->get_sku()) $schema['sku'] = $product->get_sku();
                $rating_count = $product->get_rating_count();
                if ($rating_count > 0) {
                    $schema['aggregateRating'] = array(
                        '@type' => 'AggregateRating',
                        'ratingValue' => $product->get_average_rating(),
                        'reviewCount' => $rating_count,
                    );
                }
                echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
            }
        }

        // Breadcrumb schema
        if (!is_front_page()) {
            $crumbs = $this->get_breadcrumbs();
            if (!empty($crumbs)) {
                $items = array();
                foreach ($crumbs as $i => $crumb) {
                    $items[] = array(
                        '@type' => 'ListItem',
                        'position' => $i + 1,
                        'name' => $crumb['name'],
                        'item' => $crumb['url'],
                    );
                }
                $schema = array(
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => $items,
                );
                echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
            }
        }
    }

    // Breadcrumb verileri olustur
    private function get_breadcrumbs()
    {
        $crumbs = array();
        $crumbs[] = array('name' => 'Ana Sayfa', 'url' => home_url('/'));

        if (is_singular('product')) {
            global $post;
            $terms = get_the_terms($post->ID, 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                $term = $terms[0];
                // Ust kategoriler
                $ancestors = get_ancestors($term->term_id, 'product_cat');
                $ancestors = array_reverse($ancestors);
                foreach ($ancestors as $anc_id) {
                    $anc = get_term($anc_id, 'product_cat');
                    $crumbs[] = array('name' => $anc->name, 'url' => get_term_link($anc));
                }
                $crumbs[] = array('name' => $term->name, 'url' => get_term_link($term));
            }
            $crumbs[] = array('name' => get_the_title(), 'url' => get_permalink());
        } elseif (is_singular()) {
            global $post;
            $cats = get_the_category($post->ID);
            if (!empty($cats)) {
                $crumbs[] = array('name' => $cats[0]->name, 'url' => get_category_link($cats[0]));
            }
            $crumbs[] = array('name' => get_the_title(), 'url' => get_permalink());
        } elseif (is_category() || is_tax()) {
            $term = get_queried_object();
            if ($term) {
                $ancestors = get_ancestors($term->term_id, $term->taxonomy);
                $ancestors = array_reverse($ancestors);
                foreach ($ancestors as $anc_id) {
                    $anc = get_term($anc_id, $term->taxonomy);
                    $crumbs[] = array('name' => $anc->name, 'url' => get_term_link($anc));
                }
                $crumbs[] = array('name' => $term->name, 'url' => get_term_link($term));
            }
        }

        return $crumbs;
    }

    // ─── META BOX (Sayfa/Yazi bazli SEO) ──────────────────────────
    public function add_meta_box()
    {
        $screens = array('post', 'page', 'product');
        foreach ($screens as $screen) {
            add_meta_box(
                'webyaz_seo_meta',
                'Webyaz SEO',
                array($this, 'render_meta_box'),
                $screen,
                'normal',
                'high'
            );
        }
    }

    public function render_meta_box($post)
    {
        wp_nonce_field('webyaz_seo_meta', '_webyaz_seo_nonce');

        $opts = wp_parse_args(get_option('webyaz_seo', array()), self::get_defaults());
        $title = get_post_meta($post->ID, '_webyaz_title', true);
        $desc = get_post_meta($post->ID, '_webyaz_description', true);
        $canonical = get_post_meta($post->ID, '_webyaz_canonical', true);
        $noindex = get_post_meta($post->ID, '_webyaz_noindex', true);

        // SEO skor hesapla
        $score = $this->calculate_score($post);
        $score_color = $score >= 70 ? '#4caf50' : ($score >= 40 ? '#ff9800' : '#f44336');
        $score_label = $score >= 70 ? 'Iyi' : ($score >= 40 ? 'Gelistirilebilir' : 'Zayif');
?>
        <style>
            .wz-seo-box {
                font-family: -apple-system, sans-serif;
            }

            .wz-seo-box label {
                display: block;
                font-weight: 600;
                font-size: 13px;
                margin-bottom: 4px;
                color: #333;
            }

            .wz-seo-box input[type=text],
            .wz-seo-box input[type=url],
            .wz-seo-box textarea {
                width: 100%;
                padding: 8px 12px;
                border: 2px solid #e0e0e0;
                border-radius: 6px;
                font-size: 14px;
                transition: border .2s;
            }

            .wz-seo-box input:focus,
            .wz-seo-box textarea:focus {
                border-color: #446084;
                outline: none;
            }

            .wz-seo-field {
                margin-bottom: 14px;
            }

            .wz-seo-counter {
                font-size: 11px;
                color: #999;
                margin-top: 2px;
            }

            .wz-seo-preview {
                background: #fff;
                border: 1px solid #dfe1e5;
                border-radius: 8px;
                padding: 14px 16px;
                margin-bottom: 16px;
            }

            .wz-seo-preview-title {
                color: #1a0dab;
                font-size: 18px;
                font-weight: 400;
                line-height: 1.3;
                margin-bottom: 2px;
                cursor: pointer;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .wz-seo-preview-url {
                color: #006621;
                font-size: 13px;
                margin-bottom: 2px;
            }

            .wz-seo-preview-desc {
                color: #545454;
                font-size: 13px;
                line-height: 1.5;
            }
        </style>
        <div class="wz-seo-box">

            <!-- SEO Skor -->
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;padding:14px;background:#f8f9fa;border-radius:8px;">
                <div style="width:54px;height:54px;border-radius:50%;border:4px solid <?php echo $score_color; ?>;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;color:<?php echo $score_color; ?>;">
                    <?php echo $score; ?>
                </div>
                <div>
                    <div style="font-size:15px;font-weight:700;color:<?php echo $score_color; ?>;">SEO Skoru: <?php echo $score_label; ?></div>
                    <div style="font-size:12px;color:#666;">Asagidaki alanlari doldurarak skorunuzu yukseltebilirsiniz</div>
                </div>
            </div>

            <!-- Google Onizleme -->
            <div style="margin-bottom:14px;">
                <label style="margin-bottom:8px;">Google Onizleme</label>
                <div class="wz-seo-preview">
                    <div class="wz-seo-preview-title" id="wzSeoPreviewTitle"><?php echo esc_html(!empty($title) ? $title : $post->post_title); ?></div>
                    <div class="wz-seo-preview-url"><?php echo esc_url(get_permalink($post->ID)); ?></div>
                    <div class="wz-seo-preview-desc" id="wzSeoPreviewDesc"><?php echo esc_html(!empty($desc) ? $desc : wp_trim_words(wp_strip_all_tags($post->post_content), 25, '...')); ?></div>
                </div>
            </div>

            <!-- Otomatik Olustur Butonu -->
            <div style="margin-bottom:14px;">
                <button type="button" id="wzSeoAutoFill" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:linear-gradient(135deg,#446084,#5a7ca8);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;box-shadow:0 2px 8px rgba(68,96,132,.3);">
                    <span style="font-size:16px;">⚡</span> Otomatik Olustur
                </button>
                <span id="wzAutoFillMsg" style="margin-left:12px;font-size:12px;color:#4caf50;display:none;">✓ Alanlar otomatik dolduruldu!</span>
            </div>

            <!-- SEO Title -->
            <div class="wz-seo-field">
                <label for="webyaz_seo_title">SEO Basligi</label>
                <input type="text" id="webyaz_seo_title" name="webyaz_seo_title" value="<?php echo esc_attr($title); ?>" placeholder="<?php echo esc_attr($post->post_title); ?>">
                <div class="wz-seo-counter"><span id="wzTitleCount"><?php echo mb_strlen($title); ?></span>/60 karakter (ideal: 50-60)</div>
            </div>

            <!-- SEO Description -->
            <div class="wz-seo-field">
                <label for="webyaz_seo_desc">Meta Aciklama</label>
                <textarea id="webyaz_seo_desc" name="webyaz_seo_desc" rows="3" placeholder="Bu sayfa hakkinda kisa bir aciklama yazin..."><?php echo esc_textarea($desc); ?></textarea>
                <div class="wz-seo-counter"><span id="wzDescCount"><?php echo mb_strlen($desc); ?></span>/160 karakter (ideal: 120-160)</div>
            </div>

            <!-- Canonical URL -->
            <div class="wz-seo-field">
                <label for="webyaz_canonical">Canonical URL</label>
                <input type="url" id="webyaz_canonical" name="webyaz_canonical" value="<?php echo esc_attr($canonical); ?>" placeholder="Bos birakilirsa sayfa URL'si kullanilir">
            </div>

            <!-- Noindex -->
            <div class="wz-seo-field">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="webyaz_noindex" value="1" <?php checked($noindex, '1'); ?>>
                    <span style="font-weight:normal;">Bu sayfayi arama motorlarindan gizle (noindex)</span>
                </label>
            </div>
        </div>

        <script>
            (function() {
                var titleInput = document.getElementById('webyaz_seo_title');
                var descInput = document.getElementById('webyaz_seo_desc');
                var previewTitle = document.getElementById('wzSeoPreviewTitle');
                var previewDesc = document.getElementById('wzSeoPreviewDesc');
                var titleCount = document.getElementById('wzTitleCount');
                var descCount = document.getElementById('wzDescCount');
                var autoFillBtn = document.getElementById('wzSeoAutoFill');
                var autoFillMsg = document.getElementById('wzAutoFillMsg');
                var postTitle = <?php echo wp_json_encode($post->post_title); ?>;
                var siteName = <?php echo wp_json_encode(Webyaz_Settings::get('company_name') ?: get_bloginfo('name')); ?>;
                var separator = <?php echo wp_json_encode($opts['title_separator'] ?: '-'); ?>;

                function updatePreview() {
                    titleCount.textContent = titleInput.value.length;
                    previewTitle.textContent = titleInput.value || postTitle;
                    titleCount.style.color = titleInput.value.length > 60 ? '#f44336' : '#999';
                    descCount.textContent = descInput.value.length;
                    previewDesc.textContent = descInput.value || '';
                    descCount.style.color = descInput.value.length > 160 ? '#f44336' : '#999';
                }

                titleInput.addEventListener('input', updatePreview);
                descInput.addEventListener('input', updatePreview);

                // Sayfa icerigini al (Gutenberg veya Klasik Editor)
                function getPostContent() {
                    // Gutenberg
                    if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                        var content = wp.data.select('core/editor').getEditedPostContent();
                        if (content) return content;
                    }
                    // Klasik Editor - TinyMCE
                    if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                        var editor = tinyMCE.get('content');
                        if (!editor.isHidden()) return editor.getContent();
                    }
                    // Klasik Editor - Text modu
                    var textarea = document.getElementById('content');
                    if (textarea) return textarea.value;
                    return '';
                }

                // Sayfa basligini al (Gutenberg veya Klasik)
                function getPostTitle() {
                    if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                        var t = wp.data.select('core/editor').getEditedPostAttribute('title');
                        if (t) return t;
                    }
                    var titleField = document.getElementById('title');
                    if (titleField && titleField.value) return titleField.value;
                    return postTitle;
                }

                // HTML etiketlerini ve shortcode'lari temizle
                function stripHtml(html) {
                    // Shortcode'lari kaldir
                    var cleaned = html.replace(/\[\/?\w+[^\]]*\]/g, '');
                    // style ve script bloklarini tamamen kaldir
                    cleaned = cleaned.replace(/<style[^>]*>[\s\S]*?<\/style\s*>/gi, '');
                    cleaned = cleaned.replace(new RegExp('<scr' + 'ipt[^>]*>[\\s\\S]*?<\\/scr' + 'ipt\\s*>', 'gi'), '');
                    // CSS kodu kalintilari: .class { ... } veya element { ... }
                    cleaned = cleaned.replace(/[^{}]*\{[^}]*\}/g, '');
                    // CSS yorum bloklari
                    cleaned = cleaned.replace(/\/\*[\s\S]*?\*\//g, '');
                    var tmp = document.createElement('div');
                    tmp.innerHTML = cleaned;
                    var text = tmp.textContent || tmp.innerText || '';
                    // Coklu bosluk ve satir sonlarini temizle
                    return text.replace(/\s+/g, ' ').trim();
                }

                // Meta aciklama olustur (ilk ~155 karakter, kelime sinirinda kes)
                function generateDescription(content) {
                    var text = stripHtml(content);
                    if (text.length <= 155) return text;
                    var cut = text.substring(0, 155);
                    var lastSpace = cut.lastIndexOf(' ');
                    if (lastSpace > 100) cut = cut.substring(0, lastSpace);
                    return cut + ' ...';
                }

                // Otomatik Olustur butonu
                autoFillBtn.addEventListener('click', function() {
                    var currentTitle = getPostTitle();
                    var content = getPostContent();

                    // Uzerine yazma uyarisi
                    if (titleInput.value || descInput.value) {
                        if (!confirm('Mevcut degerler degistirilecek. Devam etmek istiyor musunuz?')) return;
                    }

                    // SEO Basligi olustur
                    var seoTitle = currentTitle + ' ' + separator + ' ' + siteName;
                    if (seoTitle.length > 60) {
                        seoTitle = currentTitle;
                    }
                    titleInput.value = seoTitle;

                    // Meta Aciklama olustur
                    if (content) {
                        descInput.value = generateDescription(content);
                    } else {
                        descInput.value = currentTitle + ' hakkinda detayli bilgi icin sayfamizi ziyaret edin.';
                    }

                    // Onizleme ve sayaclari guncelle
                    updatePreview();

                    // Basarili mesaji goster
                    autoFillMsg.style.display = 'inline';
                    setTimeout(function() {
                        autoFillMsg.style.display = 'none';
                    }, 3000);
                });

                // Buton hover efekti
                autoFillBtn.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-1px)';
                    this.style.boxShadow = '0 4px 12px rgba(68,96,132,.4)';
                });
                autoFillBtn.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 2px 8px rgba(68,96,132,.3)';
                });
            })();
        </script>
    <?php
    }

    public function save_meta_box($post_id)
    {
        if (!isset($_POST['_webyaz_seo_nonce'])) return;
        if (!wp_verify_nonce($_POST['_webyaz_seo_nonce'], 'webyaz_seo_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $fields = array(
            'webyaz_seo_title' => '_webyaz_title',
            'webyaz_seo_desc'  => '_webyaz_description',
            'webyaz_canonical' => '_webyaz_canonical',
        );
        foreach ($fields as $post_key => $meta_key) {
            $val = isset($_POST[$post_key]) ? sanitize_text_field($_POST[$post_key]) : '';
            if (!empty($val)) {
                update_post_meta($post_id, $meta_key, $val);
            } else {
                delete_post_meta($post_id, $meta_key);
            }
        }

        $noindex = isset($_POST['webyaz_noindex']) ? '1' : '';
        if ($noindex) {
            update_post_meta($post_id, '_webyaz_noindex', '1');
        } else {
            delete_post_meta($post_id, '_webyaz_noindex');
        }
    }

    // ─── SEO SKOR HESAPLAMA ──────────────────────────
    private function calculate_score($post)
    {
        $score = 0;
        $title = get_post_meta($post->ID, '_webyaz_title', true);
        $desc = get_post_meta($post->ID, '_webyaz_description', true);
        $content = wp_strip_all_tags($post->post_content);

        // Baslik (25 puan)
        if (!empty($title)) {
            $score += 15;
            $len = mb_strlen($title);
            if ($len >= 30 && $len <= 60) $score += 10;
            elseif ($len > 0) $score += 5;
        }

        // Aciklama (25 puan)
        if (!empty($desc)) {
            $score += 15;
            $len = mb_strlen($desc);
            if ($len >= 80 && $len <= 160) $score += 10;
            elseif ($len > 0) $score += 5;
        }

        // Icerik uzunlugu (20 puan)
        $word_count = str_word_count($content);
        if ($word_count >= 300) $score += 20;
        elseif ($word_count >= 100) $score += 10;
        elseif ($word_count >= 50) $score += 5;

        // Gorsel (15 puan)
        if (has_post_thumbnail($post->ID)) $score += 15;

        // Baslik etiketi (15 puan)
        if (preg_match('/<h[1-3]/i', $post->post_content)) $score += 10;
        if (preg_match('/<img/i', $post->post_content)) $score += 5;

        return min(100, $score);
    }

    // Admin bar'da SEO skoru
    public function admin_bar_seo($wp_admin_bar)
    {
        if (!is_singular() || !current_user_can('edit_posts')) return;
        global $post;
        $score = $this->calculate_score($post);
        $color = $score >= 70 ? '#4caf50' : ($score >= 40 ? '#ff9800' : '#f44336');

        $wp_admin_bar->add_node(array(
            'id'    => 'webyaz-seo-score',
            'title' => '<span style="color:' . $color . ';font-weight:700;">SEO: ' . $score . '/100</span>',
            'href'  => get_edit_post_link($post->ID) . '#webyaz_seo_meta',
        ));
    }

    // ─── XML SITEMAP ──────────────────────────
    public function sitemap_rewrite()
    {
        add_rewrite_rule('^sitemap\.xml$', 'index.php?webyaz_sitemap=index', 'top');
        add_rewrite_rule('^sitemap-posts\.xml$', 'index.php?webyaz_sitemap=posts', 'top');
        add_rewrite_rule('^sitemap-pages\.xml$', 'index.php?webyaz_sitemap=pages', 'top');
        add_rewrite_rule('^sitemap-products\.xml$', 'index.php?webyaz_sitemap=products', 'top');
        add_rewrite_rule('^sitemap-categories\.xml$', 'index.php?webyaz_sitemap=categories', 'top');
        add_filter('query_vars', function ($vars) {
            $vars[] = 'webyaz_sitemap';
            return $vars;
        });
    }

    public function serve_sitemap()
    {
        $type = get_query_var('webyaz_sitemap');
        if (empty($type)) return;

        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

        if ($type === 'index') {
            $this->sitemap_index();
        } else {
            $this->sitemap_urls($type);
        }
        exit;
    }

    private function sitemap_index()
    {
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $maps = array(
            'posts'      => 'post',
            'pages'      => 'page',
            'products'   => 'product',
            'categories' => '',
        );
        foreach ($maps as $m => $post_type) {
            $lastmod = date('c');
            if (!empty($post_type)) {
                $latest = get_posts(array('post_type' => $post_type, 'posts_per_page' => 1, 'orderby' => 'modified', 'order' => 'DESC', 'post_status' => 'publish'));
                if (!empty($latest)) $lastmod = get_the_modified_date('c', $latest[0]);
            }
            echo '<sitemap><loc>' . home_url('/sitemap-' . $m . '.xml') . '</loc><lastmod>' . $lastmod . '</lastmod></sitemap>' . "\n";
        }
        echo '</sitemapindex>';
    }

    private function sitemap_urls($type)
    {
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Ana sayfa'yi her zaman ekle (pages sitemap'inde)
        if ($type === 'pages') {
            echo '<url><loc>' . home_url('/') . '</loc><changefreq>daily</changefreq><priority>1.0</priority></url>' . "\n";
        }

        $args = array('post_status' => 'publish', 'posts_per_page' => 2000, 'orderby' => 'modified', 'order' => 'DESC');

        if ($type === 'posts') {
            $args['post_type'] = 'post';
        } elseif ($type === 'pages') {
            $args['post_type'] = 'page';
        } elseif ($type === 'products') {
            $args['post_type'] = 'product';
        } elseif ($type === 'categories') {
            $taxonomies = array('category');
            if (taxonomy_exists('product_cat')) $taxonomies[] = 'product_cat';
            $terms = get_terms(array('taxonomy' => $taxonomies, 'hide_empty' => true));
            foreach ($terms as $term) {
                $link = get_term_link($term);
                if (!is_wp_error($link)) {
                    echo '<url><loc>' . esc_url($link) . '</loc><changefreq>weekly</changefreq><priority>0.6</priority></url>' . "\n";
                }
            }
            echo '</urlset>';
            return;
        }

        $posts = get_posts($args);
        foreach ($posts as $p) {
            $noindex = get_post_meta($p->ID, '_webyaz_noindex', true);
            if ($noindex === '1') continue;
            $priority = ($type === 'pages') ? '0.8' : '0.7';
            echo '<url><loc>' . get_permalink($p->ID) . '</loc><lastmod>' . get_the_modified_date('c', $p) . '</lastmod><changefreq>weekly</changefreq><priority>' . $priority . '</priority></url>' . "\n";
        }

        echo '</urlset>';
    }

    // ─── ROBOTS.TXT ──────────────────────────
    public function custom_robots($output, $public)
    {
        $extra = self::get('robots_extra');
        if (!empty($extra)) {
            $output .= "\n" . $extra . "\n";
        }
        // Sitemap ekle
        $output .= "\nSitemap: " . home_url('/sitemap.xml') . "\n";
        return $output;
    }

    // ─── 301 REDIRECT ──────────────────────────
    public function handle_actions()
    {
        // Redirect kayit
        if (isset($_POST['webyaz_save_redirect']) && wp_verify_nonce($_POST['_wpnonce_redirect'], 'webyaz_seo_redirect')) {
            if (!current_user_can('manage_options')) return;
            $from = sanitize_text_field($_POST['redirect_from'] ?? '');
            $to = esc_url_raw($_POST['redirect_to'] ?? '');
            if (!empty($from) && !empty($to)) {
                $redirects = get_option('webyaz_redirects', array());
                $redirects[$from] = $to;
                update_option('webyaz_redirects', $redirects);
            }
            wp_redirect(admin_url('admin.php?page=webyaz-seo&tab=redirects&saved=1'));
            exit;
        }

        // Redirect sil
        if (isset($_GET['delete_redirect']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_redirect')) {
            $key = sanitize_text_field($_GET['delete_redirect']);
            $redirects = get_option('webyaz_redirects', array());
            unset($redirects[$key]);
            update_option('webyaz_redirects', $redirects);
            wp_redirect(admin_url('admin.php?page=webyaz-seo&tab=redirects&deleted=1'));
            exit;
        }

        // Flush rewrite (sitemap icin)
        if (isset($_GET['webyaz_flush_sitemap'])) {
            flush_rewrite_rules();
            wp_redirect(admin_url('admin.php?page=webyaz-seo&tab=sitemap&flushed=1'));
            exit;
        }

        // 301 redirect calistir (frontend)
        if (!is_admin()) {
            $this->do_redirects();
        }
    }

    private function do_redirects()
    {
        $redirects = get_option('webyaz_redirects', array());
        if (empty($redirects)) return;
        $current = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $current = rtrim($current, '/');
        foreach ($redirects as $from => $to) {
            $from_trimmed = rtrim($from, '/');
            if ($current === $from_trimmed || $current === $from) {
                wp_redirect($to, 301);
                exit;
            }
        }
    }

    // ─── ADMIN SAYFASI ──────────────────────────
    public function add_submenu()
    {
        add_submenu_page('webyaz-dashboard', 'SEO Ayarlari', 'SEO', 'manage_options', 'webyaz-seo', array($this, 'render_admin'));
    }

    public function render_admin()
    {
        wp_enqueue_media();
        $opts = wp_parse_args(get_option('webyaz_seo', array()), self::get_defaults());
        $tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

        $primary = '#446084';
        $secondary = '#d26e4b';
        if (class_exists('Webyaz_Colors')) {
            $colors = Webyaz_Colors::get_theme_colors();
            $primary = $colors['primary'];
            $secondary = $colors['secondary'];
        }
    ?>
        <div class="webyaz-admin-wrap" style="max-width:900px;margin:20px 0;font-family:-apple-system,BlinkMacSystemFont,sans-serif;">

            <div style="background:linear-gradient(135deg,<?php echo $primary; ?>,<?php echo $secondary; ?>);color:#fff;padding:30px 35px;border-radius:12px;margin-bottom:25px;position:relative;overflow:hidden;">
                <div style="position:absolute;top:0;left:0;right:0;bottom:0;background:linear-gradient(135deg,rgba(0,0,0,0.3),rgba(0,0,0,0.1));z-index:0;"></div>
                <h1 style="margin:0 0 5px;font-size:26px;font-weight:700;color:#fff;position:relative;z-index:1;text-shadow:0 1px 4px rgba(0,0,0,0.4);">SEO Ayarlari</h1>
                <p style="margin:0;opacity:.95;font-size:14px;color:#fff;position:relative;z-index:1;text-shadow:0 1px 3px rgba(0,0,0,0.3);">Open Graph, Schema, Sitemap, Redirect ve meta etiket yonetimi</p>
            </div>

            <?php if (isset($_GET['settings-updated']) || isset($_GET['saved'])): ?>
                <div style="background:#e6f9e6;color:#22863a;border:1px solid #b7e4c7;padding:12px 18px;border-radius:8px;margin-bottom:15px;font-size:14px;">Kaydedildi!</div>
            <?php endif; ?>

            <!-- Tabs -->
            <div style="display:flex;gap:4px;margin-bottom:20px;background:#f0f0f0;border-radius:10px;padding:4px;">
                <?php
                $tabs = array(
                    'general'   => 'Genel SEO',
                    'sitemap'   => 'XML Sitemap',
                    'robots'    => 'Robots.txt',
                    'redirects' => '301 Redirect',
                );
                foreach ($tabs as $t => $label):
                    $active = ($tab === $t);
                ?>
                    <a href="<?php echo admin_url('admin.php?page=webyaz-seo&tab=' . $t); ?>" style="flex:1;text-align:center;padding:10px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:<?php echo $active ? '700' : '500'; ?>;color:<?php echo $active ? '#fff' : '#555'; ?>;background:<?php echo $active ? $primary : 'transparent'; ?>;transition:all .2s;">
                        <?php echo $label; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php
            if ($tab === 'general') $this->tab_general($opts, $primary);
            elseif ($tab === 'sitemap') $this->tab_sitemap($primary);
            elseif ($tab === 'robots') $this->tab_robots($opts, $primary);
            elseif ($tab === 'redirects') $this->tab_redirects($primary);
            ?>
        </div>
    <?php
    }

    // Tab: Genel SEO
    private function tab_general($opts, $primary)
    { ?>
        <form method="post" action="options.php">
            <?php settings_fields('webyaz_seo_group'); ?>
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px;margin-bottom:20px;">
                <h2 style="font-size:18px;font-weight:700;margin:0 0 16px;color:#1e1e2e;">Ana Sayfa SEO</h2>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div>
                        <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:4px;">Site Basligi</label>
                        <input type="text" name="webyaz_seo[site_title]" value="<?php echo esc_attr($opts['site_title']); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px;">
                    </div>
                    <div>
                        <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:4px;">Baslik Ayiricisi</label>
                        <select name="webyaz_seo[title_separator]" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px;">
                            <?php foreach (array('-', '|', '—', '·', '»', '›') as $sep): ?>
                                <option value="<?php echo $sep; ?>" <?php selected($opts['title_separator'], $sep); ?>><?php echo $sep; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:4px;">Slogan</label>
                        <input type="text" name="webyaz_seo[slogan]" value="<?php echo esc_attr($opts['slogan']); ?>" placeholder="Ornek: Kaliteli urunler, uygun fiyatlar" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px;">
                        <p style="margin:4px 0 0;font-size:11px;color:#999;">Site basligi ile birlikte gosterilecek kisa slogan</p>
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:4px;">Site Aciklamasi</label>
                        <input type="text" name="webyaz_seo[site_description]" value="<?php echo esc_attr($opts['site_description']); ?>" placeholder="Sitenizin kisa aciklamasi" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px;">
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:4px;">Paylasim Gorseli (1200x630)</label>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <input type="url" id="webyaz_og_image" name="webyaz_seo[og_image]" value="<?php echo esc_attr($opts['og_image']); ?>" placeholder="https://..." style="flex:1;padding:10px;border:2px solid #e0e0e0;border-radius:8px;">
                            <button type="button" id="webyaz_og_image_btn" style="background:<?php echo $primary; ?>;color:#fff;border:none;padding:10px 20px;border-radius:8px;cursor:pointer;font-weight:600;font-size:13px;white-space:nowrap;transition:opacity .2s;">Medya Sec</button>
                        </div>
                        <?php if (!empty($opts['og_image'])): ?>
                            <div id="webyaz_og_preview" style="margin-top:10px;">
                                <img src="<?php echo esc_url($opts['og_image']); ?>" style="max-width:300px;max-height:160px;border-radius:8px;border:2px solid #e0e0e0;object-fit:cover;" alt="OG Gorsel">
                            </div>
                        <?php else: ?>
                            <div id="webyaz_og_preview" style="margin-top:10px;display:none;"></div>
                        <?php endif; ?>
                        <p style="margin:4px 0 0;font-size:11px;color:#999;">Facebook, Twitter vb. platformlarda paylasimda gosterilecek gorsel</p>
                    </div>
                    <div>
                        <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:4px;">Twitter Card Tipi</label>
                        <select name="webyaz_seo[twitter_card]" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px;">
                            <option value="summary_large_image" <?php selected($opts['twitter_card'], 'summary_large_image'); ?>>Buyuk Gorsel</option>
                            <option value="summary" <?php selected($opts['twitter_card'], 'summary'); ?>>Kucuk Gorsel</option>
                        </select>
                    </div>
                </div>
            </div>

            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px;margin-bottom:20px;">
                <h2 style="font-size:18px;font-weight:700;margin:0 0 14px;color:#1e1e2e;">Otomatik Ozellikler</h2>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <?php
                    $features = array(
                        '✓ Open Graph meta etiketleri',
                        '✓ Twitter Card destegi',
                        '✓ Product Schema (urun)',
                        '✓ Organization Schema',
                        '✓ BreadcrumbList Schema',
                        '✓ Canonical URL',
                        '✓ Sayfa bazli SEO meta box',
                        '✓ SEO skor analizi',
                        '✓ Google arama onizlemesi',
                        '✓ XML Sitemap',
                        '✓ Robots.txt yonetimi',
                        '✓ 301 Redirect',
                    );
                    foreach ($features as $f):
                    ?>
                        <div style="font-size:13px;color:#2e7d32;padding:4px 0;"><?php echo $f; ?></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" style="background:<?php echo $primary; ?>;color:#fff;border:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">Kaydet</button>
        </form>

        <script>
            jQuery(document).ready(function($) {
                $('#webyaz_og_image_btn').on('click', function(e) {
                    e.preventDefault();
                    var frame = wp.media({
                        title: 'Paylasim Gorseli Sec',
                        button: {
                            text: 'Gorsel Olarak Kullan'
                        },
                        library: {
                            type: 'image'
                        },
                        multiple: false
                    });
                    frame.on('select', function() {
                        var att = frame.state().get('selection').first().toJSON();
                        $('#webyaz_og_image').val(att.url);
                        var preview = $('#webyaz_og_preview');
                        preview.html('<img src="' + att.url + '" style="max-width:300px;max-height:160px;border-radius:8px;border:2px solid #e0e0e0;object-fit:cover;" alt="OG Gorsel">');
                        preview.show();
                    });
                    frame.open();
                });
                // Hover efekti
                $('#webyaz_og_image_btn').hover(
                    function() {
                        $(this).css('opacity', '0.85');
                    },
                    function() {
                        $(this).css('opacity', '1');
                    }
                );
            });
        </script>
    <?php }

    // Tab: Sitemap
    private function tab_sitemap($primary)
    { ?>
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px;">
            <h2 style="font-size:18px;font-weight:700;margin:0 0 16px;color:#1e1e2e;">XML Sitemap</h2>
            <p style="color:#666;font-size:13px;margin-bottom:16px;">Sitemap otomatik olusturulur. Asagidaki linkleri Google Search Console'a ekleyin.</p>

            <?php if (isset($_GET['flushed'])): ?>
                <div style="background:#e6f9e6;color:#22863a;border:1px solid #b7e4c7;padding:10px 16px;border-radius:8px;margin-bottom:14px;font-size:13px;">Rewrite kurallari yenilendi!</div>
            <?php endif; ?>

            <div style="display:grid;gap:10px;margin-bottom:20px;">
                <?php
                $maps = array(
                    'sitemap.xml' => 'Ana Sitemap (Index)',
                    'sitemap-posts.xml' => 'Blog Yazilari',
                    'sitemap-pages.xml' => 'Sayfalar',
                    'sitemap-products.xml' => 'Urunler',
                    'sitemap-categories.xml' => 'Kategoriler',
                );
                foreach ($maps as $file => $label): ?>
                    <div style="background:#f8f9fa;border-radius:8px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <strong style="font-size:13px;"><?php echo $label; ?></strong>
                            <div style="font-size:12px;color:#666;margin-top:2px;"><?php echo home_url('/' . $file); ?></div>
                        </div>
                        <a href="<?php echo home_url('/' . $file); ?>" target="_blank" style="background:<?php echo $primary; ?>;color:#fff;padding:6px 14px;border-radius:6px;font-size:12px;text-decoration:none;font-weight:600;">Gor</a>
                    </div>
                <?php endforeach; ?>
            </div>

            <a href="<?php echo admin_url('admin.php?page=webyaz-seo&tab=sitemap&webyaz_flush_sitemap=1'); ?>" style="display:inline-block;background:#f0f0f0;color:#555;padding:10px 20px;border-radius:8px;font-size:13px;text-decoration:none;font-weight:600;">Rewrite Kurallarini Yenile</a>
        </div>
    <?php }

    // Tab: Robots.txt
    private function tab_robots($opts, $primary)
    { ?>
        <form method="post" action="options.php">
            <?php settings_fields('webyaz_seo_group'); ?>
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px;">
                <h2 style="font-size:18px;font-weight:700;margin:0 0 6px;color:#1e1e2e;">Robots.txt Yonetimi</h2>
                <p style="color:#666;font-size:13px;margin:0 0 16px;">Asagidaki kurallar robots.txt dosyasina eklenir. Sitemap otomatik eklenir.</p>

                <!-- Mevcut hali goster -->
                <div style="margin-bottom:16px;">
                    <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:4px;">Mevcut robots.txt:</label>
                    <div style="background:#1e1e2e;color:#cdd6f4;padding:14px;border-radius:8px;font-family:monospace;font-size:13px;line-height:1.7;white-space:pre-wrap;"><?php
                                                                                                                                                                            echo esc_html(apply_filters('robots_txt', "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n", get_option('blog_public')));
                                                                                                                                                                            ?></div>
                </div>

                <!-- Hepsini hidden olarak tekrar gonder -->
                <input type="hidden" name="webyaz_seo[site_title]" value="<?php echo esc_attr($opts['site_title']); ?>">
                <input type="hidden" name="webyaz_seo[slogan]" value="<?php echo esc_attr($opts['slogan']); ?>">
                <input type="hidden" name="webyaz_seo[site_description]" value="<?php echo esc_attr($opts['site_description']); ?>">
                <input type="hidden" name="webyaz_seo[og_image]" value="<?php echo esc_attr($opts['og_image']); ?>">
                <input type="hidden" name="webyaz_seo[twitter_card]" value="<?php echo esc_attr($opts['twitter_card']); ?>">
                <input type="hidden" name="webyaz_seo[title_separator]" value="<?php echo esc_attr($opts['title_separator']); ?>">

                <div>
                    <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:4px;">Ekstra Kurallar:</label>
                    <textarea name="webyaz_seo[robots_extra]" rows="6" style="width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:8px;font-family:monospace;font-size:13px;" placeholder="Disallow: /ozel-dizin/&#10;User-agent: Googlebot&#10;Allow: /"><?php echo esc_textarea($opts['robots_extra']); ?></textarea>
                </div>

                <button type="submit" style="margin-top:14px;background:<?php echo $primary; ?>;color:#fff;border:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">Kaydet</button>
            </div>
        </form>
        <div style="margin-top:12px;">
            <a href="<?php echo home_url('/robots.txt'); ?>" target="_blank" style="color:<?php echo $primary; ?>;font-size:13px;font-weight:600;">robots.txt dosyasini gor →</a>
        </div>
    <?php }

    // Tab: 301 Redirects
    private function tab_redirects($primary)
    {
        $redirects = get_option('webyaz_redirects', array());
    ?>
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px;margin-bottom:20px;">
            <h2 style="font-size:18px;font-weight:700;margin:0 0 6px;color:#1e1e2e;">Yeni Yonlendirme Ekle</h2>
            <p style="color:#666;font-size:13px;margin:0 0 16px;">Eski URL'leri yeni URL'lere kalici (301) olarak yonlendirin.</p>

            <?php if (isset($_GET['saved'])): ?>
                <div style="background:#e6f9e6;color:#22863a;border:1px solid #b7e4c7;padding:10px 16px;border-radius:8px;margin-bottom:14px;font-size:13px;">Yonlendirme eklendi!</div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <div style="background:#fff3e0;color:#e65100;border:1px solid #ffe0b2;padding:10px 16px;border-radius:8px;margin-bottom:14px;font-size:13px;">Yonlendirme silindi.</div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('webyaz_seo_redirect', '_wpnonce_redirect'); ?>
                <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end;">
                    <div>
                        <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:4px;">Eski URL (Nereden)</label>
                        <input type="text" name="redirect_from" placeholder="/eski-sayfa" required style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;">
                    </div>
                    <div>
                        <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:4px;">Yeni URL (Nereye)</label>
                        <input type="url" name="redirect_to" placeholder="https://siteadi.com/yeni-sayfa" required style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;">
                    </div>
                    <button type="submit" name="webyaz_save_redirect" value="1" style="background:<?php echo $primary; ?>;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;">+ Ekle</button>
                </div>
            </form>
        </div>

        <?php if (!empty($redirects)): ?>
            <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:24px;">
                <h2 style="font-size:18px;font-weight:700;margin:0 0 16px;color:#1e1e2e;">Mevcut Yonlendirmeler (<?php echo count($redirects); ?>)</h2>
                <div style="display:grid;gap:8px;">
                    <?php foreach ($redirects as $from => $to):
                        $del_url = wp_nonce_url(admin_url('admin.php?page=webyaz-seo&tab=redirects&delete_redirect=' . urlencode($from)), 'delete_redirect');
                    ?>
                        <div style="background:#f8f9fa;border-radius:8px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;gap:12px;">
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:13px;display:flex;align-items:center;gap:8px;">
                                    <code style="background:#fee;color:#c00;padding:2px 8px;border-radius:4px;font-size:12px;"><?php echo esc_html($from); ?></code>
                                    <span style="color:#999;">→</span>
                                    <code style="background:#efe;color:#080;padding:2px 8px;border-radius:4px;font-size:12px;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html($to); ?></code>
                                </div>
                            </div>
                            <a href="<?php echo $del_url; ?>" onclick="return confirm('Bu yonlendirmeyi silmek istediginize emin misiniz?')" style="color:#d32f2f;font-size:12px;text-decoration:none;font-weight:600;">Sil</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
<?php endif;
    }
}

new Webyaz_SEO();
