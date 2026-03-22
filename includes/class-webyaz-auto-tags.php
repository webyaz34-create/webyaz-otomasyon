<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Auto_Tags {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('save_post_product', array($this, 'auto_assign_tags'), 20, 3);
        add_action('woocommerce_new_product', array($this, 'auto_assign_on_create'), 10, 1);
    }

    public function add_submenu() {
        add_submenu_page(
            'webyaz-dashboard',
            'Otomatik Etiketler',
            'Etiketler',
            'manage_options',
            'webyaz-tags',
            array($this, 'render_admin')
        );
    }

    public function register_settings() {
        register_setting('webyaz_tags_group', 'webyaz_tags');
    }

    private static function get_defaults() {
        return array(
            'enabled' => '1',
            'global_tags' => '',
            'category_tags' => array(),
            'overwrite' => '0',
        );
    }

    public static function get($key) {
        $opts = wp_parse_args(get_option('webyaz_tags', array()), self::get_defaults());
        return isset($opts[$key]) ? $opts[$key] : '';
    }

    public function auto_assign_on_create($product_id) {
        $this->assign_tags($product_id);
    }

    public function auto_assign_tags($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'product') return;
        $this->assign_tags($post_id);
    }

    private function assign_tags($product_id) {
        $opts = wp_parse_args(get_option('webyaz_tags', array()), self::get_defaults());
        if ($opts['enabled'] !== '1') return;

        $existing_tags = wp_get_object_terms($product_id, 'product_tag', array('fields' => 'names'));
        if (!empty($existing_tags) && $opts['overwrite'] !== '1') {
            $tags_to_add = $existing_tags;
        } else {
            $tags_to_add = array();
        }

        if (!empty($opts['global_tags'])) {
            $global = array_map('trim', explode(',', $opts['global_tags']));
            $global = array_filter($global);
            $tags_to_add = array_merge($tags_to_add, $global);
        }

        if (!empty($opts['category_tags']) && is_array($opts['category_tags'])) {
            $product_cats = wp_get_object_terms($product_id, 'product_cat', array('fields' => 'ids'));
            foreach ($opts['category_tags'] as $rule) {
                if (empty($rule['category']) || empty($rule['tags'])) continue;
                $cat_id = intval($rule['category']);
                if (in_array($cat_id, $product_cats)) {
                    $cat_tags = array_map('trim', explode(',', $rule['tags']));
                    $tags_to_add = array_merge($tags_to_add, array_filter($cat_tags));
                }
            }
        }

        if (!empty($tags_to_add)) {
            $tags_to_add = array_unique($tags_to_add);
            wp_set_object_terms($product_id, $tags_to_add, 'product_tag', false);
        }
    }

    public function render_admin() {
        if (isset($_POST['webyaz_tags_save']) && check_admin_referer('webyaz_tags_nonce')) {
            $save = array(
                'enabled' => isset($_POST['wt_enabled']) ? '1' : '0',
                'global_tags' => sanitize_text_field(isset($_POST['wt_global_tags']) ? $_POST['wt_global_tags'] : ''),
                'overwrite' => isset($_POST['wt_overwrite']) ? '1' : '0',
                'category_tags' => array(),
            );

            if (isset($_POST['wt_cat_rules']) && is_array($_POST['wt_cat_rules'])) {
                foreach ($_POST['wt_cat_rules'] as $rule) {
                    if (empty($rule['category']) && empty($rule['tags'])) continue;
                    $save['category_tags'][] = array(
                        'category' => intval($rule['category']),
                        'tags' => sanitize_text_field($rule['tags']),
                    );
                }
            }

            update_option('webyaz_tags', $save);
            echo '<div class="webyaz-notice success">Etiket ayarlari kaydedildi!</div>';
        }

        $opts = wp_parse_args(get_option('webyaz_tags', array()), self::get_defaults());
        $categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
        if (is_wp_error($categories)) $categories = array();
        $cat_rules = !empty($opts['category_tags']) && is_array($opts['category_tags']) ? $opts['category_tags'] : array();
        if (count($cat_rules) < 1) {
            $cat_rules[] = array('category' => '', 'tags' => '');
        }
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Otomatik Urun Etiketleri</h1>
                <p>Yeni urun eklediginizde etiketler otomatik atanir</p>
            </div>

            <form method="post">
                <?php wp_nonce_field('webyaz_tags_nonce'); ?>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Genel Ayarlar</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>
                                <input type="checkbox" name="wt_enabled" value="1" <?php checked($opts['enabled'], '1'); ?>>
                                Otomatik Etiketleme Aktif
                            </label>
                        </div>
                        <div class="webyaz-field">
                            <label>
                                <input type="checkbox" name="wt_overwrite" value="1" <?php checked($opts['overwrite'], '1'); ?>>
                                Mevcut etiketleri sil ve yeniden ata
                            </label>
                            <small style="color:#999;display:block;margin-top:4px;">Isaretlenmezse mevcut etiketlere ek olarak yeni etiketler eklenir</small>
                        </div>
                    </div>
                </div>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Tum Urunlere Eklenecek Etiketler</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:10px;">Virgul ile ayirarak yazin. Her yeni urun eklediginizde bu etiketler otomatik atanir.</p>
                    <div class="webyaz-field">
                        <textarea name="wt_global_tags" rows="3" style="width:100%;font-size:14px;padding:12px;border:1px solid #ddd;border-radius:8px;" placeholder="ornek: yeni urun, kampanya, ozel fiyat, hizli kargo"><?php echo esc_textarea($opts['global_tags']); ?></textarea>
                    </div>
                </div>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Kategoriye Gore Etiketler</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:15px;">Belirli kategorideki urunlere otomatik ek etiketler atayabilirsiniz.</p>

                    <div id="webyazCatRules">
                        <?php foreach ($cat_rules as $i => $rule): ?>
                        <div class="webyaz-cat-rule" style="display:flex;gap:12px;align-items:flex-end;margin-bottom:10px;background:#f9f9f9;padding:12px;border-radius:8px;border:1px solid #e0e0e0;">
                            <div class="webyaz-field" style="flex:1;">
                                <label>Kategori</label>
                                <select name="wt_cat_rules[<?php echo $i; ?>][category]" style="width:100%;">
                                    <option value="">-- Kategori Secin --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat->term_id; ?>" <?php selected(isset($rule['category']) ? $rule['category'] : '', $cat->term_id); ?>>
                                            <?php echo esc_html($cat->name); ?> (<?php echo $cat->count; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="webyaz-field" style="flex:2;">
                                <label>Etiketler (virgul ile)</label>
                                <input type="text" name="wt_cat_rules[<?php echo $i; ?>][tags]" value="<?php echo esc_attr(isset($rule['tags']) ? $rule['tags'] : ''); ?>" placeholder="ornek: kadin giyim, elbise, trend">
                            </div>
                            <button type="button" onclick="this.closest('.webyaz-cat-rule').remove();" style="background:#d32f2f;color:#fff;border:none;border-radius:6px;padding:10px 14px;cursor:pointer;font-size:16px;margin-bottom:2px;">&times;</button>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" onclick="webyazAddCatRule();" style="background:var(--webyaz-primary,#446084);color:#fff;border:none;border-radius:6px;padding:10px 20px;cursor:pointer;font-size:13px;font-weight:600;margin-top:8px;">+ Yeni Kural Ekle</button>
                </div>

                <div style="margin-top:20px;">
                    <button type="submit" name="webyaz_tags_save" class="button button-primary" style="padding:8px 24px;font-size:14px;">Kaydet</button>
                </div>
            </form>

            <div class="webyaz-settings-section" style="margin-top:25px;">
                <h2 class="webyaz-section-title">Mevcut Urunlere Toplu Etiket Ata</h2>
                <p style="color:#666;font-size:13px;margin-bottom:10px;">Yukaridaki kurallari mevcut tum urunlere uygulamak icin kullanin.</p>
                <form method="post">
                    <?php wp_nonce_field('webyaz_tags_nonce'); ?>
                    <button type="submit" name="webyaz_tags_bulk" class="button" style="padding:8px 20px;font-size:13px;">Tum Urunlere Etiket Ata</button>
                </form>
                <?php
                if (isset($_POST['webyaz_tags_bulk']) && check_admin_referer('webyaz_tags_nonce')) {
                    $args = array(
                        'post_type' => 'product',
                        'posts_per_page' => -1,
                        'post_status' => 'publish',
                        'fields' => 'ids',
                    );
                    $ids = get_posts($args);
                    $count = 0;
                    foreach ($ids as $pid) {
                        $this->assign_tags($pid);
                        $count++;
                    }
                    echo '<div class="webyaz-notice success" style="margin-top:10px;">' . $count . ' urune etiketler atandi!</div>';
                }
                ?>
            </div>
        </div>

        <script>
        var webyazCatIndex = <?php echo count($cat_rules); ?>;
        function webyazAddCatRule() {
            var cats = <?php echo wp_json_encode(array_map(function($c){ return array('id' => $c->term_id, 'name' => $c->name, 'count' => $c->count); }, $categories)); ?>;
            var opts = '<option value="">-- Kategori Secin --</option>';
            for (var i = 0; i < cats.length; i++) {
                opts += '<option value="'+cats[i].id+'">'+cats[i].name+' ('+cats[i].count+')</option>';
            }
            var html = '<div class="webyaz-cat-rule" style="display:flex;gap:12px;align-items:flex-end;margin-bottom:10px;background:#f9f9f9;padding:12px;border-radius:8px;border:1px solid #e0e0e0;">';
            html += '<div class="webyaz-field" style="flex:1;"><label>Kategori</label><select name="wt_cat_rules['+webyazCatIndex+'][category]" style="width:100%;">'+opts+'</select></div>';
            html += '<div class="webyaz-field" style="flex:2;"><label>Etiketler (virgul ile)</label><input type="text" name="wt_cat_rules['+webyazCatIndex+'][tags]" placeholder="ornek: kadin giyim, elbise"></div>';
            html += '<button type="button" onclick="this.closest(\'.webyaz-cat-rule\').remove();" style="background:#d32f2f;color:#fff;border:none;border-radius:6px;padding:10px 14px;cursor:pointer;font-size:16px;margin-bottom:2px;">&times;</button>';
            html += '</div>';
            document.getElementById('webyazCatRules').insertAdjacentHTML('beforeend', html);
            webyazCatIndex++;
        }
        </script>
        <?php
    }
}

new Webyaz_Auto_Tags();
