<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Badges {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('woocommerce_before_shop_loop_item_title', array($this, 'display_badges'), 9);
        add_action('woocommerce_before_single_product_summary', array($this, 'display_badges_single'), 22);
        add_action('wp_head', array($this, 'inject_css'), 998);
        add_action('wp_footer', array($this, 'badge_repositioning_js'));

        // AJAX endpoints
        add_action('wp_ajax_webyaz_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_webyaz_search_categories', array($this, 'ajax_search_categories'));
    }

    public function register_settings() {
        register_setting('webyaz_badges_group', 'webyaz_badges', array(
            'sanitize_callback' => array(__CLASS__, 'sanitize_options'),
        ));
    }

    private static function get_badge_types() {
        return array(
            'new'           => array('icon' => '🆕', 'title' => 'Yeni Ürün Rozeti'),
            'sale'          => array('icon' => '🏷️', 'title' => 'İndirim Rozeti'),
            'low_stock'     => array('icon' => '⚡', 'title' => 'Düşük Stok Rozeti'),
            'out_stock'     => array('icon' => '🚫', 'title' => 'Tükenmiş Ürün Rozeti'),
            'featured'      => array('icon' => '⭐', 'title' => 'Öne Çıkan Rozeti'),
            'free_shipping' => array('icon' => '🚚', 'title' => 'Ücretsiz Kargo Rozeti'),
        );
    }

    private static function get_defaults() {
        return array(
            'active'              => '0',
            // Genel tasarım
            'style'               => 'rounded',   // rounded, pill, circle, ribbon, square
            'position'            => 'top-left',   // top-left, top-right, bottom-left, bottom-right
            'font_size'           => '12',
            'badge_size'          => 'medium',     // small, medium, large
            'text_color'          => '#ffffff',
            'animation'           => 'none',       // none, pulse, bounce, fade
            'spacing'             => '6',
            // Yeni
            'new_active'          => '1',
            'new_days'            => '14',
            'new_text'            => 'Yeni',
            'new_bg'              => '#4caf50',
            'new_text_color'      => '#ffffff',
            'new_categories'      => '',
            'new_products'        => '',
            // İndirim
            'sale_active'         => '1',
            'sale_text'           => 'İndirim',
            'sale_show_percent'   => '1',
            'sale_bg'             => '#d32f2f',
            'sale_text_color'     => '#ffffff',
            'sale_categories'     => '',
            'sale_products'       => '',
            // Düşük stok
            'low_stock_active'    => '1',
            'low_stock_threshold' => '5',
            'low_stock_text'      => 'Son {stock} Adet!',
            'low_stock_bg'        => '#ff9800',
            'low_stock_text_color'=> '#ffffff',
            'low_stock_categories'=> '',
            'low_stock_products'  => '',
            // Tükenmiş
            'out_stock_active'    => '1',
            'out_stock_text'      => 'Tükendi',
            'out_stock_bg'        => '#616161',
            'out_stock_text_color'=> '#ffffff',
            'out_stock_categories'=> '',
            'out_stock_products'  => '',
            // Öne çıkan
            'featured_active'     => '1',
            'featured_text'       => 'Öne Çıkan',
            'featured_bg'         => '#9c27b0',
            'featured_text_color' => '#ffffff',
            'featured_categories' => '',
            'featured_products'   => '',
            // Ücretsiz kargo
            'free_shipping_active'     => '0',
            'free_shipping_text'       => 'Ücretsiz Kargo',
            'free_shipping_bg'         => '#00bcd4',
            'free_shipping_text_color' => '#ffffff',
            'free_shipping_categories' => '',
            'free_shipping_products'   => '',
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_badges', array()), self::get_defaults());
    }

    // =========================================
    // AJAX SEARCH
    // =========================================
    public function ajax_search_products() {
        check_ajax_referer('webyaz_badges_nonce', 'nonce');
        $q = sanitize_text_field($_GET['q'] ?? '');
        $results = array();
        if (strlen($q) >= 2) {
            $products = wc_get_products(array(
                's' => $q,
                'limit' => 20,
                'status' => 'publish',
            ));
            foreach ($products as $p) {
                $results[] = array('id' => $p->get_id(), 'text' => $p->get_name() . ' (#' . $p->get_id() . ')');
            }
        }
        wp_send_json($results);
    }

    public function ajax_search_categories() {
        check_ajax_referer('webyaz_badges_nonce', 'nonce');
        $q = sanitize_text_field($_GET['q'] ?? '');
        $results = array();
        $terms = get_terms(array(
            'taxonomy' => 'product_cat',
            'search' => $q,
            'hide_empty' => false,
            'number' => 20,
        ));
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) {
                $results[] = array('id' => $t->term_id, 'text' => $t->name . ' (' . $t->count . ' ürün)');
            }
        }
        wp_send_json($results);
    }

    // =========================================
    // TARGETING CHECK
    // =========================================
    private function passes_targeting($product, $type, $opts) {
        $cat_ids_str = $opts[$type . '_categories'] ?? '';
        $prod_ids_str = $opts[$type . '_products'] ?? '';

        // Boşsa = herkese uygulanır
        if (empty($cat_ids_str) && empty($prod_ids_str)) return true;

        $pid = $product->get_id();

        // Ürün bazında kontrol
        if (!empty($prod_ids_str)) {
            $prod_ids = array_map('intval', array_filter(explode(',', $prod_ids_str)));
            if (in_array($pid, $prod_ids)) return true;
        }

        // Kategori bazında kontrol
        if (!empty($cat_ids_str)) {
            $cat_ids = array_map('intval', array_filter(explode(',', $cat_ids_str)));
            $product_cats = wp_get_post_terms($pid, 'product_cat', array('fields' => 'ids'));
            if (!is_wp_error($product_cats) && array_intersect($cat_ids, $product_cats)) {
                return true;
            }
        }

        return false;
    }

    // =========================================
    // FRONTEND DISPLAY
    // =========================================
    public function display_badges() {
        $this->render_badges();
    }

    public function display_badges_single() {
        $this->render_badges(true);
    }

    private function render_badges($single = false) {
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return;

        global $product;
        if (!$product) return;

        $badges = array();

        if ($opts['out_stock_active'] === '1' && !$product->is_in_stock() && $this->passes_targeting($product, 'out_stock', $opts)) {
            $badges[] = array('text' => $opts['out_stock_text'], 'bg' => $opts['out_stock_bg'], 'color' => $opts['out_stock_text_color']);
        } else {
            if ($opts['sale_active'] === '1' && $product->is_on_sale() && $this->passes_targeting($product, 'sale', $opts)) {
                $text = $opts['sale_text'];
                if ($opts['sale_show_percent'] === '1') {
                    $regular = floatval($product->get_regular_price());
                    $sale = floatval($product->get_sale_price());
                    if ($regular > 0 && $sale > 0) {
                        $percent = round((($regular - $sale) / $regular) * 100);
                        $text = '%' . $percent . ' ' . $text;
                    }
                }
                $badges[] = array('text' => $text, 'bg' => $opts['sale_bg'], 'color' => $opts['sale_text_color']);
            }

            if ($opts['new_active'] === '1' && $this->passes_targeting($product, 'new', $opts)) {
                $badges[] = array('text' => $opts['new_text'], 'bg' => $opts['new_bg'], 'color' => $opts['new_text_color']);
            }

            if ($opts['featured_active'] === '1' && $product->is_featured() && $this->passes_targeting($product, 'featured', $opts)) {
                $badges[] = array('text' => $opts['featured_text'], 'bg' => $opts['featured_bg'], 'color' => $opts['featured_text_color']);
            }

            if ($opts['low_stock_active'] === '1' && $product->managing_stock() && $this->passes_targeting($product, 'low_stock', $opts)) {
                $stock = $product->get_stock_quantity();
                $threshold = intval($opts['low_stock_threshold']);
                if ($stock !== null && $stock > 0 && $stock <= $threshold) {
                    $badges[] = array(
                        'text' => str_replace('{stock}', $stock, $opts['low_stock_text']),
                        'bg' => $opts['low_stock_bg'],
                        'color' => $opts['low_stock_text_color']
                    );
                }
            }

            if ($opts['free_shipping_active'] === '1' && $this->passes_targeting($product, 'free_shipping', $opts)) {
                $shipping_class = $product->get_shipping_class();
                if ($shipping_class === 'free-shipping' || has_term('free-shipping', 'product_shipping_class', $product->get_id())) {
                    $badges[] = array('text' => $opts['free_shipping_text'], 'bg' => $opts['free_shipping_bg'], 'color' => $opts['free_shipping_text_color']);
                }
            }
        }

        if (empty($badges)) return;

        remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10);
        remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10);

        $style = $opts['style'];
        echo '<div class="webyaz-badges webyaz-badges-' . esc_attr($opts['position']) . ' webyaz-badges-' . esc_attr($style) . ($single ? ' webyaz-badges-single' : '') . '">';
        foreach ($badges as $b) {
            echo '<span class="webyaz-badge-item webyaz-badge-' . esc_attr($style) . '" style="background:' . esc_attr($b['bg']) . ';color:' . esc_attr($b['color']) . ';">' . esc_html($b['text']) . '</span>';
        }
        echo '</div>';
    }

    // =========================================
    // FRONTEND CSS INJECTION
    // =========================================
    public function inject_css() {
        if (!is_shop() && !is_product_category() && !is_product_tag() && !is_product() && !is_front_page() && !is_home()) return;
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return;

        $pos = $opts['position'];
        $fs = intval($opts['font_size']);
        $spacing = intval($opts['spacing']);
        $style = $opts['style'];
        $anim = $opts['animation'];

        // Size mapping
        $sizes = array(
            'small'  => array('px' => '4px 10px', 'fs' => max(10, $fs - 2)),
            'medium' => array('px' => '6px 14px', 'fs' => $fs),
            'large'  => array('px' => '8px 20px', 'fs' => $fs + 2),
        );
        $sz = $sizes[$opts['badge_size']] ?? $sizes['medium'];

        // Position CSS
        $pos_map = array(
            'top-left'     => 'top:10px;left:10px;',
            'top-right'    => 'top:10px;right:10px;',
            'bottom-left'  => 'bottom:10px;left:10px;',
            'bottom-right' => 'bottom:10px;right:10px;',
        );
        $pos_css = $pos_map[$pos] ?? $pos_map['top-left'];

        // Flex direction based on position
        $align = (strpos($pos, 'right') !== false) ? 'align-items:flex-end;' : 'align-items:flex-start;';

        // Style-specific border-radius
        $radius_map = array(
            'rounded' => '8px',
            'pill'    => '50px',
            'circle'  => '50%',
            'ribbon'  => '0 4px 4px 0',
            'square'  => '3px',
        );
        $radius = $radius_map[$style] ?? '8px';

        // Circle needs equal padding
        $padding = $sz['px'];
        if ($style === 'circle') {
            $circle_size = $sz['fs'] + 20;
            $padding = '0';
        }

        // Ribbon special positioning
        $ribbon_extra = '';
        if ($style === 'ribbon') {
            if (strpos($pos, 'left') !== false) {
                $ribbon_extra = 'left:-3px !important;border-radius:0 4px 4px 0 !important;';
            } else {
                $ribbon_extra = 'right:-3px !important;border-radius:4px 0 0 4px !important;';
            }
        }

        // Animation keyframes
        $keyframes = '';
        if ($anim === 'pulse') {
            $keyframes = '@keyframes webyaz-pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}';
        } elseif ($anim === 'bounce') {
            $keyframes = '@keyframes webyaz-bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-4px)}}';
        } elseif ($anim === 'fade') {
            $keyframes = '@keyframes webyaz-fade{0%,100%{opacity:1}50%{opacity:0.7}}';
        }

        $anim_css = '';
        if ($anim !== 'none') {
            $anim_css = "animation:webyaz-{$anim} 2s ease-in-out infinite;";
        }

        $css = "
/* Webyaz Product Badges */
{$keyframes}
.webyaz-badges {
    position:absolute;{$pos_css}
    display:flex;flex-direction:column;gap:{$spacing}px;{$align}
    z-index:9;pointer-events:none;
}
.webyaz-badge-item {
    display:inline-flex;align-items:center;justify-content:center;
    padding:{$padding};
    font-size:{$sz['fs']}px;font-weight:700;
    letter-spacing:0.3px;text-transform:uppercase;
    border-radius:{$radius};
    line-height:1.3;white-space:nowrap;
    box-shadow:0 2px 8px rgba(0,0,0,0.18);
    pointer-events:auto;
    {$anim_css}
}";

        if ($style === 'circle') {
            $css .= "
.webyaz-badge-circle {
    width:{$circle_size}px;height:{$circle_size}px;
    border-radius:50% !important;
    font-size:" . max(9, $sz['fs'] - 2) . "px;
    text-align:center;
}";
        }

        if ($style === 'ribbon') {
            $css .= "
.webyaz-badge-ribbon {
    {$ribbon_extra}
    position:relative;
    box-shadow:0 3px 8px rgba(0,0,0,0.2);
}";
        }

        // Ensure product containers have relative position
        $css .= "
.products .product, .woocommerce ul.products li.product,
.product-gallery, .woocommerce-product-gallery,
.product-small .box-image, .product-large .box-image, .product-medium .box-image,
.col .box-image {
    position:relative !important;
    overflow:visible !important;
}
.webyaz-badges-single {
    z-index:99;
}
";

        echo '<style id="webyaz-badges-css">' . $css . '</style>';
    }

    // =========================================
    // FRONTEND JS: Badge Repositioning
    // =========================================
    public function badge_repositioning_js() {
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return;
        if (!is_shop() && !is_product_category() && !is_product_tag() && !is_front_page() && !is_home() && !is_product()) return;
        ?>
        <script>
        jQuery(function($){
            // Tüm rozetleri (single hariç) resim kutusuna taşı
            $('.webyaz-badges').not('.webyaz-badges-single').each(function(){
                var $badge = $(this);

                // Zaten resim kutusunun içindeyse atla
                if ($badge.closest('.box-image').length) return;

                // En yakın ürün container'ını bul (Flatsome + diğer temalar)
                var $container = $badge.closest('.product-small, .product-medium, .product-large, .product, li.product, .col, .product-col');
                if (!$container.length) {
                    // Son çare: en yakın img'nin büyük kutusu
                    $container = $badge.parent().closest('[class*="product"], [class*="col"]');
                }
                if (!$container.length) return;

                // Resim kutusunu bul - öncelik sırası
                var $imageWrap = null;

                // 1) Flatsome .box-image
                var $boxImg = $container.find('.box-image').first();
                if ($boxImg.length) { $imageWrap = $boxImg; }

                // 2) WooCommerce standart link wrapper
                if (!$imageWrap) {
                    var $wcLink = $container.find('.woocommerce-LoopProduct-link, .woocommerce-loop-product__link, a.woocommerce-loop-product__link').first();
                    if ($wcLink.length) { $imageWrap = $wcLink; }
                }

                // 3) İlk ürün resminin parent'ı
                if (!$imageWrap) {
                    var $img = $container.find('img.attachment-woocommerce_thumbnail, img.wp-post-image, .product-image img, .box-image img').first();
                    if ($img.length) {
                        var $imgParent = $img.closest('.box-image, .product-image, .image-fade_in');
                        $imageWrap = $imgParent.length ? $imgParent : $img.parent();
                    }
                }

                if ($imageWrap && $imageWrap.length && !$imageWrap.find('.webyaz-badges').length) {
                    $imageWrap.css({'position':'relative','overflow':'visible'});
                    $badge.appendTo($imageWrap);
                }
            });

            // Tekli ürün sayfası
            $('.webyaz-badges-single').each(function(){
                var $badge = $(this);
                // Zaten galeri içindeyse atla
                if ($badge.closest('.woocommerce-product-gallery, .product-gallery, .box-image').length) return;

                // Flatsome galeri
                var $gallery = $('.product-gallery .slide.first, .product-gallery .woocommerce-product-gallery__image, .product-gallery .woocommerce-product-gallery').first();
                if (!$gallery.length) {
                    $gallery = $('.woocommerce-product-gallery').first();
                }
                if ($gallery.length) {
                    $gallery.css({'position':'relative','overflow':'visible'});
                    $badge.appendTo($gallery);
                }
            });
        });
        </script>
        <?php
    }

    // =========================================
    // ADMIN MENU
    // =========================================
    public function add_submenu() {
        $hook = add_submenu_page('webyaz-dashboard', 'Ürün Rozetleri', 'Ürün Rozetleri', 'manage_options', 'webyaz-badges', array($this, 'render_admin'));
        add_action('admin_print_scripts-' . $hook, function() {
            wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
            wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), null, true);
        });
    }

    // =========================================
    // ADMIN PAGE
    // =========================================
    public function render_admin() {
        $opts = self::get_opts();
        $badge_types = self::get_badge_types();
        $nonce = wp_create_nonce('webyaz_badges_nonce');

        // WooCommerce ürün sayfası görsel boyutlarını al
        $wc_thumb_w = 600;
        $wc_thumb_h = 600;
        $wc_thumb_crop = false;
        if (function_exists('wc_get_image_size')) {
            $wc_size = wc_get_image_size('single');
            if (!empty($wc_size['width'])) $wc_thumb_w = intval($wc_size['width']);
            if (!empty($wc_size['height'])) $wc_thumb_h = intval($wc_size['height']);
            if (isset($wc_size['crop'])) $wc_thumb_crop = $wc_size['crop'];
        }
        // Önizleme için maksimum 280px genişlik ile oranı koru
        $preview_max = 280;
        if ($wc_thumb_w >= $wc_thumb_h) {
            $preview_w = $preview_max;
            $preview_h = $wc_thumb_crop ? round($preview_max * ($wc_thumb_h / $wc_thumb_w)) : $preview_max;
        } else {
            $preview_h = round($preview_max * 1.2);
            $preview_w = round($preview_h * ($wc_thumb_w / $wc_thumb_h));
        }
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>🏷️ Ürün Rozetleri</h1><p>Ürünlerinize profesyonel rozetler ekleyin — kategori veya ürün bazında hedefleyin</p></div>
            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">✅ Ayarlar kaydedildi!</div><?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('webyaz_badges_group'); ?>

                <style>
                    .wb-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;}
                    .wb-settings{display:flex;flex-direction:column;gap:16px;}
                    .wb-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;transition:box-shadow .2s;}
                    .wb-card:hover{box-shadow:0 4px 16px rgba(0,0,0,0.06);}
                    .wb-card-header{display:flex;align-items:center;gap:12px;padding:16px 20px;cursor:pointer;user-select:none;border-bottom:1px solid #f0f0f0;transition:background .2s;}
                    .wb-card-header:hover{background:#fafbfc;}
                    .wb-card-header .wb-icon{font-size:22px;}
                    .wb-card-header .wb-title{flex:1;font-size:15px;font-weight:700;color:#1a1a2e;}
                    .wb-card-header .wb-arrow{font-size:18px;color:#999;transition:transform .3s;}
                    .wb-card-header.open .wb-arrow{transform:rotate(180deg);}
                    .wb-card-body{padding:20px;display:none;}
                    .wb-card-body.open{display:block;}
                    .wb-fields{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
                    .wb-field{display:flex;flex-direction:column;gap:5px;}
                    .wb-field label{font-size:12px;font-weight:600;color:#555;}
                    .wb-field input,.wb-field select{padding:9px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;box-sizing:border-box;width:100%;}
                    .wb-field input[type=color]{height:40px;padding:3px;cursor:pointer;}
                    .wb-field input[type=number]{width:100%;}
                    .wb-field-full{grid-column:1/-1;}
                    .wb-toggle{position:relative;display:inline-block;width:44px;height:24px;}
                    .wb-toggle input{display:none;}
                    .wb-toggle-slider{position:absolute;top:0;left:0;right:0;bottom:0;background:#ccc;border-radius:24px;transition:.3s;cursor:pointer;}
                    .wb-toggle-slider::after{content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;background:#fff;border-radius:50%;transition:.3s;box-shadow:0 1px 3px rgba(0,0,0,.2);}
                    .wb-toggle input:checked+.wb-toggle-slider{background:#4caf50;}
                    .wb-toggle input:checked+.wb-toggle-slider::after{left:23px;}
                    .wb-active-row{display:flex;align-items:center;justify-content:space-between;padding:2px 0 10px;border-bottom:1px solid #f0f0f0;margin-bottom:12px;}
                    .wb-active-row label{font-size:13px;font-weight:600;color:#333;}
                    .wb-targeting{background:#f8f9ff;border:1px solid #e5e7eb;border-radius:10px;padding:14px;margin-top:6px;}
                    .wb-targeting-title{font-size:11px;font-weight:700;color:#446084;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;display:flex;align-items:center;gap:6px;}
                    .wb-preview-wrap{position:sticky;top:40px;}
                    .wb-preview-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:24px;text-align:center;}
                    .wb-preview-label{font-size:13px;font-weight:700;color:#999;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:8px;}
                    .wb-preview-product{position:relative;width:<?php echo $preview_w; ?>px;height:<?php echo $preview_h; ?>px;margin:0 auto;background:#f5f6f8;border-radius:12px;overflow:hidden;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 12px rgba(0,0,0,0.08);}
                    .wb-preview-product img{width:100%;height:100%;object-fit:cover;}
                    .wb-preview-badges{position:absolute;display:flex;flex-direction:column;gap:6px;z-index:2;}
                    .wb-preview-badges.top-left{top:10px;left:10px;align-items:flex-start;}
                    .wb-preview-badges.top-right{top:10px;right:10px;align-items:flex-end;}
                    .wb-preview-badges.bottom-left{bottom:10px;left:10px;align-items:flex-start;}
                    .wb-preview-badges.bottom-right{bottom:10px;right:10px;align-items:flex-end;}
                    .wb-preview-badge{display:inline-flex;align-items:center;justify-content:center;font-weight:700;letter-spacing:0.3px;text-transform:uppercase;box-shadow:0 2px 8px rgba(0,0,0,0.18);white-space:nowrap;transition:all .3s ease;}
                    .wb-style-samples{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;justify-content:center;}
                    .wb-style-sample{padding:6px 14px;border-radius:8px;font-size:11px;font-weight:700;color:#fff;text-transform:uppercase;}
                    .select2-container .select2-selection--multiple{min-height:38px !important;border:1px solid #e5e7eb !important;border-radius:8px !important;padding:4px 8px !important;}
                    .select2-container--default .select2-selection--multiple .select2-selection__choice{background:#446084 !important;border:none !important;color:#fff !important;border-radius:4px !important;padding:3px 8px !important;font-size:12px !important;}
                    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove{color:#fff !important;margin-right:4px !important;}
                    .wb-style-pick{padding:6px 14px;font-size:11px;font-weight:700;color:#fff;text-transform:uppercase;cursor:pointer;transition:all .2s;opacity:0.45;border:2px solid transparent;}
                    .wb-style-pick:hover{opacity:0.75;transform:scale(1.05);}
                    .wb-style-pick.active{opacity:1;border-color:#fff;box-shadow:0 0 0 3px #4f46e5,0 4px 12px rgba(0,0,0,0.2);transform:scale(1.1);}
                    .wb-card-selected{border-color:#4f46e5 !important;box-shadow:0 0 0 2px rgba(79,70,229,0.15),0 4px 16px rgba(79,70,229,0.1) !important;}
                    .wb-card-selected .wb-card-header{background:#f8f7ff;}
                    @media(max-width:900px){.wb-grid{grid-template-columns:1fr;}.wb-fields{grid-template-columns:1fr;}}
                </style>

                <div class="wb-grid">
                    <!-- LEFT: SETTINGS -->
                    <div class="wb-settings">

                        <!-- GENEL AYARLAR -->
                        <div class="wb-card">
                            <div class="wb-card-header open" onclick="var b=this.nextElementSibling;b.classList.toggle('open');this.classList.toggle('open');">
                                <span class="wb-icon">⚙️</span>
                                <span class="wb-title">Genel Ayarlar</span>
                                <span class="wb-arrow">▼</span>
                            </div>
                            <div class="wb-card-body open">
                                <div class="wb-active-row">
                                    <label>Rozet Sistemi</label>
                                    <label class="wb-toggle">
                                        <input type="hidden" name="webyaz_badges[active]" value="0">
                                        <input type="checkbox" name="webyaz_badges[active]" value="1" <?php checked($opts['active'], '1'); ?> onchange="updateBadgePreview();">
                                        <span class="wb-toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="wb-fields">
                                    <div class="wb-field">
                                        <label>📐 Rozet Stili</label>
                                        <select name="webyaz_badges[style]" id="wb-style" onchange="updateBadgePreview();">
                                            <option value="rounded" <?php selected($opts['style'], 'rounded'); ?>>Yuvarlatılmış Köşe</option>
                                            <option value="pill" <?php selected($opts['style'], 'pill'); ?>>Hap (Pill)</option>
                                            <option value="circle" <?php selected($opts['style'], 'circle'); ?>>Daire</option>
                                            <option value="ribbon" <?php selected($opts['style'], 'ribbon'); ?>>Şerit (Ribbon)</option>
                                            <option value="square" <?php selected($opts['style'], 'square'); ?>>Kare</option>
                                        </select>
                                    </div>
                                    <div class="wb-field">
                                        <label>📍 Konum</label>
                                        <select name="webyaz_badges[position]" id="wb-position" onchange="updateBadgePreview();">
                                            <option value="top-left" <?php selected($opts['position'], 'top-left'); ?>>Sol Üst</option>
                                            <option value="top-right" <?php selected($opts['position'], 'top-right'); ?>>Sağ Üst</option>
                                            <option value="bottom-left" <?php selected($opts['position'], 'bottom-left'); ?>>Sol Alt</option>
                                            <option value="bottom-right" <?php selected($opts['position'], 'bottom-right'); ?>>Sağ Alt</option>
                                        </select>
                                    </div>
                                    <div class="wb-field">
                                        <label>📏 Boyut</label>
                                        <select name="webyaz_badges[badge_size]" id="wb-size" onchange="updateBadgePreview();">
                                            <option value="small" <?php selected($opts['badge_size'], 'small'); ?>>Küçük</option>
                                            <option value="medium" <?php selected($opts['badge_size'], 'medium'); ?>>Orta</option>
                                            <option value="large" <?php selected($opts['badge_size'], 'large'); ?>>Büyük</option>
                                        </select>
                                    </div>
                                    <div class="wb-field">
                                        <label>🔤 Yazı Boyutu (px)</label>
                                        <input type="number" name="webyaz_badges[font_size]" id="wb-font-size" value="<?php echo esc_attr($opts['font_size']); ?>" min="8" max="22" onchange="updateBadgePreview();" oninput="updateBadgePreview();">
                                    </div>
                                    <div class="wb-field">
                                        <label>📐 Rozet Arası Boşluk (px)</label>
                                        <input type="number" name="webyaz_badges[spacing]" value="<?php echo esc_attr($opts['spacing']); ?>" min="0" max="20">
                                    </div>
                                    <div class="wb-field">
                                        <label>✨ Animasyon</label>
                                        <select name="webyaz_badges[animation]" id="wb-animation" onchange="updateBadgePreview();">
                                            <option value="none" <?php selected($opts['animation'], 'none'); ?>>Yok</option>
                                            <option value="pulse" <?php selected($opts['animation'], 'pulse'); ?>>Nabız (Pulse)</option>
                                            <option value="bounce" <?php selected($opts['animation'], 'bounce'); ?>>Zıplama (Bounce)</option>
                                            <option value="fade" <?php selected($opts['animation'], 'fade'); ?>>Yanıp Sönme (Fade)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- BADGE TYPE CARDS -->
                        <?php foreach ($badge_types as $type => $info): ?>
                        <div class="wb-card" data-badge-type="<?php echo $type; ?>">
                            <div class="wb-card-header" onclick="var b=this.nextElementSibling;b.classList.toggle('open');this.classList.toggle('open');selectBadgeForPreview('<?php echo $type; ?>');">
                                <span class="wb-icon"><?php echo $info['icon']; ?></span>
                                <span class="wb-title"><?php echo esc_html($info['title']); ?></span>
                                <span style="margin-left:auto;margin-right:10px;">
                                    <?php if ($opts[$type . '_active'] === '1'): ?>
                                        <span style="display:inline-block;width:8px;height:8px;background:#4caf50;border-radius:50%;"></span>
                                    <?php else: ?>
                                        <span style="display:inline-block;width:8px;height:8px;background:#ccc;border-radius:50%;"></span>
                                    <?php endif; ?>
                                </span>
                                <span class="wb-arrow">▼</span>
                            </div>
                            <div class="wb-card-body">
                                <!-- Active toggle -->
                                <div class="wb-active-row">
                                    <label>Aktif</label>
                                    <label class="wb-toggle">
                                        <input type="hidden" name="webyaz_badges[<?php echo $type; ?>_active]" value="0">
                                        <input type="checkbox" name="webyaz_badges[<?php echo $type; ?>_active]" value="1" <?php checked($opts[$type . '_active'], '1'); ?> onchange="updateBadgePreview();">
                                        <span class="wb-toggle-slider"></span>
                                    </label>
                                </div>

                                <div class="wb-fields">
                                    <div class="wb-field">
                                        <label>📝 Metin</label>
                                        <input type="text" name="webyaz_badges[<?php echo $type; ?>_text]" value="<?php echo esc_attr($opts[$type . '_text']); ?>" onchange="updateBadgePreview();" oninput="updateBadgePreview();">
                                    </div>
                                    <div class="wb-field">
                                        <label>🎨 Arka Plan Rengi</label>
                                        <input type="color" name="webyaz_badges[<?php echo $type; ?>_bg]" value="<?php echo esc_attr($opts[$type . '_bg']); ?>" onchange="updateBadgePreview();" oninput="updateBadgePreview();">
                                    </div>
                                    <div class="wb-field">
                                        <label>🔤 Yazı Rengi</label>
                                        <input type="color" name="webyaz_badges[<?php echo $type; ?>_text_color]" value="<?php echo esc_attr($opts[$type . '_text_color']); ?>" onchange="updateBadgePreview();" oninput="updateBadgePreview();">
                                    </div>

                                    <?php if ($type === 'new'): ?>
                                    <div class="wb-field">
                                        <label>📅 Kaç Gün Yeni Sayılsın</label>
                                        <input type="number" name="webyaz_badges[new_days]" value="<?php echo esc_attr($opts['new_days']); ?>" min="1">
                                    </div>
                                    <?php elseif ($type === 'sale'): ?>
                                    <div class="wb-field">
                                        <label>📊 Yüzde Göster</label>
                                        <select name="webyaz_badges[sale_show_percent]">
                                            <option value="1" <?php selected($opts['sale_show_percent'], '1'); ?>>Evet</option>
                                            <option value="0" <?php selected($opts['sale_show_percent'], '0'); ?>>Hayır</option>
                                        </select>
                                    </div>
                                    <?php elseif ($type === 'low_stock'): ?>
                                    <div class="wb-field">
                                        <label>📦 Eşik Değeri ({stock} = sayı)</label>
                                        <input type="number" name="webyaz_badges[low_stock_threshold]" value="<?php echo esc_attr($opts['low_stock_threshold']); ?>" min="1">
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- TARGETING -->
                                <div class="wb-targeting">
                                    <div class="wb-targeting-title">🎯 Hedefleme (Boş = Tüm Ürünler)</div>
                                    <div class="wb-fields">
                                        <div class="wb-field wb-field-full">
                                            <label>📁 Kategoriler</label>
                                            <select class="wb-cat-select" name="webyaz_badges[<?php echo $type; ?>_categories][]" multiple="multiple"
                                                    data-type="<?php echo $type; ?>"
                                                    style="width:100%;">
                                                <?php
                                                $saved_cats = array_filter(explode(',', $opts[$type . '_categories'] ?? ''));
                                                foreach ($saved_cats as $cid) {
                                                    $term = get_term(intval($cid), 'product_cat');
                                                    if ($term && !is_wp_error($term)) {
                                                        echo '<option value="' . intval($cid) . '" selected>' . esc_html($term->name) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="wb-field wb-field-full">
                                            <label>🏷️ Ürünler</label>
                                            <select class="wb-prod-select" name="webyaz_badges[<?php echo $type; ?>_products][]" multiple="multiple"
                                                    data-type="<?php echo $type; ?>"
                                                    style="width:100%;">
                                                <?php
                                                $saved_prods = array_filter(explode(',', $opts[$type . '_products'] ?? ''));
                                                foreach ($saved_prods as $pid) {
                                                    $p = wc_get_product(intval($pid));
                                                    if ($p) {
                                                        echo '<option value="' . intval($pid) . '" selected>' . esc_html($p->get_name()) . ' (#' . intval($pid) . ')</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <?php submit_button('💾 Kaydet', 'primary', 'submit', true, array('style' => 'padding:12px 32px;font-size:14px;border-radius:10px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;box-shadow:0 4px 12px rgba(79,70,229,0.25);')); ?>
                    </div>

                    <!-- RIGHT: PREVIEW -->
                    <div class="wb-preview-wrap">
                        <div class="wb-preview-card">
                            <div class="wb-preview-label">👁️ Canlı Önizleme</div>
                            <div class="wb-selected-badge-name" id="wb-selected-badge-name" style="text-align:center;font-size:13px;font-weight:600;color:#4f46e5;margin-bottom:12px;">Bir rozet kartına tıklayarak önizleme yapın</div>
                            <div class="wb-preview-product" id="wb-preview-product">
                                <img src="<?php echo esc_url(plugins_url('assets/badge-preview-product.png', dirname(__FILE__))); ?>" alt="Örnek Ürün" style="width:100%;height:100%;object-fit:cover;">
                                <div class="wb-preview-badges <?php echo esc_attr($opts['position']); ?>" id="wb-preview-badges">
                                    <!-- JS ile doldurulacak -->
                                </div>
                            </div>

                            <div class="wb-preview-info" style="margin-top:14px;background:#f8f9ff;border-radius:10px;padding:12px 14px;font-size:12px;color:#555;line-height:1.6;">
                                <div style="font-weight:700;color:#333;margin-bottom:4px;">💡 Nasıl Kullanılır?</div>
                                Sol taraftaki rozet kartlarına tıklayarak her bir rozetin ürün üzerinde nasıl göründüğünü buradan görebilirsiniz.
                                <div style="margin-top:8px;padding-top:8px;border-top:1px solid #e5e7eb;font-size:11px;color:#888;">
                                    📐 WooCommerce ürün sayfası görsel boyutu: <strong style="color:#555;"><?php echo $wc_thumb_w; ?>×<?php echo $wc_thumb_h; ?>px</strong>
                                    <?php if ($wc_thumb_crop): ?>(kırpılmış)<?php else: ?>(orantılı)<?php endif; ?>
                                </div>
                            </div>

                            <div style="margin-top:16px;padding-top:16px;border-top:1px solid #eee;">
                                <div style="font-size:11px;font-weight:700;color:#999;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;">🎨 Stili Seç (tıkla)</div>
                                <div class="wb-style-samples" id="wb-style-samples">
                                    <div class="wb-style-pick" data-style="rounded" onclick="pickStyle('rounded')" style="border-radius:8px;background:#4caf50;">Yuvarlatılmış</div>
                                    <div class="wb-style-pick" data-style="pill" onclick="pickStyle('pill')" style="border-radius:50px;background:#d32f2f;">Hap</div>
                                    <div class="wb-style-pick" data-style="circle" onclick="pickStyle('circle')" style="border-radius:50%;width:50px;height:50px;display:flex;align-items:center;justify-content:center;background:#9c27b0;font-size:9px;">Daire</div>
                                    <div class="wb-style-pick" data-style="ribbon" onclick="pickStyle('ribbon')" style="border-radius:0 4px 4px 0;background:#ff9800;">Şerit</div>
                                    <div class="wb-style-pick" data-style="square" onclick="pickStyle('square')" style="border-radius:3px;background:#00bcd4;">Kare</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <script>
            jQuery(function($){
                var nonce = '<?php echo $nonce; ?>';
                var ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
                var selectedBadgeType = null;

                var typeLabels = <?php
                    $labels = array();
                    foreach ($badge_types as $k => $v) $labels[$k] = $v['title'];
                    echo json_encode($labels);
                ?>;
                var typeIcons = <?php
                    $icons = array();
                    foreach ($badge_types as $k => $v) $icons[$k] = $v['icon'];
                    echo json_encode($icons);
                ?>;

                // Select2: Kategori arama
                $('.wb-cat-select').select2({
                    ajax: {
                        url: ajaxUrl,
                        dataType: 'json',
                        delay: 300,
                        data: function(params){ return {action:'webyaz_search_categories', q:params.term, nonce:nonce}; },
                        processResults: function(data){ return {results:data}; },
                    },
                    minimumInputLength: 0,
                    placeholder: 'Kategori ara ve seç...',
                    allowClear: true,
                    width: '100%'
                });

                // Select2: Ürün arama
                $('.wb-prod-select').select2({
                    ajax: {
                        url: ajaxUrl,
                        dataType: 'json',
                        delay: 300,
                        data: function(params){ return {action:'webyaz_search_products', q:params.term, nonce:nonce}; },
                        processResults: function(data){ return {results:data}; },
                    },
                    minimumInputLength: 2,
                    placeholder: 'Ürün adı yazarak arayın...',
                    allowClear: true,
                    width: '100%'
                });

                // Multi-select'leri hidden input'a çevir (kayıt için virgülle ayrılmış)
                $('form').on('submit', function(e){
                    var hasError = false;
                    $('.wb-cat-select, .wb-prod-select').each(function(){
                        try {
                            var $el = $(this);
                            var val = $el.val();
                            var joined = (val && Array.isArray(val)) ? val.join(',') : (val || '');
                            var nameAttr = $el.attr('name');
                            if (nameAttr) {
                                var cleanName = nameAttr.replace('[]','');
                                $el.after('<input type="hidden" name="'+cleanName+'" value="'+joined+'">');
                                $el.removeAttr('name');
                            }
                        } catch(err) {
                            console.warn('Badge form handler error:', err);
                        }
                    });
                });

                // Rozet kartına tıklayınca önizlemeyi o rozete odakla
                window.selectBadgeForPreview = function(type) {
                    selectedBadgeType = type;
                    // Seçili kartı vurgula
                    $('.wb-card').removeClass('wb-card-selected');
                    $('.wb-card[data-badge-type="'+type+'"]').addClass('wb-card-selected');
                    // Başlık güncelle
                    $('#wb-selected-badge-name').html(typeIcons[type] + ' ' + typeLabels[type] + ' <span style="font-size:11px;color:#999;font-weight:400;">önizleniyor</span>');
                    updateBadgePreview();
                };

                // Stil seçici
                window.pickStyle = function(s){
                    $('#wb-style').val(s);
                    $('.wb-style-pick').removeClass('active');
                    $('.wb-style-pick[data-style="'+s+'"]').addClass('active');
                    updateBadgePreview();
                };

                // İlk yüklemede aktif stili işaretle
                var currentStyle = $('#wb-style').val();
                $('.wb-style-pick[data-style="'+currentStyle+'"]').addClass('active');

                // Dropdown değiştiğinde de stil seçiciyi güncelle
                $('#wb-style').on('change', function(){
                    var s = $(this).val();
                    $('.wb-style-pick').removeClass('active');
                    $('.wb-style-pick[data-style="'+s+'"]').addClass('active');
                });

                // Canlı önizleme — sadece seçili rozet tipini göster
                window.updateBadgePreview = function(){
                    var pos = $('#wb-position').val();
                    var style = $('#wb-style').val();
                    var size = $('#wb-size').val();
                    var fs = parseInt($('#wb-font-size').val()) || 12;
                    var anim = $('#wb-animation').val();

                    var sizes = {small:{p:'4px 10px',f:Math.max(10,fs-2)},medium:{p:'6px 14px',f:fs},large:{p:'8px 20px',f:fs+2}};
                    var sz = sizes[size] || sizes.medium;

                    var radiusMap = {rounded:'8px',pill:'50px',circle:'50%',ribbon:'0 4px 4px 0',square:'3px'};
                    var radius = radiusMap[style] || '8px';

                    // Konum ayarla
                    var $badges = $('#wb-preview-badges');
                    $badges.removeClass('top-left top-right bottom-left bottom-right').addClass(pos);

                    var html = '';

                    // Eğer bir rozet seçilmemişse boş bırak
                    if (!selectedBadgeType) {
                        $badges.html('');
                        return;
                    }

                    var type = selectedBadgeType;
                    var text = $('input[name="webyaz_badges['+type+'_text]"]').val() || typeLabels[type];
                    var bg = $('input[name="webyaz_badges['+type+'_bg]"]').val() || '#333';
                    var color = $('input[name="webyaz_badges['+type+'_text_color]"]').val() || '#fff';

                    var padding = sz.p;
                    var extra = '';
                    if (style === 'circle') {
                        var circleSize = sz.f + 22;
                        extra = 'width:'+circleSize+'px;height:'+circleSize+'px;border-radius:50% !important;font-size:'+Math.max(9,sz.f-2)+'px;padding:0;';
                    }
                    if (style === 'ribbon') {
                        if (pos.indexOf('left') !== -1) {
                            extra += 'border-radius:0 4px 4px 0 !important;';
                        } else {
                            extra += 'border-radius:4px 0 0 4px !important;';
                        }
                    }

                    var animCss = '';
                    if (anim === 'pulse') animCss = 'animation:wb-pulse 2s ease-in-out infinite;';
                    else if (anim === 'bounce') animCss = 'animation:wb-bounce 2s ease-in-out infinite;';
                    else if (anim === 'fade') animCss = 'animation:wb-fade 2s ease-in-out infinite;';

                    html = '<span class="wb-preview-badge" style="background:'+bg+';color:'+color+';padding:'+padding+';font-size:'+sz.f+'px;border-radius:'+radius+';'+extra+animCss+'">'+text+'</span>';

                    $badges.html(html);
                };

                // İlk yükleme — boş başla
                updateBadgePreview();
            });
            </script>

            <style>
                @keyframes wb-pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}
                @keyframes wb-bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-4px)}}
                @keyframes wb-fade{0%,100%{opacity:1}50%{opacity:0.7}}
            </style>
        </div>
        <?php
    }

    // =========================================
    // SAVE HOOK: Convert arrays to comma-separated
    // =========================================
    public static function sanitize_options($input) {
        if (!is_array($input)) {
            return $input;
        }
        // Arrays from Select2 → comma-separated strings
        foreach (self::get_badge_types() as $type => $info) {
            foreach (array('categories', 'products') as $suffix) {
                $key = $type . '_' . $suffix;
                if (isset($input[$key]) && is_array($input[$key])) {
                    $input[$key] = implode(',', array_map('intval', $input[$key]));
                }
            }
        }
        return $input;
    }
}

new Webyaz_Badges();
