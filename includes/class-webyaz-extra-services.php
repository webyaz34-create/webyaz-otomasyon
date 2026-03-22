<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Extra_Services
{

    private static $option_key = 'webyaz_extra_services';

    private static $defaults = array(
        'services'       => array(),
        'title'          => 'Ekstra Hizmetler',
        'subtitle'       => 'Isteginize gore asagidaki hizmetleri ekleyebilirsiniz.',
        'position'       => 'before_add_to_cart', // before_add_to_cart, after_add_to_cart
        'style'          => 'card', // card, list, minimal
        'show_all'       => '1',    // 1=tum urunlerde, 0=sadece belirli kategorilerde
        'categories'     => array(),
    );

    public function __construct()
    {
        // Admin
        add_action('admin_menu', array($this, 'add_menu'), 90);
        add_action('admin_init', array($this, 'handle_save'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

        // Urun meta box
        add_action('add_meta_boxes', array($this, 'add_product_metabox'));
        add_action('save_post_product', array($this, 'save_product_meta'));

        // Frontend
        $opts = self::get_all();
        $hook = $opts['position'] === 'after_add_to_cart'
            ? 'woocommerce_after_add_to_cart_button'
            : 'woocommerce_before_add_to_cart_button';
        add_action($hook, array($this, 'render_services'));

        // WooCommerce entegrasyon
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_data'), 10, 2);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_data'), 10, 2);
        add_action('woocommerce_before_calculate_totals', array($this, 'adjust_price'));
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_order_meta'), 10, 4);
        add_filter('woocommerce_order_item_get_formatted_meta_data', array($this, 'format_order_meta'), 10, 2);
    }

    // ==========================================
    // AYARLAR
    // ==========================================
    public static function get_all()
    {
        return wp_parse_args(get_option(self::$option_key, array()), self::$defaults);
    }

    public static function get($key)
    {
        $opts = self::get_all();
        return isset($opts[$key]) ? $opts[$key] : '';
    }

    private static function get_services_for_product($product_id)
    {
        $opts = self::get_all();

        // Urun bazli kontrol
        $product_mode = get_post_meta($product_id, '_wz_services_mode', true);

        if ($product_mode === 'disabled') {
            return array();
        }

        if ($product_mode === 'custom') {
            $custom = get_post_meta($product_id, '_wz_custom_services', true);
            if (!empty($custom) && is_array($custom)) {
                return array_filter($custom, function ($s) {
                    return !empty($s['name']) && isset($s['price']);
                });
            }
        }

        // Kategori kontrolu
        if ($opts['show_all'] !== '1' && !empty($opts['categories'])) {
            $product_cats = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
            $allowed = array_map('intval', $opts['categories']);
            if (empty(array_intersect($product_cats, $allowed))) {
                return array();
            }
        }

        // Global hizmetler
        if (!empty($opts['services']) && is_array($opts['services'])) {
            return array_filter($opts['services'], function ($s) {
                return !empty($s['name']) && isset($s['price']) && (!isset($s['active']) || $s['active'] === '1');
            });
        }

        return array();
    }

    // ==========================================
    // ADMIN MENU
    // ==========================================
    public function add_menu()
    {
        add_submenu_page(
            'webyaz-dashboard',
            'Ekstra Hizmetler',
            'Ekstra Hizmetler',
            'manage_options',
            'webyaz-extra-services',
            array($this, 'render_page')
        );
    }

    public function admin_scripts($hook)
    {
        if (strpos($hook, 'webyaz-extra-services') === false && $hook !== 'post.php' && $hook !== 'post-new.php') return;
        wp_enqueue_script('jquery-ui-sortable');
    }

    public function handle_save()
    {
        if (!isset($_POST['webyaz_save_extra_services'])) return;
        if (!wp_verify_nonce($_POST['_wpnonce_es'], 'webyaz_extra_services_save')) return;
        if (!current_user_can('manage_options')) return;

        $clean = array();
        $clean['title']    = sanitize_text_field($_POST['wz_es_title'] ?? 'Ekstra Hizmetler');
        $clean['subtitle'] = sanitize_text_field($_POST['wz_es_subtitle'] ?? '');
        $clean['position'] = sanitize_text_field($_POST['wz_es_position'] ?? 'before_add_to_cart');
        $clean['style']    = sanitize_text_field($_POST['wz_es_style'] ?? 'card');
        $clean['show_all'] = isset($_POST['wz_es_show_all']) ? '1' : '0';
        $clean['categories'] = isset($_POST['wz_es_categories']) ? array_map('intval', $_POST['wz_es_categories']) : array();

        // Hizmetleri kaydet
        $services = array();
        if (isset($_POST['wz_svc_name']) && is_array($_POST['wz_svc_name'])) {
            foreach ($_POST['wz_svc_name'] as $i => $name) {
                $name = sanitize_text_field($name);
                if (empty($name)) continue;
                $services[] = array(
                    'name'   => $name,
                    'price'  => floatval($_POST['wz_svc_price'][$i] ?? 0),
                    'desc'   => sanitize_text_field($_POST['wz_svc_desc'][$i] ?? ''),
                    'icon'   => sanitize_text_field($_POST['wz_svc_icon'][$i] ?? '🔧'),
                    'active' => isset($_POST['wz_svc_active'][$i]) ? '1' : '0',
                );
            }
        }
        $clean['services'] = $services;

        update_option(self::$option_key, $clean);
        wp_redirect(admin_url('admin.php?page=webyaz-extra-services&saved=1'));
        exit;
    }

    // ==========================================
    // URUN META BOX
    // ==========================================
    public function add_product_metabox()
    {
        add_meta_box(
            'webyaz_extra_services_mb',
            '🔧 Ekstra Hizmetler',
            array($this, 'render_metabox'),
            'product',
            'normal',
            'default'
        );
    }

    public function render_metabox($post)
    {
        wp_nonce_field('wz_services_meta', '_wz_services_nonce');
        $mode = get_post_meta($post->ID, '_wz_services_mode', true) ?: 'global';
        $custom = get_post_meta($post->ID, '_wz_custom_services', true) ?: array();

        $primary = '#446084';
        if (class_exists('Webyaz_Colors')) {
            $colors = Webyaz_Colors::get_theme_colors();
            $primary = $colors['primary'];
        }
        ?>
        <div style="font-family:-apple-system,BlinkMacSystemFont,sans-serif;">
            <div style="margin-bottom:15px;">
                <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;cursor:pointer;">
                    <input type="radio" name="wz_services_mode" value="global" <?php checked($mode, 'global'); ?> onchange="document.getElementById('wzCustomSvc').style.display='none'">
                    <span style="font-weight:600;">Global hizmetleri kullan</span>
                    <span style="color:#888;font-size:12px;">(Admin ayarlarindaki hizmetler gosterilir)</span>
                </label>
                <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;cursor:pointer;">
                    <input type="radio" name="wz_services_mode" value="custom" <?php checked($mode, 'custom'); ?> onchange="document.getElementById('wzCustomSvc').style.display='block'">
                    <span style="font-weight:600;">Bu urune ozel hizmetler tanimla</span>
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="radio" name="wz_services_mode" value="disabled" <?php checked($mode, 'disabled'); ?> onchange="document.getElementById('wzCustomSvc').style.display='none'">
                    <span style="font-weight:600;color:#d32f2f;">Ekstra hizmetleri gosterme</span>
                </label>
            </div>

            <div id="wzCustomSvc" style="<?php echo $mode !== 'custom' ? 'display:none;' : ''; ?>border:1px solid #ddd;border-radius:8px;padding:15px;background:#fafafa;">
                <div id="wzCustomSvcList">
                    <?php if (!empty($custom)):
                        foreach ($custom as $i => $s): ?>
                            <div class="wz-csvc-row" style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">
                                <input type="text" name="wz_csvc_name[]" value="<?php echo esc_attr($s['name']); ?>" placeholder="Hizmet adi" style="flex:2;padding:8px;border:1px solid #ddd;border-radius:4px;">
                                <input type="number" name="wz_csvc_price[]" value="<?php echo esc_attr($s['price']); ?>" placeholder="Fiyat" step="0.01" style="width:100px;padding:8px;border:1px solid #ddd;border-radius:4px;">
                                <span style="color:#888;font-size:13px;">₺</span>
                                <button type="button" onclick="this.parentElement.remove()" style="background:#f44336;color:#fff;border:none;border-radius:4px;padding:8px 12px;cursor:pointer;">✕</button>
                            </div>
                        <?php endforeach;
                    endif; ?>
                </div>
                <button type="button" onclick="wzAddCustomSvc()" style="background:<?php echo $primary; ?>;color:#fff;border:none;padding:8px 16px;border-radius:6px;font-size:13px;cursor:pointer;font-weight:600;">+ Hizmet Ekle</button>
            </div>
        </div>
        <script>
        function wzAddCustomSvc() {
            var html = '<div class="wz-csvc-row" style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">' +
                '<input type="text" name="wz_csvc_name[]" placeholder="Hizmet adi" style="flex:2;padding:8px;border:1px solid #ddd;border-radius:4px;">' +
                '<input type="number" name="wz_csvc_price[]" placeholder="Fiyat" step="0.01" style="width:100px;padding:8px;border:1px solid #ddd;border-radius:4px;">' +
                '<span style="color:#888;font-size:13px;">₺</span>' +
                '<button type="button" onclick="this.parentElement.remove()" style="background:#f44336;color:#fff;border:none;border-radius:4px;padding:8px 12px;cursor:pointer;">✕</button>' +
                '</div>';
            document.getElementById('wzCustomSvcList').insertAdjacentHTML('beforeend', html);
        }
        </script>
        <?php
    }

    public function save_product_meta($post_id)
    {
        if (!isset($_POST['_wz_services_nonce']) || !wp_verify_nonce($_POST['_wz_services_nonce'], 'wz_services_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $mode = sanitize_text_field($_POST['wz_services_mode'] ?? 'global');
        update_post_meta($post_id, '_wz_services_mode', $mode);

        if ($mode === 'custom' && isset($_POST['wz_csvc_name'])) {
            $custom = array();
            foreach ($_POST['wz_csvc_name'] as $i => $name) {
                $name = sanitize_text_field($name);
                if (empty($name)) continue;
                $custom[] = array(
                    'name'  => $name,
                    'price' => floatval($_POST['wz_csvc_price'][$i] ?? 0),
                );
            }
            update_post_meta($post_id, '_wz_custom_services', $custom);
        }
    }

    // ==========================================
    // FRONTEND
    // ==========================================
    public function render_services()
    {
        global $product;
        if (!$product) return;

        $services = self::get_services_for_product($product->get_id());
        if (empty($services)) return;

        $opts = self::get_all();
        $style = $opts['style'];

        // Urun baz fiyatini al
        $base_price = $product->get_price();
        $sale_price = $product->get_sale_price();
        $regular_price = $product->get_regular_price();
        $currency_symbol = get_woocommerce_currency_symbol();

        $primary = '#446084';
        $secondary = '#d26e4b';
        if (class_exists('Webyaz_Colors')) {
            $colors = Webyaz_Colors::get_theme_colors();
            $primary = $colors['primary'];
            $secondary = $colors['secondary'];
        }
        ?>
        <div class="wz-extra-services" style="margin:12px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
            <?php if (!empty($opts['title'])): ?>
                <div style="margin-bottom:8px;">
                    <div style="font-size:14px;font-weight:700;color:#1a1a1a;display:flex;align-items:center;gap:6px;">
                        <span style="background:<?php echo $primary; ?>;color:#fff;width:22px;height:22px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;font-size:11px;">🔧</span>
                        <?php echo esc_html($opts['title']); ?>
                    </div>
                    <?php if (!empty($opts['subtitle'])): ?>
                        <div style="font-size:11px;color:#999;margin-top:2px;padding-left:28px;"><?php echo esc_html($opts['subtitle']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="wz-svc-list" style="display:flex;flex-direction:column;gap:6px;">
                <?php foreach ($services as $i => $svc):
                    $price = floatval($svc['price']);
                    $icon = !empty($svc['icon']) ? $svc['icon'] : '🔧';
                    $desc = !empty($svc['desc']) ? $svc['desc'] : '';
                    $id = 'wz_svc_' . $i;
                ?>
                    <?php if ($style === 'card'): ?>
                        <label for="<?php echo $id; ?>" class="wz-svc-card" style="display:flex;align-items:center;gap:8px;background:#fff;border:1.5px solid #e8e8e8;border-radius:8px;padding:10px 12px;cursor:pointer;transition:all .2s;position:relative;">
                            <input type="checkbox" name="wz_extra_svc[]" id="<?php echo $id; ?>" value="<?php echo $i; ?>" data-price="<?php echo $price; ?>" class="wz-svc-check" style="width:16px;height:16px;accent-color:<?php echo $primary; ?>;cursor:pointer;flex-shrink:0;">
                            <span style="font-size:16px;flex-shrink:0;"><?php echo $icon; ?></span>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:13px;font-weight:600;color:#1a1a1a;line-height:1.2;"><?php echo esc_html($svc['name']); ?></div>
                                <?php if ($desc): ?>
                                    <div style="font-size:10px;color:#999;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html($desc); ?></div>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:12px;font-weight:700;color:<?php echo $secondary; ?>;white-space:nowrap;">
                                <?php echo $price > 0 ? '+' . number_format($price, 2, ',', '.') . ' ₺' : 'Ücretsiz'; ?>
                            </div>
                        </label>
                    <?php elseif ($style === 'list'): ?>
                        <label for="<?php echo $id; ?>" style="display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #eee;border-radius:6px;margin-bottom:4px;cursor:pointer;transition:all .2s;background:#fff;">
                            <input type="checkbox" name="wz_extra_svc[]" id="<?php echo $id; ?>" value="<?php echo $i; ?>" data-price="<?php echo $price; ?>" class="wz-svc-check" style="width:15px;height:15px;accent-color:<?php echo $primary; ?>;cursor:pointer;flex-shrink:0;">
                            <span style="font-size:15px;"><?php echo $icon; ?></span>
                            <div style="flex:1;">
                                <div style="font-size:13px;font-weight:600;color:#1a1a1a;"><?php echo esc_html($svc['name']); ?></div>
                                <?php if ($desc): ?>
                                    <div style="font-size:10px;color:#999;"><?php echo esc_html($desc); ?></div>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:12px;font-weight:700;color:<?php echo $secondary; ?>;white-space:nowrap;">
                                <?php echo $price > 0 ? '+' . number_format($price, 2, ',', '.') . ' ₺' : 'Ücretsiz'; ?>
                            </div>
                        </label>
                    <?php else: /* minimal */ ?>
                        <label for="<?php echo $id; ?>" style="display:flex;align-items:center;gap:6px;padding:4px 0;cursor:pointer;">
                            <input type="checkbox" name="wz_extra_svc[]" id="<?php echo $id; ?>" value="<?php echo $i; ?>" data-price="<?php echo $price; ?>" class="wz-svc-check" style="width:14px;height:14px;accent-color:<?php echo $primary; ?>;cursor:pointer;">
                            <span style="font-size:13px;color:#333;"><?php echo esc_html($svc['name']); ?></span>
                            <span style="font-size:12px;font-weight:600;color:<?php echo $secondary; ?>;">
                                <?php echo $price > 0 ? '+' . number_format($price, 2, ',', '.') . ' ₺' : 'Ücretsiz'; ?>
                            </span>
                        </label>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Toplam -->
            <div id="wzSvcTotal" style="display:none;margin-top:8px;padding:8px 12px;background:linear-gradient(135deg,<?php echo $primary; ?>10,<?php echo $secondary; ?>10);border:1.5px solid <?php echo $primary; ?>30;border-radius:8px;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span style="font-size:12px;color:#666;">Secilen hizmetler:</span>
                    <span id="wzSvcTotalPrice" style="font-size:15px;font-weight:700;color:<?php echo $primary; ?>;">0,00 ₺</span>
                </div>
            </div>
        </div>

        <style>
            .wz-svc-card:has(input:checked) {
                border-color: <?php echo $primary; ?> !important;
                background: <?php echo $primary; ?>08 !important;
                box-shadow: 0 2px 12px <?php echo $primary; ?>15;
            }
            .wz-svc-list label:hover {
                border-color: <?php echo $primary; ?>80 !important;
            }
        </style>

        <script>
        (function() {
            var checks = document.querySelectorAll('.wz-svc-check');
            var totalEl = document.getElementById('wzSvcTotal');
            var priceEl = document.getElementById('wzSvcTotalPrice');

            // Urun baz fiyati
            var basePrice = <?php echo floatval($base_price); ?>;
            var regularPrice = <?php echo floatval($regular_price); ?>;
            var salePrice = <?php echo $sale_price ? floatval($sale_price) : 'null'; ?>;
            var currencySymbol = '<?php echo $currency_symbol; ?>';

            // Sayfadaki fiyat elementlerini bul
            function findPriceElements() {
                // WooCommerce & Flatsome fiyat secicileri
                var selectors = [
                    '.product-info .price',
                    '.summary .price',
                    '.product-page-price .price',
                    '.product_title + .price',
                    'div.product .price'
                ];
                for (var i = 0; i < selectors.length; i++) {
                    var el = document.querySelector(selectors[i]);
                    if (el) return el;
                }
                return null;
            }

            var priceContainer = findPriceElements();
            var originalPriceHTML = priceContainer ? priceContainer.innerHTML : '';

            function formatPrice(price) {
                return price.toFixed(2)
                    .replace('.', ',')
                    .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }

            function update() {
                var sum = 0;
                var count = 0;
                checks.forEach(function(c) {
                    if (c.checked) {
                        sum += parseFloat(c.dataset.price) || 0;
                        count++;
                    }
                });

                // Hizmet toplam kutusunu guncelle
                if (count > 0) {
                    totalEl.style.display = 'block';
                    priceEl.innerHTML = '+' + formatPrice(sum) + ' ' + currencySymbol;
                } else {
                    totalEl.style.display = 'none';
                }

                // Urun fiyatini guncelle
                if (priceContainer) {
                    if (count === 0) {
                        // Hicbir hizmet secilmemis → orijinal fiyata don
                        priceContainer.innerHTML = originalPriceHTML;
                    } else {
                        var newPrice = basePrice + sum;
                        var html = '';

                        if (salePrice !== null) {
                            // Indirimli urun: eski fiyat + yeni fiyat
                            var newRegular = regularPrice + sum;
                            html = '<del><span class="woocommerce-Price-amount amount"><bdi>' +
                                formatPrice(newRegular) + '&nbsp;<span class="woocommerce-Price-currencySymbol">' + currencySymbol + '</span></bdi></span></del> ' +
                                '<ins><span class="woocommerce-Price-amount amount"><bdi>' +
                                formatPrice(newPrice) + '&nbsp;<span class="woocommerce-Price-currencySymbol">' + currencySymbol + '</span></bdi></span></ins>';
                        } else {
                            // Normal fiyat
                            html = '<span class="woocommerce-Price-amount amount"><bdi>' +
                                formatPrice(newPrice) + '&nbsp;<span class="woocommerce-Price-currencySymbol">' + currencySymbol + '</span></bdi></span>';
                        }

                        priceContainer.innerHTML = html;

                        // Fiyat degisim animasyonu
                        priceContainer.style.transition = 'transform 0.2s ease';
                        priceContainer.style.transform = 'scale(1.05)';
                        setTimeout(function() {
                            priceContainer.style.transform = 'scale(1)';
                        }, 200);
                    }
                }
            }

            checks.forEach(function(c) {
                c.addEventListener('change', update);
            });
        })();
        </script>
        <?php
    }

    // ==========================================
    // WOOCOMMERCE ENTEGRASYON
    // ==========================================

    // Sepete eklerken hizmet bilgisi kaydet
    public function add_cart_data($cart_data, $product_id)
    {
        if (!isset($_POST['wz_extra_svc']) || !is_array($_POST['wz_extra_svc'])) return $cart_data;

        $services = self::get_services_for_product($product_id);
        if (empty($services)) return $cart_data;

        $services = array_values($services); // re-index
        $selected = array();
        $total = 0;

        foreach ($_POST['wz_extra_svc'] as $idx) {
            $idx = intval($idx);
            if (isset($services[$idx])) {
                $svc = $services[$idx];
                $selected[] = array(
                    'name'  => $svc['name'],
                    'price' => floatval($svc['price']),
                );
                $total += floatval($svc['price']);
            }
        }

        if (!empty($selected)) {
            $cart_data['wz_extra_services'] = $selected;
            $cart_data['wz_services_total'] = $total;
        }

        return $cart_data;
    }

    // Sepet sayfasinda hizmetleri goster
    public function display_cart_data($item_data, $cart_item)
    {
        if (isset($cart_item['wz_extra_services'])) {
            foreach ($cart_item['wz_extra_services'] as $svc) {
                $price_text = $svc['price'] > 0
                    ? '+' . number_format($svc['price'], 2, ',', '.') . ' ₺'
                    : 'Ücretsiz';
                $item_data[] = array(
                    'key'   => $svc['name'],
                    'value' => $price_text,
                );
            }
        }
        return $item_data;
    }

    // Fiyati guncelle
    public function adjust_price($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (did_action('woocommerce_before_calculate_totals') >= 2) return;

        foreach ($cart->get_cart() as $item) {
            if (isset($item['wz_services_total']) && $item['wz_services_total'] > 0) {
                $base_price = $item['data']->get_price();
                $item['data']->set_price($base_price + $item['wz_services_total']);
            }
        }
    }

    // Siparise meta ekle
    public function save_order_meta($item, $cart_item_key, $values, $order)
    {
        if (isset($values['wz_extra_services'])) {
            $names = array();
            $total = 0;
            foreach ($values['wz_extra_services'] as $svc) {
                $names[] = $svc['name'] . ' (+' . number_format($svc['price'], 2, ',', '.') . ' ₺)';
                $total += $svc['price'];
            }
            $item->add_meta_data('Ekstra Hizmetler', implode(', ', $names), true);
            $item->add_meta_data('_wz_services_total', $total, true);
        }
    }

    // Siparis meta formatla
    public function format_order_meta($formatted_meta, $item)
    {
        foreach ($formatted_meta as $meta) {
            if ($meta->key === '_wz_services_total') {
                $meta->display_key = '';
                $meta->display_value = '';
            }
        }
        return $formatted_meta;
    }

    // ==========================================
    // ADMIN SAYFA RENDER
    // ==========================================
    public function render_page()
    {
        $o = self::get_all();
        $services = isset($o['services']) ? $o['services'] : array();

        $primary = '#446084';
        $secondary = '#d26e4b';
        if (class_exists('Webyaz_Colors')) {
            $colors = Webyaz_Colors::get_theme_colors();
            $primary = $colors['primary'];
            $secondary = $colors['secondary'];
        }

        // Kategorileri al
        $all_cats = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
    ?>
        <div class="webyaz-admin-wrap" style="max-width:900px;margin:20px 0;font-family:-apple-system,BlinkMacSystemFont,sans-serif;">

            <div style="background:linear-gradient(135deg,<?php echo $primary; ?>,<?php echo $secondary; ?>);color:#fff;padding:30px 35px;border-radius:12px;margin-bottom:25px;">
                <h1 style="margin:0 0 5px;font-size:26px;font-weight:700;">Ekstra Hizmetler</h1>
                <p style="margin:0;opacity:.85;font-size:14px;">Urun sayfasinda sunulacak opsiyonel hizmetleri tanimlayin (montaj, kurulum, garanti vb.)</p>
            </div>

            <?php if (isset($_GET['saved'])): ?>
                <div style="background:#e6f9e6;color:#22863a;border:1px solid #b7e4c7;padding:12px 18px;border-radius:8px;margin-bottom:15px;font-size:14px;">
                    ✅ Hizmet ayarlari basariyla kaydedildi!
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('webyaz_extra_services_save', '_wpnonce_es'); ?>

                <!-- HIZMET LISTESI -->
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:12px;margin-bottom:20px;overflow:hidden;">
                    <div style="padding:18px 22px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:12px;">
                        <span style="font-size:22px;">📋</span>
                        <div>
                            <div style="font-size:15px;font-weight:700;color:#1a1a1a;">Hizmet Tanimlari</div>
                            <div style="font-size:12px;color:#888;margin-top:2px;">Urunlere eklenebilecek opsiyonel hizmetleri tanimlayin. Surukleyerek siralayabilirsiniz.</div>
                        </div>
                    </div>
                    <div style="padding:20px 22px;">
                        <div id="wzSvcList">
                            <?php if (!empty($services)):
                                foreach ($services as $i => $svc): ?>
                                    <div class="wz-svc-row" style="display:flex;gap:10px;align-items:center;margin-bottom:12px;padding:14px;background:#fafafa;border:1px solid #eee;border-radius:8px;cursor:grab;">
                                        <span class="dashicons dashicons-menu" style="color:#ccc;cursor:grab;flex-shrink:0;"></span>
                                        <input type="text" name="wz_svc_icon[]" value="<?php echo esc_attr($svc['icon'] ?? '🔧'); ?>" style="width:45px;text-align:center;font-size:18px;border:1px solid #ddd;border-radius:6px;padding:6px;" title="Emoji ikon">
                                        <input type="text" name="wz_svc_name[]" value="<?php echo esc_attr($svc['name']); ?>" placeholder="Hizmet adi" style="flex:2;padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                                        <input type="number" name="wz_svc_price[]" value="<?php echo esc_attr($svc['price']); ?>" placeholder="Fiyat" step="0.01" min="0" style="width:110px;padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                                        <span style="color:#888;font-size:13px;">₺</span>
                                        <input type="text" name="wz_svc_desc[]" value="<?php echo esc_attr($svc['desc'] ?? ''); ?>" placeholder="Aciklama (opsiyonel)" style="flex:1.5;padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                                        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;flex-shrink:0;" title="Aktif/Pasif">
                                            <input type="checkbox" name="wz_svc_active[<?php echo $i; ?>]" value="1" <?php checked($svc['active'] ?? '1', '1'); ?> style="width:16px;height:16px;accent-color:#4caf50;">
                                        </label>
                                        <button type="button" onclick="this.closest('.wz-svc-row').remove()" style="background:#f44336;color:#fff;border:none;border-radius:6px;width:34px;height:34px;cursor:pointer;font-size:16px;flex-shrink:0;display:flex;align-items:center;justify-content:center;" title="Sil">✕</button>
                                    </div>
                                <?php endforeach;
                            endif; ?>
                        </div>

                        <div style="display:flex;gap:10px;margin-top:10px;">
                            <button type="button" id="wzAddSvc" style="background:<?php echo $primary; ?>;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;">
                                <span style="font-size:18px;">+</span> Hizmet Ekle
                            </button>
                        </div>
                    </div>
                </div>

                <!-- GORUNTULENME AYARLARI -->
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:12px;margin-bottom:20px;overflow:hidden;">
                    <div style="padding:18px 22px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:12px;">
                        <span style="font-size:22px;">⚙️</span>
                        <div>
                            <div style="font-size:15px;font-weight:700;color:#1a1a1a;">Goruntuleme Ayarlari</div>
                            <div style="font-size:12px;color:#888;margin-top:2px;">Baslik, konum, stil ve kategori ayarlari.</div>
                        </div>
                    </div>
                    <div style="padding:20px 22px;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <div style="display:flex;flex-direction:column;gap:6px;">
                                <label style="font-size:13px;font-weight:600;color:#333;">Baslik</label>
                                <input type="text" name="wz_es_title" value="<?php echo esc_attr($o['title']); ?>" style="padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                            </div>
                            <div style="display:flex;flex-direction:column;gap:6px;">
                                <label style="font-size:13px;font-weight:600;color:#333;">Alt Baslik</label>
                                <input type="text" name="wz_es_subtitle" value="<?php echo esc_attr($o['subtitle']); ?>" style="padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                            </div>
                            <div style="display:flex;flex-direction:column;gap:6px;">
                                <label style="font-size:13px;font-weight:600;color:#333;">Konum</label>
                                <select name="wz_es_position" style="padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                                    <option value="before_add_to_cart" <?php selected($o['position'], 'before_add_to_cart'); ?>>Sepete Ekle ustunde</option>
                                    <option value="after_add_to_cart" <?php selected($o['position'], 'after_add_to_cart'); ?>>Sepete Ekle altinda</option>
                                </select>
                            </div>
                            <div style="display:flex;flex-direction:column;gap:6px;">
                                <label style="font-size:13px;font-weight:600;color:#333;">Stil</label>
                                <select name="wz_es_style" style="padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                                    <option value="card" <?php selected($o['style'], 'card'); ?>>Kart (Grid)</option>
                                    <option value="list" <?php selected($o['style'], 'list'); ?>>Liste</option>
                                    <option value="minimal" <?php selected($o['style'], 'minimal'); ?>>Minimal</option>
                                </select>
                            </div>
                        </div>

                        <hr style="border:none;border-top:1px solid #eee;margin:20px 0;">

                        <div style="margin-bottom:15px;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" name="wz_es_show_all" value="1" <?php checked($o['show_all'], '1'); ?> onchange="document.getElementById('wzCatFilter').style.display=this.checked?'none':'block'" style="width:18px;height:18px;accent-color:<?php echo $primary; ?>;">
                                <span style="font-size:14px;font-weight:600;">Tum urunlerde goster</span>
                            </label>
                        </div>

                        <div id="wzCatFilter" style="<?php echo $o['show_all'] === '1' ? 'display:none;' : ''; ?>">
                            <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:8px;">Sadece su kategorilerde goster:</label>
                            <div style="display:flex;flex-wrap:wrap;gap:8px;max-height:200px;overflow-y:auto;padding:12px;background:#fafafa;border:1px solid #eee;border-radius:8px;">
                                <?php if (!empty($all_cats) && !is_wp_error($all_cats)):
                                    $selected_cats = is_array($o['categories']) ? $o['categories'] : array();
                                    foreach ($all_cats as $cat): ?>
                                        <label style="display:flex;align-items:center;gap:4px;background:#fff;border:1px solid #ddd;border-radius:6px;padding:6px 12px;cursor:pointer;font-size:13px;">
                                            <input type="checkbox" name="wz_es_categories[]" value="<?php echo $cat->term_id; ?>" <?php checked(in_array($cat->term_id, $selected_cats)); ?> style="accent-color:<?php echo $primary; ?>;">
                                            <?php echo esc_html($cat->name); ?> <span style="color:#aaa;font-size:11px;">(<?php echo $cat->count; ?>)</span>
                                        </label>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KAYDET -->
                <button type="submit" name="webyaz_save_extra_services" value="1" style="background:<?php echo $primary; ?>;color:#fff;border:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;transition:opacity .2s;">
                    <span class="dashicons dashicons-saved" style="vertical-align:middle;margin-right:6px;"></span>Hizmetleri Kaydet
                </button>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Sortable
            $('#wzSvcList').sortable({
                handle: '.dashicons-menu',
                placeholder: 'wz-svc-placeholder',
                cursor: 'grabbing'
            });

            // Hizmet ekle
            var svcCount = <?php echo count($services); ?>;
            $('#wzAddSvc').on('click', function() {
                var html = '<div class="wz-svc-row" style="display:flex;gap:10px;align-items:center;margin-bottom:12px;padding:14px;background:#fafafa;border:1px solid #eee;border-radius:8px;cursor:grab;">' +
                    '<span class="dashicons dashicons-menu" style="color:#ccc;cursor:grab;flex-shrink:0;"></span>' +
                    '<input type="text" name="wz_svc_icon[]" value="🔧" style="width:45px;text-align:center;font-size:18px;border:1px solid #ddd;border-radius:6px;padding:6px;" title="Emoji ikon">' +
                    '<input type="text" name="wz_svc_name[]" placeholder="Hizmet adi" style="flex:2;padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">' +
                    '<input type="number" name="wz_svc_price[]" placeholder="Fiyat" step="0.01" min="0" style="width:110px;padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">' +
                    '<span style="color:#888;font-size:13px;">₺</span>' +
                    '<input type="text" name="wz_svc_desc[]" placeholder="Aciklama (opsiyonel)" style="flex:1.5;padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;">' +
                    '<label style="display:flex;align-items:center;gap:4px;cursor:pointer;flex-shrink:0;" title="Aktif/Pasif">' +
                    '<input type="checkbox" name="wz_svc_active[' + svcCount + ']" value="1" checked style="width:16px;height:16px;accent-color:#4caf50;">' +
                    '</label>' +
                    '<button type="button" onclick="this.closest(\'.wz-svc-row\').remove()" style="background:#f44336;color:#fff;border:none;border-radius:6px;width:34px;height:34px;cursor:pointer;font-size:16px;flex-shrink:0;display:flex;align-items:center;justify-content:center;" title="Sil">✕</button>' +
                    '</div>';
                $('#wzSvcList').append(html);
                svcCount++;
            });
        });
        </script>

        <style>
            .wz-svc-row:hover { border-color: #ccc !important; }
            .wz-svc-row input:focus { border-color: <?php echo $primary; ?> !important; outline: none; box-shadow: 0 0 0 2px <?php echo $primary; ?>18; }
            .wz-svc-placeholder { background: #e3f2fd !important; border: 2px dashed <?php echo $primary; ?> !important; border-radius: 8px; height: 60px; margin-bottom: 12px; }
        </style>
    <?php
    }
}

new Webyaz_Extra_Services();
