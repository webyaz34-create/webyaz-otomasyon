<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Shop_Turkish {

    public function __construct() {
        add_filter('gettext', array($this, 'translate'), 20, 3);
        add_filter('ngettext', array($this, 'translate_plural'), 20, 5);
        add_action('wp_head', array($this, 'custom_css'));
        add_filter('widget_title', array($this, 'translate_widget_titles'), 20);
    }

    public function translate_widget_titles($title) {
        $map = array(
            'Browse' => 'Kategoriler',
            'Product categories' => 'Kategoriler',
            'Product Categories' => 'Kategoriler',
            'Filter by price' => 'Fiyata Gore Filtrele',
            'Filter By Price' => 'Fiyata Gore Filtrele',
            'Recently Viewed' => 'Son Bakilanlar',
            'Recently viewed' => 'Son Bakilanlar',
            'Recent Products' => 'Son Urunler',
            'Top Rated Products' => 'En Cok Begenilen',
            'Best Sellers' => 'Cok Satanlar',
            'On Sale' => 'Indirimdekiler',
            'Products' => 'Urunler',
            'Product tags' => 'Urun Etiketleri',
            'Product Tag' => 'Urun Etiketi',
            'Cart' => 'Sepet',
            'Filter by attribute' => 'Nitelige Gore Filtrele',
            'Active filters' => 'Aktif Filtreler',
            'Average rating' => 'Ortalama Puan',
            'Filter by rating' => 'Puana Gore Filtrele',
            'Search' => 'Ara',
        );
        return isset($map[$title]) ? $map[$title] : $title;
    }

    public function translate($translated, $text, $domain) {
        if ($domain !== 'woocommerce') return $translated;

        $map = array(
            'Sale!' => 'Indirim!',
            'Add to cart' => 'Sepete Ekle',
            'Add to Cart' => 'Sepete Ekle',
            'View cart' => 'Sepeti Gor',
            'Read more' => 'Detaylar',
            'Select options' => 'Secenekleri Sec',
            'Out of stock' => 'Tukendi',
            'In stock' => 'Stokta',
            'On backorder' => 'On Sipariste',
            'Description' => 'Aciklama',
            'Additional information' => 'Ek Bilgiler',
            'Reviews' => 'Yorumlar',
            'Related products' => 'Benzer Urunler',
            'You may also like&hellip;' => 'Bunlari da Begenebilirsiniz',
            'Search products&hellip;' => 'Urun Ara...',
            'No products were found matching your selection.' => 'Aramanizla eslesen urun bulunamadi.',
            'Sort by popularity' => 'Populerlige Gore',
            'Sort by average rating' => 'Puana Gore',
            'Sort by latest' => 'Yeniye Gore',
            'Sort by price: low to high' => 'Fiyat: Dusukten Yuksege',
            'Sort by price: high to low' => 'Fiyat: Yuksekten Dusuge',
            'Default sorting' => 'Varsayilan Siralama',
            'Show all' => 'Tumunu Goster',
            'Showing all' => 'Tumu gosteriliyor',
            'Showing the single result' => 'Tek sonuc gosteriliyor',
            'Price' => 'Fiyat',
            'Availability' => 'Stok Durumu',
            'SKU' => 'Stok Kodu',
            'Category' => 'Kategori',
            'Categories' => 'Kategoriler',
            'Tag' => 'Etiket',
            'Tags' => 'Etiketler',
            'Product' => 'Urun',
            'Products' => 'Urunler',
            'Subtotal' => 'Ara Toplam',
            'Total' => 'Toplam',
            'Coupon' => 'Kupon',
            'Apply coupon' => 'Kuponu Uygula',
            'Update cart' => 'Sepeti Guncelle',
            'Cart totals' => 'Sepet Toplami',
            'Proceed to checkout' => 'Odemeye Gec',
            'Your cart is currently empty.' => 'Sepetiniz bos.',
            'Return to shop' => 'Magazaya Don',
            'Billing details' => 'Fatura Bilgileri',
            'Ship to a different address?' => 'Farkli adrese gonder?',
            'Order notes' => 'Siparis Notlari',
            'Place order' => 'Siparisi Tamamla',
            'Your order' => 'Siparisininz',
            'Thank you. Your order has been received.' => 'Tesekkurler! Siparisniniz alindi.',
            'Order details' => 'Siparis Detaylari',
            'Customer details' => 'Musteri Bilgileri',
            'Billing address' => 'Fatura Adresi',
            'Shipping address' => 'Teslimat Adresi',
            'Filter' => 'Filtrele',
            'Filter by price' => 'Fiyata Gore Filtrele',
            'Min price' => 'Min fiyat',
            'Max price' => 'Maks fiyat',
            'Search Results for: %s' => '%s icin Arama Sonuclari',
            'Home' => 'Anasayfa',
            'Shop' => 'Magaza',
            'My account' => 'Hesabim',
            'Checkout' => 'Odeme',
            'Cart' => 'Sepet',
            'Login' => 'Giris Yap',
            'Register' => 'Kayit Ol',
            'Username or email address' => 'Kullanici adi veya e-posta',
            'Password' => 'Sifre',
            'Remember me' => 'Beni Hatirla',
            'Lost your password?' => 'Sifreni mi unuttun?',
            'Showing %1$d&ndash;%2$d of %3$d results' => '%3$d urun icinden %1$d-%2$d gosteriliyor',
        );

        return isset($map[$text]) ? $map[$text] : $translated;
    }

    public function translate_plural($translated, $single, $plural, $number, $domain) {
        if ($domain !== 'woocommerce') return $translated;

        if (strpos($single, '%s customer review') !== false || strpos($single, 'customer review') !== false) {
            return $number . ' musteri yorumu';
        }
        return $translated;
    }

    public function custom_css() {
        if (!function_exists('is_woocommerce')) return;
        if (!is_woocommerce() && !is_cart() && !is_checkout() && !is_account_page()) return;
        ?>
        <style>
            /* Siralama dropdown fix */
            .woocommerce .woocommerce-ordering select,
            .woocommerce .woocommerce-result-count{font-family:'Roboto',sans-serif;font-size:13px;}
            .woocommerce .woocommerce-ordering{position:relative;z-index:10;width:auto !important;max-width:none !important;float:right;}
            .woocommerce .woocommerce-ordering select{padding:12px 40px 12px 16px;border:2px solid #e0e0e0;border-radius:10px;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='%23666'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") no-repeat right 14px center;-webkit-appearance:none;-moz-appearance:none;appearance:none;cursor:pointer;transition:all 0.2s;width:auto !important;min-width:0 !important;max-width:none !important;font-weight:500;color:#333;font-size:14px !important;line-height:1.4;white-space:nowrap;overflow:visible !important;text-overflow:clip !important;}
            .woocommerce .woocommerce-ordering select:focus{border-color:var(--webyaz-primary,#446084);outline:none;box-shadow:0 0 0 3px rgba(68,96,132,0.1);}
            .woocommerce .woocommerce-result-count{color:#888;font-weight:500;}

            /* Sidebar widget tasarimi */
            .sidebar .widget,
            .shop-sidebar .widget,
            #secondary .widget,
            .is-sidebar .widget{
                background:#fff;
                border-radius:14px;
                padding:22px;
                margin-bottom:20px;
                box-shadow:0 2px 12px rgba(0,0,0,0.04);
                border:1px solid rgba(0,0,0,0.05);
                transition:box-shadow 0.3s;
            }
            .sidebar .widget:hover,
            .shop-sidebar .widget:hover,
            #secondary .widget:hover,
            .is-sidebar .widget:hover{
                box-shadow:0 4px 20px rgba(0,0,0,0.08);
            }

            /* Widget basliklari */
            .sidebar .widget .widget-title,
            .shop-sidebar .widget .widget-title,
            #secondary .widget .widget-title,
            .is-sidebar .widget .widget-title,
            .sidebar .widget .widgettitle,
            .shop-sidebar .widget .widgettitle,
            .is-sidebar .widget .widgettitle{
                font-family:'Roboto',sans-serif !important;
                font-size:15px !important;
                font-weight:700 !important;
                color:#1a1a1a !important;
                text-transform:none !important;
                letter-spacing:0 !important;
                margin-bottom:14px !important;
                padding-bottom:10px !important;
                border-bottom:2px solid var(--webyaz-primary,#446084) !important;
                position:relative;
            }

            /* Kategori listesi */
            .widget_product_categories ul,
            .widget_layered_nav ul,
            .sidebar .widget ul{
                list-style:none !important;
                padding:0 !important;
                margin:0 !important;
            }
            .widget_product_categories ul li,
            .widget_layered_nav ul li,
            .sidebar .widget ul li{
                margin:0 !important;
                padding:0 !important;
                border-bottom:1px solid #f5f5f5;
            }
            .widget_product_categories ul li:last-child,
            .widget_layered_nav ul li:last-child,
            .sidebar .widget ul li:last-child{
                border-bottom:none;
            }
            .widget_product_categories ul li a,
            .widget_layered_nav ul li a,
            .sidebar .widget ul li a{
                font-family:'Roboto',sans-serif !important;
                font-size:14px !important;
                color:#444 !important;
                text-decoration:none !important;
                display:flex !important;
                align-items:center !important;
                justify-content:space-between !important;
                padding:10px 8px !important;
                border-radius:8px !important;
                transition:all 0.2s !important;
            }
            .widget_product_categories ul li a:hover,
            .widget_layered_nav ul li a:hover,
            .sidebar .widget ul li a:hover{
                background:rgba(68,96,132,0.06) !important;
                color:var(--webyaz-primary,#446084) !important;
                padding-left:14px !important;
            }
            .widget_product_categories ul li .count,
            .widget_layered_nav ul li .count{
                background:var(--webyaz-primary,#446084) !important;
                color:#fff !important;
                border-radius:20px !important;
                padding:2px 10px !important;
                font-size:11px !important;
                font-weight:600 !important;
                min-width:24px;
                text-align:center;
            }

            /* Alt kategoriler */
            .widget_product_categories ul li ul{
                padding-left:12px !important;
                margin-top:0 !important;
            }
            .widget_product_categories ul li ul li a{
                font-size:13px !important;
                padding:8px 8px !important;
            }

            /* Toggle ok isareti */
            .widget_product_categories .cat-parent>.cat-item-toggle,
            .widget_product_categories .cat-parent>a+.toggle{
                color:var(--webyaz-primary,#446084);
            }

            /* Fiyat filtresi */
            .woocommerce .widget_price_filter .price_slider_wrapper .ui-widget-content{background:#e8e8e8;border-radius:6px;height:6px;}
            .woocommerce .widget_price_filter .ui-slider .ui-slider-range{background:linear-gradient(90deg,var(--webyaz-primary,#446084),var(--webyaz-secondary,#d26e4b));border-radius:6px;}
            .woocommerce .widget_price_filter .ui-slider .ui-slider-handle{background:var(--webyaz-primary,#446084);border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,0.2);width:18px;height:18px;top:-7px;}
            .woocommerce .widget_price_filter .price_slider_amount .button{
                background:var(--webyaz-primary,#446084) !important;
                color:#fff !important;
                border:none !important;
                border-radius:8px !important;
                padding:8px 20px !important;
                font-family:'Roboto',sans-serif !important;
                font-weight:600 !important;
                font-size:13px !important;
                transition:opacity 0.2s !important;
            }
            .woocommerce .widget_price_filter .price_slider_amount .button:hover{opacity:0.85;}
            .woocommerce .widget_price_filter .price_slider_amount .price_label{
                font-family:'Roboto',sans-serif;font-size:13px;font-weight:500;color:#555;
            }

            /* Genel */
            .woocommerce .price{font-family:'Roboto',sans-serif;font-weight:700;}
            .woocommerce .price del{opacity:0.5;font-weight:400;font-size:0.85em;}
            .woocommerce .price ins{text-decoration:none;color:var(--webyaz-secondary,#d26e4b);}
            .woocommerce .button, .woocommerce a.button{font-family:'Roboto',sans-serif;font-weight:600;border-radius:8px;transition:all 0.2s;}
            .woocommerce .star-rating span::before{color:var(--webyaz-secondary,#d26e4b);}
            .woocommerce-breadcrumb{font-family:'Roboto',sans-serif;font-size:13px;color:#888;}
            .woocommerce-breadcrumb a{color:var(--webyaz-primary,#446084);text-decoration:none;}
            .woocommerce-pagination ul.page-numbers{display:flex;gap:6px;justify-content:center;margin:20px 0;}
            .woocommerce-pagination ul.page-numbers li a,.woocommerce-pagination ul.page-numbers li span{padding:8px 14px;border-radius:8px;font-size:14px;font-weight:600;font-family:'Roboto',sans-serif;border:1px solid #e0e0e0;color:#555;text-decoration:none;transition:all 0.2s;}
            .woocommerce-pagination ul.page-numbers li span.current,.woocommerce-pagination ul.page-numbers li a:hover{background:var(--webyaz-primary,#446084);color:#fff;border-color:var(--webyaz-primary,#446084);}
        </style>
        <?php
    }
}

new Webyaz_Shop_Turkish();
