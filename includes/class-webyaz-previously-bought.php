<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Previously_Bought {

    public function __construct() {
        add_action('woocommerce_after_single_product_summary', array($this, 'show_on_product'), 25);
        add_action('woocommerce_before_cart', array($this, 'show_on_cart'));
        add_action('woocommerce_before_checkout_form', array($this, 'show_on_checkout'), 5);
        add_shortcode('webyaz_previously_bought', array($this, 'shortcode'));
        add_action('wp_head', array($this, 'css'));
    }

    private function get_bought_products($limit = 12) {
        if (!is_user_logged_in()) return array();

        $user_id = get_current_user_id();
        $cache_key = 'webyaz_bought_' . $user_id;
        $cached = get_transient($cache_key);
        if ($cached !== false) return array_slice($cached, 0, $limit);

        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'status' => array('completed', 'processing'),
            'limit' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        $product_ids = array();
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $pid = $item->get_product_id();
                if (!in_array($pid, $product_ids) && get_post_status($pid) === 'publish') {
                    $product_ids[] = $pid;
                }
            }
        }

        set_transient($cache_key, $product_ids, 6 * HOUR_IN_SECONDS);
        return array_slice($product_ids, 0, $limit);
    }

    private function render_products($product_ids, $title = 'Daha Once Aldiginiz Urunler') {
        if (empty($product_ids)) return '';

        ob_start();
        ?>
        <div class="webyaz-prev-bought">
            <h2 class="webyaz-prev-title"><?php echo esc_html($title); ?></h2>
            <div class="webyaz-prev-scroll-wrap">
                <button class="webyaz-prev-nav left" type="button" onclick="this.nextElementSibling.scrollBy({left:-260,behavior:'smooth'});">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <div class="webyaz-prev-grid">
                    <?php foreach ($product_ids as $pid):
                        $product = wc_get_product($pid);
                        if (!$product) continue;
                        $image = wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail') ?: wc_placeholder_img_src('woocommerce_thumbnail');
                    ?>
                    <a href="<?php echo esc_url($product->get_permalink()); ?>" class="webyaz-prev-card">
                        <div class="webyaz-prev-img-wrap">
                            <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($product->get_name()); ?>" loading="lazy">
                            <?php if (!$product->is_in_stock()): ?>
                            <span class="webyaz-prev-badge-out">Tukendi</span>
                            <?php endif; ?>
                        </div>
                        <div class="webyaz-prev-info">
                            <h3 class="webyaz-prev-name"><?php echo esc_html($product->get_name()); ?></h3>
                            <div class="webyaz-prev-price"><?php echo $product->get_price_html(); ?></div>
                        </div>
                        <?php if ($product->is_in_stock()): ?>
                        <div class="webyaz-prev-rebuy">Tekrar Satin Al</div>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <button class="webyaz-prev-nav right" type="button" onclick="this.previousElementSibling.scrollBy({left:260,behavior:'smooth'});">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function show_on_product() {
        if (!is_user_logged_in()) return;
        $ids = $this->get_bought_products(8);
        $current = get_the_ID();
        $ids = array_filter($ids, function($id) use ($current) { return $id != $current; });
        echo $this->render_products($ids);
    }

    public function show_on_cart() {
        if (!is_user_logged_in()) return;
        $ids = $this->get_bought_products(8);
        $cart_ids = array();
        foreach (WC()->cart->get_cart() as $item) $cart_ids[] = $item['product_id'];
        $ids = array_filter($ids, function($id) use ($cart_ids) { return !in_array($id, $cart_ids); });
        echo $this->render_products(array_values($ids), 'Daha Once Aldiginiz Urunler - Tekrar Ekleyin');
    }

    public function show_on_checkout() {
        if (!is_user_logged_in()) return;
        $ids = $this->get_bought_products(6);
        echo $this->render_products($ids, 'Gecmis Siparisleriniz');
    }

    public function shortcode($atts) {
        $atts = shortcode_atts(array('limit' => 12, 'title' => 'Daha Once Aldiginiz Urunler'), $atts);
        $ids = $this->get_bought_products(intval($atts['limit']));
        return $this->render_products($ids, $atts['title']);
    }

    public function css() {
        if (is_admin()) return;
        ?>
        <style>
        .webyaz-prev-bought{margin:30px 0;font-family:'Roboto',sans-serif;}
        .webyaz-prev-title{font-size:20px;font-weight:800;color:#1a1a1a;margin-bottom:16px;padding-bottom:10px;border-bottom:3px solid var(--webyaz-primary,#446084);}
        .webyaz-prev-scroll-wrap{position:relative;padding:0 40px;}
        .webyaz-prev-grid{display:flex;gap:16px;overflow-x:auto;scroll-behavior:smooth;scrollbar-width:none;-ms-overflow-style:none;padding:4px 0;}
        .webyaz-prev-grid::-webkit-scrollbar{display:none;}
        .webyaz-prev-nav{position:absolute;top:50%;transform:translateY(-50%);width:36px;height:36px;border-radius:50%;background:#fff;border:2px solid #ddd;box-shadow:0 2px 10px rgba(0,0,0,0.12);cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:5;transition:all 0.2s;padding:0;color:#333;}
        .webyaz-prev-nav:hover{box-shadow:0 4px 16px rgba(0,0,0,0.2);border-color:#999;}
        .webyaz-prev-nav.left{left:0;}
        .webyaz-prev-nav.right{right:0;}
        .webyaz-prev-card{display:flex;flex-direction:column;min-width:200px;max-width:200px;background:#fff;border-radius:14px;overflow:hidden;text-decoration:none;color:#333;box-shadow:0 2px 12px rgba(0,0,0,0.06);border:1px solid rgba(0,0,0,0.05);transition:all 0.25s;flex-shrink:0;}
        .webyaz-prev-card:hover{transform:translateY(-4px);box-shadow:0 8px 24px rgba(0,0,0,0.12);}
        .webyaz-prev-img-wrap{position:relative;width:100%;aspect-ratio:1;overflow:hidden;background:#f8f8f8;}
        .webyaz-prev-img-wrap img{width:100%;height:100%;object-fit:cover;transition:transform 0.3s;}
        .webyaz-prev-card:hover .webyaz-prev-img-wrap img{transform:scale(1.06);}
        .webyaz-prev-badge-out{position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.7);color:#fff;font-size:10px;font-weight:700;padding:3px 8px;border-radius:4px;}
        .webyaz-prev-info{padding:12px 14px 8px;flex:1;}
        .webyaz-prev-name{font-size:13px;font-weight:600;line-height:1.4;margin:0;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
        .webyaz-prev-price{font-size:14px;font-weight:700;color:var(--webyaz-secondary,#d26e4b);margin-top:6px;}
        .webyaz-prev-price del{color:#bbb;font-weight:400;font-size:12px;}
        .webyaz-prev-price ins{text-decoration:none;}
        .webyaz-prev-rebuy{padding:10px 14px;text-align:center;font-size:12px;font-weight:700;color:var(--webyaz-primary,#446084);border-top:1px solid #f0f0f0;transition:all 0.2s;}
        .webyaz-prev-card:hover .webyaz-prev-rebuy{background:var(--webyaz-primary,#446084);color:#fff;}
        @media(max-width:768px){
            .webyaz-prev-scroll-wrap{padding:0;}
            .webyaz-prev-nav{display:none;}
            .webyaz-prev-card{min-width:160px;max-width:160px;}
            .webyaz-prev-title{font-size:16px;}
        }
        </style>
        <?php
    }
}

new Webyaz_Previously_Bought();
