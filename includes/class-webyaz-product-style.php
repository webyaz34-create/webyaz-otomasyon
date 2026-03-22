<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Product_Style
{

    private static $option_key = 'webyaz_product_style';

    private static $defaults = array(
        // Urun Gorseli
        'img_radius'        => '0',
        'img_border_width'  => '0',
        'img_border_color'  => '#e0e0e0',
        'img_hover'         => 'zoom',   // zoom, shadow, none
        'img_shadow'        => '0',

        // Sepete Ekle Butonu
        'btn_text'          => '',
        'btn_bg'            => '',
        'btn_color'         => '',
        'btn_radius'        => '',
        'btn_hover_bg'      => '',
        'btn_hover_color'   => '',
        'btn_icon'          => '1',
        'btn_full_width'    => '0',
        'btn_font_size'     => '',
        'btn_padding'       => '',

        // Fiyat
        'price_color'       => '',
        'price_size'        => '',
        'sale_color'        => '',
        'del_color'         => '#999',

        // Baslik
        'title_color'       => '',
        'title_size'        => '',
        'title_weight'      => '',
        'title_align'       => '',

        // SKU & Kategori
        'hide_sku'          => '0',
        'hide_categories'   => '0',
        'hide_tags'         => '0',
        'meta_color'        => '',

        // Miktar Kutusu
        'qty_style'         => 'default', // default, modern
        'qty_radius'        => '',
        'qty_color'         => '',

        // Sekmeler
        'tab_style'         => 'default', // default, modern, pills
        'tab_active_color'  => '',
        'tab_active_bg'     => '',
        'tab_border'        => '1',

        // Genel
        'page_bg'           => '',
        'section_gap'       => '',
        'summary_bg'        => '',
        'summary_radius'    => '',
        'summary_padding'   => '',
    );

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_menu'), 90);
        add_action('admin_init', array($this, 'handle_save'));
        add_action('wp_head', array($this, 'inject_css'), 997);

        // Buton metni filtresi
        $opts = self::get_all();
        if (!empty($opts['btn_text'])) {
            add_filter('woocommerce_product_single_add_to_cart_text', array($this, 'custom_btn_text'));
        }

        // SKU, Kategori, Tag gizleme
        if ($opts['hide_sku'] === '1') {
            add_filter('wc_product_sku_enabled', '__return_false');
        }
        if ($opts['hide_categories'] === '1') {
            add_action('wp', function () {
                remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
            });
        }
    }

    public static function get_all()
    {
        return wp_parse_args(get_option(self::$option_key, array()), self::$defaults);
    }

    public static function get($key)
    {
        $opts = self::get_all();
        return isset($opts[$key]) ? $opts[$key] : '';
    }

    public function custom_btn_text()
    {
        $opts = self::get_all();
        return !empty($opts['btn_text']) ? $opts['btn_text'] : __('Sepete Ekle', 'woocommerce');
    }

    public function add_menu()
    {
        add_submenu_page(
            'webyaz-dashboard',
            'Urun Tasarim',
            'Urun Tasarim',
            'manage_options',
            'webyaz-product-style',
            array($this, 'render_page')
        );
    }

    public function handle_save()
    {
        if (!isset($_POST['webyaz_save_product_style'])) return;
        if (!wp_verify_nonce($_POST['_wpnonce_ps'], 'webyaz_product_style_save')) return;
        if (!current_user_can('manage_options')) return;

        $clean = array();
        foreach (self::$defaults as $key => $default) {
            if (isset($_POST['wz_ps_' . $key])) {
                $clean[$key] = sanitize_text_field($_POST['wz_ps_' . $key]);
            } else {
                // Checkbox'lar: post'ta yoksa '0'
                if (in_array($key, array('btn_icon', 'btn_full_width', 'hide_sku', 'hide_categories', 'hide_tags', 'tab_border', 'img_shadow'))) {
                    $clean[$key] = '0';
                } else {
                    $clean[$key] = $default;
                }
            }
        }

        update_option(self::$option_key, $clean);
        wp_redirect(admin_url('admin.php?page=webyaz-product-style&saved=1'));
        exit;
    }

    // ==========================================
    // FRONTEND CSS ENJEKSIYONU
    // ==========================================
    public function inject_css()
    {
        if (!function_exists('is_product') || !is_product()) return;

        $o = self::get_all();
        $css = '';

        // -- URUN GORSELI --
        if ($o['img_radius'] && $o['img_radius'] !== '0') {
            $css .= ".woocommerce-product-gallery img,
.woocommerce-product-gallery .woocommerce-product-gallery__image,
.product-gallery-slider .slide img,
.product-thumbnails img { border-radius: {$o['img_radius']}px !important; overflow: hidden; }\n";
        }
        if ($o['img_border_width'] && $o['img_border_width'] !== '0') {
            $css .= ".woocommerce-product-gallery img,
.product-gallery-slider .slide img { border: {$o['img_border_width']}px solid {$o['img_border_color']} !important; }\n";
        }
        if ($o['img_hover'] === 'zoom') {
            $css .= ".woocommerce-product-gallery img { transition: transform 0.4s ease !important; }
.woocommerce-product-gallery img:hover { transform: scale(1.05) !important; }\n";
        } elseif ($o['img_hover'] === 'shadow') {
            $css .= ".woocommerce-product-gallery img { transition: box-shadow 0.4s ease !important; }
.woocommerce-product-gallery img:hover { box-shadow: 0 12px 35px rgba(0,0,0,0.15) !important; }\n";
        }
        if ($o['img_shadow'] === '1') {
            $css .= ".woocommerce-product-gallery img { box-shadow: 0 4px 20px rgba(0,0,0,0.1) !important; }\n";
        }

        // -- SEPETE EKLE BUTONU --
        $btn_sel = '.single_add_to_cart_button, .product-info .single_add_to_cart_button';
        $btn = '';
        if ($o['btn_bg'])         $btn .= "background: {$o['btn_bg']} !important; ";
        if ($o['btn_color'])      $btn .= "color: {$o['btn_color']} !important; ";
        if ($o['btn_radius'] !== '') $btn .= "border-radius: {$o['btn_radius']}px !important; ";
        if ($o['btn_font_size'])  $btn .= "font-size: {$o['btn_font_size']}px !important; ";
        if ($o['btn_padding'])    $btn .= "padding: {$o['btn_padding']}px {$o['btn_padding']}px !important; ";
        if ($o['btn_full_width'] === '1') $btn .= "width: 100% !important; display: block !important; text-align: center !important; ";
        $btn .= "transition: all 0.3s ease !important; border: none !important; ";
        if ($btn) {
            $css .= "{$btn_sel} { {$btn} }\n";
        }

        // Buton hover
        $btn_h = '';
        if ($o['btn_hover_bg'])    $btn_h .= "background: {$o['btn_hover_bg']} !important; ";
        if ($o['btn_hover_color']) $btn_h .= "color: {$o['btn_hover_color']} !important; ";
        if ($btn_h) {
            $css .= "{$btn_sel}:hover { {$btn_h} transform: translateY(-2px) !important; box-shadow: 0 6px 20px rgba(0,0,0,0.15) !important; }\n";
        }

        // Buton ikonu
        if ($o['btn_icon'] === '1') {
            $css .= "{$btn_sel}::before { content: '\\1F6D2'; margin-right: 8px; font-size: 1.1em; }\n";
        }

        // -- FIYAT --
        $price_sel = '.product-info .price, .summary .price';
        if ($o['price_color']) $css .= "{$price_sel}, {$price_sel} .woocommerce-Price-amount { color: {$o['price_color']} !important; }\n";
        if ($o['price_size'])  $css .= "{$price_sel} { font-size: {$o['price_size']}px !important; }\n";
        if ($o['sale_color'])  $css .= "{$price_sel} ins .woocommerce-Price-amount { color: {$o['sale_color']} !important; }\n";
        if ($o['del_color'] && $o['del_color'] !== '#999') $css .= "{$price_sel} del { color: {$o['del_color']} !important; opacity: 0.7; }\n";

        // -- BASLIK --
        $title_sel = '.product-title.product_title, .product-info .product-title, .summary .product_title';
        if ($o['title_color'])  $css .= "{$title_sel} { color: {$o['title_color']} !important; }\n";
        if ($o['title_size'])   $css .= "{$title_sel} { font-size: {$o['title_size']}px !important; }\n";
        if ($o['title_weight']) $css .= "{$title_sel} { font-weight: {$o['title_weight']} !important; }\n";
        if ($o['title_align'])  $css .= "{$title_sel} { text-align: {$o['title_align']} !important; }\n";

        // -- META (SKU, Kategori) --
        if ($o['meta_color']) $css .= ".product_meta, .product_meta a { color: {$o['meta_color']} !important; }\n";
        if ($o['hide_tags'] === '1') $css .= ".product_meta .tagged_as { display: none !important; }\n";

        // -- MIKTAR KUTUSU --
        if ($o['qty_style'] === 'modern') {
            $qc = $o['qty_color'] ?: '#446084';
            $qr = $o['qty_radius'] ?: '8';
            $css .= ".quantity { display: inline-flex !important; align-items: center !important; border: 2px solid {$qc} !important; border-radius: {$qr}px !important; overflow: hidden !important; }
.quantity .minus, .quantity .plus { background: {$qc} !important; color: #fff !important; border: none !important; width: 36px !important; height: 36px !important; font-size: 18px !important; cursor: pointer !important; display: flex !important; align-items: center !important; justify-content: center !important; }
.quantity .minus:hover, .quantity .plus:hover { opacity: 0.85 !important; }
.quantity .qty { border: none !important; text-align: center !important; width: 50px !important; font-size: 16px !important; font-weight: 600 !important; }\n";
        } else {
            if ($o['qty_radius']) $css .= ".quantity .qty { border-radius: {$o['qty_radius']}px !important; }\n";
            if ($o['qty_color'])  $css .= ".quantity .qty:focus { border-color: {$o['qty_color']} !important; box-shadow: 0 0 0 2px {$o['qty_color']}22 !important; }\n";
        }

        // -- SEKMELER --
        if ($o['tab_style'] === 'modern') {
            $tac = $o['tab_active_color'] ?: '#446084';
            $tab = $o['tab_active_bg'] ?: '#f5f5f5';
            $css .= ".woocommerce-tabs .tabs li a, .product-page-accordian .accordian-title { border: none !important; background: transparent !important; padding: 12px 20px !important; font-weight: 600 !important; transition: all 0.3s ease !important; border-radius: 8px 8px 0 0 !important; }
.woocommerce-tabs .tabs li.active a, .product-page-accordian .active .accordian-title { background: {$tab} !important; color: {$tac} !important; border-bottom: 3px solid {$tac} !important; }\n";
        } elseif ($o['tab_style'] === 'pills') {
            $tac = $o['tab_active_color'] ?: '#ffffff';
            $tab = $o['tab_active_bg'] ?: '#446084';
            $css .= ".woocommerce-tabs .tabs { border: none !important; gap: 8px !important; display: flex !important; }
.woocommerce-tabs .tabs li a { border: none !important; background: #f0f0f0 !important; padding: 10px 20px !important; border-radius: 25px !important; font-weight: 600 !important; transition: all 0.3s ease !important; }
.woocommerce-tabs .tabs li.active a { background: {$tab} !important; color: {$tac} !important; }\n";
        } else {
            if ($o['tab_active_color']) $css .= ".woocommerce-tabs .tabs li.active a { color: {$o['tab_active_color']} !important; }\n";
            if ($o['tab_active_bg'])    $css .= ".woocommerce-tabs .tabs li.active a { background: {$o['tab_active_bg']} !important; }\n";
        }
        if ($o['tab_border'] === '0') {
            $css .= ".woocommerce-tabs .tabs, .woocommerce-tabs { border: none !important; }\n";
        }

        // -- GENEL --
        if ($o['page_bg'])       $css .= ".single-product .product-container, .single-product .product-main { background: {$o['page_bg']} !important; }\n";
        if ($o['section_gap'])   $css .= ".product-info > *, .summary > * { margin-bottom: {$o['section_gap']}px !important; }\n";
        if ($o['summary_bg']) {
            $sr = $o['summary_radius'] ?: '0';
            $sp = $o['summary_padding'] ?: '0';
            $css .= ".product-info .product-info-inner, .summary { background: {$o['summary_bg']} !important; border-radius: {$sr}px !important; padding: {$sp}px !important; }\n";
        }

        if (empty($css)) return;

        echo "\n<style id=\"webyaz-product-style\">\n/* Webyaz Urun Sayfasi Ozellestirme */\n" . $css . "</style>\n";
    }

    // ==========================================
    // ADMIN SAYFA RENDER
    // ==========================================
    public function render_page()
    {
        $o = self::get_all();

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
                <h1 style="margin:0 0 5px;font-size:26px;font-weight:700;">Urun Sayfasi Tasarim</h1>
                <p style="margin:0;opacity:.85;font-size:14px;">Tek urun sayfasini admin panelden gorusel olarak ozellestirin. Kod bilgisi gerektirmez!</p>
            </div>

            <?php if (isset($_GET['saved'])): ?>
                <div style="background:#e6f9e6;color:#22863a;border:1px solid #b7e4c7;padding:12px 18px;border-radius:8px;margin-bottom:15px;font-size:14px;">
                    ✅ Ayarlar basariyla kaydedildi! Degisiklikleri gormek icin urun sayfanizi yenileyin.
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('webyaz_product_style_save', '_wpnonce_ps'); ?>

                <!-- URUN GORSELI -->
                <?php $this->render_section_start('🖼️', 'Urun Gorseli', 'Urun resminin koselerini yuvarlatin, cerceve ekleyin, hover efekti secin.'); ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <?php
                    $this->render_slider('img_radius', 'Kose Yuvarligi', $o['img_radius'], 0, 50, 'px');
                    $this->render_slider('img_border_width', 'Kenarlik Kalinligi', $o['img_border_width'], 0, 10, 'px');
                    $this->render_color('img_border_color', 'Kenarlik Rengi', $o['img_border_color']);
                    $this->render_select('img_hover', 'Hover Efekti', $o['img_hover'], array(
                        'none'   => 'Yok',
                        'zoom'   => 'Yakınlaştırma (Zoom)',
                        'shadow' => 'Gölge Efekti',
                    ));
                    $this->render_toggle('img_shadow', 'Sabit Golge', $o['img_shadow']);
                    ?>
                </div>
                <?php $this->render_section_end(); ?>

                <!-- SEPETE EKLE BUTONU -->
                <?php $this->render_section_start('🛒', 'Sepete Ekle Butonu', 'Buton metnini, rengini, seklini ve hover efektini ayarlayin.'); ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <?php
                    $this->render_text('btn_text', 'Buton Metni', $o['btn_text'], 'Ornek: Hemen Satin Al');
                    $this->render_slider('btn_radius', 'Kose Yuvarligi', $o['btn_radius'], 0, 50, 'px');
                    $this->render_color('btn_bg', 'Arkaplan Rengi', $o['btn_bg']);
                    $this->render_color('btn_color', 'Yazi Rengi', $o['btn_color']);
                    $this->render_color('btn_hover_bg', 'Hover Arkaplan', $o['btn_hover_bg']);
                    $this->render_color('btn_hover_color', 'Hover Yazi Rengi', $o['btn_hover_color']);
                    $this->render_text('btn_font_size', 'Yazi Boyutu', $o['btn_font_size'], '16');
                    $this->render_text('btn_padding', 'Ic Bosluk (Padding)', $o['btn_padding'], '14');
                    $this->render_toggle('btn_icon', 'Sepet Ikonu Goster', $o['btn_icon']);
                    $this->render_toggle('btn_full_width', 'Tam Genislik', $o['btn_full_width']);
                    ?>
                </div>
                <?php $this->render_section_end(); ?>

                <!-- FIYAT -->
                <?php $this->render_section_start('💰', 'Fiyat Alani', 'Fiyat rengini, boyutunu ve indirimli fiyat stilini ayarlayin.'); ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <?php
                    $this->render_color('price_color', 'Fiyat Rengi', $o['price_color']);
                    $this->render_text('price_size', 'Fiyat Boyutu', $o['price_size'], '24');
                    $this->render_color('sale_color', 'Indirimli Fiyat Rengi', $o['sale_color']);
                    $this->render_color('del_color', 'Eski Fiyat Rengi', $o['del_color']);
                    ?>
                </div>
                <?php $this->render_section_end(); ?>

                <!-- BASLIK -->
                <?php $this->render_section_start('📝', 'Urun Basligi', 'Baslik rengini, boyutunu, kalinligini ve hizasini ayarlayin.'); ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <?php
                    $this->render_color('title_color', 'Baslik Rengi', $o['title_color']);
                    $this->render_text('title_size', 'Baslik Boyutu', $o['title_size'], '28');
                    $this->render_select('title_weight', 'Kalinlik', $o['title_weight'], array(
                        ''    => 'Varsayilan',
                        '400' => 'Normal (400)',
                        '600' => 'Kalin (600)',
                        '700' => 'Bold (700)',
                        '900' => 'Ekstra Bold (900)',
                    ));
                    $this->render_select('title_align', 'Hizalama', $o['title_align'], array(
                        ''       => 'Varsayilan',
                        'left'   => 'Sola',
                        'center' => 'Ortaya',
                        'right'  => 'Saga',
                    ));
                    ?>
                </div>
                <?php $this->render_section_end(); ?>

                <!-- SKU & META -->
                <?php $this->render_section_start('🏷️', 'SKU & Meta Bilgileri', 'SKU, kategori ve etiketleri gizleyin veya rengini degistirin.'); ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <?php
                    $this->render_toggle('hide_sku', 'SKU Gizle', $o['hide_sku']);
                    $this->render_toggle('hide_categories', 'Kategorileri Gizle', $o['hide_categories']);
                    $this->render_toggle('hide_tags', 'Etiketleri Gizle', $o['hide_tags']);
                    $this->render_color('meta_color', 'Meta Metin Rengi', $o['meta_color']);
                    ?>
                </div>
                <?php $this->render_section_end(); ?>

                <!-- MIKTAR KUTUSU -->
                <?php $this->render_section_start('🔢', 'Miktar Kutusu', 'Miktar secici stilini ve rengini ayarlayin.'); ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <?php
                    $this->render_select('qty_style', 'Stil', $o['qty_style'], array(
                        'default' => 'Varsayilan',
                        'modern'  => 'Modern (± Butonlu)',
                    ));
                    $this->render_slider('qty_radius', 'Kose Yuvarligi', $o['qty_radius'], 0, 25, 'px');
                    $this->render_color('qty_color', 'Vurgu Rengi', $o['qty_color']);
                    ?>
                </div>
                <?php $this->render_section_end(); ?>

                <!-- SEKMELER -->
                <?php $this->render_section_start('📑', 'Urun Sekmeleri', 'Aciklama, Degerlendirme gibi sekmelerin stilini ayarlayin.'); ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <?php
                    $this->render_select('tab_style', 'Sekme Stili', $o['tab_style'], array(
                        'default' => 'Varsayilan',
                        'modern'  => 'Modern (Alt Cizgi)',
                        'pills'   => 'Hap (Pills)',
                    ));
                    $this->render_color('tab_active_color', 'Aktif Sekme Yazi', $o['tab_active_color']);
                    $this->render_color('tab_active_bg', 'Aktif Sekme Arkaplan', $o['tab_active_bg']);
                    $this->render_toggle('tab_border', 'Kenarlik Goster', $o['tab_border']);
                    ?>
                </div>
                <?php $this->render_section_end(); ?>

                <!-- GENEL -->
                <?php $this->render_section_start('⚙️', 'Genel Ayarlar', 'Sayfa arka plani, bolum aralik ve ozet alani stilini ayarlayin.'); ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <?php
                    $this->render_color('page_bg', 'Sayfa Arkaplan Rengi', $o['page_bg']);
                    $this->render_text('section_gap', 'Bolum Arasi Bosluk', $o['section_gap'], '15');
                    $this->render_color('summary_bg', 'Ozet Alani Arkaplan', $o['summary_bg']);
                    $this->render_slider('summary_radius', 'Ozet Kose Yuvarligi', $o['summary_radius'], 0, 30, 'px');
                    $this->render_text('summary_padding', 'Ozet Ic Bosluk', $o['summary_padding'], '20');
                    ?>
                </div>
                <?php $this->render_section_end(); ?>

                <!-- SIFIRLA & KAYDET -->
                <div style="display:flex;gap:12px;margin-top:25px;">
                    <button type="submit" name="webyaz_save_product_style" value="1" style="background:<?php echo $primary; ?>;color:#fff;border:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;transition:opacity .2s;">
                        <span class="dashicons dashicons-saved" style="vertical-align:middle;margin-right:6px;"></span>Ayarlari Kaydet
                    </button>
                    <button type="button" onclick="if(confirm('Tum ayarlar sifirlanacak. Emin misiniz?')){document.querySelectorAll('input[type=color]').forEach(function(i){i.value='#000000'});document.querySelectorAll('input[type=range]').forEach(function(i){i.value=0;i.dispatchEvent(new Event('input'))});document.querySelectorAll('input[type=text]').forEach(function(i){i.value=''});document.querySelectorAll('select').forEach(function(s){s.selectedIndex=0});}" style="background:#fff;color:#d32f2f;border:2px solid #d32f2f;padding:14px 28px;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;">
                        <span class="dashicons dashicons-undo" style="vertical-align:middle;margin-right:6px;"></span>Sifirla
                    </button>
                </div>
            </form>
        </div>

        <style>
            .wz-ps-section {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 12px;
                margin-bottom: 20px;
                overflow: hidden;
                transition: box-shadow 0.2s;
            }

            .wz-ps-section:hover {
                box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            }

            .wz-ps-header {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 18px 22px;
                cursor: pointer;
                user-select: none;
                border-bottom: 1px solid #f0f0f0;
            }

            .wz-ps-header:hover {
                background: #fafafa;
            }

            .wz-ps-body {
                padding: 20px 22px;
            }

            .wz-ps-field {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }

            .wz-ps-field label {
                font-size: 13px;
                font-weight: 600;
                color: #333;
            }

            .wz-ps-field input[type="text"],
            .wz-ps-field select {
                border: 1px solid #ddd;
                border-radius: 6px;
                padding: 9px 12px;
                font-size: 14px;
                transition: border-color 0.2s;
            }

            .wz-ps-field input[type="text"]:focus,
            .wz-ps-field select:focus {
                border-color: <?php echo $primary; ?>;
                outline: none;
                box-shadow: 0 0 0 2px <?php echo $primary; ?>18;
            }

            .wz-ps-color-wrap {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .wz-ps-color-wrap input[type="color"] {
                width: 40px;
                height: 34px;
                border: 2px solid #ddd;
                border-radius: 6px;
                cursor: pointer;
                padding: 2px;
            }

            .wz-ps-slider-wrap {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .wz-ps-slider-wrap input[type="range"] {
                flex: 1;
                accent-color: <?php echo $primary; ?>;
                height: 6px;
            }

            .wz-ps-slider-val {
                min-width: 45px;
                text-align: center;
                background: #f0f0f0;
                border-radius: 4px;
                padding: 4px 8px;
                font-size: 13px;
                font-weight: 600;
                color: #333;
            }

            .wz-ps-toggle {
                position: relative;
                display: inline-block;
                width: 44px;
                height: 24px;
            }

            .wz-ps-toggle input {
                display: none;
            }

            .wz-ps-toggle-slider {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: #ccc;
                border-radius: 24px;
                transition: 0.3s;
                cursor: pointer;
            }

            .wz-ps-toggle-slider::after {
                content: '';
                position: absolute;
                top: 3px;
                left: 3px;
                width: 18px;
                height: 18px;
                background: #fff;
                border-radius: 50%;
                transition: 0.3s;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
            }

            .wz-ps-toggle input:checked+.wz-ps-toggle-slider {
                background: #4caf50;
            }

            .wz-ps-toggle input:checked+.wz-ps-toggle-slider::after {
                left: 23px;
            }
        </style>
    <?php
    }

    // ==========================================
    // FORM YARDIMCI FONKSIYONLARI
    // ==========================================
    private function render_section_start($icon, $title, $desc)
    {
    ?>
        <div class="wz-ps-section">
            <div class="wz-ps-header" onclick="var b=this.nextElementSibling;b.style.display=b.style.display==='none'?'block':'block'">
                <span style="font-size:22px;"><?php echo $icon; ?></span>
                <div style="flex:1;">
                    <div style="font-size:15px;font-weight:700;color:#1a1a1a;"><?php echo $title; ?></div>
                    <div style="font-size:12px;color:#888;margin-top:2px;"><?php echo $desc; ?></div>
                </div>
            </div>
            <div class="wz-ps-body">
    <?php
    }

    private function render_section_end()
    {
    ?>
            </div>
        </div>
    <?php
    }

    private function render_text($key, $label, $value, $placeholder = '')
    {
    ?>
        <div class="wz-ps-field">
            <label><?php echo $label; ?></label>
            <input type="text" name="wz_ps_<?php echo $key; ?>" value="<?php echo esc_attr($value); ?>" placeholder="<?php echo esc_attr($placeholder); ?>">
        </div>
    <?php
    }

    private function render_color($key, $label, $value)
    {
        $val = !empty($value) ? $value : '#000000';
        $has = !empty($value) && $value !== '#000000';
    ?>
        <div class="wz-ps-field">
            <label><?php echo $label; ?></label>
            <div class="wz-ps-color-wrap">
                <input type="color" id="wz_clr_<?php echo $key; ?>" value="<?php echo esc_attr($val); ?>" onchange="document.getElementById('wz_txt_<?php echo $key; ?>').value=this.value">
                <input type="text" id="wz_txt_<?php echo $key; ?>" name="wz_ps_<?php echo $key; ?>" value="<?php echo esc_attr($value); ?>" placeholder="Varsayilan" style="flex:1;" onchange="if(this.value)document.getElementById('wz_clr_<?php echo $key; ?>').value=this.value">
                <?php if ($has): ?>
                    <button type="button" onclick="var t=document.getElementById('wz_txt_<?php echo $key; ?>');t.value='';document.getElementById('wz_clr_<?php echo $key; ?>').value='#000000';" style="background:none;border:1px solid #ddd;border-radius:4px;padding:4px 8px;cursor:pointer;font-size:11px;color:#999;" title="Temizle">✕</button>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }

    private function render_slider($key, $label, $value, $min, $max, $unit)
    {
        $val = $value !== '' ? $value : $min;
    ?>
        <div class="wz-ps-field">
            <label><?php echo $label; ?></label>
            <div class="wz-ps-slider-wrap">
                <input type="range" min="<?php echo $min; ?>" max="<?php echo $max; ?>" value="<?php echo esc_attr($val); ?>" oninput="this.nextElementSibling.textContent=this.value+'<?php echo $unit; ?>';this.parentElement.querySelector('input[type=hidden]').value=this.value">
                <span class="wz-ps-slider-val"><?php echo esc_html($val) . $unit; ?></span>
                <input type="hidden" name="wz_ps_<?php echo $key; ?>" value="<?php echo esc_attr($val); ?>">
            </div>
        </div>
    <?php
    }

    private function render_select($key, $label, $value, $options)
    {
    ?>
        <div class="wz-ps-field">
            <label><?php echo $label; ?></label>
            <select name="wz_ps_<?php echo $key; ?>">
                <?php foreach ($options as $v => $text): ?>
                    <option value="<?php echo esc_attr($v); ?>" <?php selected($value, $v); ?>><?php echo esc_html($text); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php
    }

    private function render_toggle($key, $label, $value)
    {
    ?>
        <div class="wz-ps-field" style="flex-direction:row;align-items:center;justify-content:space-between;">
            <label style="margin:0;"><?php echo $label; ?></label>
            <label class="wz-ps-toggle">
                <input type="hidden" name="wz_ps_<?php echo $key; ?>" value="0">
                <input type="checkbox" name="wz_ps_<?php echo $key; ?>" value="1" <?php checked($value, '1'); ?>>
                <span class="wz-ps-toggle-slider"></span>
            </label>
        </div>
<?php
    }
}

new Webyaz_Product_Style();
