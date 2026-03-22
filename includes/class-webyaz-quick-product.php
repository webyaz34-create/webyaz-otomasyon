<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Quick_Product
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_post_webyaz_quick_product', array($this, 'handle_submit'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_media'));
    }

    public function enqueue_media($hook)
    {
        if (strpos($hook, 'webyaz-quick-product') !== false) {
            wp_enqueue_media();
        }
    }

    public function add_submenu()
    {
        add_submenu_page(
            'webyaz-dashboard',
            'Hizli Urun Ekle',
            'Hizli Urun Ekle',
            'manage_options',
            'webyaz-quick-product',
            array($this, 'render_admin')
        );
    }

    public function handle_submit()
    {
        if (!current_user_can('manage_options')) wp_die('Yetkiniz yok.');
        check_admin_referer('webyaz_qp_nonce');

        $title = sanitize_text_field($_POST['qp_title']);
        $price = sanitize_text_field($_POST['qp_price']);
        $sale = sanitize_text_field(isset($_POST['qp_sale']) ? $_POST['qp_sale'] : '');
        $desc = wp_kses_post(isset($_POST['qp_desc']) ? $_POST['qp_desc'] : '');
        $short = sanitize_textarea_field(isset($_POST['qp_short']) ? $_POST['qp_short'] : '');
        $sku = sanitize_text_field(isset($_POST['qp_sku']) ? $_POST['qp_sku'] : '');
        $cat = intval(isset($_POST['qp_category']) ? $_POST['qp_category'] : 0);
        $image = esc_url_raw(isset($_POST['qp_image']) ? $_POST['qp_image'] : '');
        $status = sanitize_text_field(isset($_POST['qp_status']) ? $_POST['qp_status'] : 'publish');
        $sizes = isset($_POST['qp_sizes']) ? array_map('sanitize_text_field', $_POST['qp_sizes']) : array();
        $preset_colors = isset($_POST['qp_preset_colors']) ? array_map('sanitize_text_field', $_POST['qp_preset_colors']) : array();
        $shoes = isset($_POST['qp_shoes']) ? array_map('sanitize_text_field', $_POST['qp_shoes']) : array();
        $units = isset($_POST['qp_units']) ? array_map('sanitize_text_field', $_POST['qp_units']) : array();
        $weight = sanitize_text_field(isset($_POST['qp_weight']) ? $_POST['qp_weight'] : '');
        $stock_qty = sanitize_text_field(isset($_POST['qp_stock_qty']) ? $_POST['qp_stock_qty'] : '');
        $custom_prop_names = isset($_POST['qp_prop_name']) ? array_map('sanitize_text_field', $_POST['qp_prop_name']) : array();
        $custom_prop_values = isset($_POST['qp_prop_value']) ? array_map('sanitize_text_field', $_POST['qp_prop_value']) : array();

        if (empty($title) || empty($price)) {
            wp_redirect(admin_url('admin.php?page=webyaz-quick-product&error=1'));
            exit;
        }

        $post_id = wp_insert_post(array(
            'post_title' => $title,
            'post_content' => $desc,
            'post_excerpt' => $short,
            'post_status' => $status,
            'post_type' => 'product',
        ));

        if (is_wp_error($post_id)) {
            wp_redirect(admin_url('admin.php?page=webyaz-quick-product&error=2'));
            exit;
        }

        update_post_meta($post_id, '_regular_price', $price);
        if ($sale) update_post_meta($post_id, '_sale_price', $sale);
        update_post_meta($post_id, '_price', $sale ? $sale : $price);
        if ($sku) update_post_meta($post_id, '_sku', $sku);
        update_post_meta($post_id, '_stock_status', 'instock');
        update_post_meta($post_id, '_visibility', 'visible');
        wp_set_object_terms($post_id, 'simple', 'product_type');

        if ($stock_qty !== '') {
            update_post_meta($post_id, '_manage_stock', 'yes');
            update_post_meta($post_id, '_stock', intval($stock_qty));
        } else {
            update_post_meta($post_id, '_manage_stock', 'no');
        }
        if ($weight) update_post_meta($post_id, '_weight', $weight);

        if ($cat) wp_set_object_terms($post_id, $cat, 'product_cat');

        if ($image) {
            $attach_id = attachment_url_to_postid($image);
            if ($attach_id) {
                set_post_thumbnail($post_id, $attach_id);
            }
        }

        if (!empty($sizes)) update_post_meta($post_id, '_webyaz_sizes', $sizes);
        if (!empty($preset_colors)) update_post_meta($post_id, '_webyaz_preset_colors', $preset_colors);
        if (!empty($shoes)) update_post_meta($post_id, '_webyaz_shoes', $shoes);
        if (!empty($units)) update_post_meta($post_id, '_webyaz_units', $units);

        // Ozel ozellikler
        if (!empty($custom_prop_names)) {
            $props = array();
            foreach ($custom_prop_names as $i => $pn) {
                $pv = isset($custom_prop_values[$i]) ? $custom_prop_values[$i] : '';
                if (!empty($pn) && !empty($pv)) {
                    $props[] = array('name' => $pn, 'value' => $pv);
                }
            }
            if (!empty($props)) {
                update_post_meta($post_id, '_webyaz_custom_props_active', '1');
                update_post_meta($post_id, '_webyaz_custom_props', $props);
            }
        }

        // Beden/renk aktif et
        if (!empty($sizes) || !empty($preset_colors)) {
            update_post_meta($post_id, '_webyaz_attrs_active', '1');
        }
        if (!empty($shoes)) {
            update_post_meta($post_id, '_webyaz_shoes_active', '1');
        }
        if (!empty($units)) {
            update_post_meta($post_id, '_webyaz_units_active', '1');
        }

        wp_redirect(admin_url('admin.php?page=webyaz-quick-product&success=1&pid=' . $post_id));
        exit;
    }

    public function render_admin()
    {
        $categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
        if (is_wp_error($categories)) $categories = array();
        $sizes = array('XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', '5XL');
        $shoe_sizes = array('35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46', '47');
        $units = array('Adet', 'Paket', 'Koli', 'Metre', 'Kilogram', 'Litre', 'Cift', 'Duzine', 'Top', 'Rulo');
        $colors = array(
            'Siyah' => '#000000',
            'Beyaz' => '#ffffff',
            'Kirmizi' => '#e74c3c',
            'Mavi' => '#3498db',
            'Lacivert' => '#2c3e50',
            'Yesil' => '#27ae60',
            'Sari' => '#f1c40f',
            'Turuncu' => '#e67e22',
            'Mor' => '#9b59b6',
            'Pembe' => '#e91e63',
            'Gri' => '#95a5a6',
            'Kahverengi' => '#8d6e63',
            'Bordo' => '#800020',
            'Bej' => '#f5f5dc',
            'Krem' => '#fffdd0',
        );
?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Hizli Urun Ekle</h1>
                <p>Tek sayfada hizlica urun olusturun</p>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="webyaz-notice success">
                    Urun basariyla eklendi!
                    <a href="<?php echo get_edit_post_link(intval($_GET['pid'])); ?>" style="margin-left:10px;font-weight:600;">Urunu Duzenle</a>
                    <a href="<?php echo get_permalink(intval($_GET['pid'])); ?>" style="margin-left:10px;font-weight:600;" target="_blank">Urunu Gor</a>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="webyaz-notice" style="background:#fde8e8;border-color:#f5c6c6;color:#d32f2f;">Hata: Urun adi ve fiyat zorunludur.</div>
            <?php endif; ?>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="webyaz_quick_product">
                <?php wp_nonce_field('webyaz_qp_nonce'); ?>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Temel Bilgiler</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field" style="grid-column:1/-1;">
                            <label>Urun Adi *</label>
                            <input type="text" name="qp_title" required placeholder="ornek: Premium Pamuklu T-Shirt" style="font-size:16px;padding:14px;">
                        </div>
                        <div class="webyaz-field">
                            <label>Normal Fiyat (TL) *</label>
                            <input type="number" name="qp_price" required step="0.01" min="0" placeholder="199.90">
                        </div>
                        <div class="webyaz-field">
                            <label>Indirimli Fiyat (opsiyonel)</label>
                            <input type="number" name="qp_sale" step="0.01" min="0" placeholder="149.90">
                        </div>
                        <div class="webyaz-field">
                            <label>SKU (Stok Kodu)</label>
                            <input type="text" name="qp_sku" placeholder="TSH-001">
                        </div>
                        <div class="webyaz-field">
                            <label>Kategori</label>
                            <select name="qp_category">
                                <option value="0">-- Kategori Sec --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat->term_id; ?>"><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Durum</label>
                            <select name="qp_status">
                                <option value="publish">Yayinda</option>
                                <option value="draft">Taslak</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Stok Miktari (opsiyonel)</label>
                            <input type="number" name="qp_stock_qty" min="0" step="1" placeholder="Bos birakirsaniz stok takip edilmez">
                        </div>
                        <div class="webyaz-field">
                            <label>Agirlik (kg)</label>
                            <input type="number" name="qp_weight" step="0.01" min="0" placeholder="ornek: 0.5">
                        </div>
                        <div class="webyaz-field" style="grid-column:1/-1;">
                            <label>Kisa Aciklama</label>
                            <textarea name="qp_short" rows="2" placeholder="Urun ozeti..."></textarea>
                        </div>
                        <div class="webyaz-field" style="grid-column:1/-1;">
                            <label>Urun Gorseli</label>
                            <div style="display:flex;gap:10px;align-items:center;">
                                <input type="url" name="qp_image" id="qpImage" placeholder="Gorsel URL" style="flex:1;">
                                <button type="button" class="button" id="qpImageBtn">Medyadan Sec</button>
                            </div>
                            <div id="qpImagePreview" style="margin-top:8px;"></div>
                        </div>
                    </div>
                </div>

                <div class="webyaz-settings-section" style="margin-top:20px;">
                    <h2 class="webyaz-section-title">Beden & Renk</h2>
                    <label style="font-weight:600;display:block;margin-bottom:8px;">Bedenler</label>
                    <div id="qpSizeWrap" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
                        <?php foreach ($sizes as $s): ?>
                            <label style="display:inline-flex;padding:8px 14px;border:2px solid #ddd;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;" onclick="var i=this.querySelector('input');this.style.borderColor=i.checked?'#ddd':'#446084';this.style.background=i.checked?'#fff':'rgba(68,96,132,0.08)';">
                                <input type="checkbox" name="qp_sizes[]" value="<?php echo esc_attr($s); ?>" style="display:none;">
                                <?php echo esc_html($s); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:18px;">
                        <input type="text" id="qpNewSize" placeholder="Yeni beden ekle (ornek: 6XL)" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;width:200px;font-size:13px;">
                        <button type="button" id="qpAddSize" class="button" style="font-weight:600;">+ Beden Ekle</button>
                    </div>

                    <label style="font-weight:600;display:block;margin-bottom:8px;">Renkler</label>
                    <div id="qpColorWrap" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                        <?php foreach ($colors as $name => $hex): ?>
                            <label style="display:flex;flex-direction:column;align-items:center;gap:3px;cursor:pointer;" title="<?php echo esc_attr($name); ?>">
                                <span style="width:32px;height:32px;border-radius:50%;background:<?php echo $hex; ?>;border:3px solid #eee;display:block;<?php echo $hex === '#ffffff' ? 'box-shadow:inset 0 0 0 1px #ddd;' : ''; ?>" onclick="var i=this.parentElement.querySelector('input');i.checked=!i.checked;this.style.borderColor=i.checked?'#446084':'#eee';"></span>
                                <input type="checkbox" name="qp_preset_colors[]" value="<?php echo esc_attr($hex); ?>" style="display:none;">
                                <span style="font-size:9px;color:#888;"><?php echo esc_html($name); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="color" id="qpNewColorHex" value="#ff6600" style="width:40px;height:36px;border:1px solid #ddd;border-radius:6px;cursor:pointer;padding:2px;">
                        <input type="text" id="qpNewColorName" placeholder="Renk adi (ornek: Mercan)" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;width:180px;font-size:13px;">
                        <button type="button" id="qpAddColor" class="button" style="font-weight:600;">+ Renk Ekle</button>
                    </div>
                </div>

                <div class="webyaz-settings-section" style="margin-top:20px;">
                    <h2 class="webyaz-section-title">Ayakkabi Numarasi</h2>
                    <div id="qpShoeWrap" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
                        <?php foreach ($shoe_sizes as $sh): ?>
                            <label style="display:inline-flex;padding:8px 14px;border:2px solid #ddd;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;" onclick="var i=this.querySelector('input');this.style.borderColor=i.checked?'#ddd':'#446084';this.style.background=i.checked?'#fff':'rgba(68,96,132,0.08)';">
                                <input type="checkbox" name="qp_shoes[]" value="<?php echo esc_attr($sh); ?>" style="display:none;">
                                <?php echo esc_html($sh); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="text" id="qpNewShoe" placeholder="Yeni numara (ornek: 48)" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;width:200px;font-size:13px;">
                        <button type="button" id="qpAddShoe" class="button" style="font-weight:600;">+ Numara Ekle</button>
                    </div>
                </div>

                <div class="webyaz-settings-section" style="margin-top:20px;">
                    <h2 class="webyaz-section-title">Satis Birimi</h2>
                    <div id="qpUnitWrap" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
                        <?php foreach ($units as $u): ?>
                            <label style="display:inline-flex;padding:8px 14px;border:2px solid #ddd;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;" onclick="var i=this.querySelector('input');this.style.borderColor=i.checked?'#ddd':'#446084';this.style.background=i.checked?'#fff':'rgba(68,96,132,0.08)';">
                                <input type="checkbox" name="qp_units[]" value="<?php echo esc_attr($u); ?>" style="display:none;">
                                <?php echo esc_html($u); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="text" id="qpNewUnit" placeholder="Yeni birim (ornek: Palet)" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;width:200px;font-size:13px;">
                        <button type="button" id="qpAddUnit" class="button" style="font-weight:600;">+ Birim Ekle</button>
                    </div>
                </div>

                <div class="webyaz-settings-section" style="margin-top:20px;">
                    <h2 class="webyaz-section-title">Ozel Ozellikler</h2>
                    <p style="color:#666;font-size:12px;margin:0 0 12px;">Urun icin ozel ozellik ekleyin (ornek: Materyal → %100 Pamuk)</p>
                    <div id="qpPropsWrap">
                    </div>
                    <button type="button" id="qpAddProp" class="button" style="font-weight:600;margin-top:8px;">+ Ozellik Ekle</button>
                </div>

                <div style="margin-top:20px;display:flex;gap:12px;">
                    <button type="submit" class="button button-primary" style="padding:12px 30px;font-size:15px;font-weight:700;">Urunu Kaydet</button>
                    <button type="submit" name="qp_status" value="draft" class="button" style="padding:12px 20px;font-size:14px;">Taslak Olarak Kaydet</button>
                </div>
            </form>
        </div>

        <script>
            jQuery(document).ready(function($) {

                // Turkce renk ismi haritasi
                var colorNames = {
                    '#000000': 'Siyah',
                    '#ffffff': 'Beyaz',
                    '#e74c3c': 'Kirmizi',
                    '#3498db': 'Mavi',
                    '#2c3e50': 'Lacivert',
                    '#27ae60': 'Yesil',
                    '#f1c40f': 'Sari',
                    '#e67e22': 'Turuncu',
                    '#9b59b6': 'Mor',
                    '#e91e63': 'Pembe',
                    '#95a5a6': 'Gri',
                    '#8d6e63': 'Kahverengi',
                    '#800020': 'Bordo',
                    '#f5f5dc': 'Bej',
                    '#fffdd0': 'Krem'
                };

                function hexToHsl(hex) {
                    var r = parseInt(hex.substr(1, 2), 16) / 255,
                        g = parseInt(hex.substr(3, 2), 16) / 255,
                        b = parseInt(hex.substr(5, 2), 16) / 255;
                    var max = Math.max(r, g, b),
                        min = Math.min(r, g, b),
                        h, s, l = (max + min) / 2;
                    if (max === min) {
                        h = s = 0;
                    } else {
                        var d = max - min;
                        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                        switch (max) {
                            case r:
                                h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
                                break;
                            case g:
                                h = ((b - r) / d + 2) / 6;
                                break;
                            case b:
                                h = ((r - g) / d + 4) / 6;
                                break;
                        }
                    }
                    return [h * 360, s * 100, l * 100];
                }

                function closestColorName(hex) {
                    if (colorNames[hex.toLowerCase()]) return colorNames[hex.toLowerCase()];
                    var hsl = hexToHsl(hex),
                        h = hsl[0],
                        s = hsl[1],
                        l = hsl[2];
                    if (l < 12) return 'Siyah';
                    if (l > 92) return 'Beyaz';
                    if (s < 10) return 'Gri';
                    if (h < 15 || h >= 345) return s < 40 ? 'Kahverengi' : (l < 35 ? 'Bordo' : 'Kirmizi');
                    if (h >= 15 && h < 40) return 'Turuncu';
                    if (h >= 40 && h < 70) return 'Sari';
                    if (h >= 70 && h < 170) return 'Yesil';
                    if (h >= 170 && h < 250) return l < 35 ? 'Lacivert' : 'Mavi';
                    if (h >= 250 && h < 290) return 'Mor';
                    if (h >= 290 && h < 345) return 'Pembe';
                    return '';
                }
                $('#qpNewColorHex').on('input change', function() {
                    var name = closestColorName($(this).val());
                    if (name) $('#qpNewColorName').val(name);
                });
                $('#qpImageBtn').on('click', function(e) {
                    e.preventDefault();
                    if (typeof wp === 'undefined' || !wp.media) {
                        alert('Medya kutuphanesi yuklenemedi. Sayfayi yenileyin.');
                        return;
                    }
                    var frame = wp.media({
                        title: 'Urun Gorseli Sec',
                        button: {
                            text: 'Gorseli Kullan'
                        },
                        multiple: false,
                        library: {
                            type: 'image'
                        }
                    });
                    frame.on('select', function() {
                        var a = frame.state().get('selection').first().toJSON();
                        $('#qpImage').val(a.url);
                        $('#qpImagePreview').html('<img src="' + a.url + '" style="max-width:120px;border-radius:8px;border:1px solid #ddd;">');
                    });
                    frame.open();
                });

                $('#qpAddSize').on('click', function() {
                    var val = $('#qpNewSize').val().trim();
                    if (!val) return;
                    var lbl = $('<label style="display:inline-flex;padding:8px 14px;border:2px solid #446084;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;background:rgba(68,96,132,0.08);">');
                    lbl.html('<input type="checkbox" name="qp_sizes[]" value="' + val + '" style="display:none;" checked> ' + val);
                    lbl.on('click', function() {
                        var i = $(this).find('input');
                        this.style.borderColor = i.is(':checked') ? '#ddd' : '#446084';
                        this.style.background = i.is(':checked') ? '#fff' : 'rgba(68,96,132,0.08)';
                    });
                    $('#qpSizeWrap').append(lbl);
                    $('#qpNewSize').val('');
                });

                $('#qpAddColor').on('click', function() {
                    var hex = $('#qpNewColorHex').val();
                    var name = $('#qpNewColorName').val().trim() || hex;
                    var lbl = $('<label style="display:flex;flex-direction:column;align-items:center;gap:3px;cursor:pointer;" title="' + name + '">');
                    lbl.html('<span style="width:32px;height:32px;border-radius:50%;background:' + hex + ';border:3px solid #446084;display:block;"></span><input type="checkbox" name="qp_preset_colors[]" value="' + hex + '" style="display:none;" checked><span style="font-size:9px;color:#888;">' + name + '</span>');
                    lbl.find('span').first().on('click', function() {
                        var i = $(this).parent().find('input');
                        i.prop('checked', !i.is(':checked'));
                        this.style.borderColor = i.is(':checked') ? '#446084' : '#eee';
                    });
                    $('#qpColorWrap').append(lbl);
                    $('#qpNewColorName').val('');
                });

                // Ayakkabi numarasi ekle
                $('#qpAddShoe').on('click', function() {
                    var val = $('#qpNewShoe').val().trim();
                    if (!val) return;
                    var lbl = $('<label style="display:inline-flex;padding:8px 14px;border:2px solid #446084;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;background:rgba(68,96,132,0.08);">');
                    lbl.html('<input type="checkbox" name="qp_shoes[]" value="' + val + '" style="display:none;" checked> ' + val);
                    lbl.on('click', function() {
                        var i = $(this).find('input');
                        this.style.borderColor = i.is(':checked') ? '#ddd' : '#446084';
                        this.style.background = i.is(':checked') ? '#fff' : 'rgba(68,96,132,0.08)';
                    });
                    $('#qpShoeWrap').append(lbl);
                    $('#qpNewShoe').val('');
                });

                // Satis birimi ekle
                $('#qpAddUnit').on('click', function() {
                    var val = $('#qpNewUnit').val().trim();
                    if (!val) return;
                    var lbl = $('<label style="display:inline-flex;padding:8px 14px;border:2px solid #446084;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;background:rgba(68,96,132,0.08);">');
                    lbl.html('<input type="checkbox" name="qp_units[]" value="' + val + '" style="display:none;" checked> ' + val);
                    lbl.on('click', function() {
                        var i = $(this).find('input');
                        this.style.borderColor = i.is(':checked') ? '#ddd' : '#446084';
                        this.style.background = i.is(':checked') ? '#fff' : 'rgba(68,96,132,0.08)';
                    });
                    $('#qpUnitWrap').append(lbl);
                    $('#qpNewUnit').val('');
                });

                // Ozel ozellik ekle
                $('#qpAddProp').on('click', function() {
                    var row = $('<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">');
                    row.html('<input type="text" name="qp_prop_name[]" placeholder="Ozellik adi (ornek: Materyal)" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;width:200px;font-size:13px;">' +
                        '<input type="text" name="qp_prop_value[]" placeholder="Degeri (ornek: %100 Pamuk)" style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;width:220px;font-size:13px;">' +
                        '<button type="button" onclick="this.parentElement.remove();" style="background:none;border:none;color:#d32f2f;cursor:pointer;font-size:18px;font-weight:bold;">&times;</button>');
                    $('#qpPropsWrap').append(row);
                });
            });
        </script>
<?php
    }
}

new Webyaz_Quick_Product();
