<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Product_Video
{

    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post_product', array($this, 'save_meta'));
        add_action('woocommerce_product_thumbnails', array($this, 'display_video'), 20);
        add_action('woocommerce_single_product_summary', array($this, 'display_video_tab_link'), 35);
        add_filter('woocommerce_product_tabs', array($this, 'add_video_tab'));
        add_action('wp_head', array($this, 'video_css'));
    }

    // --- Meta Box ---
    public function add_meta_box()
    {
        add_meta_box(
            'webyaz_product_video',
            'Urun Videosu',
            array($this, 'meta_box_html'),
            'product',
            'side',
            'default'
        );
    }

    public function meta_box_html($post)
    {
        $video_url = get_post_meta($post->ID, '_webyaz_video_url', true);
        $video_type = get_post_meta($post->ID, '_webyaz_video_type', true);

        $primary = '#446084';
        if (class_exists('Webyaz_Colors')) {
            $c = Webyaz_Colors::get_theme_colors();
            $primary = $c['primary'];
        }

        wp_nonce_field('webyaz_video_nonce', 'webyaz_video_nonce_field');
?>
        <div style="padding:5px 0;">
            <label style="font-size:13px;font-weight:600;color:#333;display:block;margin-bottom:5px;">Video Linki</label>
            <input type="url" name="webyaz_video_url" value="<?php echo esc_attr($video_url); ?>" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;" placeholder="https://youtube.com/watch?v=... veya vimeo.com/...">

            <label style="font-size:13px;font-weight:600;color:#333;display:block;margin:10px 0 5px;">Video Tipi</label>
            <select name="webyaz_video_type" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">
                <option value="youtube" <?php selected($video_type, 'youtube'); ?>>YouTube</option>
                <option value="vimeo" <?php selected($video_type, 'vimeo'); ?>>Vimeo</option>
                <option value="mp4" <?php selected($video_type, 'mp4'); ?>>MP4 (Direkt Link)</option>
            </select>

            <?php if (!empty($video_url)): ?>
                <div style="margin-top:10px;padding:8px;background:#e8f5e9;border-radius:4px;font-size:12px;color:#2e7d32;">
                    ✓ Video eklendi
                </div>
            <?php else: ?>
                <p style="font-size:11px;color:#999;margin-top:8px;">YouTube veya Vimeo linkini yapistirin. Video urun galerisinde ve ozel tabta gosterilecek.</p>
            <?php endif; ?>
        </div>
    <?php
    }

    public function save_meta($post_id)
    {
        if (!isset($_POST['webyaz_video_nonce_field']) || !wp_verify_nonce($_POST['webyaz_video_nonce_field'], 'webyaz_video_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['webyaz_video_url'])) {
            update_post_meta($post_id, '_webyaz_video_url', esc_url_raw($_POST['webyaz_video_url']));
        }
        if (isset($_POST['webyaz_video_type'])) {
            update_post_meta($post_id, '_webyaz_video_type', sanitize_text_field($_POST['webyaz_video_type']));
        }
    }

    // --- Video ID cikar ---
    private static function get_youtube_id($url)
    {
        $pattern = '/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : '';
    }

    private static function get_vimeo_id($url)
    {
        preg_match('/vimeo\.com\/(\d+)/', $url, $matches);
        return isset($matches[1]) ? $matches[1] : '';
    }

    // --- Urun galerisinde video goster ---
    public function display_video()
    {
        global $product;
        if (!$product) return;

        $video_url = get_post_meta($product->get_id(), '_webyaz_video_url', true);
        $video_type = get_post_meta($product->get_id(), '_webyaz_video_type', true);
        if (empty($video_url)) return;

        $primary = '#446084';
        if (class_exists('Webyaz_Colors')) {
            $c = Webyaz_Colors::get_theme_colors();
            $primary = $c['primary'];
        }

        echo '<div class="webyaz-product-video-thumb" style="cursor:pointer;position:relative;border-radius:8px;overflow:hidden;border:2px solid transparent;transition:border-color 0.2s;" onclick="document.getElementById(\'webyazVideoModal\').style.display=\'flex\'">';
        echo '<div style="background:' . $primary . ';color:#fff;padding:8px 12px;text-align:center;font-size:13px;font-weight:700;font-family:Roboto,sans-serif;">';
        echo '<svg viewBox="0 0 24 24" width="16" height="16" fill="#fff" style="vertical-align:middle;margin-right:4px;"><path d="M8 5v14l11-7z"/></svg> Video Izle';
        echo '</div>';
        echo '</div>';

        // Modal
        echo '<div id="webyazVideoModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.85);z-index:99999;align-items:center;justify-content:center;" onclick="if(event.target===this){this.style.display=\'none\';this.querySelector(\'iframe,video\').src=this.querySelector(\'iframe,video\').src;}">';
        echo '<div style="width:90%;max-width:900px;position:relative;">';
        echo '<button onclick="this.parentElement.parentElement.style.display=\'none\'" style="position:absolute;top:-40px;right:0;background:none;border:none;color:#fff;font-size:28px;cursor:pointer;">&times;</button>';

        if ($video_type === 'youtube') {
            $yt_id = self::get_youtube_id($video_url);
            if ($yt_id) {
                echo '<iframe width="100%" height="506" src="https://www.youtube.com/embed/' . esc_attr($yt_id) . '?autoplay=0" frameborder="0" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope" allowfullscreen style="border-radius:12px;"></iframe>';
            }
        } elseif ($video_type === 'vimeo') {
            $vm_id = self::get_vimeo_id($video_url);
            if ($vm_id) {
                echo '<iframe src="https://player.vimeo.com/video/' . esc_attr($vm_id) . '" width="100%" height="506" frameborder="0" allow="autoplay;fullscreen" allowfullscreen style="border-radius:12px;"></iframe>';
            }
        } else {
            echo '<video controls width="100%" style="border-radius:12px;"><source src="' . esc_url($video_url) . '" type="video/mp4"></video>';
        }

        echo '</div></div>';
    }

    // --- Video linki (summary'de) ---
    public function display_video_tab_link()
    {
        global $product;
        if (!$product) return;

        $video_url = get_post_meta($product->get_id(), '_webyaz_video_url', true);
        if (empty($video_url)) return;

        $secondary = '#d26e4b';
        if (class_exists('Webyaz_Colors')) {
            $c = Webyaz_Colors::get_theme_colors();
            $secondary = $c['secondary'];
        }

        echo '<div style="margin:10px 0;">';
        echo '<a href="#" onclick="event.preventDefault();document.getElementById(\'webyazVideoModal\').style.display=\'flex\';" style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;background:' . $secondary . ';color:#fff;border-radius:8px;font-size:14px;font-weight:700;font-family:Roboto,sans-serif;text-decoration:none;transition:opacity 0.2s;" onmouseover="this.style.opacity=0.85" onmouseout="this.style.opacity=1">';
        echo '<svg viewBox="0 0 24 24" width="18" height="18" fill="#fff"><path d="M8 5v14l11-7z"/></svg>';
        echo 'Urun Videosunu Izle';
        echo '</a></div>';
    }

    // --- WooCommerce tab ---
    public function add_video_tab($tabs)
    {
        global $product;
        if (!$product) return $tabs;

        $video_url = get_post_meta($product->get_id(), '_webyaz_video_url', true);
        if (empty($video_url)) return $tabs;

        $tabs['webyaz_video'] = array(
            'title'    => 'Video',
            'priority' => 35,
            'callback' => array($this, 'video_tab_content'),
        );
        return $tabs;
    }

    public function video_tab_content()
    {
        global $product;
        if (!$product) return;

        $video_url = get_post_meta($product->get_id(), '_webyaz_video_url', true);
        $video_type = get_post_meta($product->get_id(), '_webyaz_video_type', true);
        if (empty($video_url)) return;

        echo '<div class="webyaz-video-tab-content" style="max-width:800px;margin:0 auto;">';

        if ($video_type === 'youtube') {
            $yt_id = self::get_youtube_id($video_url);
            if ($yt_id) {
                echo '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:12px;">';
                echo '<iframe width="100%" height="100%" src="https://www.youtube.com/embed/' . esc_attr($yt_id) . '" frameborder="0" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope" allowfullscreen style="position:absolute;top:0;left:0;width:100%;height:100%;border-radius:12px;"></iframe>';
                echo '</div>';
            }
        } elseif ($video_type === 'vimeo') {
            $vm_id = self::get_vimeo_id($video_url);
            if ($vm_id) {
                echo '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:12px;">';
                echo '<iframe src="https://player.vimeo.com/video/' . esc_attr($vm_id) . '" width="100%" height="100%" frameborder="0" allow="autoplay;fullscreen" allowfullscreen style="position:absolute;top:0;left:0;width:100%;height:100%;border-radius:12px;"></iframe>';
                echo '</div>';
            }
        } else {
            echo '<video controls width="100%" style="border-radius:12px;"><source src="' . esc_url($video_url) . '" type="video/mp4"></video>';
        }

        echo '</div>';
    }

    // --- CSS ---
    public function video_css()
    {
        if (!is_product()) return;
        $primary = '#446084';
        if (class_exists('Webyaz_Colors')) {
            $c = Webyaz_Colors::get_theme_colors();
            $primary = $c['primary'];
        }
    ?>
        <style>
            .webyaz-product-video-thumb:hover {
                border-color: <?php echo $primary; ?> !important;
            }

            .woocommerce-tabs .tabs li.webyaz_video_tab a {
                font-weight: 600;
            }

            .woocommerce-tabs .tabs li.webyaz_video_tab.active a {
                color: <?php echo $primary; ?> !important;
            }
        </style>
<?php
    }
}

new Webyaz_Product_Video();
