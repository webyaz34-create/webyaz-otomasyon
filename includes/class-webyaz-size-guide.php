<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Size_Guide {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('woocommerce_product_tabs', array($this, 'add_tab'));
        add_action('woocommerce_single_product_summary', array($this, 'add_link'), 25);
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Beden Tablosu', 'Beden Tablosu', 'manage_options', 'webyaz-size-guide', array($this, 'render_admin'));
    }

    public function register_settings() {
        register_setting('webyaz_size_guide_group', 'webyaz_size_guide');
    }

    private static function get_defaults() {
        return array(
            'enabled' => '1',
            'categories' => '',
            'tables' => array(
                array(
                    'name' => 'Genel Beden Tablosu',
                    'headers' => 'Beden,Gogus (cm),Bel (cm),Kalca (cm)',
                    'rows' => "XS,82-86,62-66,88-92\nS,86-90,66-70,92-96\nM,90-94,70-74,96-100\nL,94-98,74-78,100-104\nXL,98-102,78-82,104-108\nXXL,102-106,82-86,108-112",
                ),
            ),
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_size_guide', array()), self::get_defaults());
    }

    private function should_show() {
        if (!is_singular('product')) return false;
        $opts = self::get_opts();
        if ($opts['enabled'] !== '1') return false;

        $cats = array_filter(array_map('trim', explode(',', $opts['categories'])));
        if (empty($cats)) return true;

        global $post;
        $product_cats = wp_get_object_terms($post->ID, 'product_cat', array('fields' => 'slugs'));
        $product_cat_names = wp_get_object_terms($post->ID, 'product_cat', array('fields' => 'names'));
        $all = array_merge($product_cats, $product_cat_names);
        foreach ($cats as $c) {
            foreach ($all as $pc) {
                if (mb_strtolower($c) === mb_strtolower($pc)) return true;
            }
        }
        return false;
    }

    public function add_tab($tabs) {
        if (!$this->should_show()) return $tabs;
        $tabs['webyaz_size'] = array(
            'title' => 'Beden Tablosu',
            'priority' => 30,
            'callback' => array($this, 'tab_content'),
        );
        return $tabs;
    }

    public function add_link() {
        if (!$this->should_show()) return;
        echo '<a href="#tab-webyaz_size" class="webyaz-size-link" onclick="jQuery(\'.tabs li a[href=#tab-webyaz_size]\').click();window.scrollTo({top:jQuery(\'#tab-webyaz_size\').offset().top-100,behavior:\'smooth\'});return false;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg> Beden Rehberi</a>';
    }

    public function tab_content() {
        $opts = self::get_opts();
        $tables = !empty($opts['tables']) ? $opts['tables'] : array();
        foreach ($tables as $table) {
            if (empty($table['headers']) || empty($table['rows'])) continue;
            $headers = array_map('trim', explode(',', $table['headers']));
            $rows = array_filter(explode("\n", $table['rows']));
            ?>
            <div class="webyaz-size-guide">
                <?php if (!empty($table['name'])): ?>
                    <h4><?php echo esc_html($table['name']); ?></h4>
                <?php endif; ?>
                <div class="webyaz-size-table-wrap">
                    <table class="webyaz-size-table">
                        <thead>
                            <tr>
                                <?php foreach ($headers as $h): ?>
                                    <th><?php echo esc_html($h); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row):
                                $cells = array_map('trim', explode(',', $row));
                            ?>
                            <tr>
                                <?php foreach ($cells as $i => $cell): ?>
                                    <td><?php echo esc_html($cell); ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="webyaz-size-note">* Olculer yaklasik degerlerdir. Vucut tipine gore farklilik gosterebilir.</p>
            </div>
            <?php
        }
    }

    public function render_admin() {
        if (isset($_POST['webyaz_sg_save']) && check_admin_referer('webyaz_sg_nonce')) {
            $save = array(
                'enabled' => isset($_POST['sg_enabled']) ? '1' : '0',
                'categories' => sanitize_text_field(isset($_POST['sg_categories']) ? $_POST['sg_categories'] : ''),
                'tables' => array(),
            );
            if (isset($_POST['sg_tables']) && is_array($_POST['sg_tables'])) {
                foreach ($_POST['sg_tables'] as $t) {
                    if (empty($t['headers']) && empty($t['rows'])) continue;
                    $save['tables'][] = array(
                        'name' => sanitize_text_field($t['name']),
                        'headers' => sanitize_text_field($t['headers']),
                        'rows' => sanitize_textarea_field($t['rows']),
                    );
                }
            }
            update_option('webyaz_size_guide', $save);
            echo '<div class="webyaz-notice success">Beden tablosu kaydedildi!</div>';
        }

        $opts = self::get_opts();
        $tables = !empty($opts['tables']) ? $opts['tables'] : array();
        if (empty($tables)) $tables[] = array('name' => '', 'headers' => '', 'rows' => '');
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Beden Tablosu</h1>
                <p>Urun sayfalarinda beden rehberi gosterimi</p>
            </div>
            <form method="post">
                <?php wp_nonce_field('webyaz_sg_nonce'); ?>
                <div class="webyaz-settings-section">
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label><input type="checkbox" name="sg_enabled" value="1" <?php checked($opts['enabled'], '1'); ?>> Beden Tablosu Aktif</label>
                        </div>
                        <div class="webyaz-field">
                            <label>Gosterilecek Kategoriler (virgul ile, bos = hepsi)</label>
                            <input type="text" name="sg_categories" value="<?php echo esc_attr($opts['categories']); ?>" placeholder="ornek: giyim, elbise, t-shirt">
                            <small style="color:#999;">Kategori adi veya slug yazin. Bos birakirsaniz tum urunlerde gosterilir.</small>
                        </div>
                    </div>
                </div>

                <div id="webyazSgTables">
                <?php foreach ($tables as $i => $t): ?>
                <div class="webyaz-settings-section webyaz-sg-table-block">
                    <h2 class="webyaz-section-title">Tablo <?php echo ($i + 1); ?></h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Tablo Adi</label>
                            <input type="text" name="sg_tables[<?php echo $i; ?>][name]" value="<?php echo esc_attr(isset($t['name']) ? $t['name'] : ''); ?>" placeholder="Genel Beden Tablosu">
                        </div>
                        <div class="webyaz-field">
                            <label>Sutun Basliklari (virgul ile)</label>
                            <input type="text" name="sg_tables[<?php echo $i; ?>][headers]" value="<?php echo esc_attr(isset($t['headers']) ? $t['headers'] : ''); ?>" placeholder="Beden,Gogus (cm),Bel (cm),Kalca (cm)">
                        </div>
                        <div class="webyaz-field" style="grid-column:1/-1;">
                            <label>Satirlar (her satir bir beden, degerler virgul ile)</label>
                            <textarea name="sg_tables[<?php echo $i; ?>][rows]" rows="6" style="font-family:monospace;font-size:13px;"><?php echo esc_textarea(isset($t['rows']) ? $t['rows'] : ''); ?></textarea>
                        </div>
                    </div>
                    <button type="button" onclick="this.closest('.webyaz-sg-table-block').remove();" style="background:#d32f2f;color:#fff;border:none;border-radius:4px;padding:6px 14px;cursor:pointer;font-size:12px;margin-top:8px;">Tabloyu Sil</button>
                </div>
                <?php endforeach; ?>
                </div>

                <button type="button" onclick="webyazAddSgTable();" style="background:#446084;color:#fff;border:none;border-radius:6px;padding:10px 20px;cursor:pointer;font-size:13px;font-weight:600;margin-top:10px;">+ Yeni Tablo Ekle</button>

                <div style="margin-top:20px;">
                    <button type="submit" name="webyaz_sg_save" class="button button-primary" style="padding:8px 24px;font-size:14px;">Kaydet</button>
                </div>
            </form>
        </div>
        <script>
        var webyazSgIdx = <?php echo count($tables); ?>;
        function webyazAddSgTable(){
            var i = webyazSgIdx++;
            var html = '<div class="webyaz-settings-section webyaz-sg-table-block"><h2 class="webyaz-section-title">Tablo '+(i+1)+'</h2><div class="webyaz-settings-grid"><div class="webyaz-field"><label>Tablo Adi</label><input type="text" name="sg_tables['+i+'][name]" placeholder="Ust Giyim"></div><div class="webyaz-field"><label>Sutun Basliklari</label><input type="text" name="sg_tables['+i+'][headers]" placeholder="Beden,Gogus,Bel,Kalca"></div><div class="webyaz-field" style="grid-column:1/-1;"><label>Satirlar</label><textarea name="sg_tables['+i+'][rows]" rows="6" style="font-family:monospace;" placeholder="S,86-90,66-70,92-96"></textarea></div></div><button type="button" onclick="this.closest(\'.webyaz-sg-table-block\').remove();" style="background:#d32f2f;color:#fff;border:none;border-radius:4px;padding:6px 14px;cursor:pointer;font-size:12px;margin-top:8px;">Tabloyu Sil</button></div>';
            document.getElementById('webyazSgTables').insertAdjacentHTML('beforeend', html);
        }
        </script>
        <?php
    }
}

new Webyaz_Size_Guide();
