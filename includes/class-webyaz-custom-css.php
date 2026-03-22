<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Custom_CSS
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_menu'), 90);
        add_action('admin_init', array($this, 'handle_save'));
        add_action('admin_init', array($this, 'handle_presets'));
        add_action('admin_init', array($this, 'handle_extras'));
        add_action('wp_head', array($this, 'inject_css'), 999);
        add_action('wp_head', array($this, 'inject_mobile_css'), 999);
        add_action('wp_head', array($this, 'inject_page_css'), 999);
        add_action('wp_head', array($this, 'inject_presets'), 998);
        add_action('wp_head', array($this, 'inject_google_fonts'), 5);
        add_action('wp_head', array($this, 'inject_hidden_elements'), 999);
        add_action('wp_footer', array($this, 'visual_editor'));
        add_action('wp_footer', array($this, 'inject_dark_mode'));
        add_action('wp_ajax_webyaz_save_visual_css', array($this, 'ajax_save_css'));
        add_action('wp_ajax_webyaz_save_text_change', array($this, 'ajax_save_text'));
        add_action('wp_ajax_webyaz_delete_text_change', array($this, 'ajax_delete_text'));
        add_action('wp_ajax_webyaz_hide_element', array($this, 'ajax_hide_element'));
        add_action('wp_ajax_webyaz_delete_hidden', array($this, 'ajax_delete_hidden'));
        add_action('wp_ajax_webyaz_restore_css', array($this, 'ajax_restore_css'));
        add_action('wp_ajax_webyaz_export_css', array($this, 'ajax_export_css'));
        add_action('wp_ajax_webyaz_import_css', array($this, 'ajax_import_css'));
        add_action('wp_footer', array($this, 'inject_text_changes'));
    }

    public static function get_presets()
    {
        return array(
            'button_hover' => array(
                'title' => 'Buton Hover Efekti',
                'desc'  => 'Butonlara surus sirasinda site renkleriyle gecis efekti, koyu - acik yazi otomatik',
                'icon'  => '🖱️',
                'css'   => "
/* Buton Hover - Site Renkleri */
a.button:hover, button:hover, .btn:hover,
input[type=submit]:hover, .add_to_cart_button:hover,
.single_add_to_cart_button:hover, .checkout-button:hover {
    background: var(--webyaz-secondary) !important;
    color: var(--webyaz-on-secondary) !important;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
}
a.button, button, .btn, input[type=submit],
.add_to_cart_button, .single_add_to_cart_button, .checkout-button {
    transition: all 0.3s ease;
}",
            ),
            'image_frame' => array(
                'title' => 'Gorsel Cerceve',
                'desc'  => 'Resimlere oval kenar ve ince site rengi cerceve',
                'icon'  => '🖼️',
                'css'   => "
/* Gorsel Cerceve - Oval + Renk */
.product-image img, .woocommerce-product-gallery img,
.attachment-woocommerce_thumbnail,
.wp-post-image, article img {
    border-radius: 12px !important;
    border: 2px solid var(--webyaz-secondary) !important;
    transition: all 0.3s ease;
}
.product-image img:hover, .woocommerce-product-gallery img:hover,
.attachment-woocommerce_thumbnail:hover,
.wp-post-image:hover, article img:hover {
    border-color: var(--webyaz-primary) !important;
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    transform: scale(1.02);
}",
            ),
            'product_card' => array(
                'title' => 'Urun Karti Efekti',
                'desc'  => 'Urun kartlarina golge, hover buyutme ve alt cizgi',
                'icon'  => '🛍️',
                'css'   => "
/* Urun Karti - Premium Gorunum */
.product-small, .product-medium, .product-large,
li.product, .product-grid-item {
    border-radius: 12px !important;
    overflow: hidden;
    transition: all 0.35s ease;
    border: 1px solid #eee;
}
.product-small:hover, .product-medium:hover, .product-large:hover,
li.product:hover, .product-grid-item:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 35px rgba(0,0,0,0.1);
    border-color: var(--webyaz-secondary) !important;
}
.product-small .product-title, li.product .woocommerce-loop-product__title {
    position: relative;
    padding-bottom: 8px;
}
.product-small .product-title:after, li.product .woocommerce-loop-product__title:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 40px;
    height: 2px;
    background: var(--webyaz-secondary);
    transition: width 0.3s ease;
}
.product-small:hover .product-title:after, li.product:hover .woocommerce-loop-product__title:after {
    width: 100%;
}",
            ),
            'heading_accent' => array(
                'title' => 'Baslik Vurgu Cizgisi',
                'desc'  => 'Basliklarin altina site rengiyle sik bir alt cizgi',
                'icon'  => '📝',
                'css'   => "
/* Baslik Vurgu Cizgisi */
h1, h2, h3, .shop-page-title,
.product-title, .entry-title {
    position: relative;
    padding-bottom: 10px;
}
h2:after, .shop-page-title:after,
.entry-title:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 50px;
    height: 3px;
    background: linear-gradient(90deg, var(--webyaz-primary), var(--webyaz-secondary));
    border-radius: 3px;
}
.has-text-align-center h2:after,
.text-center h2:after {
    left: 50%;
    transform: translateX(-50%);
}",
            ),
            'smooth_scroll' => array(
                'title' => 'Yumusak Sayfa Gecisi',
                'desc'  => 'Sayfa icinde yumusak kayma ve gecis animasyonlari',
                'icon'  => '✨',
                'css'   => "
/* Yumusak Gecisler */
html {
    scroll-behavior: smooth;
}
* {
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}
.page-wrapper, .shop-container, main {
    animation: wzFadeIn 0.5s ease;
}
@keyframes wzFadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}",
            ),
            'shadow_depth' => array(
                'title' => 'Golge Derinlik Efekti',
                'desc'  => 'Kartlara ve bloklara profesyonel golge katmanlari',
                'icon'  => '🌑',
                'css'   => "
/* Golge Derinlik */
.col-inner, .panel, .box, .card,
.banner-inner, .widget, aside .widget {
    box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 6px 16px rgba(0,0,0,0.06) !important;
    border-radius: 10px !important;
    transition: box-shadow 0.3s ease;
}
.col-inner:hover, .panel:hover, .box:hover,
.card:hover, .banner-inner:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08), 0 16px 40px rgba(0,0,0,0.1) !important;
}",
            ),
            'link_effects' => array(
                'title' => 'Link Animasyonu',
                'desc'  => 'Linklere site rengiyle alt cizgi animasyonu',
                'icon'  => '🔗',
                'css'   => "
/* Link Alt Cizgi Animasyonu */
.entry-content a, .page-content a,
article a, .post-content a {
    color: var(--webyaz-primary);
    text-decoration: none;
    background-image: linear-gradient(var(--webyaz-secondary), var(--webyaz-secondary));
    background-position: 0 100%;
    background-repeat: no-repeat;
    background-size: 0% 2px;
    transition: background-size 0.3s ease;
    padding-bottom: 2px;
}
.entry-content a:hover, .page-content a:hover,
article a:hover, .post-content a:hover {
    background-size: 100% 2px;
    color: var(--webyaz-secondary);
}",
            ),
            'table_style' => array(
                'title' => 'Tablo Stillendirme',
                'desc'  => 'Tablolara zebra desen, hover ve site rengi baslik',
                'icon'  => '📊',
                'css'   => "
/* Tablo Stillendirme */
table {
    border-collapse: collapse;
    border-radius: 8px;
    overflow: hidden;
    width: 100%;
}
table thead th, table th {
    background: var(--webyaz-primary) !important;
    color: var(--webyaz-on-primary) !important;
    padding: 12px 16px !important;
    font-weight: 600;
}
table tbody tr:nth-child(even) {
    background: rgba(0,0,0,0.02);
}
table tbody tr:hover {
    background: rgba(0,0,0,0.04);
}
table td {
    padding: 10px 16px !important;
    border-bottom: 1px solid #eee !important;
}",
            ),
            'input_focus' => array(
                'title' => 'Form Alani Efekti',
                'desc'  => 'Input alanlarina tiklayinca site rengi cerceve ve golge',
                'icon'  => '📝',
                'css'   => "
/* Form Alani Focus */
input[type=text], input[type=email], input[type=password],
input[type=tel], input[type=number], input[type=search],
textarea, select, .input-text {
    border: 2px solid #e0e0e0 !important;
    border-radius: 8px !important;
    padding: 10px 14px !important;
    transition: all 0.3s ease;
    outline: none !important;
}
input[type=text]:focus, input[type=email]:focus, input[type=password]:focus,
input[type=tel]:focus, input[type=number]:focus, input[type=search]:focus,
textarea:focus, select:focus, .input-text:focus {
    border-color: var(--webyaz-primary) !important;
    box-shadow: 0 0 0 3px rgba(68,96,132,0.15) !important;
}",
            ),
            'footer_accent' => array(
                'title' => 'Footer Ust Cizgi',
                'desc'  => 'Footer uzerine gradient site rengi accent cizgisi',
                'icon'  => '🎨',
                'css'   => "
/* Footer Ust Accent */
footer, .footer-wrapper, #footer, .site-footer {
    position: relative;
}
footer:before, .footer-wrapper:before, #footer:before, .site-footer:before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--webyaz-primary), var(--webyaz-secondary), var(--webyaz-primary));
}",
            ),
            // -- ANIMASYON PRESETLERI --
            'anim_fade_in' => array(
                'title' => 'Fade In Giris',
                'desc'  => 'Sayfa elemanlarinin yumusak belirme animasyonu',
                'icon'  => '🎬',
                'css'   => "
/* Fade In Animasyonu */
.product-small, .product-medium, li.product,
.col-inner, .panel, .card, article,
.banner-inner, .widget, .box {
    opacity: 0;
    animation: wzFadeUp 0.6s ease forwards;
}
.product-small:nth-child(1), li.product:nth-child(1) { animation-delay: 0.1s; }
.product-small:nth-child(2), li.product:nth-child(2) { animation-delay: 0.2s; }
.product-small:nth-child(3), li.product:nth-child(3) { animation-delay: 0.3s; }
.product-small:nth-child(4), li.product:nth-child(4) { animation-delay: 0.4s; }
@keyframes wzFadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}",
            ),
            'anim_slide' => array(
                'title' => 'Slide Kaydirma',
                'desc'  => 'Elemanlar soldan saga kayarak belirir',
                'icon'  => '➡️',
                'css'   => "
/* Slide In Animasyonu */
.product-small, li.product, .col-inner,
.banner-inner, article {
    opacity: 0;
    animation: wzSlideIn 0.5s ease forwards;
}
@keyframes wzSlideIn {
    from { opacity: 0; transform: translateX(-30px); }
    to { opacity: 1; transform: translateX(0); }
}",
            ),
            'anim_bounce' => array(
                'title' => 'Bounce Efekti',
                'desc'  => 'Butonlara tiklandiginda zipla efekti',
                'icon'  => '🏀',
                'css'   => "
/* Bounce Butonu */
a.button:active, button:active, .btn:active,
.add_to_cart_button:active, .single_add_to_cart_button:active {
    animation: wzBounce 0.4s ease;
}
@keyframes wzBounce {
    0% { transform: scale(1); }
    30% { transform: scale(0.92); }
    50% { transform: scale(1.05); }
    70% { transform: scale(0.98); }
    100% { transform: scale(1); }
}",
            ),
            'anim_scale' => array(
                'title' => 'Scale Buyutme',
                'desc'  => 'Elemanlar kucukten buyuyerek gorunur',
                'icon'  => '🔍',
                'css'   => "
/* Scale Animasyonu */
.product-small, li.product, .col-inner,
.widget, .card {
    opacity: 0;
    animation: wzScaleIn 0.5s ease forwards;
}
@keyframes wzScaleIn {
    from { opacity: 0; transform: scale(0.85); }
    to { opacity: 1; transform: scale(1); }
}",
            ),
        );
    }

    public function handle_presets()
    {
        if (!isset($_POST['webyaz_save_presets'])) return;
        if (!wp_verify_nonce($_POST['_wpnonce_presets'], 'webyaz_css_presets')) return;
        if (!current_user_can('manage_options')) return;

        $presets = self::get_presets();
        $active = array();
        foreach ($presets as $key => $p) {
            if (!empty($_POST['wz_preset_' . $key])) {
                $active[] = $key;
            }
        }
        update_option('webyaz_active_presets', $active);

        wp_redirect(admin_url('admin.php?page=webyaz-custom-css&presets_saved=1'));
        exit;
    }

    public function inject_presets()
    {
        $active = get_option('webyaz_active_presets', array());
        if (empty($active)) return;

        $primary = '#446084';
        $secondary = '#d26e4b';
        if (class_exists('Webyaz_Colors')) {
            $colors = Webyaz_Colors::get_theme_colors();
            $primary = $colors['primary'];
            $secondary = $colors['secondary'];
        }

        // Kontrast hesapla
        $on_primary = self::is_dark($primary) ? '#ffffff' : '#111111';
        $on_secondary = self::is_dark($secondary) ? '#ffffff' : '#111111';

        echo "\n<style id=\"webyaz-css-vars\">\n";
        echo ":root {\n";
        echo "    --webyaz-primary: {$primary};\n";
        echo "    --webyaz-secondary: {$secondary};\n";
        echo "    --webyaz-on-primary: {$on_primary};\n";
        echo "    --webyaz-on-secondary: {$on_secondary};\n";
        echo "}\n";
        echo "</style>\n";

        $presets = self::get_presets();
        $css = '';
        foreach ($active as $key) {
            if (isset($presets[$key])) {
                $css .= $presets[$key]['css'] . "\n";
            }
        }

        if (!empty($css)) {
            echo "<style id=\"webyaz-preset-css\">\n" . $css . "</style>\n";
        }
    }

    private static function is_dark($hex)
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255 < 0.5;
    }

    public function add_menu()
    {
        add_submenu_page(
            'webyaz-dashboard',
            'Ozel CSS',
            'Ozel CSS',
            'manage_options',
            'webyaz-custom-css',
            array($this, 'render_page')
        );
    }


    public function ajax_save_css()
    {
        check_ajax_referer('webyaz_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Yetki yok');

        $selector = sanitize_text_field($_POST['selector'] ?? '');
        $new_css = wp_strip_all_tags($_POST['css'] ?? '');

        if (empty($selector) || empty($new_css)) {
            wp_send_json_error('Eksik bilgi');
        }

        $existing = get_option('webyaz_custom_css', '');
        $block = "\n/* Gorsel Duzenleyici: " . $selector . " */\n" . $selector . " {\n" . $new_css . "\n}\n";
        $updated = $existing . $block;
        update_option('webyaz_custom_css', $updated);

        wp_send_json_success(array('message' => 'Kaydedildi!', 'css' => $block));
    }

    public function ajax_save_text()
    {
        check_ajax_referer('webyaz_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Yetki yok');

        $selector = sanitize_text_field($_POST['selector'] ?? '');
        $old_text = sanitize_text_field($_POST['old_text'] ?? '');
        $new_text = sanitize_text_field($_POST['new_text'] ?? '');
        $page_url = esc_url_raw($_POST['page_url'] ?? '');

        if (empty($selector) || $new_text === '') {
            wp_send_json_error('Eksik bilgi');
        }

        $changes = get_option('webyaz_text_changes', array());
        $changes[] = array(
            'selector'  => $selector,
            'old_text'  => $old_text,
            'new_text'  => $new_text,
            'page_url'  => $page_url,
            'date'      => current_time('Y-m-d H:i'),
        );
        update_option('webyaz_text_changes', $changes);

        wp_send_json_success(array('message' => 'Yazi kaydedildi!'));
    }

    public function ajax_delete_text()
    {
        check_ajax_referer('webyaz_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Yetki yok');

        $index = intval($_POST['index'] ?? -1);
        $changes = get_option('webyaz_text_changes', array());

        if ($index < 0 || $index >= count($changes)) {
            wp_send_json_error('Gecersiz index');
        }

        array_splice($changes, $index, 1);
        update_option('webyaz_text_changes', $changes);

        wp_send_json_success(array('message' => 'Silindi!'));
    }

    public function inject_text_changes()
    {
        $changes = get_option('webyaz_text_changes', array());
        if (empty($changes)) return;

        $current_url = home_url(add_query_arg(array(), $_SERVER['REQUEST_URI'] ?? ''));
        // Query string olmadan normalize et
        $current_path = wp_parse_url($current_url, PHP_URL_PATH);
        ?>
        <script id="webyaz-text-changes">
        (function(){
            var changes = <?php echo wp_json_encode($changes); ?>;
            var currentPath = <?php echo wp_json_encode($current_path); ?>;

            function applyChanges() {
                changes.forEach(function(c) {
                    // Sayfa URL kontrolu - bos ise tum sayfalarda uygula
                    if (c.page_url) {
                        try {
                            var changePath = new URL(c.page_url).pathname;
                            if (changePath !== currentPath) return;
                        } catch(e) {}
                    }

                    try {
                        var els = document.querySelectorAll(c.selector);
                        els.forEach(function(el) {
                            // Sadece direkt text node'lari degistir
                            if (c.old_text && el.childNodes.length > 0) {
                                for (var i = 0; i < el.childNodes.length; i++) {
                                    var node = el.childNodes[i];
                                    if (node.nodeType === 3 && node.textContent.trim() === c.old_text.trim()) {
                                        node.textContent = c.new_text;
                                        return;
                                    }
                                }
                                // Fallback: innerText eslesme
                                if (el.innerText.trim() === c.old_text.trim()) {
                                    el.innerText = c.new_text;
                                }
                            } else {
                                el.innerText = c.new_text;
                            }
                        });
                    } catch(e) {}
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', applyChanges);
            } else {
                applyChanges();
            }
        })();
        </script>
        <?php
    }

    public function inject_css()
    {
        $css = get_option('webyaz_custom_css', '');
        if (empty($css)) return;
        echo "\n<style id=\"webyaz-custom-css\">\n" . $css . "\n</style>\n";
    }

    // -- MOBIL CSS --
    public function inject_mobile_css()
    {
        $tablet = get_option('webyaz_tablet_css', '');
        $mobile = get_option('webyaz_mobile_css', '');
        $css = '';
        if (!empty($tablet)) {
            $css .= "\n@media (max-width: 991px) {\n" . $tablet . "\n}\n";
        }
        if (!empty($mobile)) {
            $css .= "\n@media (max-width: 549px) {\n" . $mobile . "\n}\n";
        }
        if (!empty($css)) {
            echo "<style id=\"webyaz-mobile-css\">" . $css . "</style>\n";
        }
    }

    // -- SAYFA BAZLI CSS --
    public function inject_page_css()
    {
        $page_css = get_option('webyaz_page_css', array());
        if (empty($page_css)) return;

        $type = '';
        if (function_exists('is_front_page') && is_front_page()) $type = 'home';
        elseif (function_exists('is_shop') && is_shop()) $type = 'shop';
        elseif (function_exists('is_product') && is_product()) $type = 'product';
        elseif (function_exists('is_product_category') && is_product_category()) $type = 'category';
        elseif (function_exists('is_cart') && is_cart()) $type = 'cart';
        elseif (function_exists('is_checkout') && is_checkout()) $type = 'checkout';
        elseif (function_exists('is_account_page') && is_account_page()) $type = 'account';
        elseif (is_single()) $type = 'post';
        elseif (is_page()) $type = 'page';

        if (!empty($type) && !empty($page_css[$type])) {
            echo "\n<style id=\"webyaz-page-css\">\n" . $page_css[$type] . "\n</style>\n";
        }
    }

    // -- GOOGLE FONTS --
    public function inject_google_fonts()
    {
        $fonts = get_option('webyaz_google_fonts', array());
        if (empty($fonts) || empty($fonts['font'])) return;

        $font = $fonts['font'];
        $target = $fonts['target'] ?? 'body';
        $weight = $fonts['weight'] ?? '400';

        $family = str_replace(' ', '+', $font);
        echo "<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n";
        echo "<link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n";
        echo "<link href=\"https://fonts.googleapis.com/css2?family={$family}:wght@300;400;500;600;700;900&display=swap\" rel=\"stylesheet\">\n";
        echo "<style id=\"webyaz-google-font\">{$target} { font-family: '{$font}', sans-serif !important; font-weight: {$weight} !important; }</style>\n";
    }

    // -- GECE MODU --
    public function inject_dark_mode()
    {
        $dm = get_option('webyaz_dark_mode', array());
        if (empty($dm['enabled'])) return;

        $primary = '#446084';
        if (class_exists('Webyaz_Colors')) {
            $colors = Webyaz_Colors::get_theme_colors();
            $primary = $colors['primary'];
        }

        $pos = $dm['position'] ?? 'bottom-right';
        $positions = array(
            'bottom-right' => 'bottom:20px;right:20px;',
            'bottom-left'  => 'bottom:20px;left:20px;',
            'top-right'    => 'top:80px;right:20px;',
            'top-left'     => 'top:80px;left:20px;',
        );
        $posCSS = $positions[$pos] ?? $positions['bottom-right'];
        ?>
        <style id="webyaz-dark-mode-css">
        body.wz-dark-mode { background: #1a1a2e !important; color: #e0e0e0 !important; }
        body.wz-dark-mode .header-wrapper, body.wz-dark-mode header,
        body.wz-dark-mode .header-main, body.wz-dark-mode .nav-dropdown { background: #16213e !important; }
        body.wz-dark-mode .product-small, body.wz-dark-mode .col-inner,
        body.wz-dark-mode .panel, body.wz-dark-mode .box, body.wz-dark-mode .card,
        body.wz-dark-mode .widget, body.wz-dark-mode aside .widget,
        body.wz-dark-mode .woocommerce-tabs, body.wz-dark-mode .banner-inner { background: #222244 !important; border-color: #333366 !important; }
        body.wz-dark-mode footer, body.wz-dark-mode .footer-wrapper { background: #0f3460 !important; }
        body.wz-dark-mode img { opacity: 0.92; }
        body.wz-dark-mode a, body.wz-dark-mode .product-title, body.wz-dark-mode h1,
        body.wz-dark-mode h2, body.wz-dark-mode h3, body.wz-dark-mode h4 { color: #e0e0e0 !important; }
        body.wz-dark-mode input, body.wz-dark-mode textarea, body.wz-dark-mode select {
            background: #2a2a4a !important; color: #e0e0e0 !important; border-color: #444477 !important;
        }
        body.wz-dark-mode .price, body.wz-dark-mode .woocommerce-Price-amount { color: #a6e3a1 !important; }
        #wzDarkToggle { position:fixed;<?php echo $posCSS; ?>z-index:99999;width:48px;height:48px;border-radius:50%;border:none;cursor:pointer;font-size:22px;box-shadow:0 4px 15px rgba(0,0,0,0.3);transition:all .3s;display:flex;align-items:center;justify-content:center;background:<?php echo $primary; ?>;color:#fff; }
        #wzDarkToggle:hover { transform:scale(1.1); }
        </style>
        <button id="wzDarkToggle" title="Gece Modu">🌙</button>
        <script>
        (function(){
            var btn = document.getElementById('wzDarkToggle');
            var isDark = localStorage.getItem('wz_dark') === '1';
            if (isDark) { document.body.classList.add('wz-dark-mode'); btn.textContent = '☀️'; }
            btn.addEventListener('click', function() {
                document.body.classList.toggle('wz-dark-mode');
                var on = document.body.classList.contains('wz-dark-mode');
                localStorage.setItem('wz_dark', on ? '1' : '0');
                btn.textContent = on ? '☀️' : '🌙';
            });
        })();
        </script>
        <?php
    }

    // -- ELEMENT GIZLEYICI --
    public function inject_hidden_elements()
    {
        $hidden = get_option('webyaz_hidden_elements', array());
        if (empty($hidden)) return;
        $css = '';
        foreach ($hidden as $h) {
            $css .= $h['selector'] . " { display: none !important; }\n";
        }
        echo "<style id=\"webyaz-hidden-elements\">\n" . $css . "</style>\n";
    }

    public function ajax_hide_element()
    {
        check_ajax_referer('webyaz_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Yetki yok');

        $selector = sanitize_text_field($_POST['selector'] ?? '');
        if (empty($selector)) wp_send_json_error('Selector bos');

        $hidden = get_option('webyaz_hidden_elements', array());
        $hidden[] = array(
            'selector' => $selector,
            'page_url' => esc_url_raw($_POST['page_url'] ?? ''),
            'date'     => current_time('Y-m-d H:i'),
        );
        update_option('webyaz_hidden_elements', $hidden);
        wp_send_json_success(array('message' => 'Element gizlendi!'));
    }

    public function ajax_delete_hidden()
    {
        check_ajax_referer('webyaz_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Yetki yok');

        $index = intval($_POST['index'] ?? -1);
        $hidden = get_option('webyaz_hidden_elements', array());
        if ($index < 0 || $index >= count($hidden)) wp_send_json_error('Gecersiz');

        array_splice($hidden, $index, 1);
        update_option('webyaz_hidden_elements', $hidden);
        wp_send_json_success(array('message' => 'Geri alindi!'));
    }

    // -- HANDLE EXTRAS (Mobile CSS, Page CSS, Font, Dark Mode) --
    public function handle_extras()
    {
        if (!current_user_can('manage_options')) return;

        // Mobil CSS kaydet
        if (isset($_POST['webyaz_save_mobile_css']) && wp_verify_nonce($_POST['_wpnonce_mobile'], 'webyaz_mobile_css')) {
            update_option('webyaz_tablet_css', wp_strip_all_tags($_POST['webyaz_tablet_css'] ?? ''));
            update_option('webyaz_mobile_css', wp_strip_all_tags($_POST['webyaz_mobile_css'] ?? ''));
            wp_redirect(admin_url('admin.php?page=webyaz-custom-css&saved=1'));
            exit;
        }

        // Sayfa Bazli CSS kaydet
        if (isset($_POST['webyaz_save_page_css']) && wp_verify_nonce($_POST['_wpnonce_page_css'], 'webyaz_page_css')) {
            $types = array('home','shop','product','category','cart','checkout','account','post','page');
            $data = array();
            foreach ($types as $t) {
                $v = wp_strip_all_tags($_POST['wz_pagecss_' . $t] ?? '');
                if (!empty($v)) $data[$t] = $v;
            }
            update_option('webyaz_page_css', $data);
            wp_redirect(admin_url('admin.php?page=webyaz-custom-css&saved=1'));
            exit;
        }

        // Google Fonts kaydet
        if (isset($_POST['webyaz_save_fonts']) && wp_verify_nonce($_POST['_wpnonce_fonts'], 'webyaz_fonts')) {
            $fonts = array(
                'font'   => sanitize_text_field($_POST['wz_font_family'] ?? ''),
                'target' => sanitize_text_field($_POST['wz_font_target'] ?? 'body'),
                'weight' => sanitize_text_field($_POST['wz_font_weight'] ?? '400'),
            );
            update_option('webyaz_google_fonts', $fonts);
            wp_redirect(admin_url('admin.php?page=webyaz-custom-css&saved=1'));
            exit;
        }

        // Dark Mode kaydet
        if (isset($_POST['webyaz_save_dark_mode']) && wp_verify_nonce($_POST['_wpnonce_dark'], 'webyaz_dark_mode')) {
            $dm = array(
                'enabled'  => !empty($_POST['wz_dark_enabled']) ? '1' : '0',
                'position' => sanitize_text_field($_POST['wz_dark_position'] ?? 'bottom-right'),
            );
            update_option('webyaz_dark_mode', $dm);
            wp_redirect(admin_url('admin.php?page=webyaz-custom-css&saved=1'));
            exit;
        }

        // CSS Import
        if (isset($_POST['webyaz_import_css']) && wp_verify_nonce($_POST['_wpnonce_import'], 'webyaz_import_css')) {
            if (!empty($_FILES['wz_import_file']['tmp_name'])) {
                $json = file_get_contents($_FILES['wz_import_file']['tmp_name']);
                $data = json_decode($json, true);
                if ($data && is_array($data)) {
                    if (isset($data['css'])) update_option('webyaz_custom_css', wp_strip_all_tags($data['css']));
                    if (isset($data['mobile_css'])) update_option('webyaz_mobile_css', wp_strip_all_tags($data['mobile_css']));
                    if (isset($data['tablet_css'])) update_option('webyaz_tablet_css', wp_strip_all_tags($data['tablet_css']));
                    if (isset($data['presets'])) update_option('webyaz_active_presets', $data['presets']);
                    if (isset($data['page_css'])) update_option('webyaz_page_css', $data['page_css']);
                    if (isset($data['fonts'])) update_option('webyaz_google_fonts', $data['fonts']);
                    if (isset($data['dark_mode'])) update_option('webyaz_dark_mode', $data['dark_mode']);
                    if (isset($data['hidden'])) update_option('webyaz_hidden_elements', $data['hidden']);
                    if (isset($data['text_changes'])) update_option('webyaz_text_changes', $data['text_changes']);
                }
            }
            wp_redirect(admin_url('admin.php?page=webyaz-custom-css&saved=1'));
            exit;
        }
    }

    // -- CSS GECMISI: handle_save'de history sakla --
    public function handle_save()
    {
        if (!isset($_POST['webyaz_save_css'])) return;
        if (!wp_verify_nonce($_POST['_wpnonce_css'], 'webyaz_custom_css')) return;
        if (!current_user_can('manage_options')) return;

        // Mevcut CSS'i gecmise kaydet
        $old_css = get_option('webyaz_custom_css', '');
        if (!empty($old_css)) {
            $history = get_option('webyaz_css_history', array());
            array_unshift($history, array(
                'css'  => $old_css,
                'date' => current_time('Y-m-d H:i'),
                'size' => strlen($old_css),
            ));
            // En fazla 10 versiyon sakla
            $history = array_slice($history, 0, 10);
            update_option('webyaz_css_history', $history);
        }

        $css = isset($_POST['webyaz_custom_css']) ? wp_strip_all_tags($_POST['webyaz_custom_css']) : '';
        update_option('webyaz_custom_css', $css);

        wp_redirect(admin_url('admin.php?page=webyaz-custom-css&saved=1'));
        exit;
    }

    // CSS Gecmisinden geri yukle
    public function ajax_restore_css()
    {
        check_ajax_referer('webyaz_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Yetki yok');

        $index = intval($_POST['index'] ?? -1);
        $history = get_option('webyaz_css_history', array());
        if ($index < 0 || $index >= count($history)) wp_send_json_error('Gecersiz');

        // Mevcut CSS'i de gecmise ekle
        $old_css = get_option('webyaz_custom_css', '');
        if (!empty($old_css)) {
            array_unshift($history, array('css' => $old_css, 'date' => current_time('Y-m-d H:i'), 'size' => strlen($old_css)));
            $history = array_slice($history, 0, 10);
            update_option('webyaz_css_history', $history);
        }

        update_option('webyaz_custom_css', $history[$index]['css']);
        wp_send_json_success(array('message' => 'CSS geri yuklendi!'));
    }

    // CSS Export
    public function ajax_export_css()
    {
        check_ajax_referer('webyaz_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Yetki yok');

        $data = array(
            'css'          => get_option('webyaz_custom_css', ''),
            'mobile_css'   => get_option('webyaz_mobile_css', ''),
            'tablet_css'   => get_option('webyaz_tablet_css', ''),
            'presets'      => get_option('webyaz_active_presets', array()),
            'page_css'     => get_option('webyaz_page_css', array()),
            'fonts'        => get_option('webyaz_google_fonts', array()),
            'dark_mode'    => get_option('webyaz_dark_mode', array()),
            'hidden'       => get_option('webyaz_hidden_elements', array()),
            'text_changes' => get_option('webyaz_text_changes', array()),
            'exported_at'  => current_time('Y-m-d H:i'),
        );
        wp_send_json_success($data);
    }

    // CSS Import (AJAX - not used, form-based instead)
    public function ajax_import_css()
    {
        check_ajax_referer('webyaz_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Yetki yok');
        wp_send_json_error('Form-based import kullanin');
    }

    // Gorsel duzenleyici - sadece adminler icin frontend'de
    public function visual_editor()
    {
        if (is_admin()) return;
        if (!current_user_can('manage_options')) return;

        $primary = '#446084';
        $secondary = '#d26e4b';
        if (class_exists('Webyaz_Colors')) {
            $colors = Webyaz_Colors::get_theme_colors();
            $primary = $colors['primary'];
            $secondary = $colors['secondary'];
        }
?>
        <div id="webyazCssMenu" style="display:none;position:fixed;z-index:999999;background:#1e1e2e;border:1px solid #313244;border-radius:10px;box-shadow:0 8px 32px rgba(0,0,0,0.4);min-width:200px;font-family:'Segoe UI',Roboto,sans-serif;overflow:hidden;">
            <div style="padding:10px 14px;border-bottom:1px solid #313244;display:flex;align-items:center;gap:8px;">
                <span style="background:<?php echo $secondary; ?>;color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;">CSS</span>
                <span id="webyazMenuSelector" style="color:#89b4fa;font-size:12px;font-family:monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px;"></span>
            </div>
            <div id="webyazMenuItems" style="padding:4px 0;">
                <div class="wz-ctx-item" data-action="edit" style="padding:9px 14px;color:#cdd6f4;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:10px;transition:background .15s;">
                    <span style="font-size:16px;">🎨</span> Stili Duzenle
                </div>
                <div class="wz-ctx-item" data-action="text" style="padding:9px 14px;color:#cdd6f4;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:10px;transition:background .15s;">
                    <span style="font-size:16px;">✏️</span> Yaziyi Degistir
                </div>
                <div class="wz-ctx-item" data-action="copy" style="padding:9px 14px;color:#cdd6f4;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:10px;transition:background .15s;">
                    <span style="font-size:16px;">📋</span> Selector Kopyala
                </div>
                <div class="wz-ctx-item" data-action="inspect" style="padding:9px 14px;color:#cdd6f4;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:10px;transition:background .15s;">
                    <span style="font-size:16px;">🔍</span> Bilgileri Gor
                </div>
                <div style="border-top:1px solid #313244;margin:2px 0;"></div>
                <div class="wz-ctx-item" data-action="hide" style="padding:9px 14px;color:#f38ba8;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:10px;transition:background .15s;">
                    <span style="font-size:16px;">🙈</span> Bu Elemani Gizle
                </div>
            </div>
        </div>

        <!-- CSS Duzenleyici Modal -->
        <div id="webyazCssModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:999998;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);">
            <div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:520px;max-width:92vw;background:#1e1e2e;border:1px solid #313244;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,0.5);font-family:'Segoe UI',Roboto,sans-serif;">
                <!-- Header -->
                <div style="padding:18px 22px;border-bottom:1px solid #313244;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <div style="color:#cdd6f4;font-size:16px;font-weight:700;">Stili Duzenle</div>
                        <div id="webyazModalSelector" style="color:#89b4fa;font-size:12px;font-family:monospace;margin-top:4px;"></div>
                    </div>
                    <button id="webyazModalClose" type="button" style="background:#313244;border:none;color:#6c7086;width:32px;height:32px;border-radius:8px;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;">✕</button>
                </div>

                <!-- Hazir Ozellikler -->
                <div style="padding:14px 22px;border-bottom:1px solid #313244;">
                    <div style="color:#6c7086;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Hizli Ozellikler</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <label style="color:#a6adc8;font-size:12px;min-width:55px;">Renk</label>
                            <input type="color" id="wzPropColor" value="#ffffff" style="width:32px;height:26px;border:1px solid #313244;border-radius:4px;background:#1e1e2e;cursor:pointer;">
                            <button type="button" class="wz-prop-btn" data-prop="color" style="background:#313244;border:none;color:#89b4fa;padding:3px 10px;border-radius:4px;font-size:11px;cursor:pointer;">Uygula</button>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <label style="color:#a6adc8;font-size:12px;min-width:55px;">Arkaplan</label>
                            <input type="color" id="wzPropBg" value="#000000" style="width:32px;height:26px;border:1px solid #313244;border-radius:4px;background:#1e1e2e;cursor:pointer;">
                            <button type="button" class="wz-prop-btn" data-prop="background" style="background:#313244;border:none;color:#89b4fa;padding:3px 10px;border-radius:4px;font-size:11px;cursor:pointer;">Uygula</button>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <label style="color:#a6adc8;font-size:12px;min-width:55px;">Font</label>
                            <input type="number" id="wzPropFontSize" value="16" min="8" max="72" style="width:50px;background:#313244;border:1px solid #45475a;color:#cdd6f4;border-radius:4px;padding:3px 6px;font-size:12px;text-align:center;">
                            <span style="color:#6c7086;font-size:11px;">px</span>
                            <button type="button" class="wz-prop-btn" data-prop="font-size" style="background:#313244;border:none;color:#89b4fa;padding:3px 10px;border-radius:4px;font-size:11px;cursor:pointer;">Uygula</button>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <label style="color:#a6adc8;font-size:12px;min-width:55px;">Radius</label>
                            <input type="number" id="wzPropRadius" value="0" min="0" max="100" style="width:50px;background:#313244;border:1px solid #45475a;color:#cdd6f4;border-radius:4px;padding:3px 6px;font-size:12px;text-align:center;">
                            <span style="color:#6c7086;font-size:11px;">px</span>
                            <button type="button" class="wz-prop-btn" data-prop="border-radius" style="background:#313244;border:none;color:#89b4fa;padding:3px 10px;border-radius:4px;font-size:11px;cursor:pointer;">Uygula</button>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <label style="color:#a6adc8;font-size:12px;min-width:55px;">Padding</label>
                            <input type="number" id="wzPropPadding" value="10" min="0" max="200" style="width:50px;background:#313244;border:1px solid #45475a;color:#cdd6f4;border-radius:4px;padding:3px 6px;font-size:12px;text-align:center;">
                            <span style="color:#6c7086;font-size:11px;">px</span>
                            <button type="button" class="wz-prop-btn" data-prop="padding" style="background:#313244;border:none;color:#89b4fa;padding:3px 10px;border-radius:4px;font-size:11px;cursor:pointer;">Uygula</button>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <label style="color:#a6adc8;font-size:12px;min-width:55px;">Gizle</label>
                            <button type="button" class="wz-prop-btn" data-prop="display-none" style="background:#f38ba8;border:none;color:#1e1e2e;padding:3px 10px;border-radius:4px;font-size:11px;cursor:pointer;font-weight:600;">Gizle</button>
                        </div>
                    </div>
                </div>

                <!-- CSS Editoru -->
                <div style="padding:14px 22px;">
                    <div style="color:#6c7086;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Ozel CSS Kodu</div>
                    <textarea id="webyazVisualCss" spellcheck="false" placeholder="    color: red !important;&#10;    font-size: 18px;&#10;    background: #333;" style="width:100%;height:120px;background:#181825;color:#cdd6f4;border:1px solid #313244;border-radius:8px;padding:12px;font-family:'Fira Code','Cascadia Code','Consolas',monospace;font-size:13px;line-height:1.6;resize:vertical;outline:none;box-sizing:border-box;tab-size:4;-moz-tab-size:4;"></textarea>
                </div>

                <!-- Onizleme + Bilgi -->
                <div id="webyazPreviewInfo" style="padding:0 22px 14px;display:none;">
                    <div style="background:#181825;border:1px solid #313244;border-radius:8px;padding:10px 14px;">
                        <div style="color:#a6e3a1;font-size:12px;font-weight:600;margin-bottom:4px;">✓ Onizleme Aktif</div>
                        <div id="webyazPreviewText" style="color:#6c7086;font-size:11px;"></div>
                    </div>
                </div>

                <!-- Butonlar -->
                <div style="padding:14px 22px;border-top:1px solid #313244;display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" id="webyazPreviewBtn" style="background:#313244;border:1px solid #45475a;color:#cdd6f4;padding:9px 20px;border-radius:8px;font-size:13px;cursor:pointer;font-weight:600;">Onizle</button>
                    <button type="button" id="webyazSaveBtn" style="background:<?php echo $primary; ?>;border:none;color:#fff;padding:9px 24px;border-radius:8px;font-size:13px;cursor:pointer;font-weight:700;">Kaydet</button>
                </div>

                <!-- Sonuc -->
                <div id="webyazSaveResult" style="display:none;padding:0 22px 14px;">
                    <div style="background:#a6e3a1;color:#1e1e2e;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:600;text-align:center;"></div>
                </div>
            </div>
        </div>

        <!-- Yazi Degistirme Modali -->
        <div id="webyazTextModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:999998;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);">
            <div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:480px;max-width:92vw;background:#1e1e2e;border:1px solid #313244;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,0.5);font-family:'Segoe UI',Roboto,sans-serif;">
                <!-- Header -->
                <div style="padding:18px 22px;border-bottom:1px solid #313244;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <div style="color:#cdd6f4;font-size:16px;font-weight:700;">✏️ Yaziyi Degistir</div>
                        <div id="webyazTextSelector" style="color:#89b4fa;font-size:12px;font-family:monospace;margin-top:4px;"></div>
                    </div>
                    <button id="webyazTextClose" type="button" style="background:#313244;border:none;color:#6c7086;width:32px;height:32px;border-radius:8px;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;">✕</button>
                </div>

                <!-- Icerik -->
                <div style="padding:18px 22px;">
                    <div style="margin-bottom:14px;">
                        <label style="color:#a6adc8;font-size:12px;font-weight:600;display:block;margin-bottom:6px;">Mevcut Yazi</label>
                        <div id="webyazOldText" style="background:#181825;border:1px solid #313244;border-radius:8px;padding:12px;color:#6c7086;font-size:14px;min-height:40px;max-height:100px;overflow-y:auto;word-break:break-word;"></div>
                    </div>
                    <div style="margin-bottom:14px;">
                        <label style="color:#a6adc8;font-size:12px;font-weight:600;display:block;margin-bottom:6px;">Yeni Yazi</label>
                        <textarea id="webyazNewText" rows="3" style="width:100%;background:#181825;color:#cdd6f4;border:1px solid #313244;border-radius:8px;padding:12px;font-size:14px;resize:vertical;outline:none;box-sizing:border-box;font-family:inherit;"></textarea>
                    </div>
                    <div style="margin-bottom:6px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" id="webyazTextAllPages" checked style="accent-color:<?php echo $primary; ?>;width:16px;height:16px;">
                            <span style="color:#a6adc8;font-size:13px;">Tum sayfalarda uygula</span>
                        </label>
                    </div>
                </div>

                <!-- Butonlar -->
                <div style="padding:14px 22px;border-top:1px solid #313244;display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" id="webyazTextPreviewBtn" style="background:#313244;border:1px solid #45475a;color:#cdd6f4;padding:9px 20px;border-radius:8px;font-size:13px;cursor:pointer;font-weight:600;">Onizle</button>
                    <button type="button" id="webyazTextSaveBtn" style="background:<?php echo $primary; ?>;border:none;color:#fff;padding:9px 24px;border-radius:8px;font-size:13px;cursor:pointer;font-weight:700;">Kaydet</button>
                </div>

                <!-- Sonuc -->
                <div id="webyazTextResult" style="display:none;padding:0 22px 14px;">
                    <div style="background:#a6e3a1;color:#1e1e2e;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:600;text-align:center;"></div>
                </div>
            </div>
        </div>

        <!-- Eleman Vurgulama -->
        <div id="webyazHighlight" style="display:none;position:fixed;z-index:999990;border:2px solid <?php echo $secondary; ?>;background:rgba(210,110,75,0.08);pointer-events:none;border-radius:3px;transition:all .1s ease;"></div>
        <div id="webyazHighlightLabel" style="display:none;position:fixed;z-index:999991;background:<?php echo $secondary; ?>;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:0 0 6px 6px;font-family:monospace;pointer-events:none;white-space:nowrap;"></div>

        <style>
            .wz-ctx-item:hover {
                background: #313244 !important;
            }

            #webyazCssMenu *,
            #webyazCssModal *,
            #webyazHighlightLabel {
                box-sizing: border-box;
            }

            .wz-prop-btn:hover {
                opacity: .8;
            }

            body.wz-editor-active *:hover {
                outline: 2px dashed rgba(210, 110, 75, 0.5) !important;
                outline-offset: 2px;
                cursor: crosshair !important;
            }

            body.wz-editor-active #webyazCssMenu,
            body.wz-editor-active #webyazCssMenu *,
            body.wz-editor-active #webyazCssModal,
            body.wz-editor-active #webyazCssModal *,
            body.wz-editor-active #webyazHighlight,
            body.wz-editor-active #webyazHighlightLabel {
                outline: none !important;
                cursor: default !important;
            }
        </style>

        <script>
            (function() {
                var menu = document.getElementById('webyazCssMenu');
                var modal = document.getElementById('webyazCssModal');
                var highlight = document.getElementById('webyazHighlight');
                var highlightLabel = document.getElementById('webyazHighlightLabel');
                var currentEl = null;
                var editorActive = false;

                // Elemandan selector olustur
                function getSelector(el) {
                    if (el.id) return '#' + el.id;
                    var path = [];
                    while (el && el.nodeType === 1) {
                        if (el.id) {
                            path.unshift('#' + el.id);
                            break;
                        }
                        var classes = Array.from(el.classList).filter(function(c) {
                            return c !== 'wz-editor-active' && !c.startsWith('wz-');
                        });
                        if (classes.length > 0) {
                            path.unshift(el.tagName.toLowerCase() + '.' + classes.join('.'));
                            break;
                        }
                        path.unshift(el.tagName.toLowerCase());
                        el = el.parentElement;
                    }
                    return path.join(' > ');
                }

                // Webyaz elemani mi kontrol
                function isWebyazEl(el) {
                    return el.closest('#webyazCssMenu') || el.closest('#webyazCssModal') ||
                        el.closest('#webyazHighlight') || el.closest('#webyazHighlightLabel') ||
                        el.id === 'wpadminbar';
                }

                // Highlight goster
                function showHighlight(el) {
                    if (!el || isWebyazEl(el)) return;
                    var rect = el.getBoundingClientRect();
                    highlight.style.display = 'block';
                    highlight.style.top = rect.top + 'px';
                    highlight.style.left = rect.left + 'px';
                    highlight.style.width = rect.width + 'px';
                    highlight.style.height = rect.height + 'px';
                    highlightLabel.style.display = 'block';
                    highlightLabel.style.top = rect.top + 'px';
                    highlightLabel.style.left = rect.left + 'px';
                    highlightLabel.textContent = getSelector(el);
                }

                function hideHighlight() {
                    highlight.style.display = 'none';
                    highlightLabel.style.display = 'none';
                }

                // Hover ile vurgulama
                document.addEventListener('mousemove', function(e) {
                    if (modal.style.display !== 'none') return;
                    if (menu.style.display !== 'none') return;
                    var el = document.elementFromPoint(e.clientX, e.clientY);
                    if (el && !isWebyazEl(el)) {
                        showHighlight(el);
                    } else {
                        hideHighlight();
                    }
                });

                // Sag tik - context menu
                document.addEventListener('contextmenu', function(e) {
                    var el = e.target;
                    if (isWebyazEl(el)) return;

                    e.preventDefault();
                    currentEl = el;

                    var sel = getSelector(el);
                    document.getElementById('webyazMenuSelector').textContent = sel;

                    // Pozisyon
                    var x = e.clientX,
                        y = e.clientY;
                    menu.style.display = 'block';
                    var mw = menu.offsetWidth,
                        mh = menu.offsetHeight;
                    if (x + mw > window.innerWidth) x = window.innerWidth - mw - 10;
                    if (y + mh > window.innerHeight) y = window.innerHeight - mh - 10;
                    menu.style.left = x + 'px';
                    menu.style.top = y + 'px';
                });

                // Tikla kapanis
                document.addEventListener('click', function(e) {
                    if (!menu.contains(e.target)) {
                        menu.style.display = 'none';
                    }
                });

                // Menu aksiyon
                var textModal = document.getElementById('webyazTextModal');
                document.querySelectorAll('.wz-ctx-item').forEach(function(item) {
                    item.addEventListener('click', function() {
                        var action = this.dataset.action;
                        menu.style.display = 'none';

                        if (action === 'edit') {
                            openModal();
                        } else if (action === 'text') {
                            openTextModal();
                        } else if (action === 'copy') {
                            var sel = getSelector(currentEl);
                            navigator.clipboard.writeText(sel).then(function() {
                                showToast('Selector kopyalandi: ' + sel);
                            });
                        } else if (action === 'inspect') {
                            showInfo();
                        } else if (action === 'hide') {
                            if (!confirm('Bu eleman gizlenecek. Emin misiniz?')) return;
                            var sel = getSelector(currentEl);
                            currentEl.style.display = 'none';
                            var fd = new FormData();
                            fd.append('action', 'webyaz_hide_element');
                            fd.append('nonce', webyaz_ajax.nonce);
                            fd.append('selector', sel);
                            fd.append('page_url', window.location.href);
                            fetch(webyaz_ajax.ajax_url, { method: 'POST', body: fd })
                                .then(function(r) { return r.json(); })
                                .then(function(data) {
                                    showToast(data.success ? 'Element gizlendi!' : 'Hata!');
                                });
                        }
                    });
                });

                // -- YAZI DEGISTIRME --
                function openTextModal() {
                    if (!currentEl) return;
                    var sel = getSelector(currentEl);
                    document.getElementById('webyazTextSelector').textContent = sel;
                    var text = currentEl.innerText || '';
                    document.getElementById('webyazOldText').textContent = text;
                    document.getElementById('webyazNewText').value = text;
                    document.getElementById('webyazTextResult').style.display = 'none';
                    document.getElementById('webyazTextAllPages').checked = true;
                    textModal.style.display = 'block';
                    hideHighlight();
                    document.getElementById('webyazNewText').focus();
                }

                document.getElementById('webyazTextClose').addEventListener('click', function() {
                    textModal.style.display = 'none';
                });
                textModal.addEventListener('click', function(e) {
                    if (e.target === textModal) textModal.style.display = 'none';
                });

                // Onizle
                document.getElementById('webyazTextPreviewBtn').addEventListener('click', function() {
                    if (!currentEl) return;
                    var newText = document.getElementById('webyazNewText').value;
                    currentEl.innerText = newText;
                    showToast('Onizleme uygulandi (henuz kaydedilmedi)');
                });

                // Kaydet
                document.getElementById('webyazTextSaveBtn').addEventListener('click', function() {
                    if (!currentEl) return;
                    var sel = getSelector(currentEl);
                    var oldText = document.getElementById('webyazOldText').textContent;
                    var newText = document.getElementById('webyazNewText').value;
                    var allPages = document.getElementById('webyazTextAllPages').checked;

                    if (newText === oldText) {
                        showToast('Yazi degismedi');
                        return;
                    }

                    var btn = this;
                    btn.disabled = true;
                    btn.textContent = 'Kaydediliyor...';

                    var fd = new FormData();
                    fd.append('action', 'webyaz_save_text_change');
                    fd.append('nonce', webyaz_ajax.nonce);
                    fd.append('selector', sel);
                    fd.append('old_text', oldText);
                    fd.append('new_text', newText);
                    fd.append('page_url', allPages ? '' : window.location.href);

                    fetch(webyaz_ajax.ajax_url, { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            btn.disabled = false;
                            btn.textContent = 'Kaydet';
                            if (data.success) {
                                // Hemen uygula
                                currentEl.innerText = newText;
                                var res = document.getElementById('webyazTextResult');
                                res.style.display = 'block';
                                res.querySelector('div').textContent = '✓ Yazi degisikligi kaydedildi!';
                                setTimeout(function() {
                                    textModal.style.display = 'none';
                                }, 1500);
                            } else {
                                alert('Hata: ' + (data.data || 'Bilinmeyen hata'));
                            }
                        });
                });

                // Modal ac
                function openModal() {
                    if (!currentEl) return;
                    var sel = getSelector(currentEl);
                    document.getElementById('webyazModalSelector').textContent = sel;
                    document.getElementById('webyazVisualCss').value = '';
                    document.getElementById('webyazSaveResult').style.display = 'none';
                    document.getElementById('webyazPreviewInfo').style.display = 'none';

                    // Mevcut stilleri al
                    var cs = window.getComputedStyle(currentEl);
                    document.getElementById('wzPropColor').value = rgbToHex(cs.color);
                    document.getElementById('wzPropBg').value = rgbToHex(cs.backgroundColor);
                    document.getElementById('wzPropFontSize').value = parseInt(cs.fontSize) || 16;
                    document.getElementById('wzPropRadius').value = parseInt(cs.borderRadius) || 0;
                    document.getElementById('wzPropPadding').value = parseInt(cs.padding) || 0;

                    modal.style.display = 'block';
                    hideHighlight();
                }

                // RGB hex cevir
                function rgbToHex(rgb) {
                    if (!rgb || rgb === 'transparent' || rgb === 'rgba(0, 0, 0, 0)') return '#000000';
                    var match = rgb.match(/\d+/g);
                    if (!match || match.length < 3) return '#000000';
                    return '#' + match.slice(0, 3).map(function(x) {
                        return ('0' + parseInt(x).toString(16)).slice(-2);
                    }).join('');
                }

                // Modal kapat
                document.getElementById('webyazModalClose').addEventListener('click', function() {
                    modal.style.display = 'none';
                });
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) modal.style.display = 'none';
                });

                // Tab destegi
                document.getElementById('webyazVisualCss').addEventListener('keydown', function(e) {
                    if (e.key === 'Tab') {
                        e.preventDefault();
                        var s = this.selectionStart,
                            end = this.selectionEnd;
                        this.value = this.value.substring(0, s) + '    ' + this.value.substring(end);
                        this.selectionStart = this.selectionEnd = s + 4;
                    }
                });

                // Hizli ozellik butonlari
                document.querySelectorAll('.wz-prop-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var prop = this.dataset.prop;
                        var textarea = document.getElementById('webyazVisualCss');
                        var line = '';

                        if (prop === 'color') {
                            line = '    color: ' + document.getElementById('wzPropColor').value + ' !important;';
                        } else if (prop === 'background') {
                            line = '    background: ' + document.getElementById('wzPropBg').value + ' !important;';
                        } else if (prop === 'font-size') {
                            line = '    font-size: ' + document.getElementById('wzPropFontSize').value + 'px !important;';
                        } else if (prop === 'border-radius') {
                            line = '    border-radius: ' + document.getElementById('wzPropRadius').value + 'px !important;';
                        } else if (prop === 'padding') {
                            line = '    padding: ' + document.getElementById('wzPropPadding').value + 'px !important;';
                        } else if (prop === 'display-none') {
                            line = '    display: none !important;';
                        }

                        if (textarea.value && !textarea.value.endsWith('\n')) textarea.value += '\n';
                        textarea.value += line;
                    });
                });

                // Onizleme
                document.getElementById('webyazPreviewBtn').addEventListener('click', function() {
                    if (!currentEl) return;
                    var css = document.getElementById('webyazVisualCss').value;
                    var lines = css.split('\n');
                    lines.forEach(function(line) {
                        line = line.trim().replace(/;$/, '').replace(/\s*!important\s*/, '');
                        var parts = line.split(':');
                        if (parts.length >= 2) {
                            var prop = parts[0].trim();
                            var val = parts.slice(1).join(':').trim();
                            try {
                                currentEl.style.setProperty(prop, val, 'important');
                            } catch (e) {}
                        }
                    });
                    document.getElementById('webyazPreviewInfo').style.display = 'block';
                    document.getElementById('webyazPreviewText').textContent = lines.length + ' ozellik onizlemede goruntulenyor';
                });

                // Kaydet
                document.getElementById('webyazSaveBtn').addEventListener('click', function() {
                    if (!currentEl) return;
                    var sel = getSelector(currentEl);
                    var css = document.getElementById('webyazVisualCss').value.trim();
                    if (!css) {
                        alert('CSS kodu giriniz');
                        return;
                    }

                    this.disabled = true;
                    this.textContent = 'Kaydediliyor...';

                    var fd = new FormData();
                    fd.append('action', 'webyaz_save_visual_css');
                    fd.append('nonce', webyaz_ajax.nonce);
                    fd.append('selector', sel);
                    fd.append('css', css);

                    var btn = this;
                    fetch(webyaz_ajax.ajax_url, {
                            method: 'POST',
                            body: fd
                        })
                        .then(function(r) {
                            return r.json();
                        })
                        .then(function(data) {
                            btn.disabled = false;
                            btn.textContent = 'Kaydet';
                            if (data.success) {
                                var res = document.getElementById('webyazSaveResult');
                                res.style.display = 'block';
                                res.querySelector('div').textContent = '✓ ' + sel + ' stili kaydedildi!';

                                // Sayfaya hemen style ekle
                                var style = document.getElementById('webyaz-custom-css');
                                if (style) {
                                    style.textContent += data.data.css;
                                } else {
                                    var s = document.createElement('style');
                                    s.id = 'webyaz-custom-css';
                                    s.textContent = data.data.css;
                                    document.head.appendChild(s);
                                }

                                setTimeout(function() {
                                    modal.style.display = 'none';
                                }, 1500);
                            } else {
                                alert('Hata: ' + (data.data || 'Bilinmeyen hata'));
                            }
                        });
                });

                // Bilgi goster
                function showInfo() {
                    if (!currentEl) return;
                    var cs = window.getComputedStyle(currentEl);
                    var rect = currentEl.getBoundingClientRect();
                    var info = 'Selector: ' + getSelector(currentEl) + '\n\n';
                    info += 'Tag: ' + currentEl.tagName.toLowerCase() + '\n';
                    info += 'Classes: ' + (currentEl.className || 'yok') + '\n';
                    info += 'ID: ' + (currentEl.id || 'yok') + '\n\n';
                    info += 'Boyut: ' + Math.round(rect.width) + 'x' + Math.round(rect.height) + 'px\n';
                    info += 'Font: ' + cs.fontSize + ' ' + cs.fontFamily.split(',')[0] + '\n';
                    info += 'Renk: ' + cs.color + '\n';
                    info += 'Arkaplan: ' + cs.backgroundColor + '\n';
                    info += 'Padding: ' + cs.padding + '\n';
                    info += 'Margin: ' + cs.margin;
                    alert(info);
                }

                // Toast mesaj
                function showToast(msg) {
                    var t = document.createElement('div');
                    t.textContent = msg;
                    t.style.cssText = 'position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#1e1e2e;color:#a6e3a1;padding:12px 24px;border-radius:10px;font-size:14px;font-weight:600;z-index:9999999;box-shadow:0 4px 20px rgba(0,0,0,0.3);border:1px solid #313244;font-family:Segoe UI,Roboto,sans-serif;';
                    document.body.appendChild(t);
                    setTimeout(function() {
                        t.remove();
                    }, 2500);
                }

                // Esc ile kapat
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        menu.style.display = 'none';
                        modal.style.display = 'none';
                        textModal.style.display = 'none';
                    }
                });
            })();
        </script>
    <?php
    }

    public function render_page()
    {
        $css = get_option('webyaz_custom_css', '');

        $primary = '#446084';
        $secondary = '#d26e4b';
        if (class_exists('Webyaz_Colors')) {
            $colors = Webyaz_Colors::get_theme_colors();
            $primary = $colors['primary'];
            $secondary = $colors['secondary'];
        }
    ?>
        <div class="webyaz-admin-wrap" style="max-width:900px;margin:20px 0;font-family:-apple-system,BlinkMacSystemFont,sans-serif;">

            <div style="background:linear-gradient(135deg,<?php echo $primary; ?>,<?php echo $secondary; ?>);color:#fff;padding:30px 35px;border-radius:12px;margin-bottom:25px;">
                <h1 style="margin:0 0 5px;font-size:26px;font-weight:700;">Ozel CSS</h1>
                <p style="margin:0;opacity:.85;font-size:14px;">Hazir sablonlari acin, gorsel duzenleyici ile ozelleyin veya manuel CSS yazin.</p>
            </div>

            <?php if (isset($_GET['saved'])): ?>
                <div style="background:#e6f9e6;color:#22863a;border:1px solid #b7e4c7;padding:12px 18px;border-radius:8px;margin-bottom:15px;font-size:14px;">
                    CSS kodlari basariyla kaydedildi!
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['presets_saved'])): ?>
                <div style="background:#e6f0ff;color:#1a56db;border:1px solid #b4d0fe;padding:12px 18px;border-radius:8px;margin-bottom:15px;font-size:14px;">
                    Hazir sablonlar guncellendi!
                </div>
            <?php endif; ?>

            <!-- HAZIR SABLONLAR -->
            <div style="margin-bottom:30px;">
                <h2 style="font-size:20px;font-weight:700;margin:0 0 5px;color:#1e1e2e;">Hazir Sablonlar</h2>
                <p style="color:#666;font-size:13px;margin:0 0 18px;">Actiginiz sablonlar otomatik olarak site renkleriyle uygulanir. Kodu bilmenize gerek yok!</p>
                <form method="post">
                    <?php wp_nonce_field('webyaz_css_presets', '_wpnonce_presets'); ?>
                    <?php
                    $presets = self::get_presets();
                    $active_presets = get_option('webyaz_active_presets', array());
                    ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <?php foreach ($presets as $key => $p):
                            $is_active = in_array($key, $active_presets);
                        ?>
                            <label style="display:flex;align-items:flex-start;gap:14px;background:<?php echo $is_active ? '#f0f7ff' : '#fff'; ?>;border:2px solid <?php echo $is_active ? $primary : '#e8e8e8'; ?>;border-radius:12px;padding:16px;cursor:pointer;transition:all .2s;">
                                <input type="checkbox" name="wz_preset_<?php echo $key; ?>" value="1" <?php checked($is_active); ?> style="margin-top:3px;accent-color:<?php echo $primary; ?>;width:18px;height:18px;flex-shrink:0;">
                                <div style="flex:1;min-width:0;">
                                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;">
                                        <span style="font-size:18px;"><?php echo $p['icon']; ?></span>
                                        <strong style="font-size:14px;color:#1e1e2e;"><?php echo $p['title']; ?></strong>
                                        <?php if ($is_active): ?>
                                            <span style="background:<?php echo $primary; ?>;color:#fff;font-size:9px;padding:2px 6px;border-radius:4px;font-weight:700;">AKTIF</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:12px;color:#666;line-height:1.5;"><?php echo $p['desc']; ?></div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:14px;">
                        <button type="submit" name="webyaz_save_presets" value="1" style="background:<?php echo $primary; ?>;color:#fff;border:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">
                            <span class="dashicons dashicons-yes-alt" style="vertical-align:middle;margin-right:6px;"></span>Sablonlari Kaydet
                        </button>
                    </div>
                </form>
            </div>

            <hr style="border:none;border-top:1px solid #e0e0e0;margin:30px 0;">

            <!-- GORSEL DUZENLEYICI BILGI -->
            <div style="background:linear-gradient(135deg,#1e1e2e,#181825);border:1px solid #313244;border-radius:10px;padding:20px 24px;margin-bottom:20px;display:flex;align-items:center;gap:18px;">
                <div style="background:<?php echo $secondary; ?>;width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;">🎨</div>
                <div>
                    <div style="color:#cdd6f4;font-size:15px;font-weight:700;margin-bottom:4px;">Gorsel Duzenleyici</div>
                    <div style="color:#6c7086;font-size:13px;line-height:1.6;">Sitenizi ziyaret edin ve degistirmek istediginiz bolgeye <strong style="color:#89b4fa;">sag tiklayin</strong>. Bir menu acilacak, "Stili Duzenle" diyerek o bolgenin CSS'ini degistirebilirsiniz. <em>(Sadece admin olarak giris yaptiysaniz gorunur)</em></div>
                </div>
            </div>

            <!-- YAZI DEGISIKLIKLERI -->
            <?php
            $text_changes = get_option('webyaz_text_changes', array());
            if (!empty($text_changes)):
            ?>
            <div style="margin-bottom:25px;">
                <h2 style="font-size:20px;font-weight:700;margin:0 0 5px;color:#1e1e2e;">Yazi Degisiklikleri</h2>
                <p style="color:#666;font-size:13px;margin:0 0 14px;">Gorsel duzenleyiciden kaydedilen yazi degisiklikleri. Silmek icin X butonuna tiklayin.</p>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;overflow:hidden;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="background:#f8f9fa;border-bottom:2px solid #e0e0e0;">
                                <th style="padding:12px 16px;text-align:left;font-weight:600;color:#333;">Selector</th>
                                <th style="padding:12px 16px;text-align:left;font-weight:600;color:#333;">Eski Yazi</th>
                                <th style="padding:12px 16px;text-align:left;font-weight:600;color:#333;">Yeni Yazi</th>
                                <th style="padding:12px 16px;text-align:left;font-weight:600;color:#333;">Sayfa</th>
                                <th style="padding:12px 16px;text-align:center;font-weight:600;color:#333;width:50px;">Sil</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($text_changes as $i => $tc): ?>
                            <tr id="wzTextRow_<?php echo $i; ?>" style="border-bottom:1px solid #eee;">
                                <td style="padding:10px 16px;font-family:monospace;color:#89b4fa;font-size:12px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr($tc['selector']); ?>"><?php echo esc_html($tc['selector']); ?></td>
                                <td style="padding:10px 16px;color:#999;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr($tc['old_text']); ?>"><?php echo esc_html(mb_strimwidth($tc['old_text'], 0, 40, '...')); ?></td>
                                <td style="padding:10px 16px;color:#333;font-weight:600;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr($tc['new_text']); ?>"><?php echo esc_html(mb_strimwidth($tc['new_text'], 0, 40, '...')); ?></td>
                                <td style="padding:10px 16px;color:#666;font-size:12px;"><?php echo $tc['page_url'] ? esc_html(wp_parse_url($tc['page_url'], PHP_URL_PATH)) : '<em style="color:#a6adc8;">Tum Sayfalar</em>'; ?></td>
                                <td style="padding:10px 16px;text-align:center;"><button type="button" onclick="wzDeleteText(<?php echo $i; ?>)" style="background:#fde8e8;color:#d32f2f;border:none;width:28px;height:28px;border-radius:6px;cursor:pointer;font-size:14px;" title="Sil">✕</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <script>
                function wzDeleteText(index) {
                    if (!confirm('Bu yazi degisikligi silinecek. Emin misiniz?')) return;
                    var fd = new FormData();
                    fd.append('action', 'webyaz_delete_text_change');
                    fd.append('nonce', '<?php echo wp_create_nonce('webyaz_nonce'); ?>');
                    fd.append('index', index);
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.success) {
                                var row = document.getElementById('wzTextRow_' + index);
                                if (row) row.style.display = 'none';
                            } else {
                                alert('Hata: ' + (data.data || ''));
                            }
                        });
                }
            </script>
            <?php endif; ?>

            <hr style="border:none;border-top:1px solid #e0e0e0;margin:30px 0;">

            <!-- GIZLENEN ELEMANLAR -->
            <?php
            $hidden_els = get_option('webyaz_hidden_elements', array());
            if (!empty($hidden_els)):
            ?>
            <div style="margin-bottom:25px;">
                <h2 style="font-size:20px;font-weight:700;margin:0 0 5px;color:#1e1e2e;">🙈 Gizlenen Elemanlar</h2>
                <p style="color:#666;font-size:13px;margin:0 0 14px;">Gorsel duzenleyiciden gizlenen elemanlar. Geri almak icin X butonuna tiklayin.</p>
                <div style="display:grid;gap:8px;">
                    <?php foreach ($hidden_els as $i => $h): ?>
                    <div id="wzHiddenRow_<?php echo $i; ?>" style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;">
                        <div>
                            <code style="color:#89b4fa;font-size:13px;"><?php echo esc_html($h['selector']); ?></code>
                            <span style="color:#999;font-size:11px;margin-left:10px;"><?php echo esc_html($h['date'] ?? ''); ?></span>
                        </div>
                        <button type="button" onclick="wzDeleteHidden(<?php echo $i; ?>)" style="background:#e8f5e9;color:#2e7d32;border:none;padding:5px 14px;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;">Geri Al</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <script>
                function wzDeleteHidden(index) {
                    var fd = new FormData();
                    fd.append('action', 'webyaz_delete_hidden');
                    fd.append('nonce', '<?php echo wp_create_nonce('webyaz_nonce'); ?>');
                    fd.append('index', index);
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.success) {
                                document.getElementById('wzHiddenRow_' + index).style.display = 'none';
                            }
                        });
                }
            </script>
            <?php endif; ?>

            <hr style="border:none;border-top:1px solid #e0e0e0;margin:30px 0;">

            <!-- GOOGLE FONTS -->
            <?php $fonts = get_option('webyaz_google_fonts', array()); ?>
            <div style="margin-bottom:25px;">
                <h2 style="font-size:20px;font-weight:700;margin:0 0 5px;color:#1e1e2e;">🔤 Google Fonts</h2>
                <p style="color:#666;font-size:13px;margin:0 0 14px;">Google'dan font secip sitenize uygulayabilirsiniz.</p>
                <form method="post">
                    <?php wp_nonce_field('webyaz_fonts', '_wpnonce_fonts'); ?>
                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;align-items:end;">
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Font Ailesi</label>
                            <select name="wz_font_family" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                                <option value="">-- Secin --</option>
                                <?php
                                $gfonts = array('Inter','Roboto','Poppins','Outfit','Montserrat','Open Sans','Lato','Raleway','Nunito','Playfair Display','Cairo','Noto Sans','Rubik','Quicksand','Comfortaa','Josefin Sans','Mukta','Kanit','Barlow','Fira Sans');
                                foreach ($gfonts as $gf):
                                ?>
                                <option value="<?php echo $gf; ?>" <?php selected($fonts['font'] ?? '', $gf); ?>><?php echo $gf; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Hedef</label>
                            <select name="wz_font_target" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                                <option value="body" <?php selected($fonts['target'] ?? 'body', 'body'); ?>>Tum Site</option>
                                <option value="h1,h2,h3,h4,h5,h6" <?php selected($fonts['target'] ?? '', 'h1,h2,h3,h4,h5,h6'); ?>>Sadece Basliklar</option>
                                <option value="p,span,li,td,a" <?php selected($fonts['target'] ?? '', 'p,span,li,td,a'); ?>>Sadece Paragraflar</option>
                                <option value=".product-title,.woocommerce-loop-product__title" <?php selected($fonts['target'] ?? '', '.product-title,.woocommerce-loop-product__title'); ?>>Urun Basliklari</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Kalinlik</label>
                            <select name="wz_font_weight" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                                <?php foreach (array('300'=>'Ince','400'=>'Normal','500'=>'Medium','600'=>'SemiBold','700'=>'Bold','900'=>'Black') as $w => $l): ?>
                                <option value="<?php echo $w; ?>" <?php selected($fonts['weight'] ?? '400', $w); ?>><?php echo $l; ?> (<?php echo $w; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top:12px;">
                        <button type="submit" name="webyaz_save_fonts" value="1" style="background:<?php echo $primary; ?>;color:#fff;border:none;padding:10px 24px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">Fontu Kaydet</button>
                    </div>
                </form>
            </div>

            <hr style="border:none;border-top:1px solid #e0e0e0;margin:30px 0;">

            <!-- GECE MODU -->
            <?php $dm = get_option('webyaz_dark_mode', array()); ?>
            <div style="margin-bottom:25px;">
                <h2 style="font-size:20px;font-weight:700;margin:0 0 5px;color:#1e1e2e;">🌙 Gece Modu</h2>
                <p style="color:#666;font-size:13px;margin:0 0 14px;">Sitenize karanlik tema toggle butonu ekleyin. Ziyaretciler kendi tercihlerini secebilir.</p>
                <form method="post">
                    <?php wp_nonce_field('webyaz_dark_mode', '_wpnonce_dark'); ?>
                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px;display:flex;align-items:center;gap:30px;">
                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                            <input type="checkbox" name="wz_dark_enabled" value="1" <?php checked($dm['enabled'] ?? '', '1'); ?> style="accent-color:<?php echo $primary; ?>;width:20px;height:20px;">
                            <span style="font-size:14px;font-weight:600;color:#333;">Gece Modu Aktif</span>
                        </label>
                        <div>
                            <label style="font-size:13px;color:#666;margin-right:8px;">Buton Konumu:</label>
                            <select name="wz_dark_position" style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                                <option value="bottom-right" <?php selected($dm['position'] ?? 'bottom-right', 'bottom-right'); ?>>Sag Alt</option>
                                <option value="bottom-left" <?php selected($dm['position'] ?? '', 'bottom-left'); ?>>Sol Alt</option>
                                <option value="top-right" <?php selected($dm['position'] ?? '', 'top-right'); ?>>Sag Ust</option>
                                <option value="top-left" <?php selected($dm['position'] ?? '', 'top-left'); ?>>Sol Ust</option>
                            </select>
                        </div>
                        <button type="submit" name="webyaz_save_dark_mode" value="1" style="background:<?php echo $primary; ?>;color:#fff;border:none;padding:10px 24px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;margin-left:auto;">Kaydet</button>
                    </div>
                </form>
            </div>

            <hr style="border:none;border-top:1px solid #e0e0e0;margin:30px 0;">

            <!-- MOBIL CSS -->
            <?php
            $tablet_css = get_option('webyaz_tablet_css', '');
            $mobile_css_val = get_option('webyaz_mobile_css', '');
            ?>
            <div style="margin-bottom:25px;">
                <h2 style="font-size:20px;font-weight:700;margin:0 0 5px;color:#1e1e2e;">📱 Mobil / Tablet CSS</h2>
                <p style="color:#666;font-size:13px;margin:0 0 14px;">Tablet (max 991px) ve mobil (max 549px) icin ayri CSS yazin. Media query otomatik eklenir.</p>
                <form method="post">
                    <?php wp_nonce_field('webyaz_mobile_css', '_wpnonce_mobile'); ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">📲 Tablet CSS <span style="color:#999;font-weight:400;">(max 991px)</span></label>
                            <textarea name="webyaz_tablet_css" rows="8" style="width:100%;background:#1e1e2e;color:#cdd6f4;font-family:monospace;font-size:13px;padding:14px;border:1px solid #313244;border-radius:8px;resize:vertical;box-sizing:border-box;"><?php echo esc_textarea($tablet_css); ?></textarea>
                        </div>
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">📱 Mobil CSS <span style="color:#999;font-weight:400;">(max 549px)</span></label>
                            <textarea name="webyaz_mobile_css" rows="8" style="width:100%;background:#1e1e2e;color:#cdd6f4;font-family:monospace;font-size:13px;padding:14px;border:1px solid #313244;border-radius:8px;resize:vertical;box-sizing:border-box;"><?php echo esc_textarea($mobile_css_val); ?></textarea>
                        </div>
                    </div>
                    <div style="margin-top:12px;">
                        <button type="submit" name="webyaz_save_mobile_css" value="1" style="background:<?php echo $primary; ?>;color:#fff;border:none;padding:10px 24px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">Mobil CSS Kaydet</button>
                    </div>
                </form>
            </div>

            <hr style="border:none;border-top:1px solid #e0e0e0;margin:30px 0;">

            <!-- SAYFA BAZLI CSS -->
            <?php $page_css = get_option('webyaz_page_css', array()); ?>
            <div style="margin-bottom:25px;">
                <h2 style="font-size:20px;font-weight:700;margin:0 0 5px;color:#1e1e2e;">📄 Sayfa Bazli CSS</h2>
                <p style="color:#666;font-size:13px;margin:0 0 14px;">Her sayfa tipi icin ayri CSS yazabilirsiniz.</p>
                <form method="post">
                    <?php wp_nonce_field('webyaz_page_css', '_wpnonce_page_css'); ?>
                    <?php
                    $page_types = array(
                        'home'     => array('icon'=>'🏠','label'=>'Ana Sayfa'),
                        'shop'     => array('icon'=>'🛒','label'=>'Magaza'),
                        'product'  => array('icon'=>'📦','label'=>'Urun Sayfasi'),
                        'category' => array('icon'=>'📂','label'=>'Kategori'),
                        'cart'     => array('icon'=>'🛍️','label'=>'Sepet'),
                        'checkout' => array('icon'=>'💳','label'=>'Odeme'),
                        'account'  => array('icon'=>'👤','label'=>'Hesabim'),
                        'post'     => array('icon'=>'📝','label'=>'Blog Yazisi'),
                        'page'     => array('icon'=>'📃','label'=>'Sayfa'),
                    );
                    ?>
                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;overflow:hidden;">
                        <div style="display:flex;border-bottom:1px solid #e0e0e0;flex-wrap:wrap;" id="wzPageTabs">
                            <?php $first = true; foreach ($page_types as $pt_key => $pt): ?>
                            <button type="button" class="wz-page-tab" data-tab="<?php echo $pt_key; ?>" style="padding:10px 16px;border:none;background:<?php echo $first ? '#f0f7ff' : '#fff'; ?>;cursor:pointer;font-size:12px;font-weight:600;color:<?php echo $first ? $primary : '#666'; ?>;border-bottom:2px solid <?php echo $first ? $primary : 'transparent'; ?>;"><?php echo $pt['icon'].' '.$pt['label']; ?></button>
                            <?php $first = false; endforeach; ?>
                        </div>
                        <?php $first = true; foreach ($page_types as $pt_key => $pt): ?>
                        <div class="wz-page-panel" id="wzPage_<?php echo $pt_key; ?>" style="<?php echo $first ? '' : 'display:none;'; ?>padding:16px;">
                            <textarea name="wz_pagecss_<?php echo $pt_key; ?>" rows="6" style="width:100%;background:#1e1e2e;color:#cdd6f4;font-family:monospace;font-size:13px;padding:14px;border:1px solid #313244;border-radius:8px;resize:vertical;box-sizing:border-box;" placeholder="/* <?php echo $pt['label']; ?> icin CSS */"><?php echo esc_textarea($page_css[$pt_key] ?? ''); ?></textarea>
                        </div>
                        <?php $first = false; endforeach; ?>
                    </div>
                    <div style="margin-top:12px;">
                        <button type="submit" name="webyaz_save_page_css" value="1" style="background:<?php echo $primary; ?>;color:#fff;border:none;padding:10px 24px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">Sayfa CSS Kaydet</button>
                    </div>
                </form>
            </div>
            <script>
                document.querySelectorAll('.wz-page-tab').forEach(function(tab) {
                    tab.addEventListener('click', function() {
                        document.querySelectorAll('.wz-page-tab').forEach(function(t) {
                            t.style.background = '#fff'; t.style.color = '#666'; t.style.borderBottom = '2px solid transparent';
                        });
                        this.style.background = '#f0f7ff'; this.style.color = '<?php echo $primary; ?>'; this.style.borderBottom = '2px solid <?php echo $primary; ?>';
                        document.querySelectorAll('.wz-page-panel').forEach(function(p) { p.style.display = 'none'; });
                        document.getElementById('wzPage_' + this.dataset.tab).style.display = '';
                    });
                });
            </script>

            <hr style="border:none;border-top:1px solid #e0e0e0;margin:30px 0;">

            <!-- CSS GECMISI -->
            <?php $history = get_option('webyaz_css_history', array()); ?>
            <?php if (!empty($history)): ?>
            <div style="margin-bottom:25px;">
                <h2 style="font-size:20px;font-weight:700;margin:0 0 5px;color:#1e1e2e;">🕐 CSS Gecmisi</h2>
                <p style="color:#666;font-size:13px;margin:0 0 14px;">Son 10 CSS versiyonu saklanir. Istediginiz versiyonu geri yukleyebilirsiniz.</p>
                <div style="display:grid;gap:8px;">
                    <?php foreach ($history as $hi => $hv): ?>
                    <div id="wzHistRow_<?php echo $hi; ?>" style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <span style="background:#f0f7ff;color:<?php echo $primary; ?>;font-weight:700;font-size:12px;padding:4px 10px;border-radius:6px;">v<?php echo count($history) - $hi; ?></span>
                            <span style="color:#666;font-size:13px;"><?php echo esc_html($hv['date']); ?></span>
                            <span style="color:#999;font-size:12px;"><?php echo esc_html($hv['size']); ?> karakter</span>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button type="button" onclick="wzTogglePreview(<?php echo $hi; ?>)" style="background:#f5f5f5;border:1px solid #ddd;color:#333;padding:5px 14px;border-radius:6px;font-size:12px;cursor:pointer;">Onizle</button>
                            <button type="button" onclick="wzRestoreCss(<?php echo $hi; ?>)" style="background:<?php echo $primary; ?>;border:none;color:#fff;padding:5px 14px;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;">Geri Yukle</button>
                        </div>
                    </div>
                    <pre id="wzHistPreview_<?php echo $hi; ?>" style="display:none;background:#1e1e2e;color:#cdd6f4;padding:14px;border-radius:8px;font-size:12px;max-height:200px;overflow:auto;margin:0;border:1px solid #313244;"><?php echo esc_html(mb_strimwidth($hv['css'], 0, 500, '...')); ?></pre>
                    <?php endforeach; ?>
                </div>
            </div>
            <script>
                function wzTogglePreview(i) {
                    var el = document.getElementById('wzHistPreview_' + i);
                    el.style.display = el.style.display === 'none' ? '' : 'none';
                }
                function wzRestoreCss(index) {
                    if (!confirm('Bu versiyona geri donulecek. Mevcut CSS gecmise aktarilacak. Emin misiniz?')) return;
                    var fd = new FormData();
                    fd.append('action', 'webyaz_restore_css');
                    fd.append('nonce', '<?php echo wp_create_nonce('webyaz_nonce'); ?>');
                    fd.append('index', index);
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.success) location.reload();
                            else alert('Hata: ' + (data.data || ''));
                        });
                }
            </script>
            <?php endif; ?>

            <hr style="border:none;border-top:1px solid #e0e0e0;margin:30px 0;">

            <!-- CSS YEDEKLEME / GERI YUKLEME -->
            <div style="margin-bottom:25px;">
                <h2 style="font-size:20px;font-weight:700;margin:0 0 5px;color:#1e1e2e;">💾 Yedekle / Geri Yukle</h2>
                <p style="color:#666;font-size:13px;margin:0 0 14px;">Tum CSS ayarlarinizi JSON dosyasi olarak yedekleyin veya daha once alinan yedegi geri yukleyin.</p>
                <div style="display:flex;gap:16px;flex-wrap:wrap;">
                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px;flex:1;min-width:250px;">
                        <div style="font-weight:600;font-size:14px;color:#333;margin-bottom:8px;">📤 Disa Aktar</div>
                        <p style="color:#666;font-size:12px;margin:0 0 12px;">Tum CSS, preset, font, dark mode ve diger ayarlari indir.</p>
                        <button type="button" id="wzExportBtn" style="background:<?php echo $primary; ?>;color:#fff;border:none;padding:10px 24px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">⬇️ JSON Indir</button>
                    </div>
                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:20px;flex:1;min-width:250px;">
                        <div style="font-weight:600;font-size:14px;color:#333;margin-bottom:8px;">📥 Ice Aktar</div>
                        <p style="color:#666;font-size:12px;margin:0 0 12px;">Daha once disa aktardiginiz JSON dosyasini yukleyin.</p>
                        <form method="post" enctype="multipart/form-data">
                            <?php wp_nonce_field('webyaz_import_css', '_wpnonce_import'); ?>
                            <div style="display:flex;gap:8px;">
                                <input type="file" name="wz_import_file" accept=".json" style="font-size:12px;">
                                <button type="submit" name="webyaz_import_css" value="1" style="background:#2e7d32;color:#fff;border:none;padding:8px 18px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">Yukle</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <script>
                document.getElementById('wzExportBtn').addEventListener('click', function() {
                    var fd = new FormData();
                    fd.append('action', 'webyaz_export_css');
                    fd.append('nonce', '<?php echo wp_create_nonce('webyaz_nonce'); ?>');
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.success) {
                                var blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' });
                                var a = document.createElement('a');
                                a.href = URL.createObjectURL(blob);
                                a.download = 'webyaz-css-backup-' + new Date().toISOString().slice(0,10) + '.json';
                                a.click();
                            }
                        });
                });
            </script>

            <hr style="border:none;border-top:1px solid #e0e0e0;margin:30px 0;">

            <!-- MANUEL CSS EDITORU -->
            <h2 style="font-size:20px;font-weight:700;margin:0 0 5px;color:#1e1e2e;">Manuel CSS</h2>
            <p style="color:#666;font-size:13px;margin:0 0 14px;">Kendi ozel CSS kodlarinizi yazin veya gorsel duzenleyiciden gelen kodlari duzenleyin.</p>

            <form method="post">
                <?php wp_nonce_field('webyaz_custom_css', '_wpnonce_css'); ?>

                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;overflow:hidden;margin-bottom:20px;">
                    <!-- Toolbar -->
                    <div style="background:#1e1e2e;padding:12px 18px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #313244;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#f38ba8;"></span>
                            <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#a6e3a1;"></span>
                            <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#f9e2af;"></span>
                            <span style="color:#cdd6f4;font-size:13px;margin-left:10px;font-family:monospace;">style.css</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="color:#6c7086;font-size:11px;" id="webyazCssCount"><?php echo strlen($css); ?> karakter</span>
                        </div>
                    </div>
                    <!-- Line numbers + Editor -->
                    <div style="display:flex;background:#1e1e2e;min-height:350px;">
                        <div id="webyazLineNums" style="background:#181825;color:#6c7086;font-family:'Fira Code','Cascadia Code','Consolas',monospace;font-size:14px;line-height:1.7;padding:15px 12px;text-align:right;user-select:none;min-width:45px;border-right:1px solid #313244;">1</div>
                        <textarea name="webyaz_custom_css" id="webyazCssEditor" spellcheck="false" style="flex:1;background:#1e1e2e;color:#cdd6f4;font-family:'Fira Code','Cascadia Code','Consolas',monospace;font-size:14px;line-height:1.7;padding:15px;border:none;outline:none;resize:vertical;min-height:350px;tab-size:4;-moz-tab-size:4;white-space:pre;overflow-wrap:normal;overflow-x:auto;"><?php echo esc_textarea($css); ?></textarea>
                    </div>
                </div>

                <button type="submit" name="webyaz_save_css" value="1" style="background:<?php echo $primary; ?>;color:#fff;border:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;transition:opacity .2s;">
                    <span class="dashicons dashicons-saved" style="vertical-align:middle;margin-right:6px;"></span>CSS Kaydet
                </button>
            </form>
        </div>

        <script>
            (function() {
                var editor = document.getElementById('webyazCssEditor');
                var lineNums = document.getElementById('webyazLineNums');
                var counter = document.getElementById('webyazCssCount');

                function updateLines() {
                    var lines = editor.value.split('\n').length;
                    var html = '';
                    for (var i = 1; i <= lines; i++) html += i + '\n';
                    lineNums.textContent = html;
                    counter.textContent = editor.value.length + ' karakter';
                }
                editor.addEventListener('input', updateLines);
                editor.addEventListener('scroll', function() {
                    lineNums.scrollTop = editor.scrollTop;
                });
                editor.addEventListener('keydown', function(e) {
                    if (e.key === 'Tab') {
                        e.preventDefault();
                        var s = this.selectionStart,
                            end = this.selectionEnd;
                        this.value = this.value.substring(0, s) + '    ' + this.value.substring(end);
                        this.selectionStart = this.selectionEnd = s + 4;
                        updateLines();
                    }
                });
                updateLines();
            })();
        </script>
<?php
    }
}

new Webyaz_Custom_CSS();
