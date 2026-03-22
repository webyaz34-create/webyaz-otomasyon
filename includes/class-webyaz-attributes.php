<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Attributes
{

    private static $default_sizes = array('XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', '5XL');
    private static $default_shoes = array('35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46', '47');
    private static $default_colors = array(
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

    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post_product', array($this, 'save_meta'), 10, 2);
        add_action('woocommerce_single_product_summary', array($this, 'display_attributes'), 28);
        add_action('woocommerce_after_shop_loop_item_title', array($this, 'display_colors_loop'), 8);
    }

    public function add_meta_box()
    {
        add_meta_box(
            'webyaz_attributes',
            'Webyaz Beden & Renk',
            array($this, 'meta_box_html'),
            'product',
            'normal',
            'high'
        );
    }

    public function meta_box_html($post)
    {
        wp_nonce_field('webyaz_attr_nonce', 'webyaz_attr_nonce_field');
        $active = get_post_meta($post->ID, '_webyaz_attrs_active', true);
        $shoes_active = get_post_meta($post->ID, '_webyaz_shoes_active', true);
        $units_active = get_post_meta($post->ID, '_webyaz_units_active', true);
        $sizes = get_post_meta($post->ID, '_webyaz_sizes', true);
        $custom_sizes = get_post_meta($post->ID, '_webyaz_custom_sizes', true);
        $shoes = get_post_meta($post->ID, '_webyaz_shoes', true);
        $custom_shoes = get_post_meta($post->ID, '_webyaz_custom_shoes', true);
        $colors = get_post_meta($post->ID, '_webyaz_colors', true);
        $units = get_post_meta($post->ID, '_webyaz_units', true);
        $custom_units = get_post_meta($post->ID, '_webyaz_custom_units', true);
        $custom_props_active = get_post_meta($post->ID, '_webyaz_custom_props_active', true);
        $custom_props = get_post_meta($post->ID, '_webyaz_custom_props', true);
        if (!is_array($sizes)) $sizes = array();
        if (!is_array($custom_sizes)) $custom_sizes = array();
        if (!is_array($shoes)) $shoes = array();
        if (!is_array($custom_shoes)) $custom_shoes = array();
        if (!is_array($colors)) $colors = array();
        if (!is_array($units)) $units = array();
        if (!is_array($custom_units)) $custom_units = array();
        if (!is_array($custom_props)) $custom_props = array();
?>
        <div style="padding:10px 0;font-family:'Roboto',Arial,sans-serif;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;padding:14px 18px;background:<?php echo $active ? 'linear-gradient(135deg,#e8f5e9,#f1f8e9)' : '#f5f5f5'; ?>;border-radius:10px;border:2px solid <?php echo $active ? '#4caf50' : '#ddd'; ?>;transition:all 0.3s;">
                <label style="position:relative;display:inline-block;width:52px;height:28px;cursor:pointer;">
                    <input type="checkbox" name="webyaz_attrs_active" value="1" <?php checked($active, '1'); ?> id="webyazAttrsToggle" style="display:none;" onchange="var w=document.getElementById('webyazAttrsContent');var p=this.closest('div');if(this.checked){w.style.display='block';p.style.background='linear-gradient(135deg,#e8f5e9,#f1f8e9)';p.style.borderColor='#4caf50';this.nextElementSibling.style.background='#4caf50';}else{w.style.display='none';p.style.background='#f5f5f5';p.style.borderColor='#ddd';this.nextElementSibling.style.background='#ccc';}">
                    <span style="position:absolute;top:0;left:0;right:0;bottom:0;background:<?php echo $active ? '#4caf50' : '#ccc'; ?>;border-radius:28px;transition:0.3s;"></span>
                    <span style="position:absolute;top:3px;left:<?php echo $active ? '27px' : '3px'; ?>;width:22px;height:22px;background:#fff;border-radius:50%;transition:0.3s;box-shadow:0 1px 3px rgba(0,0,0,0.2);"></span>
                </label>
                <div>
                    <span style="font-size:15px;font-weight:700;color:<?php echo $active ? '#2e7d32' : '#888'; ?>;"><?php echo $active ? 'Beden & Renk Aktif' : 'Beden & Renk Kapali'; ?></span>
                    <span style="display:block;font-size:11px;color:#999;margin-top:2px;">Bu urunde ozel beden ve renk seceneklerini gostermek icin aktif edin</span>
                </div>
            </div>
            <div id="webyazAttrsContent" style="display:<?php echo $active ? 'block' : 'none'; ?>;">
                <h3 style="margin:0 0 10px;font-size:15px;font-weight:700;">Bedenler</h3>
                <p style="color:#666;font-size:12px;margin:0 0 10px;">Urun icin gecerli bedenleri secin:</p>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
                    <?php foreach (self::$default_sizes as $s): ?>
                        <label style="display:inline-flex;align-items:center;gap:4px;padding:8px 14px;border:2px solid <?php echo in_array($s, $sizes) ? '#446084' : '#ddd'; ?>;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;background:<?php echo in_array($s, $sizes) ? 'rgba(68,96,132,0.08)' : '#fff'; ?>;transition:all 0.15s;" onchange="this.style.borderColor=this.querySelector('input').checked?'#446084':'#ddd';this.style.background=this.querySelector('input').checked?'rgba(68,96,132,0.08)':'#fff';">
                            <input type="checkbox" name="webyaz_sizes[]" value="<?php echo esc_attr($s); ?>" <?php checked(in_array($s, $sizes)); ?> style="display:none;">
                            <?php echo esc_html($s); ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div style="margin-bottom:15px;padding:12px;background:#f9f9f9;border-radius:8px;border:1px solid #e0e0e0;">
                    <h4 style="margin:0 0 8px;font-size:13px;font-weight:700;">Ozel Beden Ekle</h4>
                    <div style="display:flex;gap:8px;align-items:flex-end;">
                        <input type="text" id="webyazCustomSizeInput" placeholder="ornek: 6XL, 7XL" style="border:1px solid #ddd;border-radius:6px;padding:8px 12px;font-size:13px;width:160px;">
                        <button type="button" id="webyazAddCustomSize" style="background:#446084;color:#fff;border:none;border-radius:6px;padding:9px 16px;font-size:13px;font-weight:600;cursor:pointer;">+ Ekle</button>
                    </div>
                    <div id="webyazCustomSizes" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;">
                        <?php foreach ($custom_sizes as $cs): ?>
                            <div class="webyaz-custom-size-item" style="display:inline-flex;align-items:center;gap:4px;padding:6px 12px;border:2px solid #446084;border-radius:6px;background:rgba(68,96,132,0.08);font-size:13px;font-weight:600;">
                                <?php echo esc_html($cs); ?>
                                <input type="hidden" name="webyaz_custom_sizes[]" value="<?php echo esc_attr($cs); ?>">
                                <button type="button" onclick="this.parentElement.remove();" style="background:none;border:none;color:#d32f2f;cursor:pointer;font-size:16px;padding:0 2px;">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <h3 style="margin:0 0 10px;font-size:15px;font-weight:700;">Renkler</h3>
                <p style="color:#666;font-size:12px;margin:0 0 10px;">Hazir renklerden secin veya ozel renk olusturun:</p>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:15px;" id="webyazColorPresets">
                    <?php foreach (self::$default_colors as $name => $hex):
                        $sel = false;
                        foreach ($colors as $c) {
                            if (isset($c['hex']) && strtolower($c['hex']) === strtolower($hex)) {
                                $sel = true;
                                break;
                            }
                        }
                    ?>
                        <label style="display:flex;flex-direction:column;align-items:center;gap:3px;cursor:pointer;" title="<?php echo esc_attr($name); ?>">
                            <span style="width:36px;height:36px;border-radius:50%;background:<?php echo $hex; ?>;border:3px solid <?php echo $sel ? '#446084' : '#eee'; ?>;display:block;transition:border-color 0.15s;<?php echo $hex === '#ffffff' ? 'box-shadow:inset 0 0 0 1px #ddd;' : ''; ?>" onmouseenter="this.style.borderColor='#446084'" onmouseleave="if(!this.parentElement.querySelector('input').checked)this.style.borderColor='#eee'"></span>
                            <input type="checkbox" class="webyaz-color-preset" name="webyaz_preset_colors[]" value="<?php echo esc_attr($hex); ?>" data-name="<?php echo esc_attr($name); ?>" data-hex="<?php echo esc_attr($hex); ?>" <?php checked($sel); ?> style="display:none;" onchange="this.previousElementSibling.style.borderColor=this.checked?'#446084':'#eee'">
                            <span style="font-size:10px;color:#666;"><?php echo esc_html($name); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div style="margin-bottom:15px;padding:14px;background:#f9f9f9;border-radius:8px;border:1px solid #e0e0e0;">
                    <h4 style="margin:0 0 10px;font-size:13px;font-weight:700;">Ozel Renk Ekle</h4>
                    <div style="display:flex;gap:10px;align-items:flex-end;">
                        <div>
                            <label style="font-size:12px;display:block;margin-bottom:4px;">Renk Adi</label>
                            <input type="text" id="webyazCustomColorName" placeholder="ornek: Mint Yesili" style="border:1px solid #ddd;border-radius:6px;padding:8px 12px;font-size:13px;width:160px;">
                        </div>
                        <div>
                            <label style="font-size:12px;display:block;margin-bottom:4px;">Renk Sec</label>
                            <input type="color" id="webyazCustomColorHex" value="#446084" style="width:50px;height:38px;border:1px solid #ddd;border-radius:6px;cursor:pointer;padding:2px;" oninput="document.getElementById('webyazCustomColorName').value=webyazGetColorName(this.value);">
                        </div>
                        <button type="button" id="webyazAddCustomColor" style="background:#446084;color:#fff;border:none;border-radius:6px;padding:10px 18px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">+ Ekle</button>
                    </div>
                </div>

                <div id="webyazCustomColors" style="display:flex;flex-wrap:wrap;gap:8px;">
                    <?php foreach ($colors as $c):
                        if (empty($c['custom'])) continue;
                    ?>
                        <div class="webyaz-custom-color-item" style="display:flex;align-items:center;gap:6px;padding:6px 12px;background:#fff;border:1px solid #e0e0e0;border-radius:20px;">
                            <span style="width:20px;height:20px;border-radius:50%;background:<?php echo esc_attr($c['hex']); ?>;display:block;border:1px solid rgba(0,0,0,0.1);"></span>
                            <span style="font-size:12px;font-weight:600;"><?php echo esc_html($c['name']); ?></span>
                            <input type="hidden" name="webyaz_custom_colors[]" value="<?php echo esc_attr($c['name'] . '|' . $c['hex']); ?>">
                            <button type="button" onclick="this.parentElement.remove();" style="background:none;border:none;color:#d32f2f;cursor:pointer;font-size:16px;padding:0 2px;">&times;</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="margin-top:20px;margin-bottom:20px;padding:16px;background:#fafafa;border-radius:10px;border:1px solid #e0e0e0;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                    <label style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer;">
                        <input type="checkbox" name="webyaz_shoes_active" value="1" <?php checked($shoes_active, '1'); ?> id="webyazShoesToggle" style="display:none;" onchange="var c=document.getElementById('webyazShoesContent');c.style.display=this.checked?'block':'none';this.nextElementSibling.style.background=this.checked?'#446084':'#ccc';this.parentElement.querySelectorAll('span')[1].style.left=this.checked?'23px':'3px';">
                        <span style="position:absolute;top:0;left:0;right:0;bottom:0;background:<?php echo $shoes_active ? '#446084' : '#ccc'; ?>;border-radius:24px;transition:0.3s;"></span>
                        <span style="position:absolute;top:3px;left:<?php echo $shoes_active ? '23px' : '3px'; ?>;width:18px;height:18px;background:#fff;border-radius:50%;transition:0.3s;box-shadow:0 1px 3px rgba(0,0,0,0.2);"></span>
                    </label>
                    <h3 style="margin:0;font-size:15px;font-weight:700;">Ayakkabi Numaralari</h3>
                </div>
                <div id="webyazShoesContent" style="display:<?php echo $shoes_active ? 'block' : 'none'; ?>;">
                    <p style="color:#666;font-size:12px;margin:0 0 10px;">Urun icin gecerli numaralari secin:</p>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
                        <?php foreach (self::$default_shoes as $sh): ?>
                            <label style="display:inline-flex;align-items:center;gap:4px;padding:8px 14px;border:2px solid <?php echo in_array($sh, $shoes) ? '#446084' : '#ddd'; ?>;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;background:<?php echo in_array($sh, $shoes) ? 'rgba(68,96,132,0.08)' : '#fff'; ?>;transition:all 0.15s;" onchange="this.style.borderColor=this.querySelector('input').checked?'#446084':'#ddd';this.style.background=this.querySelector('input').checked?'rgba(68,96,132,0.08)':'#fff';">
                                <input type="checkbox" name="webyaz_shoes[]" value="<?php echo esc_attr($sh); ?>" <?php checked(in_array($sh, $shoes)); ?> style="display:none;">
                                <?php echo esc_html($sh); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div style="padding:12px;background:#fff;border-radius:8px;border:1px solid #e0e0e0;">
                        <h4 style="margin:0 0 8px;font-size:13px;font-weight:700;">Ozel Numara Ekle</h4>
                        <div style="display:flex;gap:8px;align-items:flex-end;">
                            <input type="text" id="webyazCustomShoeInput" placeholder="ornek: 48, 49" style="border:1px solid #ddd;border-radius:6px;padding:8px 12px;font-size:13px;width:140px;">
                            <button type="button" id="webyazAddCustomShoe" style="background:#446084;color:#fff;border:none;border-radius:6px;padding:9px 16px;font-size:13px;font-weight:600;cursor:pointer;">+ Ekle</button>
                        </div>
                        <div id="webyazCustomShoes" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;">
                            <?php foreach ($custom_shoes as $csh): ?>
                                <div style="display:inline-flex;align-items:center;gap:4px;padding:6px 12px;border:2px solid #446084;border-radius:6px;background:rgba(68,96,132,0.08);font-size:13px;font-weight:600;">
                                    <?php echo esc_html($csh); ?>
                                    <input type="hidden" name="webyaz_custom_shoes[]" value="<?php echo esc_attr($csh); ?>">
                                    <button type="button" onclick="this.parentElement.remove();" style="background:none;border:none;color:#d32f2f;cursor:pointer;font-size:16px;padding:0 2px;">&times;</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:20px;margin-bottom:20px;padding:16px;background:#fafafa;border-radius:10px;border:1px solid #e0e0e0;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                    <label style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer;">
                        <input type="checkbox" name="webyaz_units_active" value="1" <?php checked($units_active, '1'); ?> id="webyazUnitsToggle" style="display:none;" onchange="var c=document.getElementById('webyazUnitsContent');c.style.display=this.checked?'block':'none';this.nextElementSibling.style.background=this.checked?'#446084':'#ccc';this.parentElement.querySelectorAll('span')[1].style.left=this.checked?'23px':'3px';">
                        <span style="position:absolute;top:0;left:0;right:0;bottom:0;background:<?php echo $units_active ? '#446084' : '#ccc'; ?>;border-radius:24px;transition:0.3s;"></span>
                        <span style="position:absolute;top:3px;left:<?php echo $units_active ? '23px' : '3px'; ?>;width:18px;height:18px;background:#fff;border-radius:50%;transition:0.3s;box-shadow:0 1px 3px rgba(0,0,0,0.2);"></span>
                    </label>
                    <h3 style="margin:0;font-size:15px;font-weight:700;">Satis Birimi</h3>
                </div>
                <div id="webyazUnitsContent" style="display:<?php echo $units_active ? 'block' : 'none'; ?>;">
                    <p style="color:#666;font-size:12px;margin:0 0 10px;">Toptan satis birimleri secin veya ozel birim ekleyin:</p>
                    <?php
                    $default_units = array(
                        '1 Duzine (12 Adet)' => '1 Duzine (12 Adet)',
                        '1 Gross (144 Adet)' => '1 Gross (144 Adet)',
                        '1 Paket' => '1 Paket',
                    );
                    ?>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
                        <?php foreach ($default_units as $uk => $uv): ?>
                            <label style="display:inline-flex;align-items:center;gap:4px;padding:8px 14px;border:2px solid <?php echo in_array($uk, $units) ? '#446084' : '#ddd'; ?>;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;background:<?php echo in_array($uk, $units) ? 'rgba(68,96,132,0.08)' : '#fff'; ?>;transition:all 0.15s;" onchange="this.style.borderColor=this.querySelector('input').checked?'#446084':'#ddd';this.style.background=this.querySelector('input').checked?'rgba(68,96,132,0.08)':'#fff';">
                                <input type="checkbox" name="webyaz_units[]" value="<?php echo esc_attr($uk); ?>" <?php checked(in_array($uk, $units)); ?> style="display:none;">
                                <?php echo esc_html($uv); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div style="padding:12px;background:#fff;border-radius:8px;border:1px solid #e0e0e0;">
                        <h4 style="margin:0 0 8px;font-size:13px;font-weight:700;">Ozel Birim Ekle</h4>
                        <div style="display:flex;gap:8px;align-items:flex-end;">
                            <input type="text" id="webyazCustomUnitInput" placeholder="ornek: 1 Koli (50 Adet)" style="border:1px solid #ddd;border-radius:6px;padding:8px 12px;font-size:13px;width:220px;">
                            <button type="button" id="webyazAddCustomUnit" style="background:#446084;color:#fff;border:none;border-radius:6px;padding:9px 16px;font-size:13px;font-weight:600;cursor:pointer;">+ Ekle</button>
                        </div>
                        <div id="webyazCustomUnits" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;">
                            <?php foreach ($custom_units as $cu): ?>
                                <div style="display:inline-flex;align-items:center;gap:4px;padding:6px 12px;border:2px solid #446084;border-radius:6px;background:rgba(68,96,132,0.08);font-size:13px;font-weight:600;">
                                    <?php echo esc_html($cu); ?>
                                    <input type="hidden" name="webyaz_custom_units[]" value="<?php echo esc_attr($cu); ?>">
                                    <button type="button" onclick="this.parentElement.remove();" style="background:none;border:none;color:#d32f2f;cursor:pointer;font-size:16px;padding:0 2px;">&times;</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:20px;padding-top:18px;border-top:2px solid #e0e0e0;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;padding:12px 16px;background:<?php echo $custom_props_active ? 'linear-gradient(135deg,#e3f2fd,#f3e5f5)' : '#f5f5f5'; ?>;border-radius:10px;border:2px solid <?php echo $custom_props_active ? '#7b1fa2' : '#ddd'; ?>;">
                    <label style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer;">
                        <input type="checkbox" name="webyaz_custom_props_active" value="1" <?php checked($custom_props_active, '1'); ?> id="webyazPropsToggle" style="display:none;" onchange="var w=document.getElementById('webyazPropsContent');var p=this.closest('div').parentElement;this.nextElementSibling.style.background=this.checked?'#7b1fa2':'#ccc';this.parentElement.querySelectorAll('span')[1].style.left=this.checked?'22px':'2px';if(this.checked){w.style.display='block';p.style.background='linear-gradient(135deg,#e3f2fd,#f3e5f5)';p.style.borderColor='#7b1fa2';}else{w.style.display='none';p.style.background='#f5f5f5';p.style.borderColor='#ddd';}">
                        <span style="position:absolute;top:0;left:0;right:0;bottom:0;background:<?php echo $custom_props_active ? '#7b1fa2' : '#ccc'; ?>;border-radius:24px;transition:0.3s;"></span>
                        <span style="position:absolute;top:2px;left:<?php echo $custom_props_active ? '22px' : '2px'; ?>;width:20px;height:20px;background:#fff;border-radius:50%;transition:0.3s;box-shadow:0 1px 3px rgba(0,0,0,0.2);"></span>
                    </label>
                    <div>
                        <span style="font-size:14px;font-weight:700;color:<?php echo $custom_props_active ? '#7b1fa2' : '#888'; ?>;">Akilli Ozellik</span>
                        <span style="display:block;font-size:11px;color:#999;">Urune ozel ozellikler ekleyin (Malzeme, Kumaş, Garanti vb.)</span>
                    </div>
                </div>
                <div id="webyazPropsContent" style="display:<?php echo $custom_props_active ? 'block' : 'none'; ?>;">
                    <div id="webyazPropsList">
                        <?php if (!empty($custom_props)):
                            foreach ($custom_props as $prop): ?>
                                <div class="webyaz-prop-item" style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
                                    <input type="text" name="webyaz_prop_keys[]" value="<?php echo esc_attr($prop['key']); ?>" placeholder="Ozellik adi (orn: Malzeme)" style="flex:1;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;font-weight:600;">
                                    <input type="text" name="webyaz_prop_vals[]" value="<?php echo esc_attr($prop['val']); ?>" placeholder="Secenekler (virgul ile: Pamuk, Polyester, Keten)" style="flex:2;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
                                    <button type="button" onclick="this.parentElement.remove();" style="background:#c62828;color:#fff;border:none;border-radius:6px;width:32px;height:36px;cursor:pointer;font-size:18px;font-weight:700;">&times;</button>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </div>
                    <button type="button" id="webyazAddProp" style="background:#7b1fa2;color:#fff;border:none;border-radius:8px;padding:10px 20px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;margin-top:4px;">
                        <span style="font-size:20px;line-height:1;">+</span> Ozellik Ekle
                    </button>
                </div>
            </div>

        </div>

        <script>
            // --- Turkce Renk Adi Cozumleyici ---
            function webyazGetColorName(hex) {
                var r = parseInt(hex.substr(1, 2), 16),
                    g = parseInt(hex.substr(3, 2), 16),
                    b = parseInt(hex.substr(5, 2), 16);
                var colors = [
                    // Kirmizi tonlari
                    {
                        n: 'Kirmizi',
                        r: 231,
                        g: 76,
                        b: 60
                    }, {
                        n: 'Koyu Kirmizi',
                        r: 192,
                        g: 57,
                        b: 43
                    }, {
                        n: 'Acik Kirmizi',
                        r: 255,
                        g: 99,
                        b: 71
                    },
                    {
                        n: 'Bordo',
                        r: 128,
                        g: 0,
                        b: 32
                    }, {
                        n: 'Nar Cicegi',
                        r: 220,
                        g: 20,
                        b: 60
                    }, {
                        n: 'Mercan',
                        r: 255,
                        g: 127,
                        b: 80
                    },
                    {
                        n: 'Kan Kirmizisi',
                        r: 138,
                        g: 7,
                        b: 7
                    }, {
                        n: 'Gul Kurusu',
                        r: 190,
                        g: 60,
                        b: 80
                    },
                    // Pembe tonlari
                    {
                        n: 'Pembe',
                        r: 233,
                        g: 30,
                        b: 99
                    }, {
                        n: 'Acik Pembe',
                        r: 255,
                        g: 182,
                        b: 193
                    }, {
                        n: 'Koyu Pembe',
                        r: 199,
                        g: 21,
                        b: 133
                    },
                    {
                        n: 'Fusya',
                        r: 255,
                        g: 0,
                        b: 255
                    }, {
                        n: 'Somon',
                        r: 250,
                        g: 128,
                        b: 114
                    }, {
                        n: 'Seftali',
                        r: 255,
                        g: 218,
                        b: 185
                    },
                    // Turuncu tonlari
                    {
                        n: 'Turuncu',
                        r: 230,
                        g: 126,
                        b: 34
                    }, {
                        n: 'Koyu Turuncu',
                        r: 211,
                        g: 84,
                        b: 0
                    }, {
                        n: 'Acik Turuncu',
                        r: 255,
                        g: 165,
                        b: 0
                    },
                    {
                        n: 'Kayisi',
                        r: 251,
                        g: 206,
                        b: 177
                    }, {
                        n: 'Kiremit',
                        r: 178,
                        g: 92,
                        b: 53
                    }, {
                        n: 'Bal',
                        r: 235,
                        g: 168,
                        b: 0
                    },
                    // Sari tonlari
                    {
                        n: 'Sari',
                        r: 241,
                        g: 196,
                        b: 15
                    }, {
                        n: 'Koyu Sari',
                        r: 243,
                        g: 156,
                        b: 18
                    }, {
                        n: 'Acik Sari',
                        r: 255,
                        g: 255,
                        b: 102
                    },
                    {
                        n: 'Altin',
                        r: 255,
                        g: 215,
                        b: 0
                    }, {
                        n: 'Limon',
                        r: 255,
                        g: 247,
                        b: 0
                    }, {
                        n: 'Hardal',
                        r: 204,
                        g: 170,
                        b: 0
                    },
                    // Yesil tonlari
                    {
                        n: 'Yesil',
                        r: 39,
                        g: 174,
                        b: 96
                    }, {
                        n: 'Koyu Yesil',
                        r: 30,
                        g: 100,
                        b: 0
                    }, {
                        n: 'Acik Yesil',
                        r: 144,
                        g: 238,
                        b: 144
                    },
                    {
                        n: 'Zumrut Yesili',
                        r: 0,
                        g: 128,
                        b: 0
                    }, {
                        n: 'Mint Yesili',
                        r: 152,
                        g: 255,
                        b: 152
                    }, {
                        n: 'Haki',
                        r: 195,
                        g: 176,
                        b: 145
                    },
                    {
                        n: 'Zeytin Yesili',
                        r: 128,
                        g: 128,
                        b: 0
                    }, {
                        n: 'Cimen Yesili',
                        r: 124,
                        g: 252,
                        b: 0
                    }, {
                        n: 'Nane',
                        r: 62,
                        g: 180,
                        b: 137
                    },
                    {
                        n: 'Cam Gobegi',
                        r: 0,
                        g: 139,
                        b: 139
                    }, {
                        n: 'Yosun',
                        r: 173,
                        g: 223,
                        b: 173
                    }, {
                        n: 'Fistik Yesili',
                        r: 147,
                        g: 197,
                        b: 114
                    },
                    // Mavi tonlari
                    {
                        n: 'Mavi',
                        r: 52,
                        g: 152,
                        b: 219
                    }, {
                        n: 'Koyu Mavi',
                        r: 0,
                        g: 0,
                        b: 139
                    }, {
                        n: 'Acik Mavi',
                        r: 135,
                        g: 206,
                        b: 250
                    },
                    {
                        n: 'Lacivert',
                        r: 44,
                        g: 62,
                        b: 80
                    }, {
                        n: 'Gok Mavisi',
                        r: 135,
                        g: 206,
                        b: 235
                    }, {
                        n: 'Turkuaz',
                        r: 0,
                        g: 206,
                        b: 209
                    },
                    {
                        n: 'Bebek Mavisi',
                        r: 137,
                        g: 207,
                        b: 240
                    }, {
                        n: 'Kobalt',
                        r: 0,
                        g: 71,
                        b: 171
                    }, {
                        n: 'Deniz Mavisi',
                        r: 70,
                        g: 130,
                        b: 180
                    },
                    {
                        n: 'Indigo',
                        r: 75,
                        g: 0,
                        b: 130
                    }, {
                        n: 'Gece Mavisi',
                        r: 25,
                        g: 25,
                        b: 112
                    }, {
                        n: 'Buz Mavisi',
                        r: 173,
                        g: 216,
                        b: 230
                    },
                    {
                        n: 'Petrol Mavisi',
                        r: 0,
                        g: 100,
                        b: 100
                    }, {
                        n: 'Elektrik Mavisi',
                        r: 0,
                        g: 119,
                        b: 182
                    },
                    // Mor tonlari
                    {
                        n: 'Mor',
                        r: 155,
                        g: 89,
                        b: 182
                    }, {
                        n: 'Koyu Mor',
                        r: 85,
                        g: 0,
                        b: 85
                    }, {
                        n: 'Acik Mor',
                        r: 200,
                        g: 162,
                        b: 200
                    },
                    {
                        n: 'Lila',
                        r: 200,
                        g: 162,
                        b: 200
                    }, {
                        n: 'Eflatun',
                        r: 128,
                        g: 0,
                        b: 128
                    }, {
                        n: 'Lavanta',
                        r: 230,
                        g: 230,
                        b: 250
                    },
                    {
                        n: 'Leylak',
                        r: 196,
                        g: 164,
                        b: 210
                    }, {
                        n: 'Ametist',
                        r: 153,
                        g: 102,
                        b: 204
                    }, {
                        n: 'Erik',
                        r: 142,
                        g: 69,
                        b: 133
                    },
                    // Kahverengi tonlari
                    {
                        n: 'Kahverengi',
                        r: 141,
                        g: 110,
                        b: 99
                    }, {
                        n: 'Koyu Kahve',
                        r: 92,
                        g: 64,
                        b: 51
                    }, {
                        n: 'Acik Kahve',
                        r: 196,
                        g: 164,
                        b: 132
                    },
                    {
                        n: 'Taba',
                        r: 178,
                        g: 132,
                        b: 72
                    }, {
                        n: 'Deve Tuyü',
                        r: 193,
                        g: 154,
                        b: 107
                    }, {
                        n: 'Cikolata',
                        r: 123,
                        g: 63,
                        b: 0
                    },
                    {
                        n: 'Tarçin',
                        r: 210,
                        g: 105,
                        b: 30
                    }, {
                        n: 'Bakir',
                        r: 184,
                        g: 115,
                        b: 51
                    }, {
                        n: 'Karamel',
                        r: 255,
                        g: 195,
                        b: 72
                    },
                    // Gri tonlari
                    {
                        n: 'Gri',
                        r: 149,
                        g: 165,
                        b: 166
                    }, {
                        n: 'Koyu Gri',
                        r: 64,
                        g: 64,
                        b: 64
                    }, {
                        n: 'Acik Gri',
                        r: 211,
                        g: 211,
                        b: 211
                    },
                    {
                        n: 'Gumus',
                        r: 192,
                        g: 192,
                        b: 192
                    }, {
                        n: 'Antrasit',
                        r: 54,
                        g: 54,
                        b: 54
                    }, {
                        n: 'Kursun',
                        r: 119,
                        g: 136,
                        b: 153
                    },
                    {
                        n: 'Fume',
                        r: 80,
                        g: 80,
                        b: 80
                    }, {
                        n: 'Grafit',
                        r: 65,
                        g: 65,
                        b: 65
                    }, {
                        n: 'Tas Rengi',
                        r: 170,
                        g: 170,
                        b: 170
                    },
                    // Siyah-Beyaz
                    {
                        n: 'Siyah',
                        r: 0,
                        g: 0,
                        b: 0
                    }, {
                        n: 'Beyaz',
                        r: 255,
                        g: 255,
                        b: 255
                    },
                    {
                        n: 'Krem',
                        r: 255,
                        g: 253,
                        b: 208
                    }, {
                        n: 'Bej',
                        r: 245,
                        g: 245,
                        b: 220
                    }, {
                        n: 'Kemik',
                        r: 255,
                        g: 255,
                        b: 240
                    },
                    {
                        n: 'Fildisi',
                        r: 255,
                        g: 255,
                        b: 240
                    }, {
                        n: 'Sampanya',
                        r: 247,
                        g: 231,
                        b: 206
                    },
                ];
                var closest = '',
                    minDist = 999999;
                for (var i = 0; i < colors.length; i++) {
                    var c = colors[i];
                    var dr = r - c.r,
                        dg = g - c.g,
                        db = b - c.b;
                    var dist = dr * dr + dg * dg + db * db;
                    if (dist < minDist) {
                        minDist = dist;
                        closest = c.n;
                    }
                }
                return closest;
            }
            document.getElementById('webyazAddProp').addEventListener('click', function() {
                var html = '<div class="webyaz-prop-item" style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">';
                html += '<input type="text" name="webyaz_prop_keys[]" value="" placeholder="Ozellik adi (orn: Malzeme)" style="flex:1;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;font-weight:600;">';
                html += '<input type="text" name="webyaz_prop_vals[]" value="" placeholder="Secenekler (virgul ile: Pamuk, Polyester, Keten)" style="flex:2;padding:8px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;">';
                html += '<button type="button" onclick="this.parentElement.remove();" style="background:#c62828;color:#fff;border:none;border-radius:6px;width:32px;height:36px;cursor:pointer;font-size:18px;font-weight:700;">&times;</button>';
                html += '</div>';
                document.getElementById('webyazPropsList').insertAdjacentHTML('beforeend', html);
            });
            document.getElementById('webyazAddCustomSize').addEventListener('click', function() {
                var input = document.getElementById('webyazCustomSizeInput');
                var val = input.value.trim().toUpperCase();
                if (!val) {
                    alert('Beden girin.');
                    return;
                }
                var html = '<div class="webyaz-custom-size-item" style="display:inline-flex;align-items:center;gap:4px;padding:6px 12px;border:2px solid #446084;border-radius:6px;background:rgba(68,96,132,0.08);font-size:13px;font-weight:600;">';
                html += val;
                html += '<input type="hidden" name="webyaz_custom_sizes[]" value="' + val + '">';
                html += '<button type="button" onclick="this.parentElement.remove();" style="background:none;border:none;color:#d32f2f;cursor:pointer;font-size:16px;padding:0 2px;">&times;</button></div>';
                document.getElementById('webyazCustomSizes').insertAdjacentHTML('beforeend', html);
                input.value = '';
            });
            document.getElementById('webyazAddCustomShoe').addEventListener('click', function() {
                var input = document.getElementById('webyazCustomShoeInput');
                var val = input.value.trim();
                if (!val) {
                    alert('Numara girin.');
                    return;
                }
                var html = '<div style="display:inline-flex;align-items:center;gap:4px;padding:6px 12px;border:2px solid #446084;border-radius:6px;background:rgba(68,96,132,0.08);font-size:13px;font-weight:600;">';
                html += val;
                html += '<input type="hidden" name="webyaz_custom_shoes[]" value="' + val + '">';
                html += '<button type="button" onclick="this.parentElement.remove();" style="background:none;border:none;color:#d32f2f;cursor:pointer;font-size:16px;padding:0 2px;">&times;</button></div>';
                document.getElementById('webyazCustomShoes').insertAdjacentHTML('beforeend', html);
                input.value = '';
            });
            document.getElementById('webyazAddCustomUnit').addEventListener('click', function() {
                var input = document.getElementById('webyazCustomUnitInput');
                var val = input.value.trim();
                if (!val) {
                    alert('Birim adi girin.');
                    return;
                }
                var html = '<div style="display:inline-flex;align-items:center;gap:4px;padding:6px 12px;border:2px solid #446084;border-radius:6px;background:rgba(68,96,132,0.08);font-size:13px;font-weight:600;">';
                html += val;
                html += '<input type="hidden" name="webyaz_custom_units[]" value="' + val + '">';
                html += '<button type="button" onclick="this.parentElement.remove();" style="background:none;border:none;color:#d32f2f;cursor:pointer;font-size:16px;padding:0 2px;">&times;</button></div>';
                document.getElementById('webyazCustomUnits').insertAdjacentHTML('beforeend', html);
                input.value = '';
            });
            document.getElementById('webyazAddCustomColor').addEventListener('click', function() {
                var name = document.getElementById('webyazCustomColorName').value.trim();
                var hex = document.getElementById('webyazCustomColorHex').value;
                if (!name) {
                    alert('Renk adi girin.');
                    return;
                }
                var html = '<div class="webyaz-custom-color-item" style="display:flex;align-items:center;gap:6px;padding:6px 12px;background:#fff;border:1px solid #e0e0e0;border-radius:20px;">';
                html += '<span style="width:20px;height:20px;border-radius:50%;background:' + hex + ';display:block;border:1px solid rgba(0,0,0,0.1);"></span>';
                html += '<span style="font-size:12px;font-weight:600;">' + name + '</span>';
                html += '<input type="hidden" name="webyaz_custom_colors[]" value="' + name + '|' + hex + '">';
                html += '<button type="button" onclick="this.parentElement.remove();" style="background:none;border:none;color:#d32f2f;cursor:pointer;font-size:16px;padding:0 2px;">&times;</button></div>';
                document.getElementById('webyazCustomColors').insertAdjacentHTML('beforeend', html);
                document.getElementById('webyazCustomColorName').value = '';
            });
            var toggle = document.getElementById('webyazAttrsToggle');
            toggle.addEventListener('change', function() {
                var knob = this.parentElement.querySelectorAll('span')[1];
                knob.style.left = this.checked ? '27px' : '3px';
            });
        </script>
    <?php
    }

    public function save_meta($post_id, $post)
    {
        if (!isset($_POST['webyaz_attr_nonce_field']) || !wp_verify_nonce($_POST['webyaz_attr_nonce_field'], 'webyaz_attr_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        $active = isset($_POST['webyaz_attrs_active']) ? '1' : '0';
        update_post_meta($post_id, '_webyaz_attrs_active', $active);

        $shoes_active = isset($_POST['webyaz_shoes_active']) ? '1' : '0';
        update_post_meta($post_id, '_webyaz_shoes_active', $shoes_active);

        $sizes = isset($_POST['webyaz_sizes']) ? array_map('sanitize_text_field', $_POST['webyaz_sizes']) : array();
        update_post_meta($post_id, '_webyaz_sizes', $sizes);

        $custom_sizes = isset($_POST['webyaz_custom_sizes']) ? array_map('sanitize_text_field', $_POST['webyaz_custom_sizes']) : array();
        update_post_meta($post_id, '_webyaz_custom_sizes', $custom_sizes);

        $shoes = isset($_POST['webyaz_shoes']) ? array_map('sanitize_text_field', $_POST['webyaz_shoes']) : array();
        update_post_meta($post_id, '_webyaz_shoes', $shoes);

        $custom_shoes = isset($_POST['webyaz_custom_shoes']) ? array_map('sanitize_text_field', $_POST['webyaz_custom_shoes']) : array();
        update_post_meta($post_id, '_webyaz_custom_shoes', $custom_shoes);

        $units_active = isset($_POST['webyaz_units_active']) ? '1' : '0';
        update_post_meta($post_id, '_webyaz_units_active', $units_active);

        $units = isset($_POST['webyaz_units']) ? array_map('sanitize_text_field', $_POST['webyaz_units']) : array();
        update_post_meta($post_id, '_webyaz_units', $units);

        $custom_units = isset($_POST['webyaz_custom_units']) ? array_map('sanitize_text_field', $_POST['webyaz_custom_units']) : array();
        update_post_meta($post_id, '_webyaz_custom_units', $custom_units);

        $colors = array();
        if (isset($_POST['webyaz_custom_colors']) && is_array($_POST['webyaz_custom_colors'])) {
            foreach ($_POST['webyaz_custom_colors'] as $val) {
                $parts = explode('|', sanitize_text_field($val));
                if (count($parts) === 2) {
                    $colors[] = array('name' => $parts[0], 'hex' => $parts[1], 'custom' => true);
                }
            }
        }
        update_post_meta($post_id, '_webyaz_colors', $colors);

        $preset = isset($_POST['webyaz_preset_colors']) ? array_map('sanitize_text_field', $_POST['webyaz_preset_colors']) : array();
        update_post_meta($post_id, '_webyaz_preset_colors', $preset);

        $custom_props_active = isset($_POST['webyaz_custom_props_active']) ? '1' : '0';
        update_post_meta($post_id, '_webyaz_custom_props_active', $custom_props_active);

        $prop_keys = isset($_POST['webyaz_prop_keys']) ? $_POST['webyaz_prop_keys'] : array();
        $prop_vals = isset($_POST['webyaz_prop_vals']) ? $_POST['webyaz_prop_vals'] : array();
        $custom_props = array();
        foreach ($prop_keys as $i => $key) {
            $k = sanitize_text_field($key);
            $v = sanitize_text_field(isset($prop_vals[$i]) ? $prop_vals[$i] : '');
            if (!empty($k)) $custom_props[] = array('key' => $k, 'val' => $v);
        }
        update_post_meta($post_id, '_webyaz_custom_props', $custom_props);
    }

    public function display_attributes()
    {
        global $product;
        if (!$product) return;
        $pid = $product->get_id();
        $attrs_active = get_post_meta($pid, '_webyaz_attrs_active', true) === '1';
        $custom_props_active_check = get_post_meta($pid, '_webyaz_custom_props_active', true) === '1';
        $custom_props_check = get_post_meta($pid, '_webyaz_custom_props', true);
        if (!is_array($custom_props_check)) $custom_props_check = array();
        $units_active_check = get_post_meta($pid, '_webyaz_units_active', true) === '1';
        $units_check = get_post_meta($pid, '_webyaz_units', true);
        $custom_units_check = get_post_meta($pid, '_webyaz_custom_units', true);
        if (!is_array($units_check)) $units_check = array();
        if (!is_array($custom_units_check)) $custom_units_check = array();
        $has_units = $units_active_check && (!empty($units_check) || !empty($custom_units_check));
        $has_props = $custom_props_active_check && !empty($custom_props_check);
        if (!$attrs_active && !$has_units && !$has_props) return;
        $sizes = get_post_meta($pid, '_webyaz_sizes', true);
        $custom_sizes = get_post_meta($pid, '_webyaz_custom_sizes', true);
        $shoes_active = get_post_meta($pid, '_webyaz_shoes_active', true);
        $shoes = get_post_meta($pid, '_webyaz_shoes', true);
        $custom_shoes = get_post_meta($pid, '_webyaz_custom_shoes', true);
        $colors = get_post_meta($pid, '_webyaz_colors', true);
        $preset_colors = get_post_meta($pid, '_webyaz_preset_colors', true);
        if (!is_array($sizes)) $sizes = array();
        if (!is_array($custom_sizes)) $custom_sizes = array();
        if (!is_array($shoes)) $shoes = array();
        if (!is_array($custom_shoes)) $custom_shoes = array();
        if (!is_array($colors)) $colors = array();
        if (!is_array($preset_colors)) $preset_colors = array();

        $all_sizes = array_merge($sizes, $custom_sizes);
        $all_shoes = ($shoes_active === '1') ? array_merge($shoes, $custom_shoes) : array();

        $units_active = get_post_meta($pid, '_webyaz_units_active', true);
        $units_data = get_post_meta($pid, '_webyaz_units', true);
        $custom_units_data = get_post_meta($pid, '_webyaz_custom_units', true);
        if (!is_array($units_data)) $units_data = array();
        if (!is_array($custom_units_data)) $custom_units_data = array();
        $all_units = ($units_active === '1') ? array_merge($units_data, $custom_units_data) : array();

        $all_colors = array();
        foreach ($preset_colors as $hex) {
            foreach (self::$default_colors as $name => $h) {
                if (strtolower($h) === strtolower($hex)) {
                    $all_colors[] = array('name' => $name, 'hex' => $h);
                    break;
                }
            }
        }
        foreach ($colors as $c) {
            if (isset($c['hex'])) $all_colors[] = $c;
        }

        if (empty($all_sizes) && empty($all_shoes) && empty($all_colors) && empty($all_units) && !$has_props) return;
        $custom_props_active = get_post_meta($pid, '_webyaz_custom_props_active', true);
        $custom_props = get_post_meta($pid, '_webyaz_custom_props', true);
        if (!is_array($custom_props)) $custom_props = array();
    ?>
        <div class="webyaz-product-attrs">
            <?php if (!empty($all_colors)): ?>
                <div class="webyaz-attr-row">
                    <span class="webyaz-attr-label">Renk:</span>
                    <div class="webyaz-color-swatches">
                        <?php foreach ($all_colors as $c): ?>
                            <span class="webyaz-color-swatch" title="<?php echo esc_attr($c['name']); ?>" data-color="<?php echo esc_attr($c['name']); ?>" style="background:<?php echo esc_attr($c['hex']); ?>;<?php echo strtolower($c['hex']) === '#ffffff' || strtolower($c['hex']) === '#fffdd0' || strtolower($c['hex']) === '#f5f5dc' ? 'box-shadow:inset 0 0 0 1px rgba(0,0,0,0.12);' : ''; ?>"></span>
                        <?php endforeach; ?>
                    </div>
                    <span class="webyaz-color-name" id="webyazColorName"></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($all_sizes)): ?>
                <div class="webyaz-attr-row">
                    <span class="webyaz-attr-label">Beden:</span>
                    <div class="webyaz-size-swatches">
                        <?php foreach ($all_sizes as $s): ?>
                            <span class="webyaz-size-swatch" data-size="<?php echo esc_attr($s); ?>"><?php echo esc_html($s); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($all_shoes)): ?>
                <div class="webyaz-attr-row">
                    <span class="webyaz-attr-label">Numara:</span>
                    <div class="webyaz-size-swatches">
                        <?php foreach ($all_shoes as $sh): ?>
                            <span class="webyaz-size-swatch webyaz-shoe-swatch" data-shoe="<?php echo esc_attr($sh); ?>"><?php echo esc_html($sh); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($all_units)): ?>
                <div class="webyaz-attr-row">
                    <span class="webyaz-attr-label">Satis Birimi:</span>
                    <div class="webyaz-size-swatches">
                        <?php foreach ($all_units as $u): ?>
                            <span class="webyaz-size-swatch webyaz-unit-swatch" data-unit="<?php echo esc_attr($u); ?>"><?php echo esc_html($u); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($custom_props_active === '1' && !empty($custom_props)): ?>
                <?php foreach ($custom_props as $pi => $prop):
                    $vals = array_map('trim', preg_split('/[,،;\/|]+/', $prop['val']));
                    $vals = array_filter($vals, function ($v) {
                        return $v !== '';
                    });
                ?>
                    <div class="webyaz-attr-row" style="margin-bottom:8px;">
                        <span class="webyaz-attr-label"><?php echo esc_html($prop['key']); ?>:</span>
                        <div class="webyaz-size-swatches">
                            <?php foreach ($vals as $v): ?>
                                <span class="webyaz-size-swatch webyaz-prop-swatch" data-group="prop<?php echo $pi; ?>" data-prop="<?php echo esc_attr($v); ?>"><?php echo esc_html($v); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <script>
            (function() {
                var colorBtns = document.querySelectorAll('.webyaz-color-swatch');
                var nameEl = document.getElementById('webyazColorName');
                colorBtns.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        colorBtns.forEach(function(b) {
                            b.classList.remove('active');
                        });
                        btn.classList.add('active');
                        if (nameEl) nameEl.textContent = btn.getAttribute('data-color');
                    });
                    btn.addEventListener('mouseenter', function() {
                        if (nameEl) nameEl.textContent = btn.getAttribute('data-color');
                    });
                });
                document.querySelectorAll('.webyaz-size-swatch:not(.webyaz-shoe-swatch):not(.webyaz-unit-swatch):not(.webyaz-prop-swatch)').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        document.querySelectorAll('.webyaz-size-swatch:not(.webyaz-shoe-swatch):not(.webyaz-unit-swatch):not(.webyaz-prop-swatch)').forEach(function(b) {
                            b.classList.remove('active');
                        });
                        btn.classList.add('active');
                    });
                });
                document.querySelectorAll('.webyaz-shoe-swatch').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        document.querySelectorAll('.webyaz-shoe-swatch').forEach(function(b) {
                            b.classList.remove('active');
                        });
                        btn.classList.add('active');
                    });
                });
                document.querySelectorAll('.webyaz-unit-swatch').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        btn.classList.toggle('active');
                    });
                });
                document.querySelectorAll('.webyaz-prop-swatch').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        btn.classList.toggle('active');
                    });
                });
            })();
        </script>
<?php
    }

    public function display_colors_loop()
    {
        global $product;
        if (!$product) return;
        $pid = $product->get_id();
        if (get_post_meta($pid, '_webyaz_attrs_active', true) !== '1') return;
        $colors = get_post_meta($pid, '_webyaz_colors', true);
        $preset_colors = get_post_meta($pid, '_webyaz_preset_colors', true);
        if (!is_array($colors)) $colors = array();
        if (!is_array($preset_colors)) $preset_colors = array();

        $all_colors = array();
        foreach ($preset_colors as $hex) {
            foreach (self::$default_colors as $name => $h) {
                if (strtolower($h) === strtolower($hex)) {
                    $all_colors[] = array('name' => $name, 'hex' => $h);
                    break;
                }
            }
        }
        foreach ($colors as $c) {
            if (isset($c['hex'])) $all_colors[] = $c;
        }

        if (empty($all_colors)) return;
        echo '<div class="webyaz-loop-colors">';
        foreach (array_slice($all_colors, 0, 6) as $c) {
            $border = (strtolower($c['hex']) === '#ffffff' || strtolower($c['hex']) === '#fffdd0' || strtolower($c['hex']) === '#f5f5dc') ? 'box-shadow:inset 0 0 0 1px rgba(0,0,0,0.12);' : '';
            echo '<span class="webyaz-loop-color" style="background:' . esc_attr($c['hex']) . ';' . $border . '" title="' . esc_attr($c['name']) . '"></span>';
        }
        if (count($all_colors) > 6) {
            echo '<span class="webyaz-loop-color-more">+' . (count($all_colors) - 6) . '</span>';
        }
        echo '</div>';
    }
}

new Webyaz_Attributes();
