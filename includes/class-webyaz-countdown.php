<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Countdown {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post_product', array($this, 'save_meta'));
        add_action('woocommerce_single_product_summary', array($this, 'display_countdown'), 11);
    }

    public function add_meta_box() {
        add_meta_box('webyaz_countdown', 'Webyaz Kampanya Zamanlayici', array($this, 'meta_html'), 'product', 'side');
    }

    public function meta_html($post) {
        wp_nonce_field('webyaz_countdown_n', 'webyaz_countdown_nonce');
        $active = get_post_meta($post->ID, '_webyaz_countdown_active', true);
        $end = get_post_meta($post->ID, '_webyaz_countdown_end', true);
        $text = get_post_meta($post->ID, '_webyaz_countdown_text', true);
        if (empty($text)) $text = 'Kampanya bitis suresi:';
        ?>
        <div style="font-family:'Roboto',sans-serif;">
            <label style="display:flex;align-items:center;gap:8px;margin-bottom:10px;cursor:pointer;">
                <input type="checkbox" name="webyaz_countdown_active" value="1" <?php checked($active, '1'); ?>>
                <strong>Geri Sayimi Aktif Et</strong>
            </label>
            <label style="display:block;margin-bottom:6px;font-size:12px;">Bitis Tarihi & Saati:</label>
            <input type="datetime-local" name="webyaz_countdown_end" value="<?php echo esc_attr($end); ?>" style="width:100%;margin-bottom:8px;">
            <label style="display:block;margin-bottom:6px;font-size:12px;">Baslik Metni:</label>
            <input type="text" name="webyaz_countdown_text" value="<?php echo esc_attr($text); ?>" style="width:100%;">
        </div>
        <?php
    }

    public function save_meta($post_id) {
        if (!isset($_POST['webyaz_countdown_nonce']) || !wp_verify_nonce($_POST['webyaz_countdown_nonce'], 'webyaz_countdown_n')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        update_post_meta($post_id, '_webyaz_countdown_active', isset($_POST['webyaz_countdown_active']) ? '1' : '0');
        update_post_meta($post_id, '_webyaz_countdown_end', sanitize_text_field($_POST['webyaz_countdown_end'] ?? ''));
        update_post_meta($post_id, '_webyaz_countdown_text', sanitize_text_field($_POST['webyaz_countdown_text'] ?? ''));
    }

    public function display_countdown() {
        global $product;
        if (!$product) return;
        $pid = $product->get_id();
        if (get_post_meta($pid, '_webyaz_countdown_active', true) !== '1') return;
        $end = get_post_meta($pid, '_webyaz_countdown_end', true);
        if (empty($end)) return;
        $text = get_post_meta($pid, '_webyaz_countdown_text', true);
        if (empty($text)) $text = 'Kampanya bitis suresi:';
        ?>
        <div class="webyaz-countdown" id="webyazCountdown" data-end="<?php echo esc_attr($end); ?>">
            <div class="webyaz-countdown-label"><?php echo esc_html($text); ?></div>
            <div class="webyaz-countdown-boxes">
                <div class="webyaz-cd-box"><span id="wcdDays">00</span><small>Gun</small></div>
                <div class="webyaz-cd-box"><span id="wcdHours">00</span><small>Saat</small></div>
                <div class="webyaz-cd-box"><span id="wcdMins">00</span><small>Dk</small></div>
                <div class="webyaz-cd-box"><span id="wcdSecs">00</span><small>Sn</small></div>
            </div>
        </div>
        <script>
        (function(){
            var el=document.getElementById('webyazCountdown');
            if(!el)return;
            var end=new Date(el.dataset.end).getTime();
            function tick(){
                var now=Date.now(),d=end-now;
                if(d<=0){el.innerHTML='<div class="webyaz-countdown-label" style="color:#d32f2f;">Kampanya sona erdi!</div>';return;}
                document.getElementById('wcdDays').textContent=String(Math.floor(d/86400000)).padStart(2,'0');
                document.getElementById('wcdHours').textContent=String(Math.floor((d%86400000)/3600000)).padStart(2,'0');
                document.getElementById('wcdMins').textContent=String(Math.floor((d%3600000)/60000)).padStart(2,'0');
                document.getElementById('wcdSecs').textContent=String(Math.floor((d%60000)/1000)).padStart(2,'0');
            }
            tick();setInterval(tick,1000);
        })();
        </script>
        <?php
    }
}

new Webyaz_Countdown();
