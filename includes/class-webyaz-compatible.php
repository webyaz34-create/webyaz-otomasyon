<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Compatible {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post_product', array($this, 'save_meta'));
        add_action('woocommerce_after_single_product_summary', array($this, 'show_compatible'), 15);
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_ajax_webyaz_search_products', array($this, 'ajax_search'));
    }

    // Admin: AJAX urun arama
    public function ajax_search() {
        check_ajax_referer('webyaz_nonce', 'nonce');
        $term = sanitize_text_field($_GET['term'] ?? '');
        if (strlen($term) < 2) wp_send_json(array());

        $products = wc_get_products(array(
            's'      => $term,
            'limit'  => 10,
            'status' => 'publish',
        ));

        $results = array();
        foreach ($products as $p) {
            $results[] = array('id' => $p->get_id(), 'text' => $p->get_name() . ' (#' . $p->get_id() . ')');
        }
        wp_send_json($results);
    }

    // Admin scripts
    public function admin_scripts($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'))) return;
        global $post;
        if (!$post || $post->post_type !== 'product') return;
        wp_enqueue_script('jquery-ui-autocomplete');
    }

    // Meta box
    public function add_meta_box() {
        add_meta_box('webyaz_compatible', '🔗 Uyumlu / Aksesuar Urunler', array($this, 'render_meta_box'), 'product', 'side', 'default');
    }

    // Meta box ici
    public function render_meta_box($post) {
        wp_nonce_field('webyaz_compatible_save', '_webyaz_compatible_nonce');
        $ids = get_post_meta($post->ID, '_webyaz_compatible_ids', true);
        $ids = is_array($ids) ? $ids : array();
        ?>
        <div id="wzCompatWrap">
            <input type="text" id="wzCompatSearch" placeholder="Urun adi yazin..." style="width:100%;padding:6px 10px;border:1px solid #ddd;border-radius:6px;margin-bottom:8px;font-size:13px;">
            <div id="wzCompatList" style="max-height:200px;overflow-y:auto;">
                <?php foreach ($ids as $pid):
                    $p = wc_get_product($pid);
                    if (!$p) continue;
                ?>
                <div class="wz-compat-item" data-id="<?php echo esc_attr($pid); ?>" style="display:flex;align-items:center;gap:6px;padding:4px 8px;background:#f5f5f5;border-radius:6px;margin-bottom:4px;font-size:12px;">
                    <span style="flex:1;"><?php echo esc_html($p->get_name()); ?></span>
                    <input type="hidden" name="webyaz_compatible_ids[]" value="<?php echo esc_attr($pid); ?>">
                    <button type="button" onclick="this.closest('.wz-compat-item').remove();" style="background:none;border:none;color:#e53935;cursor:pointer;font-size:14px;">✕</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <script>
        jQuery(function($){
            var $input = $('#wzCompatSearch');
            $input.autocomplete({
                source: function(request, response) {
                    $.get(ajaxurl, {action:'webyaz_search_products', term:request.term, nonce:'<?php echo wp_create_nonce('webyaz_nonce'); ?>'}, function(data) {
                        response($.map(data, function(item){ return {label:item.text, value:item.id}; }));
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    event.preventDefault();
                    $input.val('');
                    // Tekrari engelle
                    if ($('#wzCompatList .wz-compat-item[data-id="'+ui.item.value+'"]').length) return;
                    var html = '<div class="wz-compat-item" data-id="'+ui.item.value+'" style="display:flex;align-items:center;gap:6px;padding:4px 8px;background:#f5f5f5;border-radius:6px;margin-bottom:4px;font-size:12px;">';
                    html += '<span style="flex:1;">'+ui.item.label+'</span>';
                    html += '<input type="hidden" name="webyaz_compatible_ids[]" value="'+ui.item.value+'">';
                    html += '<button type="button" onclick="this.closest(\'.wz-compat-item\').remove();" style="background:none;border:none;color:#e53935;cursor:pointer;font-size:14px;">✕</button>';
                    html += '</div>';
                    $('#wzCompatList').append(html);
                }
            });
        });
        </script>
        <?php
    }

    // Kaydet
    public function save_meta($post_id) {
        if (!isset($_POST['_webyaz_compatible_nonce']) || !wp_verify_nonce($_POST['_webyaz_compatible_nonce'], 'webyaz_compatible_save')) return;
        $ids = isset($_POST['webyaz_compatible_ids']) ? array_map('absint', $_POST['webyaz_compatible_ids']) : array();
        update_post_meta($post_id, '_webyaz_compatible_ids', $ids);
    }

    // Frontend: Uyumlu urunleri goster
    public function show_compatible() {
        global $product;
        if (!$product) return;

        $ids = get_post_meta($product->get_id(), '_webyaz_compatible_ids', true);
        if (!is_array($ids) || empty($ids)) return;

        // Gecerli urunleri al
        $products = array();
        foreach ($ids as $pid) {
            $p = wc_get_product($pid);
            if ($p && $p->is_visible()) $products[] = $p;
        }
        if (empty($products)) return;

        echo '<div style="margin:30px 0;">';
        echo '<h3 style="font-size:18px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px;">🔗 Uyumlu Urunler & Aksesuarlar</h3>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;">';

        foreach ($products as $p) {
            $img = $p->get_image('woocommerce_thumbnail', array('style' => 'width:100%;height:180px;object-fit:cover;border-radius:8px;'));
            $price = $p->get_price_html();
            $link = get_permalink($p->get_id());

            echo '<a href="' . esc_url($link) . '" style="text-decoration:none;background:#fff;border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;transition:0.3s;display:block;" onmouseover="this.style.boxShadow=\'0 4px 12px rgba(0,0,0,0.1)\'" onmouseout="this.style.boxShadow=\'none\'">';
            echo '<div style="overflow:hidden;">' . $img . '</div>';
            echo '<div style="padding:10px 12px;">';
            echo '<div style="font-size:13px;font-weight:600;color:#333;margin-bottom:4px;line-height:1.3;">' . esc_html($p->get_name()) . '</div>';
            echo '<div style="font-size:14px;font-weight:700;color:#e65100;">' . $price . '</div>';
            echo '</div>';
            echo '</a>';
        }

        echo '</div>';
        echo '</div>';
    }
}

new Webyaz_Compatible();
