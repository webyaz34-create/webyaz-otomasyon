<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Social_Proof
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));

        // Goruntulenme takibi
        add_action('template_redirect', array($this, 'track_view'));

        // Tek urun sayfasi
        add_action('woocommerce_single_product_summary', array($this, 'display_social_proof'), 15);

        // Urun listesi (loop)
        add_action('woocommerce_after_shop_loop_item_title', array($this, 'display_social_proof_loop'), 8);

        // Frontend CSS + JS
        add_action('wp_footer', array($this, 'frontend_assets'));
    }

    public function register_settings()
    {
        register_setting('webyaz_social_proof_group', 'webyaz_social_proof');
    }

    private static function get_defaults()
    {
        return array(
            'active'          => '0',
            'show_views'      => '1',
            'show_sales'      => '1',
            'view_multiplier' => '3',
            'view_min_add'    => '50',
            'view_max_add'    => '300',
            'sale_multiplier' => '2',
            'sale_min_add'    => '10',
            'sale_max_add'    => '80',
            'view_color'      => '#1976d2',
            'view_bg'         => '#e8f0fe',
            'sale_color'      => '#2e7d32',
            'sale_bg'         => '#e8f5e9',
            'label_color'     => '#555555',
            'animate'         => '1',
            'show_in_loop'    => '1',
            'style'           => 'badge',
        );
    }

    public static function get_opts()
    {
        return wp_parse_args(get_option('webyaz_social_proof', array()), self::get_defaults());
    }

    /**
     * Goruntulenme takibi - her urun sayfasi ziyaretinde sayaci artir
     * Bot'lari ve tekrar eden ziyaretleri filtrele
     */
    public function track_view()
    {
        if (!is_singular('product')) return;
        if (is_admin()) return;

        // Bot kontrolu
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        if (empty($ua) || preg_match('/bot|crawl|spider|slurp|facebookexternalhit/i', $ua)) return;

        // Ayni oturumda tekrar sayma (30 dk)
        $product_id = get_the_ID();
        $cookie_key = 'webyaz_viewed_' . $product_id;
        if (isset($_COOKIE[$cookie_key])) return;

        // Sayaci artir
        $current = intval(get_post_meta($product_id, '_webyaz_view_count', true));
        update_post_meta($product_id, '_webyaz_view_count', $current + 1);

        // 30 dk cookie
        setcookie($cookie_key, '1', time() + 1800, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    }

    /**
     * Urun ID'ye bagli tutarli rastgele ek sayi uret
     * Ayni urun icin her zaman ayni sonucu verir
     */
    private static function get_boosted_count($product_id, $real_count, $type)
    {
        $opts = self::get_opts();
        $prefix = ($type === 'view') ? 'view' : 'sale';

        $multiplier = max(1, floatval($opts[$prefix . '_multiplier']));
        $min_add    = max(0, intval($opts[$prefix . '_min_add']));
        $max_add    = max($min_add, intval($opts[$prefix . '_max_add']));

        // Urun ID + type ile tutarli seed
        $seed = crc32($product_id . '_' . $type . '_webyaz');
        mt_srand($seed);
        $random_add = mt_rand($min_add, $max_add);
        mt_srand(); // seed'i sifirla

        $boosted = intval($real_count * $multiplier) + $random_add;

        return $boosted;
    }

    /**
     * Tek urun sayfasi gosterimi
     */
    public function display_social_proof()
    {
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return;

        global $product;
        if (!$product) return;

        $product_id = $product->get_id();

        $html = '<div class="webyaz-social-proof" data-animate="' . esc_attr($opts['animate']) . '">';

        // Goruntulenme
        if ($opts['show_views'] === '1') {
            $real_views = intval(get_post_meta($product_id, '_webyaz_view_count', true));
            $boosted_views = self::get_boosted_count($product_id, $real_views, 'view');
            $color = esc_attr($opts['view_color']);

            $html .= '<div class="webyaz-sp-item webyaz-sp-views" style="--sp-color:' . $color . ';--sp-bg:' . esc_attr($opts['view_bg']) . ';--sp-label:' . esc_attr($opts['label_color']) . ';">';
            $html .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="' . $color . '"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>';
            $html .= '<span class="webyaz-sp-count" data-target="' . $boosted_views . '">0</span>';
            $html .= '<span class="webyaz-sp-label">kez incelendi</span>';
            $html .= '</div>';
        }

        // Satin alma
        if ($opts['show_sales'] === '1') {
            $real_sales = intval($product->get_total_sales());
            $boosted_sales = self::get_boosted_count($product_id, $real_sales, 'sale');
            $color = esc_attr($opts['sale_color']);

            $html .= '<div class="webyaz-sp-item webyaz-sp-sales" style="--sp-color:' . $color . ';--sp-bg:' . esc_attr($opts['sale_bg']) . ';--sp-label:' . esc_attr($opts['label_color']) . ';">';
            $html .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="' . $color . '"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1.003 1.003 0 0020 4H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>';
            $html .= '<span class="webyaz-sp-count" data-target="' . $boosted_sales . '">0</span>';
            $html .= '<span class="webyaz-sp-label">kez satin alindi</span>';
            $html .= '</div>';
        }

        $html .= '</div>';

        echo $html;
    }

    /**
     * Urun listesinde mini gosterim
     */
    public function display_social_proof_loop()
    {
        $opts = self::get_opts();
        if ($opts['active'] !== '1' || $opts['show_in_loop'] !== '1') return;

        global $product;
        if (!$product) return;

        $product_id = $product->get_id();
        $parts = array();

        if ($opts['show_views'] === '1') {
            $real_views = intval(get_post_meta($product_id, '_webyaz_view_count', true));
            $boosted_views = self::get_boosted_count($product_id, $real_views, 'view');
            $parts[] = '<span style="color:' . esc_attr($opts['view_color']) . ';">👁 ' . number_format($boosted_views) . '</span>';
        }

        if ($opts['show_sales'] === '1') {
            $real_sales = intval($product->get_total_sales());
            $boosted_sales = self::get_boosted_count($product_id, $real_sales, 'sale');
            $parts[] = '<span style="color:' . esc_attr($opts['sale_color']) . ';">🛒 ' . number_format($boosted_sales) . '</span>';
        }

        if (!empty($parts)) {
            echo '<div class="webyaz-sp-loop">' . implode(' &middot; ', $parts) . '</div>';
        }
    }

    /**
     * Frontend CSS ve JS
     */
    public function frontend_assets()
    {
        $opts = self::get_opts();
        if ($opts['active'] !== '1') return;
        if (!is_singular('product') && !is_shop() && !is_product_category() && !is_product_tag()) return;
?>
        <style>
            .webyaz-social-proof {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin: 12px 0;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }

            .webyaz-sp-item {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 14px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 500;
                background: var(--sp-bg, #f0f0f0);
                border: 1px solid var(--sp-color);
                border-color: color-mix(in srgb, var(--sp-color) 25%, transparent);
                transition: transform .2s, box-shadow .2s;
            }

            .webyaz-sp-item:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px color-mix(in srgb, var(--sp-color) 20%, transparent);
            }

            .webyaz-sp-item svg {
                flex-shrink: 0;
                opacity: .85;
            }

            .webyaz-sp-count {
                font-weight: 700;
                font-size: 15px;
                color: var(--sp-color);
                min-width: 20px;
            }

            .webyaz-sp-label {
                color: var(--sp-label, #555);
                font-size: 13px;
            }

            .webyaz-sp-loop {
                text-align: center;
                font-size: 12px;
                margin: 4px 0 0;
                line-height: 1.6;
                opacity: .85;
            }

            .webyaz-sp-loop span {
                font-weight: 600;
            }

            /* Fallback - color-mix desteklemeyenler icin */
            @supports not (background: color-mix(in srgb, red 50%, blue)) {
                .webyaz-sp-views {
                    background: rgba(25, 118, 210, 0.08);
                    border-color: rgba(25, 118, 210, 0.18);
                }

                .webyaz-sp-sales {
                    background: rgba(46, 125, 50, 0.08);
                    border-color: rgba(46, 125, 50, 0.18);
                }
            }

            @media(max-width:600px) {
                .webyaz-social-proof {
                    gap: 6px;
                }

                .webyaz-sp-item {
                    padding: 6px 10px;
                    font-size: 13px;
                }

                .webyaz-sp-count {
                    font-size: 14px;
                }

                .webyaz-sp-label {
                    font-size: 12px;
                }
            }
        </style>

        <?php if ($opts['animate'] === '1' && is_singular('product')): ?>
            <script>
                (function() {
                    function animateCounters() {
                        var counters = document.querySelectorAll('.webyaz-sp-count[data-target]');
                        counters.forEach(function(el) {
                            var target = parseInt(el.getAttribute('data-target')) || 0;
                            if (target <= 0) {
                                el.textContent = '0';
                                return;
                            }
                            var duration = 1200;
                            var start = null;
                            var startVal = 0;

                            function step(ts) {
                                if (!start) start = ts;
                                var progress = Math.min((ts - start) / duration, 1);
                                var ease = 1 - Math.pow(1 - progress, 3); // easeOutCubic
                                var current = Math.floor(startVal + (target - startVal) * ease);
                                el.textContent = current.toLocaleString('tr-TR');
                                if (progress < 1) requestAnimationFrame(step);
                            }
                            // IntersectionObserver ile gorunur oldugunda baslat
                            if ('IntersectionObserver' in window) {
                                var obs = new IntersectionObserver(function(entries) {
                                    entries.forEach(function(e) {
                                        if (e.isIntersecting) {
                                            requestAnimationFrame(step);
                                            obs.unobserve(el);
                                        }
                                    });
                                }, {
                                    threshold: 0.3
                                });
                                obs.observe(el);
                            } else {
                                requestAnimationFrame(step);
                            }
                        });
                    }
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', animateCounters);
                    } else {
                        animateCounters();
                    }
                })();
            </script>
        <?php endif;
    }

    public function add_submenu()
    {
        add_submenu_page('webyaz-dashboard', 'Sosyal Kanit', 'Sosyal Kanit', 'manage_options', 'webyaz-social-proof', array($this, 'render_admin'));
    }

    public function render_admin()
    {
        $opts = self::get_opts();
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Sosyal Kanit Ayarlari</h1>
                <p>Urun sayfalarinda goruntulenme ve satin alma sayaci goster</p>
            </div>

            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="webyaz-notice success">Kaydedildi!</div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('webyaz_social_proof_group'); ?>

                <!-- Genel Ayarlar -->
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Genel Ayarlar</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Aktif</label>
                            <select name="webyaz_social_proof[active]">
                                <option value="0" <?php selected($opts['active'], '0'); ?>>Kapali</option>
                                <option value="1" <?php selected($opts['active'], '1'); ?>>Aktif</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Sayac Animasyonu</label>
                            <select name="webyaz_social_proof[animate]">
                                <option value="1" <?php selected($opts['animate'], '1'); ?>>Acik</option>
                                <option value="0" <?php selected($opts['animate'], '0'); ?>>Kapali</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Urun Listesinde Goster</label>
                            <select name="webyaz_social_proof[show_in_loop]">
                                <option value="1" <?php selected($opts['show_in_loop'], '1'); ?>>Evet</option>
                                <option value="0" <?php selected($opts['show_in_loop'], '0'); ?>>Hayir</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Goruntulenme Goster</label>
                            <select name="webyaz_social_proof[show_views]">
                                <option value="1" <?php selected($opts['show_views'], '1'); ?>>Evet</option>
                                <option value="0" <?php selected($opts['show_views'], '0'); ?>>Hayir</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Satin Alma Goster</label>
                            <select name="webyaz_social_proof[show_sales]">
                                <option value="1" <?php selected($opts['show_sales'], '1'); ?>>Evet</option>
                                <option value="0" <?php selected($opts['show_sales'], '0'); ?>>Hayir</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Renk Ayarlari -->
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">🎨 Renk Ayarları</h2>
                    <p style="color:#666;font-size:13px;margin:-5px 0 15px;">Görüntülenme ve satın alma rozetlerinin renklerini özelleştirin</p>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>👁️ Görüntülenme — İkon & Sayı Rengi</label>
                            <input type="color" name="webyaz_social_proof[view_color]" value="<?php echo esc_attr($opts['view_color']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>👁️ Görüntülenme — Arka Plan Rengi</label>
                            <input type="color" name="webyaz_social_proof[view_bg]" value="<?php echo esc_attr($opts['view_bg']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>🛒 Satın Alma — İkon & Sayı Rengi</label>
                            <input type="color" name="webyaz_social_proof[sale_color]" value="<?php echo esc_attr($opts['sale_color']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>🛒 Satın Alma — Arka Plan Rengi</label>
                            <input type="color" name="webyaz_social_proof[sale_bg]" value="<?php echo esc_attr($opts['sale_bg']); ?>">
                        </div>
                        <div class="webyaz-field">
                            <label>📝 Etiket Yazı Rengi</label>
                            <input type="color" name="webyaz_social_proof[label_color]" value="<?php echo esc_attr($opts['label_color']); ?>">
                            <small>"kez incelendi", "kez satın alındı" yazısının rengi</small>
                        </div>
                    </div>
                </div>

                <!-- Goruntulenme Cazibe Ayarlari -->
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Görüntülenme Çarpan Ayarları</h2>
                    <p style="color:#666;font-size:13px;margin:-5px 0 15px;">Gerçek görüntülenme sayısına eklenecek değerler. Örnek: Gerçek 10 × Çarpan 3 + Rastgele(50-300) = ~330</p>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Carpan (katsayi)</label>
                            <input type="number" name="webyaz_social_proof[view_multiplier]" value="<?php echo esc_attr($opts['view_multiplier']); ?>" min="1" max="10" step="0.5">
                        </div>
                        <div class="webyaz-field">
                            <label>Minimum Ek Sayi</label>
                            <input type="number" name="webyaz_social_proof[view_min_add]" value="<?php echo esc_attr($opts['view_min_add']); ?>" min="0" max="5000">
                        </div>
                        <div class="webyaz-field">
                            <label>Maksimum Ek Sayi</label>
                            <input type="number" name="webyaz_social_proof[view_max_add]" value="<?php echo esc_attr($opts['view_max_add']); ?>" min="0" max="5000">
                        </div>
                    </div>
                </div>

                <!-- Satin Alma Cazibe Ayarlari -->
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">Satın Alma Çarpan Ayarları</h2>
                    <p style="color:#666;font-size:13px;margin:-5px 0 15px;">Gerçek satış sayısına eklenecek değerler. Örnek: Gerçek 5 × Çarpan 2 + Rastgele(10-80) = ~90</p>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Carpan (katsayi)</label>
                            <input type="number" name="webyaz_social_proof[sale_multiplier]" value="<?php echo esc_attr($opts['sale_multiplier']); ?>" min="1" max="10" step="0.5">
                        </div>
                        <div class="webyaz-field">
                            <label>Minimum Ek Sayi</label>
                            <input type="number" name="webyaz_social_proof[sale_min_add]" value="<?php echo esc_attr($opts['sale_min_add']); ?>" min="0" max="2000">
                        </div>
                        <div class="webyaz-field">
                            <label>Maksimum Ek Sayi</label>
                            <input type="number" name="webyaz_social_proof[sale_max_add]" value="<?php echo esc_attr($opts['sale_max_add']); ?>" min="0" max="2000">
                        </div>
                    </div>
                </div>

                <!-- Onizleme -->
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">👁️ Önizleme</h2>
                    <p style="color:#666;font-size:13px;margin:-5px 0 15px;">Ürün sayfasında böyle görünecek:</p>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;padding:20px;background:#f9f9f9;border-radius:8px;border:1px dashed #ddd;">
                        <div style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:14px;font-weight:500;background:<?php echo esc_attr($opts['view_bg']); ?>;border:1px solid <?php echo esc_attr($opts['view_color']); ?>30;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="<?php echo esc_attr($opts['view_color']); ?>">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z" />
                            </svg>
                            <span style="font-weight:700;color:<?php echo esc_attr($opts['view_color']); ?>;">247</span>
                            <span style="color:<?php echo esc_attr($opts['label_color']); ?>;font-size:13px;">kez incelendi</span>
                        </div>
                        <div style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:14px;font-weight:500;background:<?php echo esc_attr($opts['sale_bg']); ?>;border:1px solid <?php echo esc_attr($opts['sale_color']); ?>30;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="<?php echo esc_attr($opts['sale_color']); ?>">
                                <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1.003 1.003 0 0020 4H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z" />
                            </svg>
                            <span style="font-weight:700;color:<?php echo esc_attr($opts['sale_color']); ?>;">63</span>
                            <span style="color:<?php echo esc_attr($opts['label_color']); ?>;font-size:13px;">kez satın alındı</span>
                        </div>
                    </div>
                </div>

                <?php submit_button('Kaydet'); ?>
            </form>
        </div>
<?php
    }
}

new Webyaz_Social_Proof();
