<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Compare {

    public function __construct() {
        add_action('wp_footer', array($this, 'render_bar'));
        add_action('wp_ajax_webyaz_compare_add', array($this, 'ajax_add'));
        add_action('wp_ajax_nopriv_webyaz_compare_add', array($this, 'ajax_add'));
        add_action('wp_ajax_webyaz_compare_remove', array($this, 'ajax_remove'));
        add_action('wp_ajax_nopriv_webyaz_compare_remove', array($this, 'ajax_remove'));
        add_action('woocommerce_after_shop_loop_item', array($this, 'add_button'), 15);
        add_action('woocommerce_single_product_summary', array($this, 'add_button_single'), 35);
        add_shortcode('webyaz_compare', array($this, 'compare_page_shortcode'));
    }

    private function get_items() {
        if (!isset($_COOKIE['webyaz_compare'])) return array();
        $ids = json_decode(stripslashes($_COOKIE['webyaz_compare']), true);
        if (!is_array($ids)) return array();
        return array_map('intval', array_slice($ids, 0, 4));
    }

    public function add_button() {
        global $product;
        if (!$product) return;
        echo '<a href="#" class="webyaz-compare-btn" data-product-id="' . esc_attr($product->get_id()) . '" title="Karşılaştır"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/></svg></a>';
    }

    public function add_button_single() {
        global $product;
        if (!$product) return;
        echo '<a href="#" class="webyaz-compare-btn-single" data-product-id="' . esc_attr($product->get_id()) . '"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/></svg> Karşılaştır</a>';
    }

    public function ajax_add() {
        $id = intval($_POST['product_id']);
        $items = $this->get_items();
        if (!in_array($id, $items)) {
            if (count($items) >= 4) {
                wp_send_json_error(array('message' => 'En fazla 4 ürün karşılaştırabilirsiniz.'));
            }
            $items[] = $id;
        }
        setcookie('webyaz_compare', wp_json_encode($items), time() + 86400, '/');
        wp_send_json_success(array('count' => count($items), 'items' => $items));
    }

    public function ajax_remove() {
        $id = intval($_POST['product_id']);
        $items = $this->get_items();
        $items = array_values(array_diff($items, array($id)));
        setcookie('webyaz_compare', wp_json_encode($items), time() + 86400, '/');
        wp_send_json_success(array('count' => count($items), 'items' => $items));
    }

    public function render_bar() {
        if (is_admin() || is_checkout()) return;
        $items = $this->get_items();
        ?>
        <div class="webyaz-compare-bar" id="webyazCompareBar" style="<?php echo empty($items) ? 'display:none;' : ''; ?>">
            <div class="webyaz-compare-bar-inner">
                <span class="webyaz-compare-bar-title">Karşılaştır (<span id="webyazCompareCount"><?php echo count($items); ?></span>/4)</span>
                <div class="webyaz-compare-bar-items" id="webyazCompareItems">
                    <?php foreach ($items as $pid):
                        $p = wc_get_product($pid);
                        if (!$p) continue;
                        $thumb = $p->get_image(array(40, 40));
                        ?>
                        <div class="webyaz-compare-bar-item" data-id="<?php echo $pid; ?>">
                            <?php echo $thumb; ?>
                            <button class="webyaz-compare-bar-remove" data-product-id="<?php echo $pid; ?>">&times;</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="<?php echo esc_url(home_url('/urun-karsilastir/')); ?>" class="webyaz-compare-bar-btn">Karşılaştır</a>
                <button class="webyaz-compare-bar-clear" id="webyazCompareClear">Temizle</button>
            </div>
        </div>
        <?php
    }

    public function compare_page_shortcode() {
        $items = $this->get_items();
        if (empty($items)) {
            return '<div class="webyaz-compare-empty"><p>Karşılaştırma listeniz boş. Ürün sayfalarından ürün ekleyebilirsiniz.</p></div>';
        }

        $products = array();
        foreach ($items as $pid) {
            $p = wc_get_product($pid);
            if ($p) $products[] = $p;
        }
        if (empty($products)) {
            return '<p>Ürün bulunamadı.</p>';
        }

        ob_start();
        ?>
        <div class="webyaz-compare-table-wrap">
            <table class="webyaz-compare-table">
                <thead>
                    <tr>
                        <th></th>
                        <?php foreach ($products as $p): ?>
                            <th>
                                <a href="<?php echo esc_url($p->get_permalink()); ?>">
                                    <?php echo $p->get_image(array(150, 150)); ?>
                                    <span><?php echo esc_html($p->get_name()); ?></span>
                                </a>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Fiyat</strong></td>
                        <?php foreach ($products as $p): ?>
                            <td><?php echo $p->get_price_html(); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td><strong>Stok</strong></td>
                        <?php foreach ($products as $p): ?>
                            <td><?php echo $p->is_in_stock() ? '<span style="color:green;">Stokta</span>' : '<span style="color:red;">Tükendi</span>'; ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td><strong>SKU</strong></td>
                        <?php foreach ($products as $p): ?>
                            <td><?php echo esc_html($p->get_sku() ? $p->get_sku() : '-'); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td><strong>Açıklama</strong></td>
                        <?php foreach ($products as $p): ?>
                            <td><?php echo wp_trim_words(wp_strip_all_tags($p->get_short_description()), 20); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php
                    $all_attrs = array();
                    foreach ($products as $p) {
                        $attrs = $p->get_attributes();
                        foreach ($attrs as $key => $attr) {
                            if (!isset($all_attrs[$key])) {
                                $all_attrs[$key] = is_object($attr) && method_exists($attr, 'get_name') ? wc_attribute_label($attr->get_name()) : $key;
                            }
                        }
                    }
                    foreach ($all_attrs as $key => $label):
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($label); ?></strong></td>
                        <?php foreach ($products as $p):
                            $attrs = $p->get_attributes();
                            $val = '-';
                            if (isset($attrs[$key])) {
                                $a = $attrs[$key];
                                if (is_object($a) && method_exists($a, 'get_options')) {
                                    $terms = array();
                                    if ($a->is_taxonomy()) {
                                        foreach ($a->get_terms() as $t) $terms[] = $t->name;
                                    } else {
                                        $terms = $a->get_options();
                                    }
                                    $val = implode(', ', $terms);
                                }
                            }
                        ?>
                            <td><?php echo esc_html($val); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td></td>
                        <?php foreach ($products as $p): ?>
                            <td><a href="<?php echo esc_url($p->get_permalink()); ?>" class="webyaz-compare-add-cart">Ürüne Git</a></td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}

new Webyaz_Compare();
