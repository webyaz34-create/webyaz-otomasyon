<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Dashboard_Stats {

    public function __construct() {
        add_action('admin_menu', array($this, 'modify_dashboard'));
    }

    public function modify_dashboard() {
        add_submenu_page('webyaz-dashboard', 'Istatistikler', 'Istatistikler', 'manage_options', 'webyaz-stats', array($this, 'render'));
    }

    public function render() {
        $today = date('Y-m-d');
        $week_ago = date('Y-m-d', strtotime('-7 days'));
        $month_ago = date('Y-m-d', strtotime('-30 days'));

        $today_orders = $this->get_orders_by_date($today, $today);
        $week_orders = $this->get_orders_by_date($week_ago, $today);
        $month_orders = $this->get_orders_by_date($month_ago, $today);

        $today_revenue = $this->get_revenue_by_date($today, $today);
        $week_revenue = $this->get_revenue_by_date($week_ago, $today);
        $month_revenue = $this->get_revenue_by_date($month_ago, $today);

        $top_products = $this->get_top_products(5);
        $low_stock = $this->get_low_stock(5);
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>Magaza Istatistikleri</h1><p>Satis ozeti ve urun performansi</p></div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
                <div class="webyaz-stat-card">
                    <span class="webyaz-stat-label">Bugun</span>
                    <span class="webyaz-stat-value"><?php echo $today_orders; ?> siparis</span>
                    <span class="webyaz-stat-sub"><?php echo wc_price($today_revenue); ?></span>
                </div>
                <div class="webyaz-stat-card">
                    <span class="webyaz-stat-label">Son 7 Gun</span>
                    <span class="webyaz-stat-value"><?php echo $week_orders; ?> siparis</span>
                    <span class="webyaz-stat-sub"><?php echo wc_price($week_revenue); ?></span>
                </div>
                <div class="webyaz-stat-card">
                    <span class="webyaz-stat-label">Son 30 Gun</span>
                    <span class="webyaz-stat-value"><?php echo $month_orders; ?> siparis</span>
                    <span class="webyaz-stat-sub"><?php echo wc_price($month_revenue); ?></span>
                </div>
                <div class="webyaz-stat-card">
                    <span class="webyaz-stat-label">Toplam Urun</span>
                    <span class="webyaz-stat-value"><?php echo wp_count_posts('product')->publish; ?></span>
                    <span class="webyaz-stat-sub">yayinda</span>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">En Cok Satan 5 Urun</h2>
                    <table style="width:100%;border-collapse:collapse;">
                        <thead><tr><th style="text-align:left;padding:8px;border-bottom:2px solid #eee;">Urun</th><th style="text-align:right;padding:8px;border-bottom:2px solid #eee;">Satis</th></tr></thead>
                        <tbody>
                        <?php if (empty($top_products)): ?>
                            <tr><td colspan="2" style="padding:12px;color:#999;">Henuz veri yok</td></tr>
                        <?php else: foreach ($top_products as $tp): ?>
                            <tr>
                                <td style="padding:8px;border-bottom:1px solid #f0f0f0;"><?php echo esc_html($tp['name']); ?></td>
                                <td style="padding:8px;text-align:right;border-bottom:1px solid #f0f0f0;font-weight:700;"><?php echo $tp['qty']; ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Dusuk Stoklu Urunler</h2>
                    <table style="width:100%;border-collapse:collapse;">
                        <thead><tr><th style="text-align:left;padding:8px;border-bottom:2px solid #eee;">Urun</th><th style="text-align:right;padding:8px;border-bottom:2px solid #eee;">Stok</th></tr></thead>
                        <tbody>
                        <?php if (empty($low_stock)): ?>
                            <tr><td colspan="2" style="padding:12px;color:#999;">Tum urunler yeterli stokta</td></tr>
                        <?php else: foreach ($low_stock as $ls): ?>
                            <tr>
                                <td style="padding:8px;border-bottom:1px solid #f0f0f0;"><?php echo esc_html($ls['name']); ?></td>
                                <td style="padding:8px;text-align:right;border-bottom:1px solid #f0f0f0;font-weight:700;color:<?php echo $ls['stock'] <= 2 ? '#d32f2f' : '#ff9800'; ?>;"><?php echo $ls['stock']; ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    private function get_orders_by_date($from, $to) {
        global $wpdb;
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE date_created_gmt >= %s AND date_created_gmt <= %s AND status IN ('wc-processing','wc-completed','wc-on-hold')", $from . ' 00:00:00', $to . ' 23:59:59'));
        }
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='shop_order' AND post_date >= %s AND post_date <= %s AND post_status IN ('wc-processing','wc-completed','wc-on-hold')", $from . ' 00:00:00', $to . ' 23:59:59'));
    }

    private function get_revenue_by_date($from, $to) {
        global $wpdb;
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            return (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total_amount),0) FROM {$wpdb->prefix}wc_orders WHERE date_created_gmt >= %s AND date_created_gmt <= %s AND status IN ('wc-processing','wc-completed')", $from . ' 00:00:00', $to . ' 23:59:59'));
        }
        return (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(pm.meta_value),0) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key='_order_total' WHERE p.post_type='shop_order' AND p.post_date >= %s AND p.post_date <= %s AND p.post_status IN ('wc-processing','wc-completed')", $from . ' 00:00:00', $to . ' 23:59:59'));
    }

    private function get_top_products($limit = 5) {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare("SELECT oi.order_item_name as name, SUM(oim.meta_value) as qty FROM {$wpdb->prefix}woocommerce_order_items oi JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id=oim.order_item_id AND oim.meta_key='_qty' WHERE oi.order_item_type='line_item' GROUP BY oi.order_item_name ORDER BY qty DESC LIMIT %d", $limit), ARRAY_A);
        return $results ?: array();
    }

    private function get_low_stock($limit = 5) {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare("SELECT p.post_title as name, CAST(pm.meta_value AS UNSIGNED) as stock FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key='_stock' JOIN {$wpdb->postmeta} pm2 ON p.ID=pm2.post_id AND pm2.meta_key='_manage_stock' AND pm2.meta_value='yes' WHERE p.post_type='product' AND p.post_status='publish' AND pm.meta_value IS NOT NULL AND CAST(pm.meta_value AS SIGNED) > 0 AND CAST(pm.meta_value AS SIGNED) <= 10 ORDER BY stock ASC LIMIT %d", $limit), ARRAY_A);
        return $results ?: array();
    }
}

new Webyaz_Dashboard_Stats();
