<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Xml_Manager {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_webyaz_export_xml', array($this, 'ajax_export'));
        add_action('wp_ajax_webyaz_import_xml', array($this, 'ajax_import'));
        add_action('init', array($this, 'register_feed'));
        add_action('template_redirect', array($this, 'handle_feed'));
        add_action('wp_ajax_webyaz_fix_stock', array($this, 'ajax_fix_stock'));
        add_action('wp_ajax_webyaz_add_supplier', array($this, 'ajax_add_supplier'));
        add_action('wp_ajax_webyaz_del_supplier', array($this, 'ajax_del_supplier'));
        add_action('wp_ajax_webyaz_fetch_supplier_xml', array($this, 'ajax_fetch_supplier_xml'));
        add_action('wp_ajax_webyaz_save_schedule', array($this, 'ajax_save_schedule'));
        add_action('wp_ajax_webyaz_save_mapping', array($this, 'ajax_save_mapping'));
        add_action('wp_ajax_webyaz_analyze_excel', array($this, 'ajax_analyze_excel'));
        add_action('wp_ajax_webyaz_import_excel', array($this, 'ajax_import_excel'));
        add_action('wp_ajax_webyaz_download_template', array($this, 'ajax_download_template'));
        add_action('webyaz_cron_xml_sync', array($this, 'cron_sync'));
        if (!wp_next_scheduled('webyaz_cron_xml_sync')) {
            wp_schedule_event(time(), 'hourly', 'webyaz_cron_xml_sync');
        }
    }

    public function ajax_fix_stock() {
        if (!current_user_can('manage_options')) wp_die('Yetkisiz');
        check_ajax_referer('webyaz_xml_action', 'nonce');
        global $wpdb;
        $count = $wpdb->query("UPDATE {$wpdb->postmeta} SET meta_value = 'instock' WHERE meta_key = '_stock_status' AND meta_value = 'outofstock'");
        wc_delete_product_transients();
        if (function_exists('wc_update_product_lookup_tables_column')) {
            $ids = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_stock_status' AND meta_value = 'instock'");
            foreach ($ids as $id) { wc_update_product_lookup_tables_column($id, 'stock_status'); }
        }
        wp_send_json_success(array('fixed' => $count));
    }

    public function ajax_add_supplier() {
        if (!current_user_can('manage_options')) wp_die('Yetkisiz');
        check_ajax_referer('webyaz_xml_action', 'nonce');
        $name = sanitize_text_field($_POST['name']);
        $url = esc_url_raw($_POST['url']);
        if (empty($name) || empty($url)) wp_send_json_error('Ad ve URL gerekli');
        $suppliers = get_option('webyaz_xml_suppliers', array());
        $suppliers[] = array('name' => $name, 'url' => $url);
        update_option('webyaz_xml_suppliers', $suppliers);
        wp_send_json_success(array('index' => count($suppliers) - 1));
    }

    public function ajax_del_supplier() {
        if (!current_user_can('manage_options')) wp_die('Yetkisiz');
        check_ajax_referer('webyaz_xml_action', 'nonce');
        $index = intval($_POST['index']);
        $suppliers = get_option('webyaz_xml_suppliers', array());
        if (isset($suppliers[$index])) {
            array_splice($suppliers, $index, 1);
            update_option('webyaz_xml_suppliers', $suppliers);
        }
        wp_send_json_success();
    }

    public function ajax_fetch_supplier_xml() {
        if (!current_user_can('manage_options')) wp_die('Yetkisiz');
        check_ajax_referer('webyaz_xml_action', 'nonce');
        $url = esc_url_raw($_POST['url']);
        if (empty($url)) wp_send_json_error('URL gerekli');
        $response = wp_remote_get($url, array('timeout' => 30, 'sslverify' => false));
        if (is_wp_error($response)) wp_send_json_error('Baglanti hatasi: ' . $response->get_error_message());
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) wp_send_json_error('Bos XML');
        $upload_dir = wp_upload_dir();
        $tmp_xml = $upload_dir['basedir'] . '/webyaz-import-temp.xml';
        file_put_contents($tmp_xml, $body);
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if (!$xml) wp_send_json_error('Gecersiz XML');
        $first = $xml->children();
        $tag = '';
        foreach ($first as $child) { $tag = $child->getName(); break; }
        if (!$tag) wp_send_json_error('Urun bulunamadi');
        $products = $xml->$tag;
        $total = count($products);
        $fields = array();
        $sample = array();
        $firstP = $products[0];
        foreach ($firstP->children() as $child) {
            $n = $child->getName();
            $fields[] = $n;
            $val = (string)$child;
            $sample[$n] = mb_substr($val, 0, 50);
        }
        wp_send_json_success(array('total' => $total, 'fields' => $fields, 'sample' => $sample));
    }

    public function ajax_save_schedule() {
        if (!current_user_can('manage_options')) wp_die('Yetkisiz');
        check_ajax_referer('webyaz_xml_action', 'nonce');
        $index = intval($_POST['index']);
        $schedule = sanitize_text_field($_POST['schedule']);
        $time1 = sanitize_text_field($_POST['time1']);
        $time2 = sanitize_text_field($_POST['time2']);
        $suppliers = get_option('webyaz_xml_suppliers', array());
        if (isset($suppliers[$index])) {
            $suppliers[$index]['schedule'] = $schedule;
            $suppliers[$index]['time1'] = $time1;
            $suppliers[$index]['time2'] = $time2;
            update_option('webyaz_xml_suppliers', $suppliers);
        }
        wp_send_json_success();
    }

    public function ajax_save_mapping() {
        if (!current_user_can('manage_options')) wp_die('Yetkisiz');
        check_ajax_referer('webyaz_xml_action', 'nonce');
        $url = esc_url_raw($_POST['url']);
        $mapping = isset($_POST['mapping']) ? $_POST['mapping'] : array();
        $suppliers = get_option('webyaz_xml_suppliers', array());
        foreach ($suppliers as $i => $sup) {
            if ($sup['url'] === $url) {
                $suppliers[$i]['mapping'] = $mapping;
                update_option('webyaz_xml_suppliers', $suppliers);
                break;
            }
        }
        wp_send_json_success();
    }

    private function parse_csv_line($line) {
        return str_getcsv($line, ',', '"');
    }

    private function parse_excel_file($filepath) {
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        $rows = array();
        if ($ext === 'csv') {
            $handle = fopen($filepath, 'r');
            if ($handle) {
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") rewind($handle);
                while (($line = fgetcsv($handle, 0, ',', '"')) !== false) {
                    $rows[] = $line;
                }
                fclose($handle);
            }
        } elseif ($ext === 'xlsx') {
            $rows = $this->parse_xlsx($filepath);
        }
        return $rows;
    }

    private function parse_xlsx($filepath) {
        $rows = array();
        $zip = new ZipArchive();
        if ($zip->open($filepath) !== true) return $rows;
        $shared = array();
        $xml_shared = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml_shared) {
            $ssx = simplexml_load_string($xml_shared);
            if ($ssx) {
                foreach ($ssx->si as $si) {
                    $text = '';
                    if (isset($si->t)) {
                        $text = (string)$si->t;
                    } elseif (isset($si->r)) {
                        foreach ($si->r as $r) { $text .= (string)$r->t; }
                    }
                    $shared[] = $text;
                }
            }
        }
        $xml_sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$xml_sheet) { $zip->close(); return $rows; }
        $sheet = simplexml_load_string($xml_sheet);
        if (!$sheet) { $zip->close(); return $rows; }
        foreach ($sheet->sheetData->row as $row) {
            $cells = array();
            $maxCol = 0;
            foreach ($row->c as $c) {
                $ref = (string)$c['r'];
                preg_match('/([A-Z]+)/', $ref, $m);
                $colIdx = 0;
                $letters = $m[1];
                for ($j = 0; $j < strlen($letters); $j++) {
                    $colIdx = $colIdx * 26 + (ord($letters[$j]) - ord('A') + 1);
                }
                $colIdx--;
                $val = '';
                if (isset($c['t']) && (string)$c['t'] === 's') {
                    $idx = intval((string)$c->v);
                    $val = isset($shared[$idx]) ? $shared[$idx] : '';
                } else {
                    $val = isset($c->v) ? (string)$c->v : '';
                }
                $cells[$colIdx] = $val;
                if ($colIdx > $maxCol) $maxCol = $colIdx;
            }
            $rowData = array();
            for ($k = 0; $k <= $maxCol; $k++) {
                $rowData[] = isset($cells[$k]) ? $cells[$k] : '';
            }
            $rows[] = $rowData;
        }
        $zip->close();
        return $rows;
    }

    public function ajax_analyze_excel() {
        if (!current_user_can('manage_options')) wp_die('Yetkisiz');
        check_ajax_referer('webyaz_xml_action', 'nonce');
        if (empty($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Dosya yuklenemedi');
        }
        $upload_dir = wp_upload_dir();
        $tmp = $upload_dir['basedir'] . '/webyaz-excel-temp.' . pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION);
        move_uploaded_file($_FILES['excel_file']['tmp_name'], $tmp);
        $rows = $this->parse_excel_file($tmp);
        if (empty($rows) || count($rows) < 2) {
            wp_send_json_error('Dosya bos veya sadece baslik satiri var');
        }
        $headers = $rows[0];
        $sample = isset($rows[1]) ? $rows[1] : array();
        $sampleData = array();
        foreach ($headers as $i => $h) {
            $h = trim($h);
            if (empty($h)) $h = 'Sutun_' . ($i + 1);
            $sampleData[$h] = isset($sample[$i]) ? mb_substr(trim($sample[$i]), 0, 50) : '';
        }
        wp_send_json_success(array(
            'total' => count($rows) - 1,
            'headers' => array_keys($sampleData),
            'sample' => $sampleData
        ));
    }

    public function ajax_import_excel() {
        if (!current_user_can('manage_options')) wp_die('Yetkisiz');
        check_ajax_referer('webyaz_xml_action', 'nonce');
        $batch_size = 5;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $upload_dir = wp_upload_dir();
        $tmp_csv = $upload_dir['basedir'] . '/webyaz-excel-temp.csv';
        $tmp_xlsx = $upload_dir['basedir'] . '/webyaz-excel-temp.xlsx';
        $filepath = file_exists($tmp_xlsx) ? $tmp_xlsx : $tmp_csv;
        if (!file_exists($filepath)) {
            wp_send_json_error('Gecici dosya bulunamadi - once dosya secin ve analiz edin');
        }
        $rows = $this->parse_excel_file($filepath);
        if (empty($rows) || count($rows) < 2) {
            wp_send_json_error('Dosya bos');
        }
        $headers = $rows[0];
        array_shift($rows);
        $total = count($rows);
        $mapping = isset($_POST['mapping']) ? $_POST['mapping'] : array();
        $update_existing = isset($_POST['update_existing']) && $_POST['update_existing'] === '1';
        $imported = 0; $updated = 0; $errors = 0;
        $batch = array_slice($rows, $offset, $batch_size);
        foreach ($batch as $row) {
            $data = array();
            $name = ''; $sku = '';
            foreach ($mapping as $col_name => $wc_field) {
                if (empty($wc_field) || $wc_field === 'skip') continue;
                $col_idx = array_search($col_name, $headers);
                if ($col_idx === false) {
                    foreach ($headers as $hi => $hv) {
                        if (trim($hv) === $col_name) { $col_idx = $hi; break; }
                    }
                }
                $val = ($col_idx !== false && isset($row[$col_idx])) ? trim($row[$col_idx]) : '';
                $data[$wc_field] = $val;
                if ($wc_field === 'name') $name = $val;
                if ($wc_field === 'sku') $sku = $val;
            }
            if (empty($name) && empty($sku)) { $errors++; continue; }
            $existing_id = 0;
            if ($sku && $update_existing) {
                $existing_id = wc_get_product_id_by_sku($sku);
            }
            if ($existing_id && $update_existing) {
                $product = wc_get_product($existing_id);
                if (!$product) { $errors++; continue; }
                $this->apply_product_data($product, $data);
                $product->save();
                $updated++;
            } else {
                $product = new WC_Product_Simple();
                $product->set_stock_status('instock');
                $this->apply_product_data($product, $data);
                $product->save();
                $pid = $product->get_id();
                if ($pid) { $this->apply_meta_data($pid, $data); $imported++; }
                else { $errors++; }
            }
        }
        $new_offset = $offset + $batch_size;
        $done = $new_offset >= $total;
        wp_send_json_success(array(
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'offset' => $new_offset,
            'total' => $total,
            'done' => $done
        ));
    }

    public function ajax_download_template() {
        check_ajax_referer('webyaz_xml_action', 'nonce');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=webyaz-urun-sablonu.csv');
        $bom = "\xEF\xBB\xBF";
        echo $bom;
        $fields = array('Urun Adi','Stok Kodu (SKU)','Satis Fiyati','Alis Fiyati','Indirimli Fiyat','Kategoriler','Kisa Aciklama','Uzun Aciklama','Bedenler','Renkler','Ayakkabi Numaralari','Satis Birimi','Akilli Ozellik','Gorsel URL','Agirlik','Etiketler');
        echo implode(',', $fields) . "\n";
        echo '"Ornek Urun","SKU001","199.90","100","149.90","Erkek Giyim > Tisort","Kisa aciklama","Uzun aciklama","S,M,L,XL","Kirmizi,Mavi","","https://ornek.com/resim.jpg","0.5","Yeni,Indirimde"' . "\n";
        exit;
    }

    public function cron_sync() {
        $suppliers = get_option('webyaz_xml_suppliers', array());
        if (empty($suppliers)) return;
        $current_hour = current_time('H:i');
        foreach ($suppliers as $i => $sup) {
            $schedule = isset($sup['schedule']) ? $sup['schedule'] : 'off';
            if ($schedule === 'off') continue;
            $time1 = isset($sup['time1']) ? $sup['time1'] : '03:00';
            $time2 = isset($sup['time2']) ? $sup['time2'] : '15:00';
            $should_run = false;
            if (abs(strtotime($current_hour) - strtotime($time1)) < 1800) $should_run = true;
            if ($schedule === 'daily2' && abs(strtotime($current_hour) - strtotime($time2)) < 1800) $should_run = true;
            if (!$should_run) continue;
            $last = isset($sup['last_sync']) ? $sup['last_sync'] : '';
            if ($last && strtotime($last) > strtotime('-2 hours')) continue;
            $this->auto_sync_supplier($i, $sup);
        }
    }

    private function auto_sync_supplier($index, $sup) {
        $url = $sup['url'];
        $response = wp_remote_get($url, array('timeout' => 60, 'sslverify' => false));
        if (is_wp_error($response)) return;
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) return;
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if (!$xml) return;
        $mapping = isset($sup['mapping']) ? $sup['mapping'] : array();
        if (empty($mapping)) return;
        $first = $xml->children();
        $tag = '';
        foreach ($first as $child) { $tag = $child->getName(); break; }
        if (!$tag) return;
        foreach ($xml->$tag as $item) {
            $data = array();
            $name = '';
            $sku = '';
            foreach ($mapping as $xml_field => $wc_field) {
                if (empty($wc_field) || $wc_field === 'skip') continue;
                $val = isset($item->$xml_field) ? (string)$item->$xml_field : '';
                $data[$wc_field] = $val;
                if ($wc_field === 'name') $name = $val;
                if ($wc_field === 'sku') $sku = $val;
            }
            if (empty($name) && empty($sku)) continue;
            $existing_id = $sku ? wc_get_product_id_by_sku($sku) : 0;
            if ($existing_id) {
                $product = wc_get_product($existing_id);
                if ($product) { $this->apply_product_data($product, $data); $product->save(); }
            } else {
                $product = new WC_Product_Simple();
                $product->set_stock_status('instock');
                $this->apply_product_data($product, $data);
                $product->save();
                $pid = $product->get_id();
                if ($pid) $this->apply_meta_data($pid, $data);
            }
        }
        $suppliers = get_option('webyaz_xml_suppliers', array());
        if (isset($suppliers[$index])) {
            $suppliers[$index]['last_sync'] = current_time('d.m.Y H:i');
            update_option('webyaz_xml_suppliers', $suppliers);
        }
    }

    public function register_feed() {
        add_rewrite_rule('^webyaz-xml-feed/?$', 'index.php?webyaz_xml_feed=1', 'top');
        add_filter('query_vars', function($vars) { $vars[] = 'webyaz_xml_feed'; return $vars; });
    }

    public function handle_feed() {
        if (!get_query_var('webyaz_xml_feed')) return;
        $opts = self::get_opts();
        $fields = $opts['export_fields'];
        $args = array('status' => 'publish', 'limit' => -1);
        $products = wc_get_products($args);

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><products></products>');
        $xml->addAttribute('exported', date('Y-m-d H:i:s'));
        $xml->addAttribute('count', count($products));
        $xml->addAttribute('site', get_site_url());

        foreach ($products as $product) {
            $pdata = $this->get_product_data($product, $fields);
            $node = $xml->addChild('product');
            foreach ($pdata as $key => $val) {
                $child = $node->addChild($key);
                $child[0] = $val;
            }
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        header('Content-Type: application/xml; charset=utf-8');
        echo $dom->saveXML();
        exit;
    }

    public function register_settings() {
        register_setting('webyaz_xml_group', 'webyaz_xml');
    }

    private static function get_defaults() {
        return array(
            'active' => '1',
            'export_fields' => array('id','name','sku','price','cost_price','sale_price','description','short_description','categories','tags','image','gallery','sizes','colors','shoes','units','weight'),
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_xml', array()), self::get_defaults());
    }

    private static function get_available_fields() {
        return array(
            'id' => 'Urun ID',
            'name' => 'Urun Adi',
            'sku' => 'Stok Kodu (SKU)',
            'price' => 'Satis Fiyati',
            'cost_price' => 'Alis Fiyati',
            'sale_price' => 'Indirimli Fiyat',
            'description' => 'Uzun Aciklama',
            'short_description' => 'Kisa Aciklama',
            'categories' => 'Kategoriler',
            'tags' => 'Etiketler',
            'image' => 'Ana Gorsel URL',
            'gallery' => 'Galeri Gorselleri',
            'sizes' => 'Bedenler',
            'colors' => 'Renkler',
            'shoes' => 'Ayakkabi Numaralari',
            'units' => 'Satis Birimleri',
            'custom_props' => 'Akilli Ozellik',
            'stock_status' => 'Stok Durumu',
            'weight' => 'Agirlik',
            'url' => 'Urun Linki',
        );
    }

    private function get_product_data($product, $fields) {
        $data = array();
        $pid = $product->get_id();
        foreach ($fields as $f) {
            switch ($f) {
                case 'id': $data['id'] = $pid; break;
                case 'name': $data['name'] = $product->get_name(); break;
                case 'sku': $data['sku'] = $product->get_sku(); break;
                case 'price': $data['price'] = $product->get_regular_price(); break;
                case 'cost_price': $data['cost_price'] = get_post_meta($pid, '_webyaz_cost_price', true); break;
                case 'sale_price': $data['sale_price'] = $product->get_sale_price(); break;
                case 'description': $data['description'] = $product->get_description(); break;
                case 'short_description': $data['short_description'] = $product->get_short_description(); break;
                case 'categories':
                    $terms = wp_get_post_terms($pid, 'product_cat', array('fields' => 'names'));
                    $data['categories'] = is_array($terms) ? implode(', ', $terms) : '';
                    break;
                case 'tags':
                    $terms = wp_get_post_terms($pid, 'product_tag', array('fields' => 'names'));
                    $data['tags'] = is_array($terms) ? implode(', ', $terms) : '';
                    break;
                case 'image':
                    $img_id = $product->get_image_id();
                    $data['image'] = $img_id ? wp_get_attachment_url($img_id) : '';
                    break;
                case 'gallery':
                    $ids = $product->get_gallery_image_ids();
                    $urls = array();
                    foreach ($ids as $gid) { $urls[] = wp_get_attachment_url($gid); }
                    $data['gallery'] = implode(' | ', $urls);
                    break;
                case 'sizes':
                    $s = get_post_meta($pid, '_webyaz_sizes', true);
                    $cs = get_post_meta($pid, '_webyaz_custom_sizes', true);
                    $all = array_merge(is_array($s) ? $s : array(), is_array($cs) ? $cs : array());
                    $data['sizes'] = implode(', ', $all);
                    break;
                case 'colors':
                    $c = get_post_meta($pid, '_webyaz_colors', true);
                    $names = array();
                    if (is_array($c)) { foreach ($c as $cl) { if (isset($cl['name'])) $names[] = $cl['name'] . ':' . $cl['hex']; }}
                    $data['colors'] = implode(', ', $names);
                    break;
                case 'shoes':
                    $sh = get_post_meta($pid, '_webyaz_shoes', true);
                    $csh = get_post_meta($pid, '_webyaz_custom_shoes', true);
                    $all = array_merge(is_array($sh) ? $sh : array(), is_array($csh) ? $csh : array());
                    $data['shoes'] = implode(', ', $all);
                    break;
                case 'units':
                    $u = get_post_meta($pid, '_webyaz_units', true);
                    $cu = get_post_meta($pid, '_webyaz_custom_units', true);
                    $all = array_merge(is_array($u) ? $u : array(), is_array($cu) ? $cu : array());
                    $data['units'] = implode(', ', $all);
                    break;
                case 'stock_status': $data['stock_status'] = $product->get_stock_status(); break;
                case 'weight': $data['weight'] = $product->get_weight(); break;
                case 'url': $data['url'] = get_permalink($pid); break;
            }
        }
        return $data;
    }

    public function ajax_export() {
        if (!current_user_can('manage_options')) wp_die('Yetkisiz');
        check_ajax_referer('webyaz_xml_action', 'nonce');

        $opts = self::get_opts();
        $fields = isset($_POST['fields']) ? array_map('sanitize_text_field', $_POST['fields']) : $opts['export_fields'];

        $args = array('status' => 'publish', 'limit' => -1);
        if (!empty($_POST['category'])) {
            $args['category'] = array(sanitize_text_field($_POST['category']));
        }
        $products = wc_get_products($args);

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><products></products>');
        $xml->addAttribute('exported', date('Y-m-d H:i:s'));
        $xml->addAttribute('count', count($products));
        $xml->addAttribute('site', get_site_url());

        foreach ($products as $product) {
            $pdata = $this->get_product_data($product, $fields);
            $node = $xml->addChild('product');
            foreach ($pdata as $key => $val) {
                $child = $node->addChild($key);
                $child[0] = $val;
            }
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="webyaz-products-' . date('Y-m-d') . '.xml"');
        echo $dom->saveXML();
        exit;
    }

    public function ajax_import() {
        if (!current_user_can('manage_options')) wp_die('Yetkisiz');
        check_ajax_referer('webyaz_xml_action', 'nonce');

        $batch_size = 5;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $upload_dir = wp_upload_dir();
        $tmp_xml = $upload_dir['basedir'] . '/webyaz-import-temp.xml';

        if ($offset === 0) {
            $source_url = isset($_POST['source_url']) ? esc_url_raw($_POST['source_url']) : '';
            if (!empty($source_url)) {
                // already fetched by ajax_fetch_supplier_xml, tmp file exists
                if (!file_exists($tmp_xml)) {
                    $response = wp_remote_get($source_url, array('timeout' => 60, 'sslverify' => false));
                    if (!is_wp_error($response)) {
                        file_put_contents($tmp_xml, wp_remote_retrieve_body($response));
                    }
                }
            } elseif (!empty($_FILES['xml_file']) && $_FILES['xml_file']['error'] === UPLOAD_ERR_OK) {
                move_uploaded_file($_FILES['xml_file']['tmp_name'], $tmp_xml);
            } else {
                if (!file_exists($tmp_xml)) {
                    wp_send_json_error('Dosya secilmedi');
                }
            }
        }

        if (!file_exists($tmp_xml)) {
            wp_send_json_error('Gecici dosya bulunamadi');
        }

        $content = file_get_contents($tmp_xml);
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if (!$xml) {
            wp_send_json_error('Gecersiz XML dosyasi');
        }

        $mapping = isset($_POST['mapping']) ? $_POST['mapping'] : array();
        $update_existing = isset($_POST['update_existing']) && $_POST['update_existing'] === '1';
        $imported = 0;
        $updated = 0;
        $errors = 0;

        $all_products = array();
        $firstTag = '';
        foreach ($xml->children() as $child) { $firstTag = $child->getName(); break; }
        if ($firstTag) {
            foreach ($xml->$firstTag as $item) { $all_products[] = $item; }
        }
        $total = count($all_products);
        $batch = array_slice($all_products, $offset, $batch_size);

        foreach ($batch as $item) {
            $name = '';
            $sku = '';
            $data = array();

            foreach ($mapping as $xml_field => $wc_field) {
                if (empty($wc_field) || $wc_field === 'skip') continue;
                $val = isset($item->$xml_field) ? (string)$item->$xml_field : '';
                $data[$wc_field] = $val;
                if ($wc_field === 'name') $name = $val;
                if ($wc_field === 'sku') $sku = $val;
            }

            if (empty($name) && empty($sku)) { $errors++; continue; }

            $existing_id = 0;
            if ($sku && $update_existing) {
                $existing_id = wc_get_product_id_by_sku($sku);
            }

            if ($existing_id && $update_existing) {
                $product = wc_get_product($existing_id);
                if (!$product) { $errors++; continue; }
                $this->apply_product_data($product, $data);
                $product->save();
                $updated++;
            } else {
                $product = new WC_Product_Simple();
                $product->set_stock_status('instock');
                $this->apply_product_data($product, $data);
                $product->save();
                $pid = $product->get_id();
                if ($pid) {
                    $this->apply_meta_data($pid, $data);
                    $imported++;
                } else {
                    $errors++;
                }
            }

            if (isset($data['categories']) && !empty($data['categories'])) {
                $pid = $product->get_id();
                $cats = array_map('trim', explode(',', $data['categories']));
                $cat_ids = array();
                foreach ($cats as $cat_name) {
                    $term = term_exists($cat_name, 'product_cat');
                    if (!$term) $term = wp_insert_term($cat_name, 'product_cat');
                    if (!is_wp_error($term)) $cat_ids[] = intval($term['term_id']);
                }
                if (!empty($cat_ids)) wp_set_object_terms($pid, $cat_ids, 'product_cat');
            }
        }

        $next_offset = $offset + $batch_size;
        $done = $next_offset >= $total;

        if ($done && file_exists($tmp_xml)) {
            @unlink($tmp_xml);
        }

        wp_send_json_success(array(
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'offset' => $next_offset,
            'total' => $total,
            'done' => $done,
        ));
    }

    private function apply_product_data($product, $data) {
        if (isset($data['name'])) $product->set_name($data['name']);
        if (isset($data['sku'])) $product->set_sku($data['sku']);
        if (isset($data['price'])) $product->set_regular_price($data['price']);
        if (isset($data['sale_price']) && $data['sale_price'] !== '') $product->set_sale_price($data['sale_price']);
        if (isset($data['description'])) $product->set_description($data['description']);
        if (isset($data['short_description'])) $product->set_short_description($data['short_description']);
        $product->set_stock_status('instock');
        $product->set_manage_stock(false);
        if (isset($data['weight']) && $data['weight'] !== '') $product->set_weight($data['weight']);
        if (isset($data['image']) && !empty($data['image'])) {
            $img_id = $this->upload_image_from_url($data['image']);
            if ($img_id) $product->set_image_id($img_id);
        }
    }

    private function apply_meta_data($pid, $data) {
        if (isset($data['cost_price'])) update_post_meta($pid, '_webyaz_cost_price', wc_format_decimal($data['cost_price']));
        if (isset($data['sizes']) && !empty($data['sizes'])) {
            $sizes = array_map('trim', explode(',', $data['sizes']));
            update_post_meta($pid, '_webyaz_sizes', $sizes);
            update_post_meta($pid, '_webyaz_attrs_active', '1');
        }
        if (isset($data['colors']) && !empty($data['colors'])) {
            $color_pairs = array_map('trim', explode(',', $data['colors']));
            $colors = array();
            foreach ($color_pairs as $cp) {
                $parts = explode(':', $cp);
                $colors[] = array('name' => trim($parts[0]), 'hex' => isset($parts[1]) ? trim($parts[1]) : '#000000');
            }
            update_post_meta($pid, '_webyaz_colors', $colors);
            update_post_meta($pid, '_webyaz_attrs_active', '1');
        }
        if (isset($data['shoes']) && !empty($data['shoes'])) {
            $shoes = array_map('trim', explode(',', $data['shoes']));
            update_post_meta($pid, '_webyaz_shoes', $shoes);
            update_post_meta($pid, '_webyaz_shoes_active', '1');
        }
        if (isset($data['units']) && !empty($data['units'])) {
            $units = array_map('trim', explode(',', $data['units']));
            update_post_meta($pid, '_webyaz_units', $units);
            update_post_meta($pid, '_webyaz_units_active', '1');
        }
        if (isset($data['custom_props']) && !empty($data['custom_props'])) {
            $props = array();
            $pairs = explode('|', $data['custom_props']);
            foreach ($pairs as $pair) {
                $kv = explode(':', $pair, 2);
                if (count($kv) === 2) {
                    $props[] = array('key' => trim($kv[0]), 'val' => trim($kv[1]));
                }
            }
            if (!empty($props)) {
                update_post_meta($pid, '_webyaz_custom_props', $props);
                update_post_meta($pid, '_webyaz_custom_props_active', '1');
            }
        }
        $auto_props = array();
        foreach ($data as $key => $val) {
            if (strpos($key, 'auto_prop_') === 0 && !empty($val)) {
                $prop_name = str_replace('auto_prop_', '', $key);
                $auto_props[] = array('key' => $prop_name, 'val' => $val);
            }
        }
        if (!empty($auto_props)) {
            $existing = get_post_meta($pid, '_webyaz_custom_props', true);
            if (!is_array($existing)) $existing = array();
            $existing = array_merge($existing, $auto_props);
            update_post_meta($pid, '_webyaz_custom_props', $existing);
            update_post_meta($pid, '_webyaz_custom_props_active', '1');
        }
    }

    private function upload_image_from_url($url) {
        if (empty($url)) return 0;
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $tmp = download_url($url, 30);
        if (is_wp_error($tmp)) return 0;
        $fname = basename(parse_url($url, PHP_URL_PATH));
        $file_array = array('name' => $fname, 'tmp_name' => $tmp);
        $id = media_handle_sideload($file_array, 0);
        if (is_wp_error($id)) { @unlink($tmp); return 0; }
        return $id;
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'XML Yonetimi', 'XML Yonetimi', 'manage_options', 'webyaz-xml', array($this, 'render_admin'));
    }

    public function render_admin() {
        $opts = self::get_opts();
        $fields = self::get_available_fields();
        $cats = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>XML Urun Yonetimi</h1>
                <p>Urunleri XML olarak disari aktar veya iceri aktar</p>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                <div class="webyaz-settings-section">
                    <h2>XML Disari Aktar (Export)</h2>
                    <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" id="webyazExportForm">
                        <input type="hidden" name="action" value="webyaz_export_xml">
                        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('webyaz_xml_action'); ?>">
                        <div class="webyaz-field" style="margin-bottom:12px;">
                            <label style="font-weight:600;">Kategori Filtresi</label>
                            <select name="category" style="width:100%;padding:8px;">
                                <option value="">Tum Urunler</option>
                                <?php foreach ($cats as $cat): ?>
                                <option value="<?php echo esc_attr($cat->slug); ?>"><?php echo esc_html($cat->name); ?> (<?php echo $cat->count; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="webyaz-field" style="margin-bottom:12px;">
                            <label style="font-weight:600;">Aktarilacak Alanlar</label>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:6px;">
                                <?php foreach ($fields as $fkey => $flabel): ?>
                                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                                    <input type="checkbox" name="fields[]" value="<?php echo $fkey; ?>" <?php checked(in_array($fkey, $opts['export_fields'])); ?> style="width:16px;height:16px;">
                                    <?php echo esc_html($flabel); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button type="submit" class="button button-primary" style="width:100%;padding:10px;font-size:14px;font-weight:600;background:#446084;border-color:#446084;">XML Indir</button>
                    </form>
                </div>

                <div class="webyaz-settings-section">
                    <h2>XML Iceri Aktar (Import)</h2>

                    <div style="margin-bottom:16px;">
                        <label style="font-weight:600;display:block;margin-bottom:8px;">Kayitli Toptancilar / XML Kaynaklari</label>
                        <div id="webyazSupplierList">
                            <?php
                            $suppliers = get_option('webyaz_xml_suppliers', array());
                            if (!empty($suppliers)):
                                foreach ($suppliers as $i => $sup):
                                    $schedule = isset($sup['schedule']) ? $sup['schedule'] : 'off';
                                    $time1 = isset($sup['time1']) ? $sup['time1'] : '03:00';
                                    $time2 = isset($sup['time2']) ? $sup['time2'] : '15:00';
                                    $last_sync = isset($sup['last_sync']) ? $sup['last_sync'] : '';
                                ?>
                                <div class="webyaz-supplier-row" style="margin-bottom:10px;padding:14px;background:#f8f9fa;border-radius:10px;border:1px solid #e0e0e0;">
                                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
                                        <span style="font-weight:700;font-size:14px;min-width:120px;"><?php echo esc_html($sup['name']); ?></span>
                                        <input type="text" value="<?php echo esc_url($sup['url']); ?>" readonly style="flex:1;padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:12px;background:#fff;">
                                        <button type="button" class="button webyaz-load-supplier" data-url="<?php echo esc_url($sup['url']); ?>" data-name="<?php echo esc_attr($sup['name']); ?>" style="background:#446084;color:#fff;border-color:#446084;font-weight:600;">Analiz Et</button>
                                        <button type="button" class="button webyaz-del-supplier" data-index="<?php echo $i; ?>" style="background:#c62828;color:#fff;border-color:#c62828;">Sil</button>
                                    </div>
                                    <div style="display:flex;gap:12px;align-items:center;padding:8px 12px;background:#fff;border-radius:8px;border:1px solid #eee;flex-wrap:wrap;">
                                        <span style="font-size:12px;font-weight:600;color:#666;">Otomatik Cekme:</span>
                                        <select class="webyaz-schedule-select" data-index="<?php echo $i; ?>" style="padding:4px 8px;border:1px solid #ddd;border-radius:4px;font-size:12px;">
                                            <option value="off" <?php selected($schedule, 'off'); ?>>Kapali</option>
                                            <option value="daily1" <?php selected($schedule, 'daily1'); ?>>Gunde 1 Kez</option>
                                            <option value="daily2" <?php selected($schedule, 'daily2'); ?>>Gunde 2 Kez</option>
                                        </select>
                                        <?php if ($last_sync): ?>
                                        <span style="font-size:11px;color:#999;margin-left:auto;">Son: <?php echo esc_html($last_sync); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="webyaz-time-inputs" data-index="<?php echo $i; ?>" style="<?php echo $schedule !== 'off' ? 'display:flex;' : 'display:none;'; ?>gap:8px;align-items:center;padding:8px 12px;background:#e8f5e9;border-radius:8px;margin-top:6px;">
                                        <span style="font-size:12px;font-weight:600;color:#2e7d32;">Saat:</span>
                                        <input type="time" class="webyaz-time1" value="<?php echo esc_attr($time1); ?>" style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;font-size:13px;">
                                        <span class="webyaz-time2-wrap" style="<?php echo $schedule === 'daily2' ? 'display:inline-flex;' : 'display:none;'; ?>align-items:center;gap:6px;">
                                            <span style="font-size:12px;color:#666;">ve</span>
                                            <input type="time" class="webyaz-time2" value="<?php echo esc_attr($time2); ?>" style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;font-size:13px;">
                                        </span>
                                        <button type="button" class="button webyaz-save-schedule" data-index="<?php echo $i; ?>" style="font-size:12px;padding:4px 14px;background:#2e7d32;color:#fff;border-color:#2e7d32;font-weight:600;">Kaydet</button>
                                    </div>
                                </div>
                            <?php endforeach;
                            else: ?>
                                <p style="color:#999;font-size:13px;" id="webyazNoSupplier">Henuz toptanci eklenmedi.</p>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:8px;margin-top:10px;padding:12px;background:#fff;border-radius:8px;border:2px dashed #446084;">
                            <input type="text" id="webyazNewSupplierName" placeholder="Toptanci Adi (orn: ABC Tekstil)" style="width:180px;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                            <input type="text" id="webyazNewSupplierUrl" placeholder="XML Link (orn: https://toptanci.com/feed.xml)" style="flex:1;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                            <button type="button" id="webyazAddSupplier" class="button" style="background:#446084;color:#fff;border-color:#446084;font-weight:600;white-space:nowrap;">+ Toptanci Ekle</button>
                        </div>
                    </div>

                    <div style="margin-bottom:16px;padding:12px;background:#f5f5f5;border-radius:8px;text-align:center;">
                        <span style="font-size:13px;color:#888;">─── veya dosyadan yukle ───</span>
                    </div>

                    <div style="display:flex;gap:8px;margin-bottom:16px;">
                        <button type="button" class="webyaz-tab-btn active" data-tab="xml" style="flex:1;padding:10px;border:2px solid #446084;border-radius:8px;background:#446084;color:#fff;font-weight:700;cursor:pointer;font-size:14px;">XML Dosyasi</button>
                        <button type="button" class="webyaz-tab-btn" data-tab="excel" style="flex:1;padding:10px;border:2px solid #2e7d32;border-radius:8px;background:#fff;color:#2e7d32;font-weight:700;cursor:pointer;font-size:14px;">Excel / CSV</button>
                    </div>

                    <div id="webyazTabXml">
                    <form id="webyazImportForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="webyaz_import_xml">
                        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('webyaz_xml_action'); ?>">
                        <input type="hidden" name="source_url" id="webyazSourceUrl" value="">
                        <div class="webyaz-field" style="margin-bottom:12px;">
                            <label style="font-weight:600;">XML Dosyasi Sec</label>
                            <input type="file" name="xml_file" accept=".xml" id="webyazXmlFile" style="width:100%;padding:8px;border:2px dashed #ddd;border-radius:8px;">
                        </div>
                        <div id="webyazSourceInfo" style="display:none;margin-bottom:12px;padding:10px;background:#e3f2fd;border-radius:8px;font-size:13px;">
                            <strong>Kaynak:</strong> <span id="webyazSourceName"></span>
                        </div>
                        <div id="webyazMappingArea" style="display:none;">
                            <div class="webyaz-field" style="margin-bottom:12px;">
                                <label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;">
                                    <input type="checkbox" name="update_existing" value="1" style="width:16px;height:16px;">
                                    Mevcut urunleri guncelle (SKU eslesirse)
                                </label>
                            </div>
                            <div class="webyaz-field" style="margin-bottom:12px;">
                                <label style="font-weight:600;margin-bottom:8px;display:block;">Alan Eslestirme</label>
                                <div id="webyazMappingFields" style="max-height:300px;overflow-y:auto;"></div>
                            </div>
                        </div>
                        <button type="button" id="webyazImportBtn" class="button button-primary" style="width:100%;padding:10px;font-size:14px;font-weight:600;background:#2e7d32;border-color:#2e7d32;" disabled>Iceri Aktar</button>
                        <div id="webyazImportResult" style="margin-top:12px;display:none;padding:12px;border-radius:8px;font-size:14px;"></div>
                    </form>
                    </div>

                    <div id="webyazTabExcel" style="display:none;">
                        <form id="webyazExcelForm" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="webyaz_import_excel">
                            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('webyaz_xml_action'); ?>">
                            <div style="margin-bottom:16px;padding:16px;background:#e8f5e9;border-radius:10px;border:1px solid #c8e6c9;">
                                <p style="margin:0 0 8px;font-weight:700;color:#2e7d32;">Excel / CSV ile Toplu Urun Yukle</p>
                                <p style="margin:0;font-size:12px;color:#666;">Dosyanizin ilk satirinda sutun basliklari olmali. Desteklenen formatlar: .xlsx, .csv</p>
                            </div>
                            <div class="webyaz-field" style="margin-bottom:12px;">
                                <label style="font-weight:600;">Dosya Sec (.xlsx veya .csv)</label>
                                <input type="file" name="excel_file" accept=".xlsx,.csv" id="webyazExcelFile" style="width:100%;padding:8px;border:2px dashed #2e7d32;border-radius:8px;">
                            </div>
                            <div id="webyazExcelPreview" style="display:none;margin-bottom:12px;"></div>
                            <div id="webyazExcelMapping" style="display:none;margin-bottom:12px;">
                                <label style="font-weight:600;margin-bottom:8px;display:block;">Alan Eslestirme</label>
                                <div id="webyazExcelMappingFields" style="max-height:300px;overflow-y:auto;"></div>
                            </div>
                            <div class="webyaz-field" style="margin-bottom:12px;">
                                <label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;">
                                    <input type="checkbox" name="update_existing" value="1" style="width:16px;height:16px;">
                                    Mevcut urunleri guncelle (SKU eslesirse)
                                </label>
                            </div>
                            <button type="button" id="webyazExcelImportBtn" class="button button-primary" style="width:100%;padding:10px;font-size:14px;font-weight:600;background:#2e7d32;border-color:#2e7d32;" disabled>Excel Iceri Aktar</button>
                            <div id="webyazExcelResult" style="margin-top:12px;display:none;padding:12px;border-radius:8px;font-size:14px;"></div>
                        </form>
                        <div style="margin-top:12px;padding:12px;background:#fff3e0;border-radius:8px;border:1px solid #ffe0b2;">
                            <p style="margin:0 0 6px;font-weight:600;font-size:13px;color:#e65100;">Ornek Excel Sablonu:</p>
                            <button type="button" id="webyazDownloadTemplate" class="button" style="background:#e65100;color:#fff;border-color:#e65100;font-weight:600;">Sablon Indir (.csv)</button>
                        </div>
                    </div>

                </div>
            </div>

            <div class="webyaz-settings-section" style="margin-top:20px;">
                <h2>Otomatik XML Feed Linki</h2>
                <div style="background:#fff;padding:16px;border-radius:10px;border:1px solid #e0e0e0;">
                    <p style="margin:0 0 8px;font-size:13px;color:#666;">Bu linki pazaryerleri, karsilastirma siteleri veya baska sistemlere verin. Surekli guncel kalir:</p>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="text" id="webyazFeedUrl" value="<?php echo esc_url(home_url('/webyaz-xml-feed/')); ?>" readonly style="flex:1;padding:10px 14px;border:2px solid #446084;border-radius:8px;font-size:14px;font-weight:500;background:#f8f9fa;">
                        <button type="button" onclick="var i=document.getElementById('webyazFeedUrl');i.select();document.execCommand('copy');this.textContent='Kopyalandi!';var b=this;setTimeout(function(){b.textContent='Kopyala'},2000);" style="padding:10px 20px;background:#446084;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Kopyala</button>
                        <a href="<?php echo esc_url(home_url('/webyaz-xml-feed/')); ?>" target="_blank" style="padding:10px 20px;background:#2e7d32;color:#fff;border-radius:8px;font-weight:600;text-decoration:none;">Onizle</a>
                    </div>
                    <p style="margin:8px 0 0;font-size:12px;color:#999;">Not: Ilk kullanim icin Ayarlar > Kalici Baglantilar sayfasini bir kez kaydedin.</p>
                </div>
            </div>

            <div class="webyaz-settings-section" style="margin-top:20px;">
                <h2>Bilgi</h2>
                <div style="color:#666;line-height:1.8;font-size:13px;">
                    <p><strong>Export:</strong> Urunlerinizi XML formatinda indirin. Baska sitelere veya sistemlere aktarin.</p>
                    <p><strong>Import:</strong> XML dosyasi yukleyin, alanlari eslestirin ve urunleri topluca iceri aktarin.</p>
                    <p><strong>Desteklenen Alanlar:</strong> Urun adi, fiyat, alis fiyati, bedenler, renkler, ayakkabi numaralari, satis birimleri, gorsel URL ve daha fazlasi.</p>
                    <p><strong>Gorsel Import:</strong> Gorsel URL verilirse otomatik olarak medya kutuphanesine yuklenir.</p>
                </div>
                <div style="margin-top:16px;padding:14px;background:#fff3e0;border-radius:10px;border:1px solid #ffe0b2;">
                    <p style="margin:0 0 10px;font-weight:600;color:#e65100;">Tum urunlerin stok durumunu "Stokta" yap:</p>
                    <button type="button" id="webyazFixStock" class="button" style="background:#e65100;color:#fff;border-color:#e65100;font-weight:600;">Stoklari Duzelt</button>
                    <span id="webyazFixStockResult" style="margin-left:10px;font-size:13px;"></span>
                </div>
            </div>
        </div>

        <script>
        jQuery(function($){
            var wcFields = <?php echo json_encode($fields); ?>;
            var nonce = '<?php echo wp_create_nonce("webyaz_xml_action"); ?>';

            function buildMappingTable(xmlFields, sampleData) {
                var html = '<table style="width:100%;font-size:13px;"><tr style="background:#f5f5f5;"><th style="padding:8px;text-align:left;">XML Alani</th><th style="padding:8px;text-align:left;">Ornek Veri</th><th style="padding:8px;text-align:left;">Eslesecek Alan</th></tr>';
                xmlFields.forEach(function(xf){
                    var sample = sampleData[xf] || '';
                    if (sample.length > 50) sample = sample.substring(0, 50) + '...';
                    html += '<tr style="border-bottom:1px solid #eee;"><td style="padding:6px 8px;font-weight:600;">' + xf + '</td>';
                    html += '<td style="padding:6px 8px;color:#888;font-size:12px;">' + sample + '</td>';
                    html += '<td style="padding:6px 8px;"><select name="mapping[' + xf + ']" class="webyaz-mapping-select" style="width:100%;padding:4px;">';
                    html += '<option value="skip">-- Atla --</option>';
                    var autoMatch = '';
                    var lxf = xf.toLowerCase().replace(/[_\-\s]/g, '');
                    for (var k in wcFields) {
                        var lk = k.toLowerCase().replace(/[_\-\s]/g, '');
                        if (lk === lxf || wcFields[k].toLowerCase().replace(/[_\-\s]/g, '') === lxf) autoMatch = k;
                    }
                    for (var k in wcFields) {
                        var sel = (k === autoMatch) ? ' selected' : '';
                        html += '<option value="' + k + '"' + sel + '>' + wcFields[k] + '</option>';
                    }
                    html += '<option disabled style="font-weight:700;color:#7b1fa2;">─── Akilli Ozellik ───</option>';
                    html += '<option value="auto_prop_' + xf + '" style="color:#7b1fa2;">+ "' + xf + '" Akilli Ozellik Olarak Ekle</option>';
                    html += '</select></td></tr>';
                });
                html += '</table>';
                return html;
            }

            // Zamanlama select degisince
            $(document).on('change', '.webyaz-schedule-select', function(){
                var val = $(this).val();
                var row = $(this).closest('.webyaz-supplier-row');
                if (!row.length) row = $(this).parent().parent();
                var timeDiv = row.find('.webyaz-time-inputs');
                if (val !== 'off') {
                    timeDiv.show().css('display','flex');
                } else {
                    timeDiv.hide();
                }
                var t2 = row.find('.webyaz-time2-wrap');
                if (val === 'daily2') {
                    t2.show().css('display','inline-flex');
                } else {
                    t2.hide();
                }
            });

            // Sayfa yuklenince mevcut schedule durumlarini kontrol et
            $('.webyaz-schedule-select').each(function(){
                var val = $(this).val();
                var row = $(this).closest('.webyaz-supplier-row');
                if (val && val !== 'off') {
                    row.find('.webyaz-time-inputs').show().css('display','flex');
                    if (val === 'daily2') {
                        row.find('.webyaz-time2-wrap').show().css('display','inline-flex');
                    }
                }
            });

            // Zamanlama kaydet
            $(document).on('click', '.webyaz-save-schedule', function(){
                var btn = $(this);
                var idx = btn.data('index');
                var row = btn.closest('.webyaz-supplier-row');
                var schedule = row.find('.webyaz-schedule-select').val();
                var time1 = row.find('.webyaz-time1').val();
                var time2 = row.find('.webyaz-time2').val();
                btn.text('Kaydediliyor...').prop('disabled', true);
                $.post(ajaxurl, {action:'webyaz_save_schedule', nonce:nonce, index:idx, schedule:schedule, time1:time1, time2:time2}, function(res){
                    btn.text('Kaydedildi!').css('color','#2e7d32');
                    setTimeout(function(){ btn.text('Kaydet').css('color','').prop('disabled', false); }, 2000);
                });
            });

            // Toptanci ekle
            $('#webyazAddSupplier').on('click', function(){
                var name = $('#webyazNewSupplierName').val().trim();
                var url = $('#webyazNewSupplierUrl').val().trim();
                if (!name || !url) { alert('Toptanci adi ve XML linki girin!'); return; }
                var btn = $(this);
                btn.text('Ekleniyor...').prop('disabled', true);
                $.post(ajaxurl, {action:'webyaz_add_supplier', nonce:nonce, name:name, url:url}, function(res){
                    if (res.success) {
                        $('#webyazNoSupplier').remove();
                        var row = '<div class="webyaz-supplier-row" style="display:flex;gap:8px;align-items:center;margin-bottom:6px;padding:10px;background:#f8f9fa;border-radius:8px;border:1px solid #e0e0e0;">';
                        row += '<span style="font-weight:600;font-size:14px;min-width:120px;">' + name + '</span>';
                        row += '<input type="text" value="' + url + '" readonly style="flex:1;padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:12px;background:#fff;">';
                        row += '<button type="button" class="button webyaz-load-supplier" data-url="' + url + '" data-name="' + name + '" style="background:#446084;color:#fff;border-color:#446084;font-weight:600;">Analiz Et</button>';
                        row += '<button type="button" class="button webyaz-del-supplier" data-index="' + res.data.index + '" style="background:#c62828;color:#fff;border-color:#c62828;">Sil</button>';
                        row += '</div>';
                        $('#webyazSupplierList').append(row);
                        $('#webyazNewSupplierName').val('');
                        $('#webyazNewSupplierUrl').val('');
                    }
                    btn.text('+ Toptanci Ekle').prop('disabled', false);
                });
            });

            // Toptanci sil
            $(document).on('click', '.webyaz-del-supplier', function(){
                if (!confirm('Bu toptanciyi silmek istiyor musunuz?')) return;
                var row = $(this).closest('.webyaz-supplier-row');
                var idx = $(this).data('index');
                $.post(ajaxurl, {action:'webyaz_del_supplier', nonce:nonce, index:idx}, function(res){
                    if (res.success) row.fadeOut(300, function(){ $(this).remove(); });
                });
            });

            // Toptanci XML analiz et
            $(document).on('click', '.webyaz-load-supplier', function(){
                var btn = $(this);
                var url = btn.data('url');
                var name = btn.data('name');
                btn.text('Analiz ediliyor...').prop('disabled', true);
                $.post(ajaxurl, {action:'webyaz_fetch_supplier_xml', nonce:nonce, url:url}, function(res){
                    if (res.success) {
                        var d = res.data;
                        var html = buildMappingTable(d.fields, d.sample);
                        html = '<p style="margin-bottom:10px;font-weight:600;color:#446084;">' + d.total + ' urun bulundu</p>' + html;
                        $('#webyazMappingFields').html(html);
                        $('#webyazMappingArea').show();
                        $('#webyazImportBtn').prop('disabled', false);
                        $('#webyazSourceUrl').val(url);
                        $('#webyazSourceName').text(name + ' (' + d.total + ' urun)');
                        $('#webyazSourceInfo').show();
                        $('#webyazXmlFile').val('');
                    } else {
                        alert('Hata: ' + res.data);
                    }
                    btn.text('Analiz Et').prop('disabled', false);
                });
            });

            // Dosyadan analiz
            $('#webyazXmlFile').on('change', function(){
                var file = this.files[0];
                if (!file) return;
                $('#webyazSourceUrl').val('');
                $('#webyazSourceInfo').hide();
                var reader = new FileReader();
                reader.onload = function(e){
                    try {
                        var parser = new DOMParser();
                        var xml = parser.parseFromString(e.target.result, 'text/xml');
                        var firstProduct = xml.querySelector('product');
                        if (!firstProduct) { alert('Gecerli bir urun XML dosyasi degil!'); return; }
                        var xmlFields = [];
                        var sampleData = {};
                        for (var i = 0; i < firstProduct.children.length; i++) {
                            var tag = firstProduct.children[i].tagName;
                            xmlFields.push(tag);
                            sampleData[tag] = firstProduct.children[i].textContent || '';
                        }
                        var count = xml.querySelectorAll('product').length;
                        var html = buildMappingTable(xmlFields, sampleData);
                        html = '<p style="margin-bottom:10px;font-weight:600;color:#446084;">' + count + ' urun bulundu</p>' + html;
                        $('#webyazMappingFields').html(html);
                        $('#webyazMappingArea').show();
                        $('#webyazImportBtn').prop('disabled', false);
                    } catch(err) {
                        alert('XML okunamadi: ' + err.message);
                    }
                };
                reader.readAsText(file);
            });

            $('#webyazImportBtn').on('click', function(){
                var btn = $(this);
                var totalImported = 0, totalUpdated = 0, totalErrors = 0;

                function runBatch(offset) {
                    var formData = new FormData($('#webyazImportForm')[0]);
                    formData.append('offset', offset);
                    if (offset > 0) { formData.delete('xml_file'); }
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(res){
                            if (res.success) {
                                var d = res.data;
                                totalImported += d.imported;
                                totalUpdated += d.updated;
                                totalErrors += d.errors;
                                var pct = Math.min(100, Math.round((d.offset / d.total) * 100));
                                btn.text('Aktariliyor... (' + pct + '% - ' + d.offset + '/' + d.total + ')');
                                $('#webyazImportResult').html('<div style="background:#e3f2fd;border-radius:6px;height:8px;margin-bottom:6px;"><div style="background:#1565c0;height:100%;border-radius:6px;width:' + pct + '%;transition:width 0.3s;"></div></div>' + totalImported + ' eklendi, ' + totalUpdated + ' guncellendi').css({background:'#e8f5e9',color:'#2e7d32'}).show();
                                if (!d.done) {
                                    setTimeout(function(){ runBatch(d.offset); }, 500);
                                } else {
                                    btn.text('Iceri Aktar').prop('disabled', false);
                                    $('#webyazImportResult').html('<strong>' + totalImported + '</strong> urun eklendi, <strong>' + totalUpdated + '</strong> guncellendi' + (totalErrors > 0 ? ', <strong style="color:#c62828;">' + totalErrors + '</strong> hata' : '') + ' - <strong>Tamamlandi!</strong>').css({background:'#e8f5e9',color:'#2e7d32'}).show();
                                    var srcUrl = $('#webyazSourceUrl').val();
                                    if (srcUrl) {
                                        var mp = {};
                                        $('#webyazMappingFields select').each(function(){ mp[$(this).attr('name').replace('mapping[','').replace(']','')] = $(this).val(); });
                                        $.post(ajaxurl, {action:'webyaz_save_mapping', nonce:nonce, url:srcUrl, mapping:mp});
                                    }
                                }
                            } else {
                                $('#webyazImportResult').html('Hata: ' + res.data).css({background:'#ffebee',color:'#c62828'}).show();
                                btn.text('Iceri Aktar').prop('disabled', false);
                            }
                        },
                        error: function(){
                            $('#webyazImportResult').html('Sunucu hatasi - tekrar deneyin').css({background:'#ffebee',color:'#c62828'}).show();
                            btn.text('Iceri Aktar').prop('disabled', false);
                        }
                    });
                }

                btn.text('Aktariliyor... (0%)').prop('disabled', true);
                $('#webyazImportResult').html('Baslatiliyor...').css({background:'#e3f2fd',color:'#1565c0'}).show();
                runBatch(0);
            });

            $('#webyazFixStock').on('click', function(){
                var btn = $(this);
                btn.text('Duzeltiliyor...').prop('disabled', true);
                $.post(ajaxurl, {action:'webyaz_fix_stock', nonce:'<?php echo wp_create_nonce("webyaz_xml_action"); ?>'}, function(res){
                    if (res.success) {
                        $('#webyazFixStockResult').html('<strong style="color:#2e7d32;">' + res.data.fixed + ' urun stokta olarak guncellendi!</strong>');
                    }
                    btn.text('Stoklari Duzelt').prop('disabled', false);
                });
            });

            // Tab degistirme
            $(document).on('click', '.webyaz-tab-btn', function(){
                var tab = $(this).data('tab');
                $('.webyaz-tab-btn').each(function(){
                    $(this).css({background:'#fff',color: $(this).data('tab')==='xml' ? '#446084' : '#2e7d32'}).removeClass('active');
                });
                $(this).css({background: tab==='xml' ? '#446084' : '#2e7d32', color:'#fff'}).addClass('active');
                $('#webyazTabXml').toggle(tab==='xml');
                $('#webyazTabExcel').toggle(tab==='excel');
            });

            // Excel dosya sec -> analiz et
            $('#webyazExcelFile').on('change', function(){
                var file = this.files[0];
                if (!file) return;
                var fd = new FormData();
                fd.append('action', 'webyaz_analyze_excel');
                fd.append('nonce', nonce);
                fd.append('excel_file', file);
                $('#webyazExcelPreview').html('<p style="color:#446084;font-weight:600;">Analiz ediliyor...</p>').show();
                $.ajax({url:ajaxurl, type:'POST', data:fd, processData:false, contentType:false, success:function(res){
                    if (res.success) {
                        var d = res.data;
                        $('#webyazExcelPreview').html('<p style="font-weight:600;color:#2e7d32;">' + d.total + ' urun bulundu</p>').show();
                        var html = buildMappingTable(d.headers, d.sample);
                        $('#webyazExcelMappingFields').html(html);
                        $('#webyazExcelMapping').show();
                        $('#webyazExcelImportBtn').prop('disabled', false);
                    } else {
                        $('#webyazExcelPreview').html('<p style="color:#c62828;">' + res.data + '</p>').show();
                    }
                }, error:function(){
                    $('#webyazExcelPreview').html('<p style="color:#c62828;">Sunucu hatasi</p>').show();
                }});
            });

            // Excel import
            $('#webyazExcelImportBtn').on('click', function(){
                var btn = $(this);
                var mapping = {};
                $('#webyazExcelMappingFields select').each(function(){
                    var n = $(this).attr('name');
                    if (n) {
                        var key = n.replace('mapping[','').replace(']','');
                        mapping[key] = $(this).val();
                    }
                });
                var updateExisting = $('#webyazExcelForm input[name="update_existing"]').is(':checked') ? '1' : '0';
                var totalImported = 0, totalUpdated = 0, totalErrors = 0;

                function runBatch(off) {
                    $.post(ajaxurl, {action:'webyaz_import_excel', nonce:nonce, offset:off, mapping:mapping, update_existing:updateExisting}, function(res){
                        if (res.success) {
                            var d = res.data;
                            totalImported += d.imported;
                            totalUpdated += d.updated;
                            totalErrors += d.errors;
                            var pct = Math.min(100, Math.round((d.offset / d.total) * 100));
                            btn.text('Aktariliyor... (' + pct + '%)');
                            $('#webyazExcelResult').html('<div style="background:#e3f2fd;border-radius:6px;height:8px;margin-bottom:6px;"><div style="background:#2e7d32;height:100%;border-radius:6px;width:' + pct + '%;transition:width 0.3s;"></div></div>' + totalImported + ' eklendi, ' + totalUpdated + ' guncellendi').css({background:'#e8f5e9',color:'#2e7d32'}).show();
                            if (!d.done) {
                                setTimeout(function(){ runBatch(d.offset); }, 500);
                            } else {
                                btn.text('Excel Iceri Aktar').prop('disabled', false);
                                $('#webyazExcelResult').html('<strong>' + totalImported + '</strong> urun eklendi, <strong>' + totalUpdated + '</strong> guncellendi' + (totalErrors > 0 ? ', <strong style="color:#c62828;">' + totalErrors + '</strong> hata' : '') + ' - <strong>Tamamlandi!</strong>').css({background:'#e8f5e9',color:'#2e7d32'}).show();
                            }
                        } else {
                            $('#webyazExcelResult').html('Hata: ' + res.data).css({background:'#ffebee',color:'#c62828'}).show();
                            btn.text('Excel Iceri Aktar').prop('disabled', false);
                        }
                    }).fail(function(){
                        $('#webyazExcelResult').html('Sunucu hatasi - tekrar deneyin').css({background:'#ffebee',color:'#c62828'}).show();
                        btn.text('Excel Iceri Aktar').prop('disabled', false);
                    });
                }

                btn.text('Aktariliyor... (0%)').prop('disabled', true);
                $('#webyazExcelResult').html('Baslatiliyor...').css({background:'#e3f2fd',color:'#1565c0'}).show();
                runBatch(0);
            });

            // Sablon indir
            $('#webyazDownloadTemplate').on('click', function(){
                window.location.href = ajaxurl + '?action=webyaz_download_template&nonce=' + nonce;
            });
        });
        </script>
        <?php
    }
}

new Webyaz_Xml_Manager();
