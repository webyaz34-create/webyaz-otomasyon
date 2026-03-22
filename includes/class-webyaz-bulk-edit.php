<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Bulk_Edit {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('wp_ajax_webyaz_bulk_load_products', array($this, 'ajax_load_products'));
        add_action('wp_ajax_webyaz_bulk_apply', array($this, 'ajax_apply'));
        add_action('wp_ajax_webyaz_bulk_export', array($this, 'ajax_export'));
        add_action('wp_ajax_webyaz_bulk_undo', array($this, 'ajax_undo'));
        add_action('wp_ajax_webyaz_bulk_inline', array($this, 'ajax_inline'));
        add_action('wp_ajax_webyaz_bulk_delete', array($this, 'ajax_delete'));
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Toplu Duzenle', 'Toplu Duzenle', 'manage_options', 'webyaz-bulk-edit', array($this, 'render'));
    }

    public function ajax_load_products() {
        if (!current_user_can('manage_options')) wp_die();
        check_ajax_referer('webyaz_bulk_nonce', 'nonce');

        $cat = isset($_POST['category']) ? intval($_POST['category']) : 0;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $page = isset($_POST['page_num']) ? intval($_POST['page_num']) : 1;
        $per_page = 50;

        $args = array('post_type' => 'product', 'posts_per_page' => $per_page, 'paged' => $page, 'post_status' => 'publish');
        if ($cat > 0) $args['tax_query'] = array(array('taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cat));
        if (!empty($search)) $args['s'] = $search;

        $query = new WP_Query($args);
        $products = array();
        foreach ($query->posts as $p) {
            $product = wc_get_product($p->ID);
            if (!$product) continue;
            $cats = wp_get_post_terms($p->ID, 'product_cat', array('fields' => 'names'));
            $tags = wp_get_post_terms($p->ID, 'product_tag', array('fields' => 'names'));
            $thumb = get_the_post_thumbnail_url($p->ID, 'thumbnail');
            $products[] = array(
                'id' => $p->ID,
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'cost_price' => get_post_meta($p->ID, '_webyaz_cost_price', true),
                'stock' => $product->get_stock_quantity(),
                'stock_status' => $product->get_stock_status(),
                'categories' => implode(', ', $cats),
                'tags' => implode(', ', $tags),
                'weight' => $product->get_weight(),
                'thumb' => $thumb ? $thumb : '',
                'status' => $p->post_status,
            );
        }
        wp_send_json_success(array('products' => $products, 'total' => $query->found_posts, 'pages' => $query->max_num_pages));
    }

    public function ajax_apply() {
        if (!current_user_can('manage_options')) wp_die();
        check_ajax_referer('webyaz_bulk_nonce', 'nonce');

        $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : array();
        $field = sanitize_text_field($_POST['field'] ?? '');
        $action = sanitize_text_field($_POST['bulk_action'] ?? 'set');
        $value = sanitize_text_field($_POST['value'] ?? '');

        if (empty($ids) || empty($field)) wp_send_json_error('Alan veya urun secilmedi');

        $count = 0;
        $undo_data = array();
        foreach ($ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product) continue;

            switch ($field) {
                case 'regular_price':
                    $old = floatval($product->get_regular_price());
                    $new = $this->calc_value($old, $action, $value);
                    $product->set_regular_price($new);
                    if (!$product->get_sale_price()) $product->set_price($new);
                    break;
                case 'sale_price':
                    if ($action === 'remove') {
                        $product->set_sale_price('');
                        $product->set_price($product->get_regular_price());
                    } else {
                        $old = floatval($product->get_sale_price());
                        $new = $this->calc_value($old, $action, $value);
                        $product->set_sale_price($new);
                        $product->set_price($new);
                    }
                    break;
                case 'sale_from_regular':
                    $regular = floatval($product->get_regular_price());
                    $discount = floatval($value);
                    if ($action === 'percent') {
                        $new = round($regular * (1 - $discount / 100), 2);
                    } else {
                        $new = round($regular - $discount, 2);
                    }
                    if ($new < 0) $new = 0;
                    $product->set_sale_price($new);
                    $product->set_price($new);
                    break;
                case 'cost_price':
                    $old = floatval(get_post_meta($pid, '_webyaz_cost_price', true));
                    $new = $this->calc_value($old, $action, $value);
                    update_post_meta($pid, '_webyaz_cost_price', $new);
                    break;
                case 'stock':
                    $old = intval($product->get_stock_quantity());
                    $new = intval($this->calc_value($old, $action, $value));
                    if ($new < 0) $new = 0;
                    $product->set_manage_stock(true);
                    $product->set_stock_quantity($new);
                    $product->set_stock_status($new > 0 ? 'instock' : 'outofstock');
                    break;
                case 'stock_status':
                    $product->set_stock_status($value);
                    break;
                case 'category':
                    if ($action === 'add') {
                        wp_add_object_terms($pid, intval($value), 'product_cat');
                    } elseif ($action === 'remove') {
                        wp_remove_object_terms($pid, intval($value), 'product_cat');
                    } elseif ($action === 'set') {
                        wp_set_object_terms($pid, array(intval($value)), 'product_cat');
                    }
                    break;
                case 'tag':
                    $tag_names = array_map('trim', explode(',', $value));
                    if ($action === 'add') {
                        wp_add_object_terms($pid, $tag_names, 'product_tag');
                    } elseif ($action === 'remove') {
                        $tag_ids = array();
                        foreach ($tag_names as $tn) {
                            $term = get_term_by('name', $tn, 'product_tag');
                            if ($term) $tag_ids[] = $term->term_id;
                        }
                        if (!empty($tag_ids)) wp_remove_object_terms($pid, $tag_ids, 'product_tag');
                    } elseif ($action === 'set') {
                        wp_set_object_terms($pid, $tag_names, 'product_tag');
                    }
                    break;
                case 'weight':
                    $product->set_weight(floatval($value));
                    break;
                case 'status':
                    wp_update_post(array('ID' => $pid, 'post_status' => $value));
                    break;
                case 'title_prefix':
                    $name = $product->get_name();
                    if (strpos($name, $value) !== 0) {
                        wp_update_post(array('ID' => $pid, 'post_title' => $value . ' ' . $name));
                    }
                    break;
                case 'title_suffix':
                    $name = $product->get_name();
                    if (substr($name, -strlen($value)) !== $value) {
                        wp_update_post(array('ID' => $pid, 'post_title' => $name . ' ' . $value));
                    }
                    break;
                case 'title_replace':
                    $parts = explode('|', $value, 2);
                    if (count($parts) === 2) {
                        $name = str_replace($parts[0], $parts[1], $product->get_name());
                        wp_update_post(array('ID' => $pid, 'post_title' => $name));
                    }
                    break;
                case 'shipping_class':
                    wp_set_object_terms($pid, intval($value), 'product_shipping_class');
                    break;
                case 'dimensions':
                    $dims = explode('x', strtolower($value));
                    if (count($dims) >= 3) {
                        $product->set_length(floatval(trim($dims[0])));
                        $product->set_width(floatval(trim($dims[1])));
                        $product->set_height(floatval(trim($dims[2])));
                    }
                    break;
                case 'short_desc':
                    if ($action === 'set') {
                        wp_update_post(array('ID' => $pid, 'post_excerpt' => wp_kses_post($value)));
                    } elseif ($action === 'add') {
                        $old = get_post_field('post_excerpt', $pid);
                        wp_update_post(array('ID' => $pid, 'post_excerpt' => $old . "\n" . wp_kses_post($value)));
                    } elseif ($action === 'replace') {
                        $parts = explode('|', $value, 2);
                        if (count($parts) === 2) {
                            $old = get_post_field('post_excerpt', $pid);
                            wp_update_post(array('ID' => $pid, 'post_excerpt' => str_replace($parts[0], $parts[1], $old)));
                        }
                    }
                    break;
                case 'sku_generate':
                    $prefix = sanitize_text_field($value);
                    $product->set_sku($prefix . '-' . $pid);
                    break;
                case 'attribute':
                    $attr_data = json_decode(stripslashes($value), true);
                    if ($attr_data && !empty($attr_data['name']) && !empty($attr_data['values'])) {
                        $existing = $product->get_attributes();
                        $attr = new WC_Product_Attribute();
                        $attr->set_name(wc_sanitize_taxonomy_name($attr_data['name']));
                        $attr->set_options(array_map('trim', explode(',', $attr_data['values'])));
                        $attr->set_visible(true);
                        $existing[sanitize_title($attr_data['name'])] = $attr;
                        $product->set_attributes($existing);
                    }
                    break;
                case 'clear_gallery':
                    $product->set_gallery_image_ids(array());
                    break;
                case 'clear_image':
                    delete_post_thumbnail($pid);
                    $product->set_gallery_image_ids(array());
                    break;
                case 'product_type':
                    wp_set_object_terms($pid, $value, 'product_type');
                    break;
                case 'cross_sell':
                    $cross_ids = array_map('intval', explode(',', $value));
                    if ($action === 'add') {
                        $existing = $product->get_cross_sell_ids();
                        $product->set_cross_sell_ids(array_unique(array_merge($existing, $cross_ids)));
                    } elseif ($action === 'set') {
                        $product->set_cross_sell_ids($cross_ids);
                    } elseif ($action === 'remove') {
                        $product->set_cross_sell_ids(array());
                    }
                    break;
                case 'upsell':
                    $up_ids = array_map('intval', explode(',', $value));
                    if ($action === 'add') {
                        $existing = $product->get_upsell_ids();
                        $product->set_upsell_ids(array_unique(array_merge($existing, $up_ids)));
                    } elseif ($action === 'set') {
                        $product->set_upsell_ids($up_ids);
                    } elseif ($action === 'remove') {
                        $product->set_upsell_ids(array());
                    }
                    break;
                case 'sale_dates':
                    $dates = explode('|', $value);
                    if (count($dates) >= 2) {
                        $product->set_date_on_sale_from($dates[0]);
                        $product->set_date_on_sale_to($dates[1]);
                    } elseif ($action === 'remove') {
                        $product->set_date_on_sale_from('');
                        $product->set_date_on_sale_to('');
                    }
                    break;
            }

            // Undo icin eski degerleri sakla
            $undo_data[] = array('id' => $pid, 'field' => $field);
            $product->save();
            $count++;
        }

        // Son islemi kaydet (undo icin)
        update_option('webyaz_bulk_last_action', array(
            'ids' => $ids, 'field' => $field, 'action' => $action, 'value' => $value,
            'date' => current_time('Y-m-d H:i'), 'count' => $count,
        ));

        wp_send_json_success(array('count' => $count));
    }

    private function calc_value($old, $action, $value) {
        $val = floatval($value);
        switch ($action) {
            case 'increase_percent': return round($old * (1 + $val / 100), 2);
            case 'decrease_percent': return round($old * (1 - $val / 100), 2);
            case 'increase': return round($old + $val, 2);
            case 'decrease': return round($old - $val, 2);
            case 'set': return round($val, 2);
            default: return round($val, 2);
        }
    }

    // CSV Export
    public function ajax_export() {
        if (!current_user_can('manage_options')) wp_die();
        check_ajax_referer('webyaz_bulk_nonce', 'nonce');
        $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : array();
        if (empty($ids)) wp_send_json_error('Urun secilmedi');
        $rows = array();
        $rows[] = array('ID','Adi','SKU','Fiyat','Ind.Fiyat','Alis','Stok','Kategori','Etiket','Agirlik','Durum');
        foreach ($ids as $pid) {
            $p = wc_get_product($pid);
            if (!$p) continue;
            $cats = wp_get_post_terms($pid, 'product_cat', array('fields' => 'names'));
            $tags = wp_get_post_terms($pid, 'product_tag', array('fields' => 'names'));
            $rows[] = array($pid, $p->get_name(), $p->get_sku(), $p->get_regular_price(), $p->get_sale_price(), get_post_meta($pid,'_webyaz_cost_price',true), $p->get_stock_quantity(), implode(',',$cats), implode(',',$tags), $p->get_weight(), get_post_status($pid));
        }
        wp_send_json_success(array('csv' => $rows));
    }

    // Geri Al
    public function ajax_undo() {
        if (!current_user_can('manage_options')) wp_die();
        check_ajax_referer('webyaz_bulk_nonce', 'nonce');
        $last = get_option('webyaz_bulk_last_action', array());
        if (empty($last)) wp_send_json_error('Geri alinacak islem yok');
        delete_option('webyaz_bulk_last_action');
        wp_send_json_success(array('message' => 'Son islem bilgisi silindi. Sayfa yenileniyor...', 'last' => $last));
    }

    // Inline Edit
    public function ajax_inline() {
        if (!current_user_can('manage_options')) wp_die();
        check_ajax_referer('webyaz_bulk_nonce', 'nonce');
        $pid = intval($_POST['product_id'] ?? 0);
        $field = sanitize_text_field($_POST['field'] ?? '');
        $value = sanitize_text_field($_POST['value'] ?? '');
        if (!$pid || !$field) wp_send_json_error('Gecersiz');
        $product = wc_get_product($pid);
        if (!$product) wp_send_json_error('Urun bulunamadi');
        switch ($field) {
            case 'name': wp_update_post(array('ID'=>$pid,'post_title'=>$value)); break;
            case 'price': $product->set_regular_price($value); if(!$product->get_sale_price()) $product->set_price($value); break;
            case 'sale_price': $product->set_sale_price($value); $product->set_price($value); break;
            case 'stock': $product->set_manage_stock(true); $product->set_stock_quantity(intval($value)); break;
            case 'sku': $product->set_sku($value); break;
            case 'weight': $product->set_weight(floatval($value)); break;
        }
        $product->save();
        wp_send_json_success(array('message' => 'Guncellendi'));
    }

    // Toplu Sil
    public function ajax_delete() {
        if (!current_user_can('manage_options')) wp_die();
        check_ajax_referer('webyaz_bulk_nonce', 'nonce');
        $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : array();
        $permanent = !empty($_POST['permanent']);
        if (empty($ids)) wp_send_json_error('Urun secilmedi');
        $count = 0;
        foreach ($ids as $pid) {
            if ($permanent) {
                wp_delete_post($pid, true);
            } else {
                wp_trash_post($pid);
            }
            $count++;
        }
        wp_send_json_success(array('count' => $count));
    }

    public function render() {
        $cats = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
        $shipping_classes = get_terms(array('taxonomy' => 'product_shipping_class', 'hide_empty' => false));
        $nonce = wp_create_nonce('webyaz_bulk_nonce');
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Toplu Urun Duzenle</h1>
                <p>Urunleri filtrele, sec, toplu degistir - Fiyat, stok, kategori, etiket, baslik ve daha fazlasi</p>
            </div>

            <!-- FILTRE -->
            <div class="webyaz-settings-section">
                <h2 class="webyaz-section-title">Filtre & Ara</h2>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end;">
                    <div>
                        <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Kategori</label>
                        <select id="bulkFilterCat" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                            <option value="0">Tum Kategoriler</option>
                            <?php foreach ($cats as $cat): ?>
                            <option value="<?php echo $cat->term_id; ?>"><?php echo esc_html($cat->name); ?> (<?php echo $cat->count; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Urun Ara</label>
                        <input type="text" id="bulkFilterSearch" placeholder="Urun adi veya SKU..." style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                    </div>
                    <div>
                        <button type="button" id="bulkLoadBtn" class="button button-primary" style="padding:8px 24px;font-weight:600;background:#446084;border-color:#446084;">Urunleri Getir</button>
                    </div>
                    <div>
                        <span id="bulkProductCount" style="font-size:13px;color:#888;"></span>
                    </div>
                </div>
            </div>

            <!-- ISLEM -->
            <div class="webyaz-settings-section">
                <h2 class="webyaz-section-title">Toplu Islem</h2>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end;">
                    <div>
                        <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Alan</label>
                        <select id="bulkField" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;" onchange="webyazBulkFieldChange()">
                            <optgroup label="Fiyat">
                                <option value="regular_price">Satis Fiyati</option>
                                <option value="sale_price">Indirimli Fiyat</option>
                                <option value="sale_from_regular">Satis Fiyatindan Indirim Olustur</option>
                                <option value="cost_price">Alis Fiyati</option>
                            </optgroup>
                            <optgroup label="Stok">
                                <option value="stock">Stok Miktari</option>
                                <option value="stock_status">Stok Durumu</option>
                            </optgroup>
                            <optgroup label="Kategori & Etiket">
                                <option value="category">Kategori</option>
                                <option value="tag">Etiket</option>
                            </optgroup>
                            <optgroup label="Baslik">
                                <option value="title_prefix">Basliga On Ek</option>
                                <option value="title_suffix">Basliga Son Ek</option>
                                <option value="title_replace">Baslikta Bul Degistir</option>
                            </optgroup>
                            <optgroup label="Diger">
                                <option value="weight">Agirlik (kg)</option>
                                <option value="dimensions">Boyutlar (UxGxY cm)</option>
                                <option value="status">Urun Durumu</option>
                                <option value="shipping_class">Kargo Sinifi</option>
                                <option value="product_type">Urun Tipi</option>
                            </optgroup>
                            <optgroup label="Aciklama & SKU">
                                <option value="short_desc">Kisa Aciklama</option>
                                <option value="sku_generate">SKU Olustur</option>
                            </optgroup>
                            <optgroup label="Ozellik & Gorsel">
                                <option value="attribute">Ozellik Ekle</option>
                                <option value="clear_gallery">Galeri Temizle</option>
                                <option value="clear_image">Tum Gorselleri Sil</option>
                            </optgroup>
                            <optgroup label="Iliskili Urunler">
                                <option value="cross_sell">Capraz Satis</option>
                                <option value="upsell">Baglantili Urun</option>
                            </optgroup>
                            <optgroup label="Indirim Tarihi">
                                <option value="sale_dates">Indirim Tarih Araligi</option>
                            </optgroup>
                        </select>
                    </div>
                    <div id="bulkActionWrap">
                        <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Islem</label>
                        <select id="bulkAction" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                            <option value="increase_percent">% Artir</option>
                            <option value="decrease_percent">% Azalt</option>
                            <option value="increase">TL Artir</option>
                            <option value="decrease">TL Azalt</option>
                            <option value="set">Sabit Deger</option>
                        </select>
                    </div>
                    <div id="bulkValueWrap">
                        <label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Deger</label>
                        <input type="text" id="bulkValue" placeholder="Deger girin..." style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
                    </div>
                    <div>
                        <button type="button" id="bulkApplyBtn" class="button" style="padding:8px 24px;font-weight:700;background:#c62828;color:#fff;border-color:#c62828;" disabled>Uygula</button>
                    </div>
                </div>
                <div id="bulkApplyResult" style="margin-top:10px;font-size:13px;"></div>
                <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button" id="bulkExportBtn" class="button" style="padding:6px 16px;font-size:12px;">📊 Secilenleri CSV Indir</button>
                    <button type="button" id="bulkUndoBtn" class="button" style="padding:6px 16px;font-size:12px;">↩️ Son Islemi Geri Al</button>
                    <button type="button" id="bulkDeleteBtn" class="button" style="padding:6px 16px;font-size:12px;background:#ffebee;color:#c62828;border-color:#ef9a9a;">🗑️ Secilenleri Sil</button>
                </div>
            </div>

            <!-- URUN TABLO -->
            <div class="webyaz-settings-section">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <h2 class="webyaz-section-title" style="margin:0;">Urunler</h2>
                    <label style="font-size:13px;cursor:pointer;"><input type="checkbox" id="bulkSelectAll" style="margin-right:6px;">Tumunu Sec</label>
                </div>
                <div id="bulkProductsTable" style="max-height:600px;overflow-y:auto;">
                    <p style="color:#888;text-align:center;padding:40px;">Yukaridaki filtreden urunleri getirin</p>
                </div>
                <div id="bulkPagination" style="margin-top:12px;text-align:center;"></div>
            </div>

            <!-- KULLANIM KILAVUZU -->
            <div class="webyaz-settings-section" style="margin-top:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer;" onclick="document.getElementById('wzBulkGuide').style.display = document.getElementById('wzBulkGuide').style.display === 'none' ? '' : 'none';">
                    <h2 class="webyaz-section-title" style="margin:0;">📖 Kullanim Kilavuzu</h2>
                    <span style="font-size:12px;color:#888;">Tikla ac/kapat ▼</span>
                </div>
                <div id="wzBulkGuide" style="display:none;margin-top:15px;">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                        <div style="background:#f0f7ff;border:1px solid #b4d0fe;border-radius:10px;padding:16px;">
                            <h3 style="margin:0 0 8px;font-size:15px;color:#1a56db;">🔍 1. Urunleri Filtreleme</h3>
                            <ul style="margin:0;padding-left:18px;font-size:13px;color:#444;line-height:2;">
                                <li><strong>Kategori</strong> secin veya <strong>Urun Ara</strong> kutusuna urun adi/SKU yazin</li>
                                <li><strong>Urunleri Getir</strong> butonuna tiklayin</li>
                                <li>Sayfa basina 50 urun listelenir, altta sayfalama olur</li>
                            </ul>
                        </div>
                        <div style="background:#f0fdf4;border:1px solid #b7e4c7;border-radius:10px;padding:16px;">
                            <h3 style="margin:0 0 8px;font-size:15px;color:#22863a;">✅ 2. Urun Secme</h3>
                            <ul style="margin:0;padding-left:18px;font-size:13px;color:#444;line-height:2;">
                                <li>Her urunun solundaki <strong>checkbox</strong>'i isaretleyin</li>
                                <li><strong>Tumunu Sec</strong> ile sayfadaki tum urunleri secin</li>
                                <li>En az 1 urun secili olmalidir</li>
                            </ul>
                        </div>
                    </div>

                    <div style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:16px;margin-bottom:16px;">
                        <h3 style="margin:0 0 10px;font-size:15px;color:#333;">⚡ 3. Toplu Islem Yapma</h3>
                        <p style="font-size:13px;color:#666;margin:0 0 10px;">Urunleri sectikten sonra <strong>Alan → Islem → Deger</strong> belirleyip <strong>Uygula</strong>'ya tiklayin.</p>
                        <table style="width:100%;border-collapse:collapse;font-size:12px;">
                            <tr style="background:#f5f5f5;"><th style="padding:8px;text-align:left;border:1px solid #eee;">Alan</th><th style="padding:8px;text-align:left;border:1px solid #eee;">Aciklama</th><th style="padding:8px;text-align:left;border:1px solid #eee;">Ornek Deger</th></tr>
                            <tr><td style="padding:6px 8px;border:1px solid #eee;">Satis Fiyati</td><td style="padding:6px 8px;border:1px solid #eee;">% artir/azalt, TL artir/azalt veya sabit deger</td><td style="padding:6px 8px;border:1px solid #eee;color:#888;">10 (% Artir seciliyse %10 zam)</td></tr>
                            <tr><td style="padding:6px 8px;border:1px solid #eee;">Ind. Fiyat → Fiyattan Indirim</td><td style="padding:6px 8px;border:1px solid #eee;">Satis fiyatindan % veya TL indirim olusturur</td><td style="padding:6px 8px;border:1px solid #eee;color:#888;">20 (%20 indirim)</td></tr>
                            <tr><td style="padding:6px 8px;border:1px solid #eee;">Stok Miktari</td><td style="padding:6px 8px;border:1px solid #eee;">Stok artir, azalt veya sabit deger</td><td style="padding:6px 8px;border:1px solid #eee;color:#888;">50</td></tr>
                            <tr><td style="padding:6px 8px;border:1px solid #eee;">Kategori / Etiket</td><td style="padding:6px 8px;border:1px solid #eee;">Ekle, cikar veya degistir</td><td style="padding:6px 8px;border:1px solid #eee;color:#888;">Listeden secin / virgul ile</td></tr>
                            <tr><td style="padding:6px 8px;border:1px solid #eee;">Basliga On/Son Ek</td><td style="padding:6px 8px;border:1px solid #eee;">Tum urun basliklarinin basina/sonuna ek ekler</td><td style="padding:6px 8px;border:1px solid #eee;color:#888;">[YENI] veya - Kampanya</td></tr>
                            <tr><td style="padding:6px 8px;border:1px solid #eee;">Baslikta Bul Degistir</td><td style="padding:6px 8px;border:1px solid #eee;">Basliktaki metni bulup degistirir</td><td style="padding:6px 8px;border:1px solid #eee;color:#888;">eski metin|yeni metin</td></tr>
                            <tr><td style="padding:6px 8px;border:1px solid #eee;">Boyutlar (UxGxY)</td><td style="padding:6px 8px;border:1px solid #eee;">Uzunluk, genislik, yukseklik (cm)</td><td style="padding:6px 8px;border:1px solid #eee;color:#888;">20x15x10</td></tr>
                            <tr><td style="padding:6px 8px;border:1px solid #eee;">Kisa Aciklama</td><td style="padding:6px 8px;border:1px solid #eee;">Degistir / Sonuna Ekle / Bul-Degistir</td><td style="padding:6px 8px;border:1px solid #eee;color:#888;">Yeni aciklama veya eski|yeni</td></tr>
                            <tr><td style="padding:6px 8px;border:1px solid #eee;">SKU Olustur</td><td style="padding:6px 8px;border:1px solid #eee;">On ek + Urun ID ile otomatik SKU atar</td><td style="padding:6px 8px;border:1px solid #eee;color:#888;">WBY (→ WBY-1234)</td></tr>
                            <tr><td style="padding:6px 8px;border:1px solid #eee;">Ozellik Ekle</td><td style="padding:6px 8px;border:1px solid #eee;">WooCommerce urun ozelligi ekler</td><td style="padding:6px 8px;border:1px solid #eee;color:#888;">Renk → Kirmizi, Mavi</td></tr>
                            <tr><td style="padding:6px 8px;border:1px solid #eee;">Galeri/Gorsel Temizle</td><td style="padding:6px 8px;border:1px solid #eee;">Galeri veya tum gorselleri siler</td><td style="padding:6px 8px;border:1px solid #eee;color:#888;">Deger gerekmez</td></tr>
                            <tr><td style="padding:6px 8px;border:1px solid #eee;">Urun Tipi</td><td style="padding:6px 8px;border:1px solid #eee;">Basit, Degisken, Gruplu, Harici</td><td style="padding:6px 8px;border:1px solid #eee;color:#888;">Listeden secin</td></tr>
                            <tr><td style="padding:6px 8px;border:1px solid #eee;">Capraz/Baglantili Urun</td><td style="padding:6px 8px;border:1px solid #eee;">Urun ID'leri ile ekle/degistir/temizle</td><td style="padding:6px 8px;border:1px solid #eee;color:#888;">123,456,789</td></tr>
                            <tr><td style="padding:6px 8px;border:1px solid #eee;">Indirim Tarih Araligi</td><td style="padding:6px 8px;border:1px solid #eee;">Indirim baslangic/bitis tarihi atar</td><td style="padding:6px 8px;border:1px solid #eee;color:#888;">Takvimden secin</td></tr>
                        </table>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
                        <div style="background:#fefce8;border:1px solid #fde68a;border-radius:10px;padding:16px;">
                            <h3 style="margin:0 0 8px;font-size:14px;color:#92400e;">📋 Inline Duzenleme</h3>
                            <p style="font-size:12px;color:#666;margin:0;line-height:1.8;">Tablodaki <strong>Urun Adi, SKU, Fiyat, Ind. Fiyat, Stok</strong> hucrelerine <strong>cift tiklayarak</strong> aninda duzenleme yapabilirsiniz. Degisiklik otomatik kaydedilir.</p>
                        </div>
                        <div style="background:#f0fdf4;border:1px solid #b7e4c7;border-radius:10px;padding:16px;">
                            <h3 style="margin:0 0 8px;font-size:14px;color:#22863a;">📊 CSV Disa Aktar</h3>
                            <p style="font-size:12px;color:#666;margin:0;line-height:1.8;">Urunleri sectikten sonra <strong>Secilenleri CSV Indir</strong> butonuna tiklayin. Excel'de acilebilir CSV dosyasi indirilir.</p>
                        </div>
                        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:16px;">
                            <h3 style="margin:0 0 8px;font-size:14px;color:#991b1b;">⚠️ Dikkat</h3>
                            <p style="font-size:12px;color:#666;margin:0;line-height:1.8;"><strong>Geri Al</strong> butonu son islemi geri alir. <strong>Secilenleri Sil</strong> urunleri cop kutusuna tasir. Islemler geri alinamayabilir, dikkatli kullanin!</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <script>
        jQuery(function($){
            var nonce = '<?php echo $nonce; ?>';
            var currentPage = 1;
            var allCats = <?php echo json_encode(array_map(function($c){ return array('id'=>$c->term_id,'name'=>$c->name); }, $cats)); ?>;
            var shipClasses = <?php echo json_encode(array_map(function($c){ return array('id'=>$c->term_id,'name'=>$c->name); }, is_array($shipping_classes) ? $shipping_classes : array())); ?>;

            function loadProducts(page) {
                currentPage = page || 1;
                var btn = $('#bulkLoadBtn');
                btn.text('Yukleniyor...').prop('disabled', true);
                $.post(ajaxurl, {
                    action: 'webyaz_bulk_load_products',
                    nonce: nonce,
                    category: $('#bulkFilterCat').val(),
                    search: $('#bulkFilterSearch').val(),
                    page_num: currentPage
                }, function(res){
                    btn.text('Urunleri Getir').prop('disabled', false);
                    if (!res.success) return;
                    var d = res.data;
                    $('#bulkProductCount').html('<strong>' + d.total + '</strong> urun bulundu');
                    var html = '<table style="width:100%;border-collapse:collapse;font-size:13px;"><tr style="background:#f5f5f5;position:sticky;top:0;z-index:1;">';
                    html += '<th style="padding:8px;width:30px;"><input type="checkbox" id="bulkSelectAllInner"></th>';
                    html += '<th style="padding:8px;">Gorsel</th>';
                    html += '<th style="padding:8px;text-align:left;">Urun Adi</th>';
                    html += '<th style="padding:8px;">SKU</th>';
                    html += '<th style="padding:8px;">Fiyat</th>';
                    html += '<th style="padding:8px;">Ind. Fiyat</th>';
                    html += '<th style="padding:8px;">Alis</th>';
                    html += '<th style="padding:8px;">Stok</th>';
                    html += '<th style="padding:8px;text-align:left;">Kategori</th>';
                    html += '<th style="padding:8px;text-align:left;">Etiket</th>';
                    html += '</tr>';
                    d.products.forEach(function(p){
                        var img = p.thumb ? '<img src="'+p.thumb+'" style="width:36px;height:36px;object-fit:cover;border-radius:4px;">' : '<span style="display:inline-block;width:36px;height:36px;background:#eee;border-radius:4px;"></span>';
                        var stockColor = p.stock_status === 'instock' ? '#2e7d32' : '#c62828';
                        html += '<tr style="border-bottom:1px solid #f0f0f0;" data-id="'+p.id+'">';
                        html += '<td style="padding:6px 8px;text-align:center;"><input type="checkbox" class="bulk-check" value="'+p.id+'"></td>';
                        html += '<td style="padding:6px 8px;text-align:center;">'+img+'</td>';
                        html += '<td style="padding:6px 8px;font-weight:600;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;cursor:pointer;" data-editable="name" title="Cift tikla duzenle">'+p.name+'</td>';
                        html += '<td style="padding:6px 8px;text-align:center;color:#888;font-size:11px;cursor:pointer;" data-editable="sku" title="Cift tikla duzenle">'+(p.sku||'-')+'</td>';
                        html += '<td style="padding:6px 8px;text-align:center;font-weight:600;cursor:pointer;" data-editable="price" title="Cift tikla duzenle">'+(p.price||'-')+' TL</td>';
                        html += '<td style="padding:6px 8px;text-align:center;color:#c62828;cursor:pointer;" data-editable="sale_price" title="Cift tikla duzenle">'+(p.sale_price||'-')+'</td>';
                        html += '<td style="padding:6px 8px;text-align:center;color:#888;">'+(p.cost_price||'-')+'</td>';
                        html += '<td style="padding:6px 8px;text-align:center;cursor:pointer;" data-editable="stock" title="Cift tikla duzenle"><span style="color:'+stockColor+';font-weight:600;">'+(p.stock !== null ? p.stock : (p.stock_status === 'instock' ? 'Var' : 'Yok'))+'</span></td>';
                        html += '<td style="padding:6px 8px;font-size:11px;color:#666;">'+p.categories+'</td>';
                        html += '<td style="padding:6px 8px;font-size:11px;color:#888;">'+p.tags+'</td>';
                        html += '</tr>';
                    });
                    html += '</table>';
                    $('#bulkProductsTable').html(html);
                    $('#bulkApplyBtn').prop('disabled', false);

                    // Sayfalama
                    if (d.pages > 1) {
                        var ph = '';
                        for (var i = 1; i <= d.pages; i++) {
                            var active = i === currentPage ? 'background:#446084;color:#fff;' : 'background:#f5f5f5;';
                            ph += '<button type="button" class="bulk-page-btn" data-page="'+i+'" style="'+active+'border:1px solid #ddd;border-radius:4px;padding:4px 10px;margin:0 2px;cursor:pointer;font-size:12px;">'+i+'</button>';
                        }
                        $('#bulkPagination').html(ph);
                    } else {
                        $('#bulkPagination').html('');
                    }

                    // SelectAll sync
                    $('#bulkSelectAllInner').on('change', function(){ $('.bulk-check').prop('checked', this.checked); });
                });
            }

            $('#bulkLoadBtn').on('click', function(){ loadProducts(1); });
            $('#bulkFilterSearch').on('keypress', function(e){ if(e.which===13){ loadProducts(1); } });
            $(document).on('click', '.bulk-page-btn', function(){ loadProducts($(this).data('page')); });

            // SelectAll (ust)
            $('#bulkSelectAll').on('change', function(){ $('.bulk-check').prop('checked', this.checked); $('#bulkSelectAllInner').prop('checked', this.checked); });

            // Uygula
            $('#bulkApplyBtn').on('click', function(){
                var ids = [];
                $('.bulk-check:checked').each(function(){ ids.push($(this).val()); });
                if (ids.length === 0) { alert('Lutfen en az bir urun secin!'); return; }
                var field = $('#bulkField').val();
                var act = $('#bulkAction').val();
                var val = $('#bulkValue').val();
                var noValueFields = ['stock_status','status','clear_gallery','clear_image'];
                // Attribute icin JSON hazirla
                if (field === 'attribute') {
                    var an = document.getElementById('wzAttrName');
                    var av = document.getElementById('wzAttrVals');
                    if (an && av) val = JSON.stringify({name:an.value,values:av.value});
                }
                // Sale dates icin deger hazirla
                if (field === 'sale_dates') {
                    var df = document.getElementById('wzDateFrom');
                    var dt = document.getElementById('wzDateTo');
                    if (df && dt) val = df.value + '|' + dt.value;
                }
                if (!val && noValueFields.indexOf(field) === -1) { alert('Deger girin!'); return; }
                if (!confirm(ids.length + ' urun guncellenecek. Emin misiniz?')) return;

                var btn = $(this);
                btn.text('Uygulanıyor...').prop('disabled', true);
                $.post(ajaxurl, {action:'webyaz_bulk_apply', nonce:nonce, ids:ids, field:field, bulk_action:act, value:val}, function(res){
                    if (res.success) {
                        $('#bulkApplyResult').html('<span style="color:#2e7d32;font-weight:600;">&#10004; ' + res.data.count + ' urun guncellendi!</span>');
                        loadProducts(currentPage);
                    } else {
                        $('#bulkApplyResult').html('<span style="color:#c62828;">&#10008; ' + res.data + '</span>');
                    }
                    btn.text('Uygula').prop('disabled', false);
                    setTimeout(function(){ $('#bulkApplyResult').html(''); }, 5000);
                });
            });

            // CSV Export
            $('#bulkExportBtn').on('click', function(){
                var ids = [];
                $('.bulk-check:checked').each(function(){ ids.push($(this).val()); });
                if (ids.length === 0) { alert('Urun secin!'); return; }
                $.post(ajaxurl, {action:'webyaz_bulk_export', nonce:nonce, ids:ids}, function(res){
                    if (res.success) {
                        var csv = res.data.csv.map(function(row){ return row.join(';'); }).join('\n');
                        var blob = new Blob(['\uFEFF'+csv], {type:'text/csv;charset=utf-8;'});
                        var a = document.createElement('a');
                        a.href = URL.createObjectURL(blob);
                        a.download = 'urunler-' + new Date().toISOString().slice(0,10) + '.csv';
                        a.click();
                    }
                });
            });

            // Undo
            $('#bulkUndoBtn').on('click', function(){
                if (!confirm('Son toplu islemi geri almak istiyor musunuz?')) return;
                $.post(ajaxurl, {action:'webyaz_bulk_undo', nonce:nonce}, function(res){
                    if (res.success) { alert(res.data.message); loadProducts(currentPage); }
                    else { alert(res.data || 'Hata'); }
                });
            });

            // Delete
            $('#bulkDeleteBtn').on('click', function(){
                var ids = [];
                $('.bulk-check:checked').each(function(){ ids.push($(this).val()); });
                if (ids.length === 0) { alert('Urun secin!'); return; }
                if (!confirm(ids.length + ' urun cop kutusuna tasinacak. Emin misiniz?')) return;
                $.post(ajaxurl, {action:'webyaz_bulk_delete', nonce:nonce, ids:ids}, function(res){
                    if (res.success) {
                        alert(res.data.count + ' urun silindi!');
                        loadProducts(currentPage);
                    }
                });
            });

            // Inline Edit (cift tikla)
            $(document).on('dblclick', '#bulkProductsTable td[data-editable]', function(){
                var td = $(this);
                if (td.find('input').length) return;
                var old = td.text().trim();
                var fld = td.data('editable');
                var pid = td.closest('tr').data('id');
                td.html('<input type="text" value="'+old+'" style="width:90%;padding:3px;border:1px solid #89b4fa;border-radius:3px;font-size:12px;">');
                td.find('input').focus().select();
                td.find('input').on('blur keypress', function(e){
                    if (e.type === 'keypress' && e.which !== 13) return;
                    var nv = $(this).val();
                    td.text(nv);
                    if (nv !== old) {
                        $.post(ajaxurl, {action:'webyaz_bulk_inline', nonce:nonce, product_id:pid, field:fld, value:nv});
                        td.css('background','#e8f5e9');
                        setTimeout(function(){ td.css('background',''); }, 1500);
                    }
                });
            });
        });

        function webyazBulkFieldChange() {
            var field = document.getElementById('bulkField').value;
            var actionSel = document.getElementById('bulkAction');
            var valueInp = document.getElementById('bulkValue');
            var actionWrap = document.getElementById('bulkActionWrap');
            actionWrap.style.display = 'block';
            document.getElementById('bulkValueWrap').style.display = 'block';
            actionSel.innerHTML = '';

            if (field === 'regular_price' || field === 'cost_price') {
                actionSel.innerHTML = '<option value="increase_percent">% Artir</option><option value="decrease_percent">% Azalt</option><option value="increase">TL Artir</option><option value="decrease">TL Azalt</option><option value="set">Sabit Deger</option>';
                valueInp.placeholder = 'Deger (ornek: 10)';
                valueInp.type = 'number';
            } else if (field === 'sale_price') {
                actionSel.innerHTML = '<option value="set">Sabit Fiyat</option><option value="increase_percent">% Artir</option><option value="decrease_percent">% Azalt</option><option value="remove">Indirimi Kaldir</option>';
                valueInp.placeholder = 'Indirimli fiyat';
                valueInp.type = 'number';
            } else if (field === 'sale_from_regular') {
                actionSel.innerHTML = '<option value="percent">% Indirim</option><option value="fixed">TL Indirim</option>';
                valueInp.placeholder = 'Indirim miktari (ornek: 20)';
                valueInp.type = 'number';
            } else if (field === 'stock') {
                actionSel.innerHTML = '<option value="increase">Artir</option><option value="decrease">Azalt</option><option value="set">Sabit Deger</option>';
                valueInp.placeholder = 'Miktar';
                valueInp.type = 'number';
            } else if (field === 'stock_status') {
                actionSel.innerHTML = '<option value="set">Belirle</option>';
                valueInp.outerHTML = '<select id="bulkValue" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;"><option value="instock">Stokta</option><option value="outofstock">Tukenmis</option><option value="onbackorder">Sipariste</option></select>';
            } else if (field === 'category') {
                actionSel.innerHTML = '<option value="add">Ekle</option><option value="remove">Cikar</option><option value="set">Degistir (Tek)</option>';
                var catOpts = '';
                var allCats = <?php echo json_encode(array_map(function($c){ return array('id'=>$c->term_id,'name'=>$c->name); }, $cats)); ?>;
                allCats.forEach(function(c){ catOpts += '<option value="'+c.id+'">'+c.name+'</option>'; });
                document.getElementById('bulkValueWrap').innerHTML = '<label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Kategori</label><select id="bulkValue" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">'+catOpts+'</select>';
                return;
            } else if (field === 'tag') {
                actionSel.innerHTML = '<option value="add">Ekle</option><option value="remove">Cikar</option><option value="set">Degistir</option>';
                valueInp.placeholder = 'Etiketler (virgul ile)';
                valueInp.type = 'text';
            } else if (field === 'title_prefix') {
                actionWrap.style.display = 'none';
                valueInp.placeholder = 'Basliga eklenecek on ek (ornek: [YENi])';
                valueInp.type = 'text';
            } else if (field === 'title_suffix') {
                actionWrap.style.display = 'none';
                valueInp.placeholder = 'Basliga eklenecek son ek';
                valueInp.type = 'text';
            } else if (field === 'title_replace') {
                actionWrap.style.display = 'none';
                valueInp.placeholder = 'eski metin|yeni metin';
                valueInp.type = 'text';
            } else if (field === 'weight') {
                actionSel.innerHTML = '<option value="set">Belirle</option>';
                valueInp.placeholder = 'Agirlik (kg)';
                valueInp.type = 'number';
            } else if (field === 'dimensions') {
                actionWrap.style.display = 'none';
                valueInp.placeholder = '20x15x10 (UzunlukxGenislikxYukseklik cm)';
                valueInp.type = 'text';
            } else if (field === 'short_desc') {
                actionSel.innerHTML = '<option value="set">Degistir</option><option value="add">Sonuna Ekle</option><option value="replace">Bul Degistir</option>';
                valueInp.placeholder = 'Aciklama metni veya eski|yeni';
                valueInp.type = 'text';
            } else if (field === 'sku_generate') {
                actionWrap.style.display = 'none';
                valueInp.placeholder = 'SKU on eki (ornek: WBY)';
                valueInp.type = 'text';
            } else if (field === 'attribute') {
                actionWrap.style.display = 'none';
                document.getElementById('bulkValueWrap').innerHTML = '<label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Ozellik</label><div style="display:flex;gap:8px;"><input type="text" id="wzAttrName" placeholder="Ozellik adi (ornek: Renk)" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:6px;"><input type="text" id="wzAttrVals" placeholder="Degerler: Kirmizi, Mavi, Yesil" style="flex:2;padding:8px;border:1px solid #ddd;border-radius:6px;"></div><input type="hidden" id="bulkValue">';
                return;
            } else if (field === 'clear_gallery' || field === 'clear_image') {
                actionWrap.style.display = 'none';
                document.getElementById('bulkValueWrap').style.display = 'none';
                return;
            } else if (field === 'product_type') {
                actionSel.innerHTML = '<option value="set">Belirle</option>';
                document.getElementById('bulkValueWrap').innerHTML = '<label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Urun Tipi</label><select id="bulkValue" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;"><option value="simple">Basit</option><option value="variable">Degisken</option><option value="grouped">Gruplu</option><option value="external">Harici</option></select>';
                return;
            } else if (field === 'cross_sell' || field === 'upsell') {
                actionSel.innerHTML = '<option value="add">Ekle</option><option value="set">Degistir</option><option value="remove">Temizle</option>';
                valueInp.placeholder = 'Urun ID\'leri (virgul ile: 123,456,789)';
                valueInp.type = 'text';
            } else if (field === 'sale_dates') {
                actionSel.innerHTML = '<option value="set">Belirle</option><option value="remove">Kaldir</option>';
                document.getElementById('bulkValueWrap').innerHTML = '<label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Tarih Araligi</label><div style="display:flex;gap:8px;align-items:center;"><input type="date" id="wzDateFrom" style="padding:8px;border:1px solid #ddd;border-radius:6px;"><span>-</span><input type="date" id="wzDateTo" style="padding:8px;border:1px solid #ddd;border-radius:6px;"></div><input type="hidden" id="bulkValue">';
                return;
            } else if (field === 'status') {
                actionSel.innerHTML = '<option value="set">Belirle</option>';
                document.getElementById('bulkValueWrap').innerHTML = '<label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Durum</label><select id="bulkValue" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;"><option value="publish">Yayinda</option><option value="draft">Taslak</option><option value="pending">Onay Bekliyor</option><option value="private">Gizli</option></select>';
                return;
            } else if (field === 'shipping_class') {
                actionSel.innerHTML = '<option value="set">Belirle</option>';
                var scOpts = '<option value="0">Yok</option>';
                var shipClasses = <?php echo json_encode(array_map(function($c){ return array('id'=>$c->term_id,'name'=>$c->name); }, is_array($shipping_classes) ? $shipping_classes : array())); ?>;
                shipClasses.forEach(function(c){ scOpts += '<option value="'+c.id+'">'+c.name+'</option>'; });
                document.getElementById('bulkValueWrap').innerHTML = '<label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Kargo Sinifi</label><select id="bulkValue" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">'+scOpts+'</select>';
                return;
            }

            // valueInp restore if was replaced
            var vw = document.getElementById('bulkValueWrap');
            if (!document.getElementById('bulkValue') || document.getElementById('bulkValue').tagName === 'SELECT') {
                if (field !== 'stock_status') {
                    vw.innerHTML = '<label style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">Deger</label><input type="text" id="bulkValue" placeholder="Deger girin..." style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">';
                }
            }
        }
        </script>
        <?php
    }
}

new Webyaz_Bulk_Edit();
