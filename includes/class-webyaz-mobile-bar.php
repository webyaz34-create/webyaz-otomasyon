<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Mobile_Bar {

    public function __construct() {
        add_action('wp_footer', array($this, 'render_bar'));
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_submenu() {
        add_submenu_page(
            'webyaz-dashboard',
            'Mobil Menu',
            'Mobil Menu',
            'manage_options',
            'webyaz-mobile-bar',
            array($this, 'render_admin')
        );
    }

    public function register_settings() {
        register_setting('webyaz_mobile_bar_group', 'webyaz_mobile_bar');
    }

    private static function get_default_items() {
        return array(
            array('icon' => 'home', 'label' => 'Ana Sayfa', 'url' => '', 'enabled' => '1'),
            array('icon' => 'search', 'label' => 'Ara', 'url' => '', 'enabled' => '1'),
            array('icon' => 'categories', 'label' => 'Kategoriler', 'url' => '', 'enabled' => '1'),
            array('icon' => 'cart', 'label' => 'Sepet', 'url' => '', 'enabled' => '1'),
            array('icon' => 'account', 'label' => 'Hesabim', 'url' => '', 'enabled' => '1'),
        );
    }

    private static function get_defaults() {
        return array(
            'enabled' => '1',
            'hide_on_scroll' => '0',
            'bg_color' => '',
            'icon_color' => '',
            'active_color' => '',
            'label_color' => '',
            'badge_color' => '',
            'border_color' => '',
            'style' => 'default',
            'items' => self::get_default_items(),
        );
    }

    public static function get_items() {
        $opts = get_option('webyaz_mobile_bar', array());
        $opts = wp_parse_args($opts, self::get_defaults());
        if (empty($opts['items']) || !is_array($opts['items'])) {
            $opts['items'] = self::get_default_items();
        }
        return $opts;
    }

    private static function get_icon_svg($icon) {
        $icons = array(
            'home' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
            'search' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
            'categories' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
            'cart' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>',
            'account' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
            'heart' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
            'phone' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
            'whatsapp' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>',
            'compare' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/></svg>',
            'offers' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>',
            'custom' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
        );
        return isset($icons[$icon]) ? $icons[$icon] : $icons['custom'];
    }

    private static function resolve_url($item) {
        $url = isset($item['url']) ? trim($item['url']) : '';
        if (!empty($url)) return $url;

        $icon = isset($item['icon']) ? $item['icon'] : '';
        switch ($icon) {
            case 'home':
                return home_url('/');
            case 'cart':
                return function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/sepet/');
            case 'account':
                return function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/hesabim/');
            case 'categories':
                return function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/magaza/');
            case 'heart':
                return home_url('/favorilerim/');
            case 'search':
                return '#webyaz-mobile-search';
            default:
                return home_url('/');
        }
    }

    public static function get_available_icons() {
        return array(
            'home' => 'Ana Sayfa',
            'search' => 'Arama',
            'categories' => 'Kategoriler / Magaza',
            'cart' => 'Sepet',
            'account' => 'Hesabim',
            'heart' => 'Favoriler',
            'phone' => 'Telefon',
            'whatsapp' => 'WhatsApp',
            'compare' => 'Karsilastir',
            'offers' => 'Kampanyalar',
            'custom' => 'Ozel Link',
        );
    }

    public function render_bar() {
        if (is_admin()) return;

        $opts = self::get_items();
        if ($opts['enabled'] !== '1') return;

        $items = $opts['items'];
        $active_items = array();
        foreach ($items as $item) {
            if (isset($item['enabled']) && $item['enabled'] === '1') {
                $active_items[] = $item;
            }
        }
        if (empty($active_items)) return;

        $hide_class = ($opts['hide_on_scroll'] === '1') ? ' webyaz-mb-hide-scroll' : '';
        $style_class = (!empty($opts['style']) && $opts['style'] !== 'default') ? ' webyaz-mb-' . $opts['style'] : '';
        $cart_count = 0;
        if (function_exists('WC') && WC()->cart) {
            $cart_count = WC()->cart->get_cart_contents_count();
        }

        $colors = Webyaz_Colors::get_theme_colors();
        $bg = !empty($opts['bg_color']) ? $opts['bg_color'] : '#ffffff';
        $ic = !empty($opts['icon_color']) ? $opts['icon_color'] : '#888888';
        $ac = !empty($opts['active_color']) ? $opts['active_color'] : $colors['primary'];
        $lc = !empty($opts['label_color']) ? $opts['label_color'] : '';
        $bc = !empty($opts['badge_color']) ? $opts['badge_color'] : $colors['secondary'];
        $brc = !empty($opts['border_color']) ? $opts['border_color'] : 'rgba(0,0,0,0.06)';

        $inline_css = '#webyazMobileBar{background:' . esc_attr($bg) . ';border-top:1px solid ' . esc_attr($brc) . ';}';
        $inline_css .= '#webyazMobileBar .webyaz-mb-item{color:' . esc_attr($ic) . ' !important;}';
        $inline_css .= '#webyazMobileBar .webyaz-mb-item.active,#webyazMobileBar .webyaz-mb-item:active{color:' . esc_attr($ac) . ' !important;}';
        if ($lc) {
            $inline_css .= '#webyazMobileBar .webyaz-mb-label{color:' . esc_attr($lc) . ';}';
        }
        $inline_css .= '#webyazMobileBar .webyaz-mb-badge{background:' . esc_attr($bc) . ';}';
        $inline_css .= '#webyazMobileBar .webyaz-mb-item.active .webyaz-mb-icon{background:' . esc_attr($ac) . '18;border-radius:12px;}';

        $current_url = home_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');
        ?>
        <style><?php echo $inline_css; ?></style>
        <nav class="webyaz-mobile-bar<?php echo $hide_class . $style_class; ?>" id="webyazMobileBar">
            <?php foreach ($active_items as $item):
                $url = self::resolve_url($item);
                $icon = isset($item['icon']) ? $item['icon'] : 'custom';
                $label = isset($item['label']) ? $item['label'] : '';
                $is_search = ($url === '#webyaz-mobile-search');
                $is_active = (!$is_search && rtrim($current_url, '/') === rtrim($url, '/'));
                $extra_class = $is_active ? ' active' : '';
                $extra_attr = $is_search ? ' data-action="search"' : '';
            ?>
                <a href="<?php echo esc_url($is_search ? '#' : $url); ?>" class="webyaz-mb-item<?php echo $extra_class; ?>"<?php echo $extra_attr; ?>>
                    <span class="webyaz-mb-icon">
                        <?php echo self::get_icon_svg($icon); ?>
                        <?php if ($icon === 'cart' && $cart_count > 0): ?>
                            <span class="webyaz-mb-badge"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="webyaz-mb-label"><?php echo esc_html($label); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="webyaz-mobile-search-overlay" id="webyazMobileSearchOverlay" style="display:none;">
            <div class="webyaz-mobile-search-box">
                <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                    <input type="search" name="s" placeholder="Urun ara..." autofocus>
                    <?php if (function_exists('WC')): ?>
                        <input type="hidden" name="post_type" value="product">
                    <?php endif; ?>
                    <button type="submit"><?php echo self::get_icon_svg('search'); ?></button>
                </form>
                <button class="webyaz-mobile-search-close" id="webyazMobileSearchClose">&times;</button>
            </div>
        </div>

        <script>
        (function(){
            var bar = document.getElementById('webyazMobileBar');
            if (!bar) return;

            var searchBtns = bar.querySelectorAll('[data-action="search"]');
            var overlay = document.getElementById('webyazMobileSearchOverlay');
            var closeBtn = document.getElementById('webyazMobileSearchClose');

            for (var i = 0; i < searchBtns.length; i++) {
                searchBtns[i].addEventListener('click', function(e){
                    e.preventDefault();
                    if (overlay) {
                        overlay.style.display = 'flex';
                        var input = overlay.querySelector('input[type="search"]');
                        if (input) input.focus();
                    }
                });
            }

            if (closeBtn && overlay) {
                closeBtn.addEventListener('click', function(){ overlay.style.display = 'none'; });
                overlay.addEventListener('click', function(e){ if (e.target === overlay) overlay.style.display = 'none'; });
            }

            <?php if ($opts['hide_on_scroll'] === '1'): ?>
            var lastScroll = 0;
            window.addEventListener('scroll', function(){
                var st = window.pageYOffset || document.documentElement.scrollTop;
                if (st > lastScroll && st > 100) {
                    bar.classList.add('webyaz-mb-hidden');
                } else {
                    bar.classList.remove('webyaz-mb-hidden');
                }
                lastScroll = st <= 0 ? 0 : st;
            });
            <?php endif; ?>
        })();
        </script>
        <?php
    }

    public function render_admin() {
        if (isset($_POST['webyaz_mobile_bar_save']) && check_admin_referer('webyaz_mobile_bar_nonce')) {
            $save = array(
                'enabled' => isset($_POST['webyaz_mb_enabled']) ? '1' : '0',
                'hide_on_scroll' => isset($_POST['webyaz_mb_hide_scroll']) ? '1' : '0',
                'bg_color' => sanitize_hex_color(isset($_POST['webyaz_mb_bg_color']) ? $_POST['webyaz_mb_bg_color'] : ''),
                'icon_color' => sanitize_hex_color(isset($_POST['webyaz_mb_icon_color']) ? $_POST['webyaz_mb_icon_color'] : ''),
                'active_color' => sanitize_hex_color(isset($_POST['webyaz_mb_active_color']) ? $_POST['webyaz_mb_active_color'] : ''),
                'label_color' => sanitize_hex_color(isset($_POST['webyaz_mb_label_color']) ? $_POST['webyaz_mb_label_color'] : ''),
                'badge_color' => sanitize_hex_color(isset($_POST['webyaz_mb_badge_color']) ? $_POST['webyaz_mb_badge_color'] : ''),
                'border_color' => sanitize_hex_color(isset($_POST['webyaz_mb_border_color']) ? $_POST['webyaz_mb_border_color'] : ''),
                'style' => sanitize_text_field(isset($_POST['webyaz_mb_style']) ? $_POST['webyaz_mb_style'] : 'default'),
                'items' => array(),
            );

            if (isset($_POST['webyaz_mb_items']) && is_array($_POST['webyaz_mb_items'])) {
                foreach ($_POST['webyaz_mb_items'] as $item) {
                    $save['items'][] = array(
                        'icon' => sanitize_text_field($item['icon']),
                        'label' => sanitize_text_field($item['label']),
                        'url' => esc_url_raw($item['url']),
                        'enabled' => isset($item['enabled']) ? '1' : '0',
                    );
                }
            }

            update_option('webyaz_mobile_bar', $save);
            echo '<div class="webyaz-notice success">Ayarlar kaydedildi!</div>';
        }

        $opts = self::get_items();
        $items = $opts['items'];
        $available_icons = self::get_available_icons();
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Mobil Alt Menu</h1>
                <p>Telefonda altta gorunen sabit navigasyon cubugu ayarlari</p>
            </div>

            <form method="post">
                <?php wp_nonce_field('webyaz_mobile_bar_nonce'); ?>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Genel Ayarlar</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>
                                <input type="checkbox" name="webyaz_mb_enabled" value="1" <?php checked($opts['enabled'], '1'); ?>>
                                Mobil Bar Aktif
                            </label>
                        </div>
                        <div class="webyaz-field">
                            <label>
                                <input type="checkbox" name="webyaz_mb_hide_scroll" value="1" <?php checked($opts['hide_on_scroll'], '1'); ?>>
                                Asagi kaydirildiginda gizle
                            </label>
                        </div>
                    </div>
                </div>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Renk ve Stil Ayarlari</h2>
                    <p style="color:#666;font-size:13px;margin-bottom:15px;">Bos birakilirsa tema renkleri otomatik kullanilir (Birinci renk = aktif ikon, Ikinci renk = badge)</p>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Bar Stili</label>
                            <select name="webyaz_mb_style">
                                <option value="default" <?php selected(isset($opts['style']) ? $opts['style'] : '', 'default'); ?>>Klasik (Duz)</option>
                                <option value="rounded" <?php selected(isset($opts['style']) ? $opts['style'] : '', 'rounded'); ?>>Yuvarlatilmis</option>
                                <option value="glass" <?php selected(isset($opts['style']) ? $opts['style'] : '', 'glass'); ?>>Cam Efekti (Glassmorphism)</option>
                                <option value="shadow" <?php selected(isset($opts['style']) ? $opts['style'] : '', 'shadow'); ?>>Golgeli</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Arka Plan Rengi</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="color" name="webyaz_mb_bg_color" value="<?php echo esc_attr(!empty($opts['bg_color']) ? $opts['bg_color'] : '#ffffff'); ?>" style="width:50px;height:36px;padding:2px;border:1px solid #ddd;border-radius:6px;cursor:pointer;">
                                <input type="text" value="<?php echo esc_attr(!empty($opts['bg_color']) ? $opts['bg_color'] : ''); ?>" placeholder="Bos = beyaz" style="flex:1;" onchange="this.previousElementSibling.value=this.value" oninput="this.previousElementSibling.value=this.value">
                            </div>
                        </div>
                        <div class="webyaz-field">
                            <label>Ikon Rengi (varsayilan)</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="color" name="webyaz_mb_icon_color" value="<?php echo esc_attr(!empty($opts['icon_color']) ? $opts['icon_color'] : '#888888'); ?>" style="width:50px;height:36px;padding:2px;border:1px solid #ddd;border-radius:6px;cursor:pointer;">
                                <input type="text" value="<?php echo esc_attr(!empty($opts['icon_color']) ? $opts['icon_color'] : ''); ?>" placeholder="Bos = #888" style="flex:1;" onchange="this.previousElementSibling.value=this.value">
                            </div>
                        </div>
                        <div class="webyaz-field">
                            <label>Aktif Ikon Rengi</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="color" name="webyaz_mb_active_color" value="<?php echo esc_attr(!empty($opts['active_color']) ? $opts['active_color'] : '#446084'); ?>" style="width:50px;height:36px;padding:2px;border:1px solid #ddd;border-radius:6px;cursor:pointer;">
                                <input type="text" value="<?php echo esc_attr(!empty($opts['active_color']) ? $opts['active_color'] : ''); ?>" placeholder="Bos = tema birinci rengi" style="flex:1;" onchange="this.previousElementSibling.value=this.value">
                            </div>
                        </div>
                        <div class="webyaz-field">
                            <label>Yazi Rengi</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="color" name="webyaz_mb_label_color" value="<?php echo esc_attr(!empty($opts['label_color']) ? $opts['label_color'] : '#888888'); ?>" style="width:50px;height:36px;padding:2px;border:1px solid #ddd;border-radius:6px;cursor:pointer;">
                                <input type="text" value="<?php echo esc_attr(!empty($opts['label_color']) ? $opts['label_color'] : ''); ?>" placeholder="Bos = ikon rengiyle ayni" style="flex:1;" onchange="this.previousElementSibling.value=this.value">
                            </div>
                        </div>
                        <div class="webyaz-field">
                            <label>Sepet Badge Rengi</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="color" name="webyaz_mb_badge_color" value="<?php echo esc_attr(!empty($opts['badge_color']) ? $opts['badge_color'] : '#d26e4b'); ?>" style="width:50px;height:36px;padding:2px;border:1px solid #ddd;border-radius:6px;cursor:pointer;">
                                <input type="text" value="<?php echo esc_attr(!empty($opts['badge_color']) ? $opts['badge_color'] : ''); ?>" placeholder="Bos = tema ikinci rengi" style="flex:1;" onchange="this.previousElementSibling.value=this.value">
                            </div>
                        </div>
                        <div class="webyaz-field">
                            <label>Ust Cizgi Rengi</label>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="color" name="webyaz_mb_border_color" value="<?php echo esc_attr(!empty($opts['border_color']) ? $opts['border_color'] : '#eeeeee'); ?>" style="width:50px;height:36px;padding:2px;border:1px solid #ddd;border-radius:6px;cursor:pointer;">
                                <input type="text" value="<?php echo esc_attr(!empty($opts['border_color']) ? $opts['border_color'] : ''); ?>" placeholder="Bos = hafif gri" style="flex:1;" onchange="this.previousElementSibling.value=this.value">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="webyaz-settings-section">
                    <p style="color:#666;font-size:13px;margin-bottom:15px;">URL bos birakilirsa otomatik sayfa atanir (Ana Sayfa, Sepet, Hesabim vb.)</p>

                    <div id="webyazMbItems">
                        <?php for ($i = 0; $i < 5; $i++):
                            $item = isset($items[$i]) ? $items[$i] : array('icon' => 'custom', 'label' => '', 'url' => '', 'enabled' => '0');
                        ?>
                        <div class="webyaz-mb-admin-item" style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:8px;padding:15px;margin-bottom:10px;">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                                <input type="checkbox" name="webyaz_mb_items[<?php echo $i; ?>][enabled]" value="1" <?php checked(isset($item['enabled']) ? $item['enabled'] : '0', '1'); ?>>
                                <strong>Oge <?php echo ($i + 1); ?></strong>
                            </div>
                            <div class="webyaz-settings-grid" style="grid-template-columns:repeat(3,1fr);">
                                <div class="webyaz-field">
                                    <label>Ikon</label>
                                    <select name="webyaz_mb_items[<?php echo $i; ?>][icon]">
                                        <?php foreach ($available_icons as $key => $lbl): ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected(isset($item['icon']) ? $item['icon'] : '', $key); ?>><?php echo esc_html($lbl); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="webyaz-field">
                                    <label>Etiket</label>
                                    <input type="text" name="webyaz_mb_items[<?php echo $i; ?>][label]" value="<?php echo esc_attr(isset($item['label']) ? $item['label'] : ''); ?>" placeholder="Buton yazisi">
                                </div>
                                <div class="webyaz-field">
                                    <label>URL (opsiyonel)</label>
                                    <input type="url" name="webyaz_mb_items[<?php echo $i; ?>][url]" value="<?php echo esc_attr(isset($item['url']) ? $item['url'] : ''); ?>" placeholder="Bos = otomatik">
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div style="margin-top:20px;">
                    <button type="submit" name="webyaz_mobile_bar_save" class="button button-primary" style="padding:8px 24px;font-size:14px;">Kaydet</button>
                </div>
            </form>

            <div class="webyaz-settings-section" style="margin-top:25px;">
                <h2 class="webyaz-section-title">Onizleme</h2>
                <?php
                $pre_bg = !empty($opts['bg_color']) ? $opts['bg_color'] : '#fff';
                $pre_ic = !empty($opts['icon_color']) ? $opts['icon_color'] : '#888';
                $pre_ac = !empty($opts['active_color']) ? $opts['active_color'] : '#446084';
                $pre_brc = !empty($opts['border_color']) ? $opts['border_color'] : '#eee';
                $pre_style = isset($opts['style']) ? $opts['style'] : 'default';
                $pre_radius = ($pre_style === 'rounded') ? 'border-radius:20px;margin:0 10px;' : '';
                $pre_blur = ($pre_style === 'glass') ? 'background:rgba(255,255,255,0.75) !important;backdrop-filter:blur(10px);' : '';
                $pre_shadow = ($pre_style === 'shadow') ? 'box-shadow:0 -4px 30px rgba(0,0,0,0.15);border:none;' : '';
                ?>
                <div style="max-width:375px;margin:0 auto;border:1px solid <?php echo esc_attr($pre_brc); ?>;overflow:hidden;position:relative;height:80px;background:<?php echo esc_attr($pre_bg); ?>;<?php echo $pre_radius . $pre_blur . $pre_shadow; ?>box-shadow:0 -2px 12px rgba(0,0,0,0.06);">
                    <div style="display:flex;justify-content:space-around;align-items:center;height:100%;padding:0 10px;">
                        <?php $first = true; foreach ($items as $item):
                            if (!isset($item['enabled']) || $item['enabled'] !== '1') continue;
                            $icon = isset($item['icon']) ? $item['icon'] : 'custom';
                            $label = isset($item['label']) ? $item['label'] : '';
                            $color = $first ? $pre_ac : $pre_ic;
                        ?>
                        <div style="text-align:center;flex:1;">
                            <div style="color:<?php echo esc_attr($color); ?>;"><?php echo self::get_icon_svg($icon); ?></div>
                            <div style="font-size:10px;color:<?php echo esc_attr($color); ?>;margin-top:3px;font-weight:<?php echo $first ? '700' : '400'; ?>;"><?php echo esc_html($label); ?></div>
                        </div>
                        <?php $first = false; endforeach; ?>
                    </div>
                </div>
                <p style="text-align:center;color:#999;font-size:12px;margin-top:8px;">Gercek gorunumu telefondan kontrol edin</p>
            </div>
        </div>
        <?php
    }
}

new Webyaz_Mobile_Bar();
