<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Marketplace {

    private static $trendyol_base = 'https://api.trendyol.com/sapigw';
    private static $hb_base = 'https://listing-external.hepsiburada.com';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('wp_ajax_webyaz_marketplace_save', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_webyaz_marketplace_test', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_webyaz_marketplace_send', array($this, 'ajax_send_products'));
        add_action('wp_ajax_webyaz_marketplace_sync_stock', array($this, 'ajax_sync_stock'));
        add_action('wp_ajax_webyaz_marketplace_fetch_orders', array($this, 'ajax_fetch_orders'));
        add_action('wp_ajax_webyaz_marketplace_get_categories', array($this, 'ajax_get_categories'));
        add_action('webyaz_cron_marketplace_sync', array($this, 'cron_sync'));
        if (!wp_next_scheduled('webyaz_cron_marketplace_sync')) {
            wp_schedule_event(time(), 'hourly', 'webyaz_cron_marketplace_sync');
        }
    }

    public static function get_settings() {
        return get_option('webyaz_marketplace', array(
            'trendyol_active' => 0,
            'trendyol_seller_id' => '',
            'trendyol_api_key' => '',
            'trendyol_api_secret' => '',
            'trendyol_brand_id' => '',
            'trendyol_category_id' => '',
            'trendyol_auto_sync' => 0,
            'hb_active' => 0,
            'hb_merchant_id' => '',
            'hb_username' => '',
            'hb_password' => '',
            'hb_category_id' => '',
            'hb_auto_sync' => 0,
            'sync_stock' => 1,
            'sync_price' => 1,
            'sync_interval' => 'hourly',
        ));
    }

    // --- TRENDYOL API ---
    private function trendyol_request($method, $endpoint, $body = null) {
        $opts = self::get_settings();
        $url = self::$trendyol_base . str_replace('(sellerId)', $opts['trendyol_seller_id'], $endpoint);
        $auth = base64_encode($opts['trendyol_api_key'] . ':' . $opts['trendyol_api_secret']);
        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . $auth,
                'Content-Type' => 'application/json',
                'User-Agent' => $opts['trendyol_seller_id'] . ' - Webyaz',
            ),
        );
        if ($body) $args['body'] = json_encode($body);
        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) return array('error' => $response->get_error_message());
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code >= 400) return array('error' => 'HTTP ' . $code . ': ' . wp_remote_retrieve_body($response));
        return $data ? $data : array('success' => true);
    }

    private function trendyol_send_product($product) {
        $opts = self::get_settings();
        $images = array();
        $thumb = wp_get_attachment_url($product->get_image_id());
        if ($thumb) $images[] = array('url' => $thumb);
        $gallery = $product->get_gallery_image_ids();
        foreach ($gallery as $gid) {
            $url = wp_get_attachment_url($gid);
            if ($url) $images[] = array('url' => $url);
        }
        $attrs = array();
        $item = array(
            'barcode' => $product->get_sku() ? $product->get_sku() : 'WBY' . $product->get_id(),
            'title' => $product->get_name(),
            'productMainId' => 'WBY' . $product->get_id(),
            'brandId' => intval($opts['trendyol_brand_id']),
            'categoryId' => intval($opts['trendyol_category_id']),
            'quantity' => $product->get_stock_quantity() ? $product->get_stock_quantity() : 100,
            'stockCode' => $product->get_sku() ? $product->get_sku() : 'WBY' . $product->get_id(),
            'dimensionalWeight' => 1,
            'description' => $product->get_description() ? wp_strip_all_tags($product->get_description()) : $product->get_name(),
            'currencyType' => 'TRY',
            'listPrice' => floatval($product->get_regular_price()),
            'salePrice' => floatval($product->get_price()),
            'vatRate' => 20,
            'cargoCompanyId' => 17,
            'images' => $images,
            'attributes' => $attrs,
        );
        return $this->trendyol_request('POST', '/integration/product/sellers/(sellerId)/products', array('items' => array($item)));
    }

    private function trendyol_update_stock_price($product) {
        $item = array(
            'barcode' => $product->get_sku() ? $product->get_sku() : 'WBY' . $product->get_id(),
            'quantity' => $product->get_stock_quantity() ? $product->get_stock_quantity() : 100,
            'salePrice' => floatval($product->get_price()),
            'listPrice' => floatval($product->get_regular_price()),
        );
        return $this->trendyol_request('POST', '/integration/inventory/sellers/(sellerId)/products/price-and-inventory', array('items' => array($item)));
    }

    // --- HEPSIBURADA API ---
    private function hb_request($method, $endpoint, $body = null) {
        $opts = self::get_settings();
        $url = self::$hb_base . $endpoint;
        $auth = base64_encode($opts['hb_username'] . ':' . $opts['hb_password']);
        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . $auth,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
        );
        if ($body) $args['body'] = json_encode($body);
        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) return array('error' => $response->get_error_message());
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code >= 400) return array('error' => 'HTTP ' . $code . ': ' . wp_remote_retrieve_body($response));
        return $data ? $data : array('success' => true);
    }

    private function hb_send_product($product) {
        $opts = self::get_settings();
        $thumb = wp_get_attachment_url($product->get_image_id());
        $sku = $product->get_sku() ? $product->get_sku() : 'WBY' . $product->get_id();
        $item = array(
            'categoryId' => intval($opts['hb_category_id']),
            'merchant' => $opts['hb_merchant_id'],
            'attributes' => array(
                'merchantSku' => $sku,
                'VaryantGroupID' => $sku,
                'Barcode' => $sku,
                'UrunAdi' => $product->get_name(),
                'UrunAciklamasi' => $product->get_description() ? wp_strip_all_tags($product->get_description()) : $product->get_name(),
                'Marka' => '',
                'GasantiSuresi' => 24,
                'kg' => $product->get_weight() ? floatval($product->get_weight()) : 1,
                'tax_vat_rate' => 20,
                'price' => floatval($product->get_price()),
                'availablestock' => $product->get_stock_quantity() ? $product->get_stock_quantity() : 100,
                'Image1' => $thumb ? $thumb : '',
            ),
        );
        $gallery = $product->get_gallery_image_ids();
        $gi = 2;
        foreach ($gallery as $gid) {
            if ($gi > 5) break;
            $url = wp_get_attachment_url($gid);
            if ($url) { $item['attributes']['Image' . $gi] = $url; $gi++; }
        }
        return $this->hb_request('POST', '/listings/merchantid/' . $opts['hb_merchant_id'] . '/inventory-uploads', array('listings' => array($item)));
    }

    private function hb_update_stock_price($product) {
        $opts = self::get_settings();
        $sku = $product->get_sku() ? $product->get_sku() : 'WBY' . $product->get_id();
        $item = array(
            'merchantSku' => $sku,
            'price' => floatval($product->get_price()),
            'availableStock' => $product->get_stock_quantity() ? $product->get_stock_quantity() : 100,
        );
        return $this->hb_request('POST', '/listings/merchantid/' . $opts['hb_merchant_id'] . '/inventory-uploads', array('listings' => array($item)));
    }

    // --- AJAX Handlers ---
    public function ajax_save_settings() {
        if (!current_user_can('manage_options')) wp_die();
        check_ajax_referer('webyaz_mp_nonce', 'nonce');
        $fields = array('trendyol_active','trendyol_seller_id','trendyol_api_key','trendyol_api_secret','trendyol_brand_id','trendyol_category_id','trendyol_auto_sync','hb_active','hb_merchant_id','hb_username','hb_password','hb_category_id','hb_auto_sync','sync_stock','sync_price','sync_interval');
        $opts = self::get_settings();
        foreach ($fields as $f) {
            if (isset($_POST[$f])) $opts[$f] = sanitize_text_field($_POST[$f]);
        }
        update_option('webyaz_marketplace', $opts);
        wp_send_json_success('Kaydedildi');
    }

    public function ajax_test_connection() {
        if (!current_user_can('manage_options')) wp_die();
        check_ajax_referer('webyaz_mp_nonce', 'nonce');
        $platform = sanitize_text_field($_POST['platform']);
        if ($platform === 'trendyol') {
            $result = $this->trendyol_request('GET', '/integration/sellers/(sellerId)/addresses');
            if (isset($result['error'])) wp_send_json_error($result['error']);
            wp_send_json_success('Trendyol baglantisi basarili!');
        } elseif ($platform === 'hepsiburada') {
            $opts = self::get_settings();
            $result = $this->hb_request('GET', '/listings/merchantid/' . $opts['hb_merchant_id'] . '/inventory-uploads');
            if (isset($result['error'])) wp_send_json_error($result['error']);
            wp_send_json_success('Hepsiburada baglantisi basarili!');
        }
        wp_send_json_error('Bilinmeyen platform');
    }

    public function ajax_send_products() {
        if (!current_user_can('manage_options')) wp_die();
        check_ajax_referer('webyaz_mp_nonce', 'nonce');
        $platform = sanitize_text_field($_POST['platform']);
        $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : array();
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = 5;
        if (empty($product_ids)) {
            $args = array('post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids');
            $cat = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
            if ($cat) $args['tax_query'] = array(array('taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $cat));
            $product_ids = get_posts($args);
        }
        $total = count($product_ids);
        $batch = array_slice($product_ids, $offset, $batch_size);
        $sent = 0; $errors = 0; $error_msgs = array();
        foreach ($batch as $pid) {
            $product = wc_get_product($pid);
            if (!$product) { $errors++; continue; }
            if ($platform === 'trendyol') {
                $result = $this->trendyol_send_product($product);
            } else {
                $result = $this->hb_send_product($product);
            }
            if (isset($result['error'])) {
                $errors++;
                $error_msgs[] = $product->get_name() . ': ' . $result['error'];
            } else {
                $sent++;
                update_post_meta($pid, '_webyaz_mp_' . $platform, current_time('Y-m-d H:i'));
            }
        }
        $new_offset = $offset + $batch_size;
        wp_send_json_success(array(
            'sent' => $sent,
            'errors' => $errors,
            'error_msgs' => $error_msgs,
            'offset' => $new_offset,
            'total' => $total,
            'done' => $new_offset >= $total,
        ));
    }

    public function ajax_sync_stock() {
        if (!current_user_can('manage_options')) wp_die();
        check_ajax_referer('webyaz_mp_nonce', 'nonce');
        $platform = sanitize_text_field($_POST['platform']);
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = 10;
        $args = array('post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_query' => array(array('key' => '_webyaz_mp_' . $platform, 'compare' => 'EXISTS')));
        $product_ids = get_posts($args);
        $total = count($product_ids);
        $batch = array_slice($product_ids, $offset, $batch_size);
        $synced = 0; $errors = 0;
        foreach ($batch as $pid) {
            $product = wc_get_product($pid);
            if (!$product) { $errors++; continue; }
            if ($platform === 'trendyol') {
                $result = $this->trendyol_update_stock_price($product);
            } else {
                $result = $this->hb_update_stock_price($product);
            }
            if (isset($result['error'])) { $errors++; } else { $synced++; }
        }
        $new_offset = $offset + $batch_size;
        wp_send_json_success(array('synced' => $synced, 'errors' => $errors, 'offset' => $new_offset, 'total' => $total, 'done' => $new_offset >= $total));
    }

    public function ajax_fetch_orders() {
        if (!current_user_can('manage_options')) wp_die();
        check_ajax_referer('webyaz_mp_nonce', 'nonce');
        $platform = sanitize_text_field($_POST['platform']);
        $orders = array();
        if ($platform === 'trendyol') {
            $result = $this->trendyol_request('GET', '/integration/order/sellers/(sellerId)/orders?status=Created&orderByField=CreatedDate&orderByDirection=DESC&size=50');
            if (!isset($result['error']) && isset($result['content'])) {
                foreach ($result['content'] as $o) {
                    $items = array();
                    if (isset($o['lines'])) foreach ($o['lines'] as $l) { $items[] = $l['productName'] . ' x' . $l['quantity']; }
                    $orders[] = array(
                        'id' => $o['orderNumber'],
                        'date' => isset($o['orderDate']) ? date('d.m.Y H:i', $o['orderDate']/1000) : '',
                        'customer' => isset($o['shipmentAddress']) ? $o['shipmentAddress']['firstName'] . ' ' . $o['shipmentAddress']['lastName'] : '',
                        'total' => isset($o['totalPrice']) ? number_format($o['totalPrice'], 2, ',', '.') . ' TL' : '',
                        'items' => implode(', ', $items),
                        'status' => isset($o['status']) ? $o['status'] : '',
                    );
                }
            } elseif (isset($result['error'])) {
                wp_send_json_error($result['error']);
            }
        } elseif ($platform === 'hepsiburada') {
            $opts = self::get_settings();
            $result = $this->hb_request('GET', '/orders/merchantid/' . $opts['hb_merchant_id'] . '?offset=0&limit=50');
            if (!isset($result['error']) && is_array($result)) {
                foreach ($result as $o) {
                    $orders[] = array(
                        'id' => isset($o['orderId']) ? $o['orderId'] : '',
                        'date' => isset($o['orderDate']) ? $o['orderDate'] : '',
                        'customer' => isset($o['customerName']) ? $o['customerName'] : '',
                        'total' => isset($o['totalAmount']) ? number_format($o['totalAmount'], 2, ',', '.') . ' TL' : '',
                        'items' => isset($o['productName']) ? $o['productName'] : '',
                        'status' => isset($o['status']) ? $o['status'] : '',
                    );
                }
            } elseif (isset($result['error'])) {
                wp_send_json_error($result['error']);
            }
        }
        wp_send_json_success($orders);
    }

    public function ajax_get_categories() {
        if (!current_user_can('manage_options')) wp_die();
        check_ajax_referer('webyaz_mp_nonce', 'nonce');
        $platform = sanitize_text_field($_POST['platform']);
        if ($platform === 'trendyol') {
            $result = $this->trendyol_request('GET', '/integration/product/product-categories');
            if (isset($result['categories'])) {
                wp_send_json_success($result['categories']);
            } else {
                wp_send_json_error(isset($result['error']) ? $result['error'] : 'Kategoriler alinamadi');
            }
        }
        wp_send_json_error('Bu platform icin kategori listesi desteklenmiyor');
    }

    public function cron_sync() {
        $opts = self::get_settings();
        if ($opts['trendyol_active'] && $opts['trendyol_auto_sync']) {
            $args = array('post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 50, 'fields' => 'ids', 'meta_query' => array(array('key' => '_webyaz_mp_trendyol', 'compare' => 'EXISTS')));
            $ids = get_posts($args);
            foreach ($ids as $pid) {
                $product = wc_get_product($pid);
                if ($product) $this->trendyol_update_stock_price($product);
                usleep(200000);
            }
        }
        if ($opts['hb_active'] && $opts['hb_auto_sync']) {
            $args = array('post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 50, 'fields' => 'ids', 'meta_query' => array(array('key' => '_webyaz_mp_hepsiburada', 'compare' => 'EXISTS')));
            $ids = get_posts($args);
            foreach ($ids as $pid) {
                $product = wc_get_product($pid);
                if ($product) $this->hb_update_stock_price($product);
                usleep(200000);
            }
        }
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Pazaryeri', 'Pazaryeri', 'manage_options', 'webyaz-marketplace', array($this, 'render_admin'));
    }

    public function render_admin() {
        $opts = self::get_settings();
        $cats = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
        $nonce = wp_create_nonce('webyaz_mp_nonce');
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Pazaryeri Entegrasyonu</h1>
                <p>Trendyol ve Hepsiburada'ya urun gonderin, stok/fiyat senkronize edin</p>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                <!-- TRENDYOL -->
                <div class="webyaz-settings-section" style="border-top:4px solid #f27a1a;">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                        <div style="width:40px;height:40px;background:#f27a1a;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                            <span style="color:#fff;font-weight:900;font-size:18px;">T</span>
                        </div>
                        <div>
                            <h2 style="margin:0;font-size:18px;">Trendyol</h2>
                            <p style="margin:0;font-size:12px;color:#888;">Trendyol Marketplace API Entegrasyonu</p>
                        </div>
                        <label style="margin-left:auto;display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" id="trendyol_active" <?php checked($opts['trendyol_active'], 1); ?> style="width:18px;height:18px;">
                            <span style="font-weight:600;font-size:13px;">Aktif</span>
                        </label>
                    </div>
                    <div id="trendyolSettings">
                        <div style="display:grid;gap:10px;">
                            <div><label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Satici ID</label>
                            <input type="text" id="trendyol_seller_id" value="<?php echo esc_attr($opts['trendyol_seller_id']); ?>" placeholder="Trendyol satici panelinden alinir" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;"></div>
                            <div><label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">API Key</label>
                            <input type="text" id="trendyol_api_key" value="<?php echo esc_attr($opts['trendyol_api_key']); ?>" placeholder="API Key" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;"></div>
                            <div><label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">API Secret</label>
                            <input type="password" id="trendyol_api_secret" value="<?php echo esc_attr($opts['trendyol_api_secret']); ?>" placeholder="API Secret" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;"></div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                <div><label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Marka ID</label>
                                <input type="text" id="trendyol_brand_id" value="<?php echo esc_attr($opts['trendyol_brand_id']); ?>" placeholder="Trendyol Marka ID" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;"></div>
                                <div><label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Kategori ID</label>
                                <input type="text" id="trendyol_category_id" value="<?php echo esc_attr($opts['trendyol_category_id']); ?>" placeholder="Trendyol Kategori ID" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;"></div>
                            </div>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
                                <input type="checkbox" id="trendyol_auto_sync" <?php checked($opts['trendyol_auto_sync'], 1); ?> style="width:16px;height:16px;">
                                Otomatik stok/fiyat senkronizasyonu (saatlik)
                            </label>
                        </div>
                        <div style="display:flex;gap:8px;margin-top:14px;">
                            <button type="button" class="button webyaz-mp-test" data-platform="trendyol" style="background:#f27a1a;color:#fff;border-color:#f27a1a;font-weight:600;">Baglanti Test</button>
                            <button type="button" class="button webyaz-mp-save" data-platform="trendyol" style="background:#446084;color:#fff;border-color:#446084;font-weight:600;">Kaydet</button>
                        </div>
                        <div class="webyaz-mp-result" data-platform="trendyol" style="margin-top:8px;font-size:13px;"></div>
                    </div>
                </div>

                <!-- HEPSIBURADA -->
                <div class="webyaz-settings-section" style="border-top:4px solid #ff6000;">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                        <div style="width:40px;height:40px;background:#ff6000;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                            <span style="color:#fff;font-weight:900;font-size:18px;">H</span>
                        </div>
                        <div>
                            <h2 style="margin:0;font-size:18px;">Hepsiburada</h2>
                            <p style="margin:0;font-size:12px;color:#888;">Hepsiburada Merchant API Entegrasyonu</p>
                        </div>
                        <label style="margin-left:auto;display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" id="hb_active" <?php checked($opts['hb_active'], 1); ?> style="width:18px;height:18px;">
                            <span style="font-weight:600;font-size:13px;">Aktif</span>
                        </label>
                    </div>
                    <div id="hbSettings">
                        <div style="display:grid;gap:10px;">
                            <div><label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Merchant ID</label>
                            <input type="text" id="hb_merchant_id" value="<?php echo esc_attr($opts['hb_merchant_id']); ?>" placeholder="Hepsiburada Merchant ID" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;"></div>
                            <div><label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Kullanici Adi</label>
                            <input type="text" id="hb_username" value="<?php echo esc_attr($opts['hb_username']); ?>" placeholder="API Kullanici Adi" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;"></div>
                            <div><label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Sifre</label>
                            <input type="password" id="hb_password" value="<?php echo esc_attr($opts['hb_password']); ?>" placeholder="API Sifre" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;"></div>
                            <div><label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Kategori ID</label>
                            <input type="text" id="hb_category_id" value="<?php echo esc_attr($opts['hb_category_id']); ?>" placeholder="Hepsiburada Kategori ID" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;"></div>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
                                <input type="checkbox" id="hb_auto_sync" <?php checked($opts['hb_auto_sync'], 1); ?> style="width:16px;height:16px;">
                                Otomatik stok/fiyat senkronizasyonu (saatlik)
                            </label>
                        </div>
                        <div style="display:flex;gap:8px;margin-top:14px;">
                            <button type="button" class="button webyaz-mp-test" data-platform="hepsiburada" style="background:#ff6000;color:#fff;border-color:#ff6000;font-weight:600;">Baglanti Test</button>
                            <button type="button" class="button webyaz-mp-save" data-platform="hepsiburada" style="background:#446084;color:#fff;border-color:#446084;font-weight:600;">Kaydet</button>
                        </div>
                        <div class="webyaz-mp-result" data-platform="hepsiburada" style="margin-top:8px;font-size:13px;"></div>
                    </div>
                </div>
            </div>

            <!-- URUN GONDER -->
            <div class="webyaz-settings-section" style="margin-top:24px;">
                <h2>Urun Gonder</h2>
                <p style="color:#666;font-size:13px;">Sectiginiz urunleri pazaryerlerine gonderin veya stok/fiyat guncelleyin.</p>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;">
                    <div>
                        <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Platform</label>
                        <select id="mpSendPlatform" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                            <option value="trendyol">Trendyol</option>
                            <option value="hepsiburada">Hepsiburada</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Kategori Filtresi</label>
                        <select id="mpSendCategory" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                            <option value="">Tum Urunler</option>
                            <?php foreach ($cats as $cat): ?>
                            <option value="<?php echo esc_attr($cat->slug); ?>"><?php echo esc_html($cat->name); ?> (<?php echo $cat->count; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Islem</label>
                        <select id="mpSendAction" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                            <option value="send">Urunleri Gonder (Yeni)</option>
                            <option value="sync">Stok/Fiyat Guncelle</option>
                        </select>
                    </div>
                </div>
                <div style="display:flex;gap:12px;">
                    <button type="button" id="mpSendBtn" class="button button-primary" style="padding:10px 24px;font-size:14px;font-weight:600;background:#2e7d32;border-color:#2e7d32;">Baslat</button>
                    <button type="button" id="mpFetchOrders" class="button" style="padding:10px 24px;font-size:14px;font-weight:600;background:#1565c0;color:#fff;border-color:#1565c0;">Siparisleri Getir</button>
                </div>
                <div id="mpSendResult" style="margin-top:12px;display:none;padding:14px;border-radius:8px;font-size:14px;"></div>
            </div>

            <!-- SIPARISLER -->
            <div class="webyaz-settings-section" style="margin-top:24px;">
                <h2>Pazaryeri Siparisleri</h2>
                <div id="mpOrdersList" style="font-size:13px;color:#666;">Siparisleri gormek icin yukardaki "Siparisleri Getir" butonuna basin.</div>
            </div>

            <!-- REHBER -->
            <div class="webyaz-settings-section" style="margin-top:24px;">
                <h2>Nasil Kullanilir?</h2>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div style="background:#fff3e0;border-radius:10px;padding:16px;border-left:4px solid #f27a1a;">
                        <h3 style="margin:0 0 8px;font-size:14px;color:#f27a1a;">Trendyol API Bilgileri Nereden Alinir?</h3>
                        <ol style="margin:0;padding-left:18px;font-size:12px;line-height:2;color:#333;">
                            <li>Trendyol Satici Paneli'ne giris yapin</li>
                            <li>Entegrasyon > API Bilgileri sayfasina gidin</li>
                            <li>Satici ID, API Key ve API Secret'i kopyalayin</li>
                            <li>Marka ID: Trendyol'da marka arayin, URL'deki ID'yi alin</li>
                            <li>Kategori ID: Trendyol kategori agacindan bulun</li>
                        </ol>
                    </div>
                    <div style="background:#fff3e0;border-radius:10px;padding:16px;border-left:4px solid #ff6000;">
                        <h3 style="margin:0 0 8px;font-size:14px;color:#ff6000;">Hepsiburada API Bilgileri Nereden Alinir?</h3>
                        <ol style="margin:0;padding-left:18px;font-size:12px;line-height:2;color:#333;">
                            <li>Hepsiburada Satici Paneli'ne giris yapin</li>
                            <li>Hesap > Entegrasyon Bilgileri'ne gidin</li>
                            <li>Merchant ID, Kullanici Adi ve Sifre'yi kopyalayin</li>
                            <li>Kategori ID: Hepsiburada kategori listesinden secin</li>
                            <li>"Baglanti Test" ile kontrol edin</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(function($){
            var nonce = '<?php echo $nonce; ?>';

            // Kaydet
            $(document).on('click', '.webyaz-mp-save', function(){
                var btn = $(this);
                var data = {action:'webyaz_marketplace_save', nonce:nonce};
                var fields = ['trendyol_seller_id','trendyol_api_key','trendyol_api_secret','trendyol_brand_id','trendyol_category_id','hb_merchant_id','hb_username','hb_password','hb_category_id'];
                fields.forEach(function(f){ data[f] = $('#'+f).val(); });
                data.trendyol_active = $('#trendyol_active').is(':checked') ? 1 : 0;
                data.trendyol_auto_sync = $('#trendyol_auto_sync').is(':checked') ? 1 : 0;
                data.hb_active = $('#hb_active').is(':checked') ? 1 : 0;
                data.hb_auto_sync = $('#hb_auto_sync').is(':checked') ? 1 : 0;
                btn.text('Kaydediliyor...').prop('disabled', true);
                $.post(ajaxurl, data, function(res){
                    btn.text('Kaydedildi!').css('background','#2e7d32');
                    setTimeout(function(){ btn.text('Kaydet').css('background','#446084').prop('disabled', false); }, 2000);
                });
            });

            // Baglanti test
            $(document).on('click', '.webyaz-mp-test', function(){
                var btn = $(this);
                var platform = btn.data('platform');
                var resultDiv = $('.webyaz-mp-result[data-platform="'+platform+'"]');
                btn.text('Test ediliyor...').prop('disabled', true);
                // once kaydet
                var data = {action:'webyaz_marketplace_save', nonce:nonce};
                var fields = ['trendyol_seller_id','trendyol_api_key','trendyol_api_secret','trendyol_brand_id','trendyol_category_id','hb_merchant_id','hb_username','hb_password','hb_category_id'];
                fields.forEach(function(f){ data[f] = $('#'+f).val(); });
                data.trendyol_active = $('#trendyol_active').is(':checked') ? 1 : 0;
                data.hb_active = $('#hb_active').is(':checked') ? 1 : 0;
                data.trendyol_auto_sync = $('#trendyol_auto_sync').is(':checked') ? 1 : 0;
                data.hb_auto_sync = $('#hb_auto_sync').is(':checked') ? 1 : 0;
                $.post(ajaxurl, data, function(){
                    $.post(ajaxurl, {action:'webyaz_marketplace_test', nonce:nonce, platform:platform}, function(res){
                        if (res.success) {
                            resultDiv.html('<span style="color:#2e7d32;font-weight:600;">&#10004; ' + res.data + '</span>');
                        } else {
                            resultDiv.html('<span style="color:#c62828;font-weight:600;">&#10008; ' + res.data + '</span>');
                        }
                        btn.text('Baglanti Test').prop('disabled', false);
                    }).fail(function(){
                        resultDiv.html('<span style="color:#c62828;">Sunucu hatasi</span>');
                        btn.text('Baglanti Test').prop('disabled', false);
                    });
                });
            });

            // Urun gonder / stok sync
            $('#mpSendBtn').on('click', function(){
                var btn = $(this);
                var platform = $('#mpSendPlatform').val();
                var category = $('#mpSendCategory').val();
                var action_type = $('#mpSendAction').val();
                var ajaxAction = action_type === 'sync' ? 'webyaz_marketplace_sync_stock' : 'webyaz_marketplace_send';
                var totalSent = 0, totalErrors = 0;

                function runBatch(off) {
                    var postData = {action:ajaxAction, nonce:nonce, platform:platform, category:category, offset:off};
                    $.post(ajaxurl, postData, function(res){
                        if (res.success) {
                            var d = res.data;
                            totalSent += (d.sent || d.synced || 0);
                            totalErrors += d.errors;
                            var pct = Math.min(100, Math.round((d.offset / d.total) * 100));
                            btn.text('Islem devam ediyor... (' + pct + '%)');
                            $('#mpSendResult').html('<div style="background:#e3f2fd;border-radius:6px;height:8px;margin-bottom:8px;"><div style="background:#2e7d32;height:100%;border-radius:6px;width:'+pct+'%;transition:width 0.3s;"></div></div>' + totalSent + ' basarili, ' + totalErrors + ' hata').css({background:'#e8f5e9',color:'#2e7d32'}).show();
                            if (!d.done) {
                                setTimeout(function(){ runBatch(d.offset); }, 1000);
                            } else {
                                btn.text('Baslat').prop('disabled', false);
                                var msg = '<strong>' + totalSent + '</strong> urun islendi';
                                if (totalErrors > 0) msg += ', <strong style="color:#c62828;">' + totalErrors + '</strong> hata';
                                if (d.error_msgs && d.error_msgs.length > 0) {
                                    msg += '<br><small style="color:#c62828;">' + d.error_msgs.join('<br>') + '</small>';
                                }
                                $('#mpSendResult').html(msg + ' - <strong>Tamamlandi!</strong>').show();
                            }
                        } else {
                            $('#mpSendResult').html('Hata: ' + res.data).css({background:'#ffebee',color:'#c62828'}).show();
                            btn.text('Baslat').prop('disabled', false);
                        }
                    }).fail(function(){
                        $('#mpSendResult').html('Sunucu hatasi').css({background:'#ffebee',color:'#c62828'}).show();
                        btn.text('Baslat').prop('disabled', false);
                    });
                }

                btn.text('Baslatiliyor...').prop('disabled', true);
                $('#mpSendResult').html('Hazirlaniyor...').css({background:'#e3f2fd',color:'#1565c0'}).show();
                runBatch(0);
            });

            // Siparisleri getir
            $('#mpFetchOrders').on('click', function(){
                var btn = $(this);
                var platform = $('#mpSendPlatform').val();
                btn.text('Getiriliyor...').prop('disabled', true);
                $.post(ajaxurl, {action:'webyaz_marketplace_fetch_orders', nonce:nonce, platform:platform}, function(res){
                    if (res.success) {
                        var orders = res.data;
                        if (!orders || orders.length === 0) {
                            $('#mpOrdersList').html('<p style="color:#888;">Siparis bulunamadi.</p>');
                        } else {
                            var html = '<table style="width:100%;border-collapse:collapse;font-size:13px;"><tr style="background:#f5f5f5;"><th style="padding:10px;text-align:left;">Siparis No</th><th style="padding:10px;text-align:left;">Tarih</th><th style="padding:10px;text-align:left;">Musteri</th><th style="padding:10px;text-align:left;">Urunler</th><th style="padding:10px;text-align:right;">Toplam</th><th style="padding:10px;text-align:center;">Durum</th></tr>';
                            orders.forEach(function(o){
                                html += '<tr style="border-bottom:1px solid #eee;"><td style="padding:8px;font-weight:600;">' + o.id + '</td><td style="padding:8px;">' + o.date + '</td><td style="padding:8px;">' + o.customer + '</td><td style="padding:8px;max-width:200px;overflow:hidden;text-overflow:ellipsis;">' + o.items + '</td><td style="padding:8px;text-align:right;font-weight:600;">' + o.total + '</td><td style="padding:8px;text-align:center;"><span style="background:#e8f5e9;color:#2e7d32;padding:3px 10px;border-radius:20px;font-size:11px;">' + o.status + '</span></td></tr>';
                            });
                            html += '</table>';
                            $('#mpOrdersList').html(html);
                        }
                    } else {
                        $('#mpOrdersList').html('<p style="color:#c62828;">Hata: ' + res.data + '</p>');
                    }
                    btn.text('Siparisleri Getir').prop('disabled', false);
                }).fail(function(){
                    $('#mpOrdersList').html('<p style="color:#c62828;">Sunucu hatasi</p>');
                    btn.text('Siparisleri Getir').prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }
}

new Webyaz_Marketplace();
