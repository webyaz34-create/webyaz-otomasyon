<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Recently_Viewed {

    public function __construct() {
        add_action('template_redirect', array($this, 'track_product'));
        add_action('woocommerce_after_single_product_summary', array($this, 'display_recently_viewed'), 25);
        add_shortcode('webyaz_recently_viewed', array($this, 'shortcode'));
    }

    public function track_product() {
        if (!is_singular('product')) return;
        global $post;
        $viewed = isset($_COOKIE['webyaz_recently_viewed']) ? json_decode(stripslashes($_COOKIE['webyaz_recently_viewed']), true) : array();
        if (!is_array($viewed)) $viewed = array();
        $viewed = array_diff($viewed, array($post->ID));
        array_unshift($viewed, $post->ID);
        $viewed = array_slice($viewed, 0, 12);
        setcookie('webyaz_recently_viewed', wp_json_encode($viewed), time() + 2592000, '/');
    }

    public function display_recently_viewed() {
        global $post;
        $viewed = isset($_COOKIE['webyaz_recently_viewed']) ? json_decode(stripslashes($_COOKIE['webyaz_recently_viewed']), true) : array();
        if (!is_array($viewed)) return;
        $viewed = array_diff($viewed, array($post->ID));
        $viewed = array_slice($viewed, 0, 6);
        if (count($viewed) < 2) return;
        echo $this->render_products($viewed, 'Son Goruntulediginiz Urunler');
    }

    public function shortcode($atts) {
        $atts = shortcode_atts(array('limit' => 8, 'title' => 'Son Goruntulediginiz Urunler'), $atts);
        $viewed = isset($_COOKIE['webyaz_recently_viewed']) ? json_decode(stripslashes($_COOKIE['webyaz_recently_viewed']), true) : array();
        if (!is_array($viewed) || empty($viewed)) {
            return '<p>Henuz urun goruntulemediniz.</p>';
        }
        $viewed = array_slice($viewed, 0, intval($atts['limit']));
        return $this->render_products($viewed, $atts['title']);
    }

    private function render_products($ids, $title) {
        if (empty($ids)) return '';
        ob_start();
        ?>
        <div class="webyaz-recently-viewed">
            <h3><?php echo esc_html($title); ?></h3>
            <div class="webyaz-rv-grid">
                <?php foreach ($ids as $pid):
                    $p = wc_get_product($pid);
                    if (!$p || $p->get_status() !== 'publish') continue;
                ?>
                <div class="webyaz-rv-item">
                    <a href="<?php echo esc_url($p->get_permalink()); ?>">
                        <?php echo $p->get_image(array(200, 200)); ?>
                        <h4><?php echo esc_html($p->get_name()); ?></h4>
                        <span class="webyaz-rv-price"><?php echo $p->get_price_html(); ?></span>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

new Webyaz_Recently_Viewed();
