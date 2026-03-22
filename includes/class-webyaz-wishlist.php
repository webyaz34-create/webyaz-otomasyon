<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Wishlist {

    public function __construct() {
        add_action('wp_ajax_webyaz_wishlist_toggle', array($this, 'ajax_toggle'));
        add_action('wp_ajax_nopriv_webyaz_wishlist_toggle', array($this, 'ajax_toggle'));
        add_action('woocommerce_after_shop_loop_item', array($this, 'add_button'), 12);
        add_action('woocommerce_single_product_summary', array($this, 'add_button_single'), 32);
        add_shortcode('webyaz_wishlist', array($this, 'wishlist_page_shortcode'));
    }

    private function get_items() {
        if (is_user_logged_in()) {
            $items = get_user_meta(get_current_user_id(), '_webyaz_wishlist', true);
            return is_array($items) ? $items : array();
        }
        if (!isset($_COOKIE['webyaz_wishlist'])) return array();
        $ids = json_decode(stripslashes($_COOKIE['webyaz_wishlist']), true);
        return is_array($ids) ? array_map('intval', $ids) : array();
    }

    private function save_items($items) {
        $items = array_values(array_unique(array_map('intval', $items)));
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), '_webyaz_wishlist', $items);
        }
        setcookie('webyaz_wishlist', wp_json_encode($items), time() + 2592000, '/');
    }

    public function add_button() {
        global $product;
        if (!$product) return;
        $items = $this->get_items();
        $active = in_array($product->get_id(), $items) ? ' active' : '';
        echo '<a href="#" class="webyaz-wishlist-btn' . $active . '" data-product-id="' . esc_attr($product->get_id()) . '" title="Favorilere Ekle"><svg width="18" height="18" viewBox="0 0 24 24" fill="' . ($active ? 'currentColor' : 'none') . '" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></a>';
    }

    public function add_button_single() {
        global $product;
        if (!$product) return;
        $items = $this->get_items();
        $active = in_array($product->get_id(), $items) ? ' active' : '';
        echo '<a href="#" class="webyaz-wishlist-btn-single' . $active . '" data-product-id="' . esc_attr($product->get_id()) . '"><svg width="16" height="16" viewBox="0 0 24 24" fill="' . ($active ? 'currentColor' : 'none') . '" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg> Favorilere Ekle</a>';
    }

    public function ajax_toggle() {
        $id = intval($_POST['product_id']);
        $items = $this->get_items();
        if (in_array($id, $items)) {
            $items = array_values(array_diff($items, array($id)));
            $added = false;
        } else {
            $items[] = $id;
            $added = true;
        }
        $this->save_items($items);
        wp_send_json_success(array('added' => $added, 'count' => count($items)));
    }

    public function wishlist_page_shortcode() {
        $items = $this->get_items();
        if (empty($items)) {
            return '<div class="webyaz-wishlist-empty"><p>Favori listeniz boş.</p><a href="' . esc_url(wc_get_page_permalink('shop')) . '" class="webyaz-btn">Alışverişe Başla</a></div>';
        }

        ob_start();
        ?>
        <div class="webyaz-wishlist-grid">
            <?php foreach ($items as $pid):
                $p = wc_get_product($pid);
                if (!$p) continue;
            ?>
            <div class="webyaz-wishlist-item">
                <a href="#" class="webyaz-wishlist-remove" data-product-id="<?php echo $pid; ?>">&times;</a>
                <a href="<?php echo esc_url($p->get_permalink()); ?>">
                    <?php echo $p->get_image(array(200, 200)); ?>
                    <h4><?php echo esc_html($p->get_name()); ?></h4>
                </a>
                <div class="webyaz-wishlist-price"><?php echo $p->get_price_html(); ?></div>
                <?php if ($p->is_in_stock()): ?>
                    <a href="<?php echo esc_url($p->add_to_cart_url()); ?>" class="webyaz-btn webyaz-wishlist-cart">Sepete Ekle</a>
                <?php else: ?>
                    <span class="webyaz-out-of-stock">Tükendi</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

new Webyaz_Wishlist();
