<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Story_Menu
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_head', array($this, 'front_css'));
        add_action('loop_start', array($this, 'front_html_hook'));
        add_shortcode('webyaz_stories', array($this, 'shortcode'));
        add_action('wp_ajax_webyaz_story_click', array($this, 'ajax_click'));
        add_action('wp_ajax_nopriv_webyaz_story_click', array($this, 'ajax_click'));
    }

    public function front_html_hook($query)
    {
        if (!is_main_query() || is_admin()) return;
        if (did_action('webyaz_stories_rendered')) return;

        $s = self::get_settings();
        if ($s['active'] !== '1') return;
        if ($s['position'] === 'shortcode') return;

        $show = false;
        if ($s['position'] === 'everywhere') $show = true;
        elseif ($s['position'] === 'home_top' && is_front_page()) $show = true;
        elseif ($s['position'] === 'shop_top' && function_exists('is_shop') && (is_shop() || is_product_category())) $show = true;
        if (!$show) return;

        do_action('webyaz_stories_rendered');
        echo $this->render_stories();
    }

    public function register_settings()
    {
        register_setting('webyaz_story_group', 'webyaz_stories');
        register_setting('webyaz_story_group', 'webyaz_story_settings');
    }

    private static function get_settings()
    {
        return wp_parse_args(get_option('webyaz_story_settings', array()), array(
            'active' => '1',
            'position' => 'home_top',
            'border_style' => 'gradient',
            'show_title' => '1',
            'size' => '80',
            'max_items' => '10',
            'nav_bg' => '#ffffff',
            'nav_arrow' => '#333333',
            'section_bg' => '#ffffff',
            'popup_mode' => '1',
            'autoplay' => '0',
            'autoplay_speed' => '3000',
            'mobile_items' => '6',
            'mobile_fullwidth' => '1',
        ));
    }

    private static function get_stories()
    {
        $stories = get_option('webyaz_stories', array());
        if (!is_array($stories)) return array();
        usort($stories, function ($a, $b) {
            return intval($a['order'] ?? 0) - intval($b['order'] ?? 0);
        });
        return $stories;
    }

    public function admin_scripts($hook)
    {
        if (strpos($hook, 'webyaz-story') === false) return;
        wp_enqueue_media();
    }

    public function add_submenu()
    {
        add_submenu_page('webyaz-dashboard', 'Story Menu', 'Story Menu', 'manage_options', 'webyaz-story', array($this, 'render_admin'));
    }

    /* ===== AJAX: Click Counter ===== */
    public function ajax_click()
    {
        $idx = intval($_POST['story_index'] ?? -1);
        if ($idx < 0) wp_die('invalid');
        $clicks = get_option('webyaz_story_clicks', array());
        if (!is_array($clicks)) $clicks = array();
        $clicks[$idx] = intval($clicks[$idx] ?? 0) + 1;
        update_option('webyaz_story_clicks', $clicks);
        wp_send_json_success($clicks[$idx]);
    }

    /* ===== ADMIN PANEL ===== */
    public function render_admin()
    {
        if (isset($_POST['webyaz_story_save']) && wp_verify_nonce($_POST['_wpnonce_story'], 'webyaz_story_nonce')) {
            $settings = array(
                'active' => sanitize_text_field($_POST['story_active'] ?? '0'),
                'position' => sanitize_text_field($_POST['story_position'] ?? 'home_top'),
                'border_style' => sanitize_text_field($_POST['story_border'] ?? 'gradient'),
                'show_title' => sanitize_text_field($_POST['story_show_title'] ?? '0'),
                'size' => intval($_POST['story_size'] ?? 80),
                'max_items' => intval($_POST['story_max'] ?? 10),
                'nav_bg' => sanitize_hex_color($_POST['story_nav_bg'] ?? '#ffffff'),
                'nav_arrow' => sanitize_hex_color($_POST['story_nav_arrow'] ?? '#333333'),
                'section_bg' => sanitize_hex_color($_POST['story_section_bg'] ?? '#ffffff'),
                'popup_mode' => sanitize_text_field($_POST['story_popup'] ?? '1'),
                'autoplay' => sanitize_text_field($_POST['story_autoplay'] ?? '0'),
                'autoplay_speed' => intval($_POST['story_autoplay_speed'] ?? 3000),
                'mobile_items' => intval($_POST['story_mobile_items'] ?? 6),
                'mobile_fullwidth' => sanitize_text_field($_POST['story_mobile_fullwidth'] ?? '1'),
            );
            update_option('webyaz_story_settings', $settings);

            $stories = array();
            if (isset($_POST['story_title']) && is_array($_POST['story_title'])) {
                foreach ($_POST['story_title'] as $i => $title) {
                    if (empty($title) && empty($_POST['story_image'][$i])) continue;
                    $sub_images = array();
                    if (!empty($_POST['story_sub_images'][$i])) {
                        $sub_images = array_filter(array_map('esc_url_raw', explode(',', $_POST['story_sub_images'][$i])));
                    }
                    $stories[] = array(
                        'title' => sanitize_text_field($title),
                        'image' => esc_url_raw($_POST['story_image'][$i] ?? ''),
                        'link' => esc_url_raw($_POST['story_link'][$i] ?? ''),
                        'color' => sanitize_hex_color($_POST['story_color'][$i] ?? ''),
                        'order' => intval($_POST['story_order'][$i] ?? $i),
                        'is_new' => sanitize_text_field($_POST['story_is_new'][$i] ?? '0'),
                        'sub_images' => $sub_images,
                        'date_start' => sanitize_text_field($_POST['story_date_start'][$i] ?? ''),
                        'date_end' => sanitize_text_field($_POST['story_date_end'][$i] ?? ''),
                    );
                }
            }
            update_option('webyaz_stories', $stories);
            echo '<div class="webyaz-notice success">Kaydedildi!</div>';
        }

        $s = self::get_settings();
        $stories = self::get_stories();
        $clicks = get_option('webyaz_story_clicks', array());
        if (!is_array($clicks)) $clicks = array();
        $primary = '#446084';
        $secondary = '#d26e4b';
        if (class_exists('Webyaz_Colors')) {
            $c = Webyaz_Colors::get_theme_colors();
            $primary = $c['primary'];
            $secondary = $c['secondary'];
        }
?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Story Menu</h1>
                <p>Instagram tarzi kategori/hikaye menusu</p>
            </div>
            <form method="post">
                <?php wp_nonce_field('webyaz_story_nonce', '_wpnonce_story'); ?>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Genel Ayarlar</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Durum</label>
                            <select name="story_active">
                                <option value="1" <?php selected($s['active'], '1'); ?>>Aktif</option>
                                <option value="0" <?php selected($s['active'], '0'); ?>>Kapali</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Konum</label>
                            <select name="story_position">
                                <option value="home_top" <?php selected($s['position'], 'home_top'); ?>>Ana Sayfa Ust</option>
                                <option value="shop_top" <?php selected($s['position'], 'shop_top'); ?>>Magaza Ust</option>
                                <option value="everywhere" <?php selected($s['position'], 'everywhere'); ?>>Her Yerde</option>
                                <option value="shortcode" <?php selected($s['position'], 'shortcode'); ?>>Sadece Shortcode</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Cerceve Stili</label>
                            <select name="story_border">
                                <option value="gradient" <?php selected($s['border_style'], 'gradient'); ?>>Gradient (Instagram)</option>
                                <option value="solid" <?php selected($s['border_style'], 'solid'); ?>>Duz Renk</option>
                                <option value="none" <?php selected($s['border_style'], 'none'); ?>>Cercevesiz</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Baslik Goster</label>
                            <select name="story_show_title">
                                <option value="1" <?php selected($s['show_title'], '1'); ?>>Evet</option>
                                <option value="0" <?php selected($s['show_title'], '0'); ?>>Hayir</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Popup Modu</label>
                            <select name="story_popup">
                                <option value="1" <?php selected($s['popup_mode'], '1'); ?>>Evet (Instagram)</option>
                                <option value="0" <?php selected($s['popup_mode'], '0'); ?>>Hayir (Link)</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Otomatik Kaydir</label>
                            <select name="story_autoplay">
                                <option value="0" <?php selected($s['autoplay'], '0'); ?>>Kapali</option>
                                <option value="1" <?php selected($s['autoplay'], '1'); ?>>Aktif</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Kaydir Hizi (ms)</label>
                            <input type="number" name="story_autoplay_speed" value="<?php echo esc_attr($s['autoplay_speed']); ?>" min="1000" max="10000" step="500">
                        </div>
                        <div class="webyaz-field">
                            <label>Boyut (px)</label>
                            <input type="number" name="story_size" value="<?php echo esc_attr($s['size']); ?>" min="50" max="150">
                        </div>
                        <div class="webyaz-field">
                            <label>Maks. Gosterim</label>
                            <input type="number" name="story_max" value="<?php echo esc_attr($s['max_items']); ?>" min="3" max="30">
                        </div>
                        <div class="webyaz-field">
                            <label>Mobil Gosterim</label>
                            <input type="number" name="story_mobile_items" value="<?php echo esc_attr($s['mobile_items']); ?>" min="2" max="20">
                        </div>
                        <div class="webyaz-field">
                            <label>Mobil Tam Genislik</label>
                            <select name="story_mobile_fullwidth">
                                <option value="1" <?php selected($s['mobile_fullwidth'], '1'); ?>>Evet</option>
                                <option value="0" <?php selected($s['mobile_fullwidth'], '0'); ?>>Hayir</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Ok Arka Plan</label>
                            <input type="color" name="story_nav_bg" value="<?php echo esc_attr($s['nav_bg']); ?>" style="width:50px;height:36px;border:1px solid #ddd;border-radius:6px;cursor:pointer;padding:2px;">
                        </div>
                        <div class="webyaz-field">
                            <label>Ok Rengi</label>
                            <input type="color" name="story_nav_arrow" value="<?php echo esc_attr($s['nav_arrow']); ?>" style="width:50px;height:36px;border:1px solid #ddd;border-radius:6px;cursor:pointer;padding:2px;">
                        </div>
                        <div class="webyaz-field">
                            <label>Bolum Arka Plan</label>
                            <input type="color" name="story_section_bg" value="<?php echo esc_attr($s['section_bg']); ?>" style="width:50px;height:36px;border:1px solid #ddd;border-radius:6px;cursor:pointer;padding:2px;">
                        </div>
                    </div>
                    <p style="margin-top:10px;font-size:12px;color:#888;">Shortcode: <code>[webyaz_stories]</code></p>
                </div>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Story Ogeleri</h2>
                    <p style="font-size:12px;color:#888;margin-bottom:8px;">Siralamak icin story satirlarini surukleyip birakin.</p>
                    <div id="webyazStoryList">
                        <?php if (!empty($stories)): foreach ($stories as $i => $st):
                                $click_count = intval($clicks[$i] ?? 0);
                                $sub_imgs = !empty($st['sub_images']) && is_array($st['sub_images']) ? implode(',', $st['sub_images']) : '';
                        ?>
                                <div class="webyaz-story-item" draggable="true" style="display:flex;gap:8px;align-items:center;padding:12px;background:#f9f9f9;border-radius:10px;margin-bottom:10px;flex-wrap:wrap;cursor:grab;">
                                    <span style="cursor:grab;font-size:16px;color:#aaa;" title="Surukle">&#9776;</span>
                                    <input type="hidden" name="story_order[<?php echo $i; ?>]" value="<?php echo esc_attr($st['order']); ?>">
                                    <div style="width:50px;height:50px;border-radius:50%;overflow:hidden;border:2px solid #ddd;flex-shrink:0;cursor:pointer;background:#eee url('<?php echo esc_url($st['image']); ?>') center/cover;" class="webyaz-story-img-preview" onclick="webyazStoryPickImage(this)"></div>
                                    <input type="hidden" name="story_image[<?php echo $i; ?>]" value="<?php echo esc_url($st['image']); ?>" class="webyaz-story-img-input">
                                    <input type="text" name="story_title[<?php echo $i; ?>]" value="<?php echo esc_attr($st['title']); ?>" placeholder="Baslik" style="flex:1;min-width:100px;padding:6px 10px;border:1px solid #ddd;border-radius:6px;">
                                    <input type="url" name="story_link[<?php echo $i; ?>]" value="<?php echo esc_url($st['link']); ?>" placeholder="Link" style="flex:1;min-width:100px;padding:6px 10px;border:1px solid #ddd;border-radius:6px;">
                                    <label style="display:flex;flex-direction:column;align-items:center;gap:2px;font-size:9px;color:#888;">Cerceve<input type="color" name="story_color[<?php echo $i; ?>]" value="<?php echo esc_attr($st['color'] ?? '#446084'); ?>" style="width:30px;height:30px;border:none;border-radius:4px;cursor:pointer;padding:0;"></label>
                                    <label style="display:flex;align-items:center;gap:4px;font-size:10px;color:#888;"><input type="checkbox" name="story_is_new[<?php echo $i; ?>]" value="1" <?php checked($st['is_new'] ?? '0', '1'); ?>> Yeni</label>
                                    <input type="hidden" name="story_sub_images[<?php echo $i; ?>]" value="<?php echo esc_attr($sub_imgs); ?>" class="webyaz-sub-images-input">
                                    <button type="button" onclick="webyazStoryAddSub(this);" style="background:#9c27b0;color:#fff;border:none;border-radius:4px;padding:4px 8px;cursor:pointer;font-size:10px;" title="Alt Resim Ekle">+Resim</button>
                                    <input type="datetime-local" name="story_date_start[<?php echo $i; ?>]" value="<?php echo esc_attr($st['date_start'] ?? ''); ?>" style="padding:4px 6px;border:1px solid #ddd;border-radius:4px;font-size:10px;" title="Baslangic">
                                    <input type="datetime-local" name="story_date_end[<?php echo $i; ?>]" value="<?php echo esc_attr($st['date_end'] ?? ''); ?>" style="padding:4px 6px;border:1px solid #ddd;border-radius:4px;font-size:10px;" title="Bitis">
                                    <span style="font-size:10px;color:#4caf50;font-weight:600;" title="Tiklanma"><?php echo $click_count; ?> tik</span>
                                    <button type="button" onclick="webyazStoryClone(this);" style="background:#2196f3;color:#fff;border:none;border-radius:4px;padding:4px 8px;cursor:pointer;font-size:11px;" title="Kopyala">&#x2398;</button>
                                    <button type="button" onclick="this.closest('.webyaz-story-item').remove();" style="background:#f44336;color:#fff;border:none;border-radius:4px;padding:4px 8px;cursor:pointer;font-weight:700;">&times;</button>
                                    <?php if (!empty($st['sub_images']) && is_array($st['sub_images'])): ?>
                                        <div style="width:100%;display:flex;gap:4px;flex-wrap:wrap;margin-top:4px;">
                                            <?php foreach ($st['sub_images'] as $si): ?>
                                                <div style="width:36px;height:36px;border-radius:6px;background:url('<?php echo esc_url($si); ?>') center/cover;border:1px solid #ddd;"></div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>
                    <button type="button" id="webyazStoryAdd" class="webyaz-btn webyaz-btn-outline" style="margin-top:10px;">+ Yeni Story Ekle</button>
                </div>

                <button type="submit" name="webyaz_story_save" value="1" class="webyaz-btn webyaz-btn-primary" style="padding:12px 28px;font-size:14px;">Kaydet</button>
            </form>
        </div>
        <script>
            var webyazStoryIdx = <?php echo max(count($stories), 0); ?>;

            /* ===== Add New Story ===== */
            document.getElementById('webyazStoryAdd').addEventListener('click', function() {
                var html = '<div class="webyaz-story-item" draggable="true" style="display:flex;gap:8px;align-items:center;padding:12px;background:#f9f9f9;border-radius:10px;margin-bottom:10px;flex-wrap:wrap;cursor:grab;">';
                html += '<span style="cursor:grab;font-size:16px;color:#aaa;" title="Surukle">&#9776;</span>';
                html += '<input type="hidden" name="story_order[' + webyazStoryIdx + ']" value="' + webyazStoryIdx + '">';
                html += '<div style="width:50px;height:50px;border-radius:50%;overflow:hidden;border:2px solid #ddd;flex-shrink:0;cursor:pointer;background:#eee;" class="webyaz-story-img-preview" onclick="webyazStoryPickImage(this)"></div>';
                html += '<input type="hidden" name="story_image[' + webyazStoryIdx + ']" value="" class="webyaz-story-img-input">';
                html += '<input type="text" name="story_title[' + webyazStoryIdx + ']" value="" placeholder="Baslik" style="flex:1;min-width:100px;padding:6px 10px;border:1px solid #ddd;border-radius:6px;">';
                html += '<input type="url" name="story_link[' + webyazStoryIdx + ']" value="" placeholder="Link" style="flex:1;min-width:100px;padding:6px 10px;border:1px solid #ddd;border-radius:6px;">';
                html += '<label style="display:flex;flex-direction:column;align-items:center;gap:2px;font-size:9px;color:#888;">Cerceve<input type="color" name="story_color[' + webyazStoryIdx + ']" value="#446084" style="width:30px;height:30px;border:none;border-radius:4px;cursor:pointer;padding:0;"></label>';
                html += '<label style="display:flex;align-items:center;gap:4px;font-size:10px;color:#888;"><input type="checkbox" name="story_is_new[' + webyazStoryIdx + ']" value="1"> Yeni</label>';
                html += '<input type="hidden" name="story_sub_images[' + webyazStoryIdx + ']" value="" class="webyaz-sub-images-input">';
                html += '<button type="button" onclick="webyazStoryAddSub(this);" style="background:#9c27b0;color:#fff;border:none;border-radius:4px;padding:4px 8px;cursor:pointer;font-size:10px;" title="Alt Resim Ekle">+Resim</button>';
                html += '<input type="datetime-local" name="story_date_start[' + webyazStoryIdx + ']" value="" style="padding:4px 6px;border:1px solid #ddd;border-radius:4px;font-size:10px;" title="Baslangic">';
                html += '<input type="datetime-local" name="story_date_end[' + webyazStoryIdx + ']" value="" style="padding:4px 6px;border:1px solid #ddd;border-radius:4px;font-size:10px;" title="Bitis">';
                html += '<span style="font-size:10px;color:#4caf50;font-weight:600;">0 tik</span>';
                html += '<button type="button" onclick="this.closest(\'.webyaz-story-item\').remove();" style="background:#f44336;color:#fff;border:none;border-radius:4px;padding:4px 8px;cursor:pointer;font-weight:700;">&times;</button>';
                html += '</div>';
                document.getElementById('webyazStoryList').insertAdjacentHTML('beforeend', html);
                webyazStoryIdx++;
            });

            /* ===== Image Picker ===== */
            function webyazStoryPickImage(el) {
                var frame = wp.media({
                    title: 'Gorsel Sec',
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });
                frame.on('select', function() {
                    var url = frame.state().get('selection').first().toJSON().url;
                    el.style.backgroundImage = 'url(' + url + ')';
                    el.nextElementSibling.value = url;
                });
                frame.open();
            }

            /* ===== Sub Images ===== */
            function webyazStoryAddSub(btn) {
                var item = btn.closest('.webyaz-story-item');
                var input = item.querySelector('.webyaz-sub-images-input');
                var frame = wp.media({
                    title: 'Alt Resim Sec',
                    multiple: true,
                    library: {
                        type: 'image'
                    }
                });
                frame.on('select', function() {
                    var urls = [];
                    frame.state().get('selection').each(function(att) {
                        urls.push(att.toJSON().url);
                    });
                    var current = input.value ? input.value.split(',') : [];
                    input.value = current.concat(urls).filter(Boolean).join(',');
                });
                frame.open();
            }

            /* ===== Clone ===== */
            function webyazStoryClone(btn) {
                var item = btn.closest('.webyaz-story-item');
                var title = item.querySelector('input[name*="story_title"]').value;
                var imgInput = item.querySelector('.webyaz-story-img-input');
                var image = imgInput ? imgInput.value : '';
                var link = item.querySelector('input[name*="story_link"]').value;
                var color = item.querySelector('input[name*="story_color"]').value;
                var subInput = item.querySelector('.webyaz-sub-images-input');
                var subImages = subInput ? subInput.value : '';
                var ds = item.querySelector('input[name*="story_date_start"]');
                var de = item.querySelector('input[name*="story_date_end"]');
                var dateStart = ds ? ds.value : '';
                var dateEnd = de ? de.value : '';
                var bgStyle = image ? "background:#eee url('" + image + "') center/cover;" : "background:#eee;";
                var html = '<div class="webyaz-story-item" draggable="true" style="display:flex;gap:8px;align-items:center;padding:12px;background:#f9f9f9;border-radius:10px;margin-bottom:10px;flex-wrap:wrap;cursor:grab;">';
                html += '<span style="cursor:grab;font-size:16px;color:#aaa;" title="Surukle">&#9776;</span>';
                html += '<input type="hidden" name="story_order[' + webyazStoryIdx + ']" value="' + webyazStoryIdx + '">';
                html += '<div style="width:50px;height:50px;border-radius:50%;overflow:hidden;border:2px solid #ddd;flex-shrink:0;cursor:pointer;' + bgStyle + '" class="webyaz-story-img-preview" onclick="webyazStoryPickImage(this)"></div>';
                html += '<input type="hidden" name="story_image[' + webyazStoryIdx + ']" value="' + image + '" class="webyaz-story-img-input">';
                html += '<input type="text" name="story_title[' + webyazStoryIdx + ']" value="' + title + ' (Kopya)" placeholder="Baslik" style="flex:1;min-width:100px;padding:6px 10px;border:1px solid #ddd;border-radius:6px;">';
                html += '<input type="url" name="story_link[' + webyazStoryIdx + ']" value="' + link + '" placeholder="Link" style="flex:1;min-width:100px;padding:6px 10px;border:1px solid #ddd;border-radius:6px;">';
                html += '<label style="display:flex;flex-direction:column;align-items:center;gap:2px;font-size:9px;color:#888;">Cerceve<input type="color" name="story_color[' + webyazStoryIdx + ']" value="' + color + '" style="width:30px;height:30px;border:none;border-radius:4px;cursor:pointer;padding:0;"></label>';
                html += '<label style="display:flex;align-items:center;gap:4px;font-size:10px;color:#888;"><input type="checkbox" name="story_is_new[' + webyazStoryIdx + ']" value="1"> Yeni</label>';
                html += '<input type="hidden" name="story_sub_images[' + webyazStoryIdx + ']" value="' + subImages + '" class="webyaz-sub-images-input">';
                html += '<button type="button" onclick="webyazStoryAddSub(this);" style="background:#9c27b0;color:#fff;border:none;border-radius:4px;padding:4px 8px;cursor:pointer;font-size:10px;" title="Alt Resim Ekle">+Resim</button>';
                html += '<input type="datetime-local" name="story_date_start[' + webyazStoryIdx + ']" value="' + dateStart + '" style="padding:4px 6px;border:1px solid #ddd;border-radius:4px;font-size:10px;" title="Baslangic">';
                html += '<input type="datetime-local" name="story_date_end[' + webyazStoryIdx + ']" value="' + dateEnd + '" style="padding:4px 6px;border:1px solid #ddd;border-radius:4px;font-size:10px;" title="Bitis">';
                html += '<span style="font-size:10px;color:#4caf50;font-weight:600;">0 tik</span>';
                html += '<button type="button" onclick="webyazStoryClone(this);" style="background:#2196f3;color:#fff;border:none;border-radius:4px;padding:4px 8px;cursor:pointer;font-size:11px;" title="Kopyala">&#x2398;</button>';
                html += '<button type="button" onclick="this.closest(\'.webyaz-story-item\').remove();" style="background:#f44336;color:#fff;border:none;border-radius:4px;padding:4px 8px;cursor:pointer;font-weight:700;">&times;</button>';
                html += '</div>';
                item.insertAdjacentHTML('afterend', html);
                webyazStoryIdx++;
            }

            /* ===== Drag & Drop Sort ===== */
            (function() {
                var list = document.getElementById('webyazStoryList');
                var dragEl = null;
                list.addEventListener('dragstart', function(e) {
                    dragEl = e.target.closest('.webyaz-story-item');
                    if (dragEl) e.dataTransfer.effectAllowed = 'move';
                });
                list.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    var target = e.target.closest('.webyaz-story-item');
                    if (target && target !== dragEl) {
                        var rect = target.getBoundingClientRect();
                        var next = (e.clientY - rect.top) > (rect.height / 2);
                        list.insertBefore(dragEl, next ? target.nextSibling : target);
                    }
                });
                list.addEventListener('dragend', function() {
                    var items = list.querySelectorAll('.webyaz-story-item');
                    items.forEach(function(item, idx) {
                        var orderInput = item.querySelector('input[name*="story_order"]');
                        if (orderInput) orderInput.value = idx;
                    });
                    dragEl = null;
                });
            })();
        </script>
    <?php
    }

    /* ===== FRONTEND CSS ===== */
    public function front_css()
    {
        $s = self::get_settings();
        if ($s['active'] !== '1') return;
        $stories = self::get_stories();
        if (empty($stories)) return;

        $show = false;
        if ($s['position'] === 'everywhere') $show = true;
        elseif ($s['position'] === 'home_top' && is_front_page()) $show = true;
        elseif ($s['position'] === 'shop_top' && function_exists('is_shop') && (is_shop() || is_product_category())) $show = true;
        if (!$show && $s['position'] !== 'shortcode') return;

        $size = intval($s['size']);
        $border = $s['border_style'];
        $mobile_fw = $s['mobile_fullwidth'] === '1';
    ?>
        <style>
            .webyaz-stories {
                display: flex;
                gap: 18px;
                overflow-x: auto;
                padding: 16px 0;
                scrollbar-width: none;
                -ms-overflow-style: none;
                scroll-behavior: smooth;
                position: relative;
                justify-content: center;
                scroll-snap-type: x mandatory;
            }

            .webyaz-stories::-webkit-scrollbar {
                display: none;
            }

            .webyaz-stories-section {
                width: 100%;
                background: <?php echo esc_attr($s['section_bg']); ?>;
                padding: 0;
            }

            .webyaz-stories-wrap {
                position: relative;
                max-width: 1200px;
                margin: 0 auto;
                padding: 0 44px;
            }

            .webyaz-story-item-front {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
                text-decoration: none;
                flex-shrink: 0;
                transition: transform 0.3s ease, filter 0.3s ease;
                cursor: pointer;
                scroll-snap-align: start;
            }

            .webyaz-story-item-front:hover {
                transform: scale(1.1);
            }

            .webyaz-story-item-front:hover .webyaz-story-ring {
                box-shadow: 0 0 20px rgba(210, 110, 75, 0.5), 0 0 40px rgba(68, 96, 132, 0.3);
            }

            /* Viewed story */
            .webyaz-story-item-front.webyaz-story-viewed .webyaz-story-ring {
                opacity: 0.5;
                filter: grayscale(40%);
            }

            .webyaz-story-ring {
                width: <?php echo $size + 8; ?>px;
                height: <?php echo $size + 8; ?>px;
                border-radius: 50%;
                padding: 3px;
                transition: box-shadow 0.3s ease, opacity 0.3s ease;
                position: relative;
                <?php if ($border === 'none'): ?>background: transparent;
                <?php elseif ($border === 'gradient'): ?>background: linear-gradient(45deg, var(--webyaz-secondary, #d26e4b), var(--webyaz-primary, #446084), var(--webyaz-secondary, #d26e4b));
                <?php else: ?>background: var(--webyaz-primary, #446084);
                <?php endif; ?>
            }

            /* New story badge */
            .webyaz-story-ring.is-new {
                animation: webyazPulse 2s ease-in-out infinite;
            }

            @keyframes webyazPulse {

                0%,
                100% {
                    box-shadow: 0 0 0 0 rgba(210, 110, 75, 0.6);
                }

                50% {
                    box-shadow: 0 0 18px 6px rgba(210, 110, 75, 0.3);
                }
            }

            .webyaz-story-img-front {
                width: <?php echo $size; ?>px;
                height: <?php echo $size; ?>px;
                border-radius: 50%;
                object-fit: cover;
                border: 3px solid #fff;
                display: block;
            }

            .webyaz-story-title {
                font-family: 'Roboto', sans-serif;
                font-size: 11px;
                font-weight: 600;
                color: #333;
                text-align: center;
                max-width: <?php echo $size + 16; ?>px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                line-height: 1.3;
            }

            .webyaz-story-new-badge {
                position: absolute;
                bottom: -2px;
                left: 50%;
                transform: translateX(-50%);
                font-size: 9px;
                font-weight: 700;
                color: #fff;
                background: linear-gradient(135deg, #ff4d4d, #d26e4b);
                padding: 2px 8px;
                border-radius: 4px;
                letter-spacing: 0.5px;
                text-transform: uppercase;
                line-height: 1;
                z-index: 2;
                white-space: nowrap;
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
            }

            .webyaz-stories-nav {
                position: absolute;
                top: 50%;
                transform: translateY(-60%);
                width: 38px;
                height: 38px;
                border-radius: 50%;
                background: <?php echo esc_attr($s['nav_bg']); ?>;
                box-shadow: 0 2px 12px rgba(0, 0, 0, 0.2);
                border: 2px solid #ddd;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10;
                transition: all 0.2s;
                padding: 0;
            }

            .webyaz-stories-nav:hover {
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
                border-color: #999;
            }

            .webyaz-stories-nav svg {
                stroke: <?php echo esc_attr($s['nav_arrow']); ?> !important;
                color: <?php echo esc_attr($s['nav_arrow']); ?>;
            }

            .webyaz-stories-nav.left {
                left: 0;
            }

            .webyaz-stories-nav.right {
                right: 0;
            }

            /* ===== POPUP MODAL ===== */
            .webyaz-story-modal-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.92);
                z-index: 999999;
                justify-content: center;
                align-items: center;
            }

            .webyaz-story-modal-overlay.active {
                display: flex;
            }

            .webyaz-story-modal {
                position: relative;
                max-width: 420px;
                width: 90%;
                max-height: 90vh;
                background: #000;
                border-radius: 16px;
                overflow: hidden;
                display: flex;
                flex-direction: column;
            }

            .webyaz-story-modal-progress {
                display: flex;
                gap: 3px;
                padding: 8px 12px 0;
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                z-index: 5;
            }

            .webyaz-story-modal-progress-bar {
                flex: 1;
                height: 3px;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 2px;
                overflow: hidden;
            }

            .webyaz-story-modal-progress-bar .fill {
                height: 100%;
                width: 0%;
                background: #fff;
                border-radius: 2px;
                transition: width 0.1s linear;
            }

            .webyaz-story-modal-progress-bar.done .fill {
                width: 100%;
            }

            .webyaz-story-modal-progress-bar.active .fill {
                width: 0%;
            }

            .webyaz-story-modal-header {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 14px 12px 8px;
                position: absolute;
                top: 10px;
                left: 0;
                right: 0;
                z-index: 5;
            }

            .webyaz-story-modal-header img {
                width: 36px;
                height: 36px;
                border-radius: 50%;
                object-fit: cover;
                border: 2px solid #fff;
            }

            .webyaz-story-modal-header span {
                color: #fff;
                font-weight: 600;
                font-size: 14px;
                text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
            }

            .webyaz-story-modal-close {
                position: absolute;
                top: 14px;
                right: 14px;
                z-index: 10;
                background: none;
                border: none;
                color: #fff;
                font-size: 28px;
                cursor: pointer;
                text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
            }

            .webyaz-story-modal-img {
                width: 100%;
                height: auto;
                max-height: 70vh;
                object-fit: contain;
                display: block;
            }

            .webyaz-story-modal-nav {
                position: absolute;
                top: 0;
                bottom: 0;
                width: 40%;
                background: none;
                border: none;
                cursor: pointer;
                z-index: 4;
            }

            .webyaz-story-modal-nav.prev {
                left: 0;
            }

            .webyaz-story-modal-nav.next {
                right: 0;
            }

            .webyaz-story-modal-link {
                display: block;
                text-align: center;
                padding: 12px;
                background: linear-gradient(transparent, rgba(0, 0, 0, 0.6));
                color: #fff;
                text-decoration: none;
                font-weight: 600;
                font-size: 13px;
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
            }

            .webyaz-story-modal-link:hover {
                background: rgba(255, 255, 255, 0.15);
                color: #fff;
            }

            @media(max-width:768px) {
                <?php
                $mi = intval($s['mobile_items']);
                $mgap = $mobile_fw ? 10 : 12;
                ?>.webyaz-stories {
                    gap: <?php echo $mgap; ?>px;
                    padding: 12px 0;
                    justify-content: flex-start;
                    scroll-snap-type: x mandatory;
                }

                .webyaz-story-item-front {
                    min-width: calc((100% - <?php echo ($mi - 1) * $mgap; ?>px) / <?php echo $mi; ?>);
                    max-width: calc((100% - <?php echo ($mi - 1) * $mgap; ?>px) / <?php echo $mi; ?>);
                    scroll-snap-align: start;
                }

                .webyaz-story-ring {
                    width: <?php echo max($size - 16, 54); ?>px;
                    height: <?php echo max($size - 16, 54); ?>px;
                }

                .webyaz-story-img-front {
                    width: <?php echo max($size - 22, 48); ?>px;
                    height: <?php echo max($size - 22, 48); ?>px;
                    border-width: 2px;
                }

                .webyaz-story-title {
                    font-size: 10px;
                    max-width: <?php echo max($size - 8, 50); ?>px;
                }

                .webyaz-stories-nav {
                    display: none;
                }


                <?php if ($mobile_fw): ?>.webyaz-stories-wrap {
                    padding: 0 10px;
                }

                <?php endif; ?>
            }
        </style>
    <?php
    }

    /* ===== FRONTEND RENDER ===== */
    private function render_stories()
    {
        $s = self::get_settings();
        $stories = self::get_stories();
        if (empty($stories)) return '';
        $max = intval($s['max_items']);
        $show_title = $s['show_title'] === '1';
        $popup = $s['popup_mode'] === '1';
        $autoplay = $s['autoplay'] === '1';
        $autoplay_speed = intval($s['autoplay_speed']);
        $mobile_items = intval($s['mobile_items']);
        $now = current_time('Y-m-d\TH:i');

        /* Filter by date scheduling */
        $filtered = array();
        foreach ($stories as $st) {
            if (!empty($st['date_start']) && $st['date_start'] > $now) continue;
            if (!empty($st['date_end']) && $st['date_end'] < $now) continue;
            $filtered[] = $st;
            if (count($filtered) >= $max) break;
        }
        $stories = $filtered;
        if (empty($stories)) return '';

        /* Build story data for popup modal JS */
        $story_data = array();
        foreach ($stories as $idx => $st) {
            $imgs = array(!empty($st['image']) ? $st['image'] : '');
            if (!empty($st['sub_images']) && is_array($st['sub_images'])) {
                $imgs = array_merge($imgs, $st['sub_images']);
            }
            $story_data[] = array(
                'title' => $st['title'] ?? '',
                'images' => array_values(array_filter($imgs)),
                'link' => $st['link'] ?? '',
                'thumb' => $st['image'] ?? '',
            );
        }

        ob_start();
    ?>
        <div class="webyaz-stories-section">
            <div class="webyaz-stories-wrap">
                <button class="webyaz-stories-nav left" type="button" onclick="webyazStoryScroll(-1)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                </button>
                <div class="webyaz-stories" id="webyazStoriesScroll">
                    <?php foreach ($stories as $idx => $st):
                        $link = !empty($st['link']) ? $st['link'] : '#';
                        $img = !empty($st['image']) ? $st['image'] : '';
                        $color = !empty($st['color']) ? $st['color'] : '';
                        $is_new = ($st['is_new'] ?? '0') === '1';
                        $ring_style = '';
                        if ($color && $s['border_style'] !== 'none') {
                            if ($s['border_style'] === 'gradient') {
                                $ring_style = 'background:linear-gradient(45deg, ' . esc_attr($color) . ', var(--webyaz-primary,#446084), ' . esc_attr($color) . ');';
                            } else {
                                $ring_style = 'background:' . esc_attr($color) . ';';
                            }
                        }
                        $ring_class = 'webyaz-story-ring' . ($is_new ? ' is-new' : '');
                        if ($popup): ?>
                            <div class="webyaz-story-item-front" data-story-index="<?php echo $idx; ?>" onclick="webyazStoryOpen(<?php echo $idx; ?>)">
                            <?php else: ?>
                                <a href="<?php echo esc_url($link); ?>" class="webyaz-story-item-front" data-story-index="<?php echo $idx; ?>">
                                <?php endif; ?>
                                <div class="<?php echo $ring_class; ?>" <?php if ($ring_style) echo 'style="' . $ring_style . '"'; ?>>
                                    <?php if ($img): ?>
                                        <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($st['title']); ?>" class="webyaz-story-img-front" loading="lazy">
                                    <?php else: ?>
                                        <div class="webyaz-story-img-front" style="background:#e0e0e0;"></div>
                                    <?php endif; ?>
                                    <?php if ($is_new): ?>
                                        <span class="webyaz-story-new-badge">Yeni</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($show_title && !empty($st['title'])): ?>
                                    <span class="webyaz-story-title"><?php echo esc_html($st['title']); ?></span>
                                <?php endif; ?>
                                <?php if ($popup): ?>
                            </div>
                        <?php else: ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <button class="webyaz-stories-nav right" type="button" onclick="webyazStoryScroll(1)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Story Modal -->
        <div class="webyaz-story-modal-overlay" id="webyazStoryModal">
            <div class="webyaz-story-modal">
                <div class="webyaz-story-modal-progress" id="webyazStoryProgress"></div>
                <div class="webyaz-story-modal-header">
                    <img id="webyazStoryModalThumb" src="" alt="">
                    <span id="webyazStoryModalTitle"></span>
                </div>
                <button class="webyaz-story-modal-close" onclick="webyazStoryClose()">&times;</button>
                <img class="webyaz-story-modal-img" id="webyazStoryModalImg" src="" alt="">
                <button class="webyaz-story-modal-nav prev" onclick="webyazStoryNav(-1)"></button>
                <button class="webyaz-story-modal-nav next" onclick="webyazStoryNav(1)"></button>
                <a class="webyaz-story-modal-link" id="webyazStoryModalLink" href="#" style="display:none;" target="_blank">Ziyaret Et &#8599;</a>
            </div>
        </div>

        <script>
            (function() {
                var storyData = <?php echo json_encode($story_data); ?>;
                var currentStory = 0,
                    currentSlide = 0,
                    timer = null,
                    progressTimer = null;
                var ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
                var mobileItems = <?php echo $mobile_items; ?>;

                /* ===== Scroll function (mobile-aware) ===== */
                function getScrollAmount() {
                    var scroll = document.getElementById('webyazStoriesScroll');
                    if (!scroll) return 200;
                    var isMobile = window.innerWidth <= 768;
                    if (isMobile) {
                        return scroll.clientWidth + <?php echo ($s['mobile_fullwidth'] === '1') ? 10 : 12; ?>;
                    }
                    return 200;
                }
                window.webyazStoryScroll = function(dir) {
                    var scroll = document.getElementById('webyazStoriesScroll');
                    if (!scroll) return;
                    scroll.scrollBy({
                        left: dir * getScrollAmount(),
                        behavior: 'smooth'
                    });
                };

                /* ===== Viewed Stories (localStorage) ===== */
                var viewedKey = 'webyaz_viewed_stories';

                function getViewed() {
                    try {
                        return JSON.parse(localStorage.getItem(viewedKey)) || [];
                    } catch (e) {
                        return [];
                    }
                }

                function markViewed(idx) {
                    var v = getViewed();
                    if (v.indexOf(idx) === -1) v.push(idx);
                    localStorage.setItem(viewedKey, JSON.stringify(v));
                    var el = document.querySelector('[data-story-index="' + idx + '"]');
                    if (el) el.classList.add('webyaz-story-viewed');
                }
                /* Apply viewed on load */
                var viewed = getViewed();
                viewed.forEach(function(idx) {
                    var el = document.querySelector('[data-story-index="' + idx + '"]');
                    if (el) el.classList.add('webyaz-story-viewed');
                });

                /* ===== Click Counter (AJAX) ===== */
                function trackClick(idx) {
                    var fd = new FormData();
                    fd.append('action', 'webyaz_story_click');
                    fd.append('story_index', idx);
                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: fd
                    });
                }

                /* ===== Popup Modal ===== */
                window.webyazStoryOpen = function(idx) {
                    currentStory = idx;
                    currentSlide = 0;
                    markViewed(idx);
                    trackClick(idx);
                    showSlide();
                    document.getElementById('webyazStoryModal').classList.add('active');
                    document.body.style.overflow = 'hidden';
                    startAutoProgress();
                };
                window.webyazStoryClose = function() {
                    document.getElementById('webyazStoryModal').classList.remove('active');
                    document.body.style.overflow = '';
                    clearTimers();
                };
                window.webyazStoryNav = function(dir) {
                    var st = storyData[currentStory];
                    if (!st) return;
                    currentSlide += dir;
                    if (currentSlide >= st.images.length) {
                        /* Next story */
                        currentSlide = 0;
                        currentStory++;
                        if (currentStory >= storyData.length) {
                            webyazStoryClose();
                            return;
                        }
                        markViewed(currentStory);
                        trackClick(currentStory);
                    } else if (currentSlide < 0) {
                        /* Prev story */
                        currentStory--;
                        if (currentStory < 0) {
                            currentStory = 0;
                            currentSlide = 0;
                        } else {
                            currentSlide = storyData[currentStory].images.length - 1;
                        }
                    }
                    showSlide();
                    startAutoProgress();
                };

                function showSlide() {
                    var st = storyData[currentStory];
                    if (!st) return;
                    document.getElementById('webyazStoryModalImg').src = st.images[currentSlide] || '';
                    document.getElementById('webyazStoryModalThumb').src = st.thumb || '';
                    document.getElementById('webyazStoryModalTitle').textContent = st.title || '';
                    var linkEl = document.getElementById('webyazStoryModalLink');
                    if (st.link && st.link !== '#') {
                        linkEl.href = st.link;
                        linkEl.style.display = 'block';
                    } else {
                        linkEl.style.display = 'none';
                    }
                    /* Progress bars */
                    var pc = document.getElementById('webyazStoryProgress');
                    pc.innerHTML = '';
                    for (var i = 0; i < st.images.length; i++) {
                        var bar = document.createElement('div');
                        bar.className = 'webyaz-story-modal-progress-bar' + (i < currentSlide ? ' done' : (i === currentSlide ? ' active' : ''));
                        bar.innerHTML = '<div class="fill"></div>';
                        pc.appendChild(bar);
                    }
                }

                function startAutoProgress() {
                    clearTimers();
                    var duration = 5000;
                    var elapsed = 0;
                    var step = 50;
                    progressTimer = setInterval(function() {
                        elapsed += step;
                        var pct = Math.min((elapsed / duration) * 100, 100);
                        var bar = document.querySelector('.webyaz-story-modal-progress-bar.active .fill');
                        if (bar) bar.style.width = pct + '%';
                        if (elapsed >= duration) {
                            clearTimers();
                            webyazStoryNav(1);
                        }
                    }, step);
                }

                function clearTimers() {
                    if (timer) clearTimeout(timer);
                    if (progressTimer) clearInterval(progressTimer);
                    timer = null;
                    progressTimer = null;
                }

                /* Close on overlay click */
                document.getElementById('webyazStoryModal').addEventListener('click', function(e) {
                    if (e.target === this) webyazStoryClose();
                });
                /* ESC key */
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') webyazStoryClose();
                    if (e.key === 'ArrowRight') webyazStoryNav(1);
                    if (e.key === 'ArrowLeft') webyazStoryNav(-1);
                });

                /* ===== Autoplay Scroll ===== */
                <?php if ($autoplay): ?>
                        (function() {
                            var scroll = document.getElementById('webyazStoriesScroll');
                            if (!scroll) return;
                            var speed = <?php echo $autoplay_speed; ?>;
                            var paused = false;
                            var dir = 1;
                            scroll.addEventListener('mouseenter', function() {
                                paused = true;
                            });
                            scroll.addEventListener('mouseleave', function() {
                                paused = false;
                            });
                            scroll.addEventListener('touchstart', function() {
                                paused = true;
                            });
                            scroll.addEventListener('touchend', function() {
                                setTimeout(function() {
                                    paused = false;
                                }, 2000);
                            });
                            setInterval(function() {
                                if (paused) return;
                                var maxScroll = scroll.scrollWidth - scroll.clientWidth;
                                if (scroll.scrollLeft >= maxScroll - 2) dir = -1;
                                if (scroll.scrollLeft <= 2) dir = 1;
                                var amt = getScrollAmount();
                                scroll.scrollBy({
                                    left: dir * amt,
                                    behavior: 'smooth'
                                });
                            }, speed);
                        })();
                <?php endif; ?>

                /* ===== Non-popup click tracking ===== */
                <?php if (!$popup): ?>
                    document.querySelectorAll('.webyaz-story-item-front').forEach(function(el) {
                        el.addEventListener('click', function() {
                            var idx = parseInt(this.dataset.storyIndex);
                            markViewed(idx);
                            trackClick(idx);
                        });
                    });
                <?php endif; ?>

            })();
        </script>
<?php
        return ob_get_clean();
    }

    public function shortcode()
    {
        return $this->render_stories();
    }
}

new Webyaz_Story_Menu();
