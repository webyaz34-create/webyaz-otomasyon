<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Cost_Price {

    public function __construct() {
        add_action('woocommerce_product_options_pricing', array($this, 'add_cost_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_cost_field'));
        add_action('woocommerce_product_after_variable_attributes', array($this, 'add_variation_cost_field'), 10, 3);
        add_action('woocommerce_save_product_variation', array($this, 'save_variation_cost_field'), 10, 2);
        add_action('admin_menu', array($this, 'add_submenu'));
        add_filter('manage_edit-product_columns', array($this, 'add_column'));
        add_action('manage_product_posts_custom_column', array($this, 'column_content'), 10, 2);
        add_filter('manage_edit-product_sortable_columns', array($this, 'sortable_column'));
    }

    public function add_cost_field() {
        woocommerce_wp_text_input(array(
            'id' => '_webyaz_cost_price',
            'label' => 'Alis Fiyati (' . get_woocommerce_currency_symbol() . ')',
            'desc_tip' => true,
            'description' => 'Urunun alis/maliyet fiyati. Kar hesaplamasi icin kullanilir.',
            'type' => 'text',
            'data_type' => 'price',
        ));
    }

    public function save_cost_field($post_id) {
        if (isset($_POST['_webyaz_cost_price'])) {
            update_post_meta($post_id, '_webyaz_cost_price', wc_format_decimal($_POST['_webyaz_cost_price']));
        }
    }

    public function add_variation_cost_field($loop, $variation_data, $variation) {
        woocommerce_wp_text_input(array(
            'id' => '_webyaz_cost_price_var[' . $loop . ']',
            'name' => '_webyaz_cost_price_var[' . $loop . ']',
            'label' => 'Alis Fiyati (' . get_woocommerce_currency_symbol() . ')',
            'desc_tip' => true,
            'description' => 'Varyasyon alis fiyati',
            'type' => 'text',
            'data_type' => 'price',
            'value' => get_post_meta($variation->ID, '_webyaz_cost_price', true),
            'wrapper_class' => 'form-row form-row-first',
        ));
    }

    public function save_variation_cost_field($variation_id, $i) {
        if (isset($_POST['_webyaz_cost_price_var'][$i])) {
            update_post_meta($variation_id, '_webyaz_cost_price', wc_format_decimal($_POST['_webyaz_cost_price_var'][$i]));
        }
    }

    public function add_column($columns) {
        $new = array();
        foreach ($columns as $key => $val) {
            $new[$key] = $val;
            if ($key === 'price') {
                $new['cost_price'] = 'Alis / Kar';
            }
        }
        return $new;
    }

    public function column_content($column, $post_id) {
        if ($column !== 'cost_price') return;
        $cost = get_post_meta($post_id, '_webyaz_cost_price', true);
        $product = wc_get_product($post_id);
        if (!$product) return;
        $sale = floatval($product->get_price());
        if ($cost) {
            $cost_f = floatval($cost);
            $kar = $sale - $cost_f;
            $margin = $sale > 0 ? round(($kar / $sale) * 100) : 0;
            $color = $kar >= 0 ? '#2e7d32' : '#c62828';
            echo '<span style="color:#666;">' . wc_price($cost_f) . '</span><br>';
            echo '<strong style="color:' . $color . ';">Kar: ' . wc_price($kar) . ' (%' . $margin . ')</strong>';
        } else {
            echo '<span style="color:#999;">-</span>';
        }
    }

    public function sortable_column($columns) {
        $columns['cost_price'] = 'cost_price';
        return $columns;
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Kar Analizi', 'Kar Analizi', 'manage_options', 'webyaz-profit', array($this, 'render_admin'));
    }

    private function get_stats($days = 30) {
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        $orders = wc_get_orders(array(
            'status' => array('completed', 'processing'),
            'date_after' => $date_from,
            'limit' => -1,
            'return' => 'ids',
        ));

        $total_revenue = 0;
        $total_cost = 0;
        $total_items = 0;
        $product_stats = array();

        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_variation_id() ?: $item->get_product_id();
                $qty = $item->get_quantity();
                $line_total = floatval($item->get_total());
                $cost = floatval(get_post_meta($product_id, '_webyaz_cost_price', true));
                if (!$cost) {
                    $cost = floatval(get_post_meta($item->get_product_id(), '_webyaz_cost_price', true));
                }
                $line_cost = $cost * $qty;

                $total_revenue += $line_total;
                $total_cost += $line_cost;
                $total_items += $qty;

                $pid = $item->get_product_id();
                if (!isset($product_stats[$pid])) {
                    $product_stats[$pid] = array('name' => $item->get_name(), 'qty' => 0, 'revenue' => 0, 'cost' => 0);
                }
                $product_stats[$pid]['qty'] += $qty;
                $product_stats[$pid]['revenue'] += $line_total;
                $product_stats[$pid]['cost'] += $line_cost;
            }
        }

        usort($product_stats, function($a, $b) {
            return ($b['revenue'] - $b['cost']) - ($a['revenue'] - $a['cost']);
        });

        return array(
            'orders' => count($orders),
            'items' => $total_items,
            'revenue' => $total_revenue,
            'cost' => $total_cost,
            'profit' => $total_revenue - $total_cost,
            'margin' => $total_revenue > 0 ? round((($total_revenue - $total_cost) / $total_revenue) * 100, 1) : 0,
            'products' => array_slice($product_stats, 0, 20),
        );
    }

    public function render_admin() {
        $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
        if ($days < 1) $days = 30;
        $stats = $this->get_stats($days);
        $sym = get_woocommerce_currency_symbol();
        $profit_color = $stats['profit'] >= 0 ? '#2e7d32' : '#c62828';
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Kar Analizi</h1>
                <p>Satis ve maliyet verilerine dayali kar raporu</p>
            </div>
            <div style="margin-bottom:20px;display:flex;gap:8px;align-items:center;">
                <span style="font-weight:500;">Donem:</span>
                <?php foreach (array(7 => '7 Gun', 30 => '30 Gun', 90 => '90 Gun', 365 => '1 Yil') as $d => $label): ?>
                    <a href="?page=webyaz-profit&days=<?php echo $d; ?>" style="padding:6px 16px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:500;<?php echo $days == $d ? 'background:var(--webyaz-primary,#e53935);color:#fff;' : 'background:#f5f5f5;color:#333;'; ?>"><?php echo $label; ?></a>
                <?php endforeach; ?>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:30px;">
                <div style="background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.06);text-align:center;">
                    <div style="font-size:13px;color:#888;margin-bottom:6px;">Siparis</div>
                    <div style="font-size:28px;font-weight:700;"><?php echo $stats['orders']; ?></div>
                </div>
                <div style="background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.06);text-align:center;">
                    <div style="font-size:13px;color:#888;margin-bottom:6px;">Ciro</div>
                    <div style="font-size:28px;font-weight:700;color:#1565c0;"><?php echo wc_price($stats['revenue']); ?></div>
                </div>
                <div style="background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.06);text-align:center;">
                    <div style="font-size:13px;color:#888;margin-bottom:6px;">Maliyet</div>
                    <div style="font-size:28px;font-weight:700;color:#e65100;"><?php echo wc_price($stats['cost']); ?></div>
                </div>
                <div style="background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.06);text-align:center;">
                    <div style="font-size:13px;color:#888;margin-bottom:6px;">Net Kar</div>
                    <div style="font-size:28px;font-weight:700;color:<?php echo $profit_color; ?>;"><?php echo wc_price($stats['profit']); ?></div>
                </div>
                <div style="background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.06);text-align:center;">
                    <div style="font-size:13px;color:#888;margin-bottom:6px;">Kar Marji</div>
                    <div style="font-size:28px;font-weight:700;color:<?php echo $profit_color; ?>;">%<?php echo $stats['margin']; ?></div>
                </div>
                <div style="background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.06);text-align:center;">
                    <div style="font-size:13px;color:#888;margin-bottom:6px;">Satilan Urun</div>
                    <div style="font-size:28px;font-weight:700;"><?php echo $stats['items']; ?></div>
                </div>
            </div>

            <?php if (!empty($stats['products'])): ?>
            <div class="webyaz-settings-section">
                <h2>Urun Bazli Kar Tablosu (Top 20)</h2>
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <thead>
                        <tr style="background:#f8f9fa;text-align:left;">
                            <th style="padding:12px 16px;font-weight:600;">Urun</th>
                            <th style="padding:12px 16px;font-weight:600;text-align:center;">Adet</th>
                            <th style="padding:12px 16px;font-weight:600;text-align:right;">Ciro</th>
                            <th style="padding:12px 16px;font-weight:600;text-align:right;">Maliyet</th>
                            <th style="padding:12px 16px;font-weight:600;text-align:right;">Kar</th>
                            <th style="padding:12px 16px;font-weight:600;text-align:center;">Marj</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['products'] as $p):
                            $kar = $p['revenue'] - $p['cost'];
                            $marj = $p['revenue'] > 0 ? round(($kar / $p['revenue']) * 100, 1) : 0;
                            $kc = $kar >= 0 ? '#2e7d32' : '#c62828';
                        ?>
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:10px 16px;"><?php echo esc_html($p['name']); ?></td>
                            <td style="padding:10px 16px;text-align:center;"><?php echo $p['qty']; ?></td>
                            <td style="padding:10px 16px;text-align:right;"><?php echo wc_price($p['revenue']); ?></td>
                            <td style="padding:10px 16px;text-align:right;"><?php echo wc_price($p['cost']); ?></td>
                            <td style="padding:10px 16px;text-align:right;font-weight:600;color:<?php echo $kc; ?>;"><?php echo wc_price($kar); ?></td>
                            <td style="padding:10px 16px;text-align:center;font-weight:600;color:<?php echo $kc; ?>;">%<?php echo $marj; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="webyaz-settings-section" style="margin-top:20px;">
                <h2>Bilgi</h2>
                <p style="color:#666;line-height:1.8;">
                    Urun duzenlerken <strong>"Alis Fiyati"</strong> alanina maliyet fiyatini girin.<br>
                    Alis fiyati girilmemis urunlerde maliyet 0 olarak hesaplanir.<br>
                    Urun listesinde <strong>"Alis / Kar"</strong> sutununda hizli gorebilirsiniz.
                </p>
            </div>
        </div>
        <?php
    }
}

new Webyaz_Cost_Price();
