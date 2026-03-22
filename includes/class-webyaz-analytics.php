<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Analytics
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_head', array($this, 'output_gtag'), 1);
        add_action('wp_head', array($this, 'output_gtm_head'), 2);
        add_action('wp_body_open', array($this, 'output_gtm_body'));
        add_action('wp_head', array($this, 'output_search_console'), 3);
        add_action('wp_head', array($this, 'output_facebook_pixel'), 4);
        add_action('wp_head', array($this, 'output_custom_head'), 99);
        add_action('wp_footer', array($this, 'output_custom_footer'), 99);

        // WooCommerce e-commerce tracking
        add_action('woocommerce_thankyou', array($this, 'purchase_tracking'));
        add_action('woocommerce_after_add_to_cart_button', array($this, 'add_to_cart_tracking'));
    }

    public function add_submenu()
    {
        add_submenu_page(
            'webyaz-dashboard',
            'Analytics & Izleme',
            'Analytics',
            'manage_options',
            'webyaz-analytics',
            array($this, 'render_admin')
        );
    }

    public function register_settings()
    {
        register_setting('webyaz_analytics_group', 'webyaz_analytics');
    }

    private static function get_defaults()
    {
        return array(
            'ga_id'              => '',
            'gtm_id'             => '',
            'search_console'     => '',
            'facebook_pixel'     => '',
            'ecommerce_tracking' => '1',
            'custom_head'        => '',
            'custom_footer'      => '',
        );
    }

    public static function get($key)
    {
        $opts = wp_parse_args(get_option('webyaz_analytics', array()), self::get_defaults());
        return isset($opts[$key]) ? $opts[$key] : '';
    }

    // --- Google Analytics 4 (gtag.js) ---
    public function output_gtag()
    {
        $ga_id = self::get('ga_id');
        if (empty($ga_id) || is_admin()) return;
?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga_id); ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];

            function gtag() {
                dataLayer.push(arguments);
            }
            gtag('js', new Date());
            gtag('config', '<?php echo esc_js($ga_id); ?>');
        </script>
    <?php
    }

    // --- Google Tag Manager (head) ---
    public function output_gtm_head()
    {
        $gtm_id = self::get('gtm_id');
        if (empty($gtm_id) || is_admin()) return;
    ?>
        <script>
            (function(w, d, s, l, i) {
                w[l] = w[l] || [];
                w[l].push({
                    'gtm.start': new Date().getTime(),
                    event: 'gtm.js'
                });
                var f = d.getElementsByTagName(s)[0],
                    j = d.createElement(s),
                    dl = l != 'dataLayer' ? '&l=' + l : '';
                j.async = true;
                j.src =
                    'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
                f.parentNode.insertBefore(j, f);
            })(window, document, 'script', 'dataLayer', '<?php echo esc_js($gtm_id); ?>');
        </script>
    <?php
    }

    // --- Google Tag Manager (body) ---
    public function output_gtm_body()
    {
        $gtm_id = self::get('gtm_id');
        if (empty($gtm_id) || is_admin()) return;
    ?>
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($gtm_id); ?>"
                height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <?php
    }

    // --- Google Search Console ---
    public function output_search_console()
    {
        $code = self::get('search_console');
        if (empty($code) || is_admin()) return;
        if (strpos($code, 'content=') !== false) {
            echo $code . "\n";
        } else {
            echo '<meta name="google-site-verification" content="' . esc_attr($code) . '">' . "\n";
        }
    }

    // --- Facebook Pixel ---
    public function output_facebook_pixel()
    {
        $pixel = self::get('facebook_pixel');
        if (empty($pixel) || is_admin()) return;
    ?>
        <script>
            ! function(f, b, e, v, n, t, s) {
                if (f.fbq) return;
                n = f.fbq = function() {
                    n.callMethod ?
                        n.callMethod.apply(n, arguments) : n.queue.push(arguments)
                };
                if (!f._fbq) f._fbq = n;
                n.push = n;
                n.loaded = !0;
                n.version = '2.0';
                n.queue = [];
                t = b.createElement(e);
                t.async = !0;
                t.src = v;
                s = b.getElementsByTagName(e)[0];
                s.parentNode.insertBefore(t, s)
            }(window,
                document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '<?php echo esc_js($pixel); ?>');
            fbq('track', 'PageView');
        </script>
        <noscript><img height="1" width="1" style="display:none"
                src="https://www.facebook.com/tr?id=<?php echo esc_attr($pixel); ?>&ev=PageView&noscript=1" /></noscript>
    <?php
    }

    // --- Custom head/footer codes ---
    public function output_custom_head()
    {
        $code = self::get('custom_head');
        if (!empty($code) && !is_admin()) echo $code . "\n";
    }

    public function output_custom_footer()
    {
        $code = self::get('custom_footer');
        if (!empty($code) && !is_admin()) echo $code . "\n";
    }

    // --- E-Commerce: Purchase tracking ---
    public function purchase_tracking($order_id)
    {
        if (self::get('ecommerce_tracking') !== '1') return;
        $ga_id = self::get('ga_id');
        if (empty($ga_id)) return;

        $order = wc_get_order($order_id);
        if (!$order) return;
    ?>
        <script>
            gtag('event', 'purchase', {
                transaction_id: '<?php echo esc_js($order->get_order_number()); ?>',
                value: <?php echo floatval($order->get_total()); ?>,
                currency: '<?php echo esc_js($order->get_currency()); ?>',
                items: [
                    <?php foreach ($order->get_items() as $item): ?> {
                            item_id: '<?php echo esc_js($item->get_product_id()); ?>',
                            item_name: '<?php echo esc_js($item->get_name()); ?>',
                            quantity: <?php echo intval($item->get_quantity()); ?>,
                            price: <?php echo floatval($item->get_total() / max(1, $item->get_quantity())); ?>
                        },
                    <?php endforeach; ?>
                ]
            });
            <?php if (!empty(self::get('facebook_pixel'))): ?>
                fbq('track', 'Purchase', {
                    value: <?php echo floatval($order->get_total()); ?>,
                    currency: '<?php echo esc_js($order->get_currency()); ?>'
                });
            <?php endif; ?>
        </script>
    <?php
    }

    // --- E-Commerce: Add to cart tracking ---
    public function add_to_cart_tracking()
    {
        if (self::get('ecommerce_tracking') !== '1') return;
        $ga_id = self::get('ga_id');
        if (empty($ga_id)) return;

        global $product;
        if (!$product) return;
    ?>
        <script>
            document.querySelector('.single_add_to_cart_button')?.addEventListener('click', function() {
                gtag('event', 'add_to_cart', {
                    currency: '<?php echo esc_js(get_woocommerce_currency()); ?>',
                    value: <?php echo floatval($product->get_price()); ?>,
                    items: [{
                        item_id: '<?php echo esc_js($product->get_id()); ?>',
                        item_name: '<?php echo esc_js($product->get_name()); ?>',
                        price: <?php echo floatval($product->get_price()); ?>,
                        quantity: 1
                    }]
                });
                <?php if (!empty(self::get('facebook_pixel'))): ?>
                    fbq('track', 'AddToCart', {
                        content_ids: ['<?php echo esc_js($product->get_id()); ?>'],
                        content_type: 'product',
                        value: <?php echo floatval($product->get_price()); ?>,
                        currency: '<?php echo esc_js(get_woocommerce_currency()); ?>'
                    });
                <?php endif; ?>
            });
        </script>
    <?php
    }

    // --- Admin Page ---
    public function render_admin()
    {
        $opts = wp_parse_args(get_option('webyaz_analytics', array()), self::get_defaults());

        $primary = '#446084';
        $secondary = '#d26e4b';
        if (class_exists('Webyaz_Colors')) {
            $colors = Webyaz_Colors::get_theme_colors();
            $primary = $colors['primary'];
            $secondary = $colors['secondary'];
        }
    ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header" style="background:linear-gradient(135deg,<?php echo $primary; ?>,<?php echo $secondary; ?>);">
                <h1>Analytics & Izleme Kodlari</h1>
                <p>Google Analytics, Tag Manager, Search Console, Facebook Pixel ve ozel kodlar</p>
            </div>

            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="webyaz-notice success">Ayarlar kaydedildi!</div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('webyaz_analytics_group'); ?>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title" style="border-bottom-color:<?php echo $secondary; ?>;">Google Analytics 4</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:15px;">Google Analytics 4 olcum kimliginizi girin. E-ticaret izleme otomatik aktif olur.</p>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>GA4 Olcum Kimligi</label>
                            <input type="text" name="webyaz_analytics[ga_id]" value="<?php echo esc_attr($opts['ga_id']); ?>" placeholder="G-XXXXXXXXXX">
                        </div>
                        <div class="webyaz-field">
                            <label>E-Ticaret Izleme</label>
                            <select name="webyaz_analytics[ecommerce_tracking]">
                                <option value="1" <?php selected($opts['ecommerce_tracking'], '1'); ?>>Aktif (Satin alma + Sepet izle)</option>
                                <option value="0" <?php selected($opts['ecommerce_tracking'], '0'); ?>>Kapali</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title" style="border-bottom-color:<?php echo $secondary; ?>;">Google Tag Manager</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>GTM Kodu</label>
                            <input type="text" name="webyaz_analytics[gtm_id]" value="<?php echo esc_attr($opts['gtm_id']); ?>" placeholder="GTM-XXXXXXX">
                        </div>
                    </div>
                </div>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title" style="border-bottom-color:<?php echo $secondary; ?>;">Google Search Console</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:15px;">HTML etiketindeki content degerini veya meta etiketi oldugu gibi yapistirin.</p>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field" style="grid-column:span 2;">
                            <label>Dogrulama Kodu</label>
                            <input type="text" name="webyaz_analytics[search_console]" value="<?php echo esc_attr($opts['search_console']); ?>" placeholder="XXXXXXXXXXXXXXX veya tam meta etiketi">
                        </div>
                    </div>
                </div>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title" style="border-bottom-color:<?php echo $secondary; ?>;">Facebook Pixel</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Pixel ID</label>
                            <input type="text" name="webyaz_analytics[facebook_pixel]" value="<?php echo esc_attr($opts['facebook_pixel']); ?>" placeholder="XXXXXXXXXXXXXXXX">
                        </div>
                    </div>
                </div>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title" style="border-bottom-color:<?php echo $secondary; ?>;">Ozel Kodlar</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:15px;">Head veya footer alanina ozel script, meta etiketleri ekleyebilirsiniz.</p>
                    <div class="webyaz-field" style="margin-bottom:15px;">
                        <label>Head Kodlari (wp_head)</label>
                        <textarea name="webyaz_analytics[custom_head]" rows="4" style="width:100%;font-family:monospace;font-size:13px;border:1px solid #ddd;border-radius:6px;padding:10px;"><?php echo esc_textarea($opts['custom_head']); ?></textarea>
                    </div>
                    <div class="webyaz-field">
                        <label>Footer Kodlari (wp_footer)</label>
                        <textarea name="webyaz_analytics[custom_footer]" rows="4" style="width:100%;font-family:monospace;font-size:13px;border:1px solid #ddd;border-radius:6px;padding:10px;"><?php echo esc_textarea($opts['custom_footer']); ?></textarea>
                    </div>
                </div>

                <div style="margin-top:20px;">
                    <?php submit_button('Ayarlari Kaydet', 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>
<?php
    }
}

new Webyaz_Analytics();
