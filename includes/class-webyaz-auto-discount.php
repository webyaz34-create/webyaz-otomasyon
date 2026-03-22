<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('WooCommerce')) return;

class Webyaz_Auto_Discount {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('woocommerce_before_calculate_totals', array($this, 'apply_cart_discount'), 25);
        add_action('woocommerce_cart_totals_before_order_total', array($this, 'show_discount_info'));
        add_action('woocommerce_review_order_before_order_total', array($this, 'show_discount_info'));
    }

    public function register_settings() {
        register_setting('webyaz_auto_discount_group', 'webyaz_auto_discount');
    }

    private static function get_defaults() {
        return array(
            'rules' => array(),
        );
    }

    public static function get_opts() {
        return wp_parse_args(get_option('webyaz_auto_discount', array()), self::get_defaults());
    }

    /**
     * Kural tipleri:
     * cart_total    = Sepet tutarına göre % indirim
     * cart_qty      = Sepetteki ürün adedine göre % indirim
     * category      = Belirli kategoriye X al Y öde
     * bogo          = 1 Al 1 Bedava (en ucuz ürüne)
     */
    public function apply_cart_discount($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (did_action('woocommerce_before_calculate_totals') >= 2) return;

        $opts = self::get_opts();
        if (empty($opts['rules'])) return;

        $subtotal = 0;
        $total_qty = 0;
        foreach ($cart->get_cart() as $item) {
            $subtotal += floatval($item['data']->get_price()) * $item['quantity'];
            $total_qty += $item['quantity'];
        }

        foreach ($opts['rules'] as $rule) {
            if (empty($rule['active']) || $rule['active'] !== '1') continue;
            $type = $rule['type'] ?? '';

            switch ($type) {
                case 'cart_total':
                    // Sepet tutarı X TL üstüyse Y% indirim
                    $min = floatval($rule['min_total'] ?? 0);
                    $disc = floatval($rule['discount'] ?? 0);
                    if ($subtotal >= $min && $disc > 0) {
                        foreach ($cart->get_cart() as $item) {
                            $price = floatval($item['data']->get_price());
                            $item['data']->set_price($price * (1 - $disc / 100));
                        }
                    }
                    break;

                case 'cart_qty':
                    // Sepette X adet üstüyse Y% indirim
                    $min_qty = intval($rule['min_qty'] ?? 0);
                    $disc = floatval($rule['discount'] ?? 0);
                    if ($total_qty >= $min_qty && $disc > 0) {
                        foreach ($cart->get_cart() as $item) {
                            $price = floatval($item['data']->get_price());
                            $item['data']->set_price($price * (1 - $disc / 100));
                        }
                    }
                    break;

                case 'bogo':
                    // 1 Al 1 Bedava — en ucuz ürün bedava
                    if ($total_qty >= 2) {
                        $cheapest_key = null;
                        $cheapest_price = PHP_FLOAT_MAX;
                        foreach ($cart->get_cart() as $key => $item) {
                            $price = floatval($item['data']->get_price());
                            if ($price < $cheapest_price) {
                                $cheapest_price = $price;
                                $cheapest_key = $key;
                            }
                        }
                        if ($cheapest_key !== null) {
                            $cart_items = $cart->get_cart();
                            $cart_items[$cheapest_key]['data']->set_price(0);
                        }
                    }
                    break;

                case 'category':
                    // Belirli kategoride X al Y öde
                    $cat_id = intval($rule['category_id'] ?? 0);
                    $buy = intval($rule['buy_qty'] ?? 3);
                    $pay = intval($rule['pay_qty'] ?? 2);
                    if ($cat_id <= 0 || $buy <= $pay) break;

                    $cat_items = array();
                    foreach ($cart->get_cart() as $key => $item) {
                        $product_id = $item['product_id'];
                        if (has_term($cat_id, 'product_cat', $product_id)) {
                            for ($i = 0; $i < $item['quantity']; $i++) {
                                $cat_items[] = array('key' => $key, 'price' => floatval($item['data']->get_price()));
                            }
                        }
                    }

                    // En ucuzlardan bedava yap
                    $cat_count = count($cat_items);
                    if ($cat_count >= $buy) {
                        usort($cat_items, function($a, $b) { return $a['price'] <=> $b['price']; });
                        $free_count = floor($cat_count / $buy) * ($buy - $pay);
                        // En ucuzları 0 TL yap — basitleştirilmiş yaklaşım: tüm ürüne oran uygula
                        $total_cat_price = array_sum(array_column($cat_items, 'price'));
                        $free_total = 0;
                        for ($i = 0; $i < $free_count && $i < $cat_count; $i++) {
                            $free_total += $cat_items[$i]['price'];
                        }
                        if ($total_cat_price > 0) {
                            $ratio = 1 - ($free_total / $total_cat_price);
                            foreach ($cart->get_cart() as $key => $item) {
                                if (has_term($cat_id, 'product_cat', $item['product_id'])) {
                                    $item['data']->set_price(floatval($item['data']->get_price()) * $ratio);
                                }
                            }
                        }
                    }
                    break;
            }
        }
    }

    public function show_discount_info() {
        $opts = self::get_opts();
        if (empty($opts['rules'])) return;

        // Sepet bilgilerini al
        $cart = WC()->cart;
        if (!$cart) return;
        $subtotal = $cart->get_subtotal();
        $total_qty = $cart->get_cart_contents_count();

        // Aynı tipten tüm kuralları topla ve sırayla kontrol et
        $cart_total_rules = array();
        $cart_qty_rules = array();

        foreach ($opts['rules'] as $rule) {
            if (empty($rule['active']) || $rule['active'] !== '1') continue;
            $type = $rule['type'] ?? '';

            switch ($type) {
                case 'cart_total':
                    $cart_total_rules[] = $rule;
                    break;
                case 'cart_qty':
                    $cart_qty_rules[] = $rule;
                    break;
                case 'bogo':
                    if ($total_qty >= 2) {
                        echo '<tr><th colspan="2" style="padding:10px 0;"><div style="background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border-radius:10px;padding:14px 18px;border-left:4px solid #4caf50;">';
                        echo '<div style="color:#2e7d32;font-weight:700;font-size:14px;">🎁 1 Al 1 Bedava Uygulandı!</div>';
                        echo '<div style="color:#388e3c;font-size:12px;margin-top:4px;">En düşük fiyatlı ürün <strong>bedava</strong> oldu</div>';
                        echo '</div></th></tr>';
                    } else {
                        echo '<tr><th colspan="2" style="padding:10px 0;"><div style="background:linear-gradient(135deg,#fff3e0,#ffe0b2);border-radius:10px;padding:14px 18px;border-left:4px solid #ff9800;cursor:pointer;" onclick="window.location.href=\'' . esc_url(wc_get_page_permalink('shop')) . '\'">';
                        echo '<div style="color:#e65100;font-weight:700;font-size:14px;">🎁 1 ürün daha ekleyin → 1 Al 1 Bedava!</div>';
                        echo '<div style="color:#f57c00;font-size:12px;margin-top:4px;">Sepete 1 ürün daha ekleyin, en ucuz ürün <strong>bedava</strong> olsun</div>';
                        echo '</div></th></tr>';
                    }
                    break;
                case 'category':
                    $cat_id = intval($rule['category_id'] ?? 0);
                    $buy = intval($rule['buy_qty'] ?? 3);
                    $pay = intval($rule['pay_qty'] ?? 2);
                    $cat_qty = 0;
                    foreach ($cart->get_cart() as $item) {
                        if (has_term($cat_id, 'product_cat', $item['product_id'])) {
                            $cat_qty += $item['quantity'];
                        }
                    }
                    $cat_obj = get_term($cat_id, 'product_cat');
                    $cat_name = $cat_obj && !is_wp_error($cat_obj) ? $cat_obj->name : '';
                    $remaining = $buy - ($cat_qty % $buy);
                    if ($remaining == $buy) $remaining = 0;

                    if ($cat_qty >= $buy) {
                        $free_count = floor($cat_qty / $buy) * ($buy - $pay);
                        echo '<tr><th colspan="2" style="padding:10px 0;"><div style="background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border-radius:10px;padding:14px 18px;border-left:4px solid #4caf50;">';
                        echo '<div style="color:#2e7d32;font-weight:700;font-size:14px;">🎉 ' . $buy . ' Al ' . $pay . ' Öde Uygulandı!' . ($cat_name ? ' (' . esc_html($cat_name) . ')' : '') . '</div>';
                        echo '<div style="color:#388e3c;font-size:12px;margin-top:4px;"><strong>' . $free_count . ' ürün</strong> bedava oldu!</div>';
                        echo '</div></th></tr>';
                        if ($remaining > 0) {
                            echo '<tr><th colspan="2" style="padding:4px 0;"><div style="background:linear-gradient(135deg,#fff3e0,#ffe0b2);border-radius:10px;padding:12px 18px;border-left:4px solid #ff9800;">';
                            echo '<div style="color:#e65100;font-weight:600;font-size:13px;">🔥 ' . $remaining . ' ürün daha ekleyin → ' . ($buy - $pay) . ' ürün daha bedava!</div>';
                            echo '</div></th></tr>';
                        }
                    } else {
                        echo '<tr><th colspan="2" style="padding:10px 0;"><div style="background:linear-gradient(135deg,#fff3e0,#ffe0b2);border-radius:10px;padding:14px 18px;border-left:4px solid #ff9800;cursor:pointer;" onclick="window.location.href=\'' . esc_url(wc_get_page_permalink('shop')) . '\'">';
                        echo '<div style="color:#e65100;font-weight:700;font-size:14px;">🏷️ ' . $remaining . ' ürün daha → ' . $buy . ' Al ' . $pay . ' Öde!' . ($cat_name ? ' (' . esc_html($cat_name) . ')' : '') . '</div>';
                        echo '<div style="color:#f57c00;font-size:12px;margin-top:4px;">' . esc_html($cat_name) . ' kategorisinden <strong>' . $remaining . ' ürün</strong> daha ekleyin, ' . ($buy - $pay) . ' tanesi <strong>bedava</strong> olsun!</div>';
                        echo '</div></th></tr>';
                    }
                    break;
            }
        }

        // Sepet tutarı kuralları — kademeli göster
        if (!empty($cart_total_rules)) {
            usort($cart_total_rules, function($a, $b) { return floatval($a['min_total'] ?? 0) <=> floatval($b['min_total'] ?? 0); });
            $applied_rule = null;
            $next_rule = null;
            foreach ($cart_total_rules as $rule) {
                $min = floatval($rule['min_total'] ?? 0);
                if ($subtotal >= $min) {
                    $applied_rule = $rule;
                } else {
                    if (!$next_rule) $next_rule = $rule;
                }
            }

            if ($applied_rule) {
                $disc = floatval($applied_rule['discount'] ?? 0);
                $saved = wc_price($subtotal * $disc / 100);
                echo '<tr><th colspan="2" style="padding:10px 0;"><div style="background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border-radius:10px;padding:14px 18px;border-left:4px solid #4caf50;">';
                echo '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">';
                echo '<div><div style="color:#2e7d32;font-weight:700;font-size:14px;">🎉 %' . esc_html($disc) . ' İndirim Uygulandı!</div>';
                echo '<div style="color:#388e3c;font-size:12px;margin-top:4px;">' . esc_html($applied_rule['min_total']) . ' TL üstü alışverişte</div></div>';
                echo '<div style="background:#2e7d32;color:#fff;padding:6px 14px;border-radius:20px;font-size:13px;font-weight:700;">−' . $saved . ' kazandınız</div>';
                echo '</div></div></th></tr>';
            }

            if ($next_rule) {
                $next_min = floatval($next_rule['min_total'] ?? 0);
                $next_disc = floatval($next_rule['discount'] ?? 0);
                $remaining = $next_min - $subtotal;
                $progress = min(100, ($subtotal / $next_min) * 100);
                echo '<tr><th colspan="2" style="padding:' . ($applied_rule ? '4' : '10') . 'px 0;"><div style="background:linear-gradient(135deg,#fff3e0,#ffe0b2);border-radius:10px;padding:14px 18px;border-left:4px solid #ff9800;cursor:pointer;" onclick="window.location.href=\'' . esc_url(wc_get_page_permalink('shop')) . '\'">';
                echo '<div style="color:#e65100;font-weight:700;font-size:14px;">🔥 ' . wc_price($remaining) . ' daha → %' . esc_html($next_disc) . ' indirim!</div>';
                echo '<div style="color:#f57c00;font-size:12px;margin-top:4px;">' . esc_html($next_min) . ' TL\'ye tamamlayın, <strong>%' . esc_html($next_disc) . ' indirim</strong> kazanın!</div>';
                echo '<div style="background:rgba(255,152,0,0.2);border-radius:20px;height:6px;margin-top:10px;overflow:hidden;"><div style="height:100%;background:#ff9800;border-radius:20px;width:' . $progress . '%;transition:width .3s;"></div></div>';
                echo '</div></th></tr>';
            }
        }

        // Ürün adedi kuralları — kademeli göster
        if (!empty($cart_qty_rules)) {
            usort($cart_qty_rules, function($a, $b) { return intval($a['min_qty'] ?? 0) <=> intval($b['min_qty'] ?? 0); });
            $applied_rule = null;
            $next_rule = null;
            foreach ($cart_qty_rules as $rule) {
                $min_qty = intval($rule['min_qty'] ?? 0);
                if ($total_qty >= $min_qty) {
                    $applied_rule = $rule;
                } else {
                    if (!$next_rule) $next_rule = $rule;
                }
            }

            if ($applied_rule) {
                $disc = floatval($applied_rule['discount'] ?? 0);
                $saved = wc_price($subtotal * $disc / 100);
                echo '<tr><th colspan="2" style="padding:10px 0;"><div style="background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border-radius:10px;padding:14px 18px;border-left:4px solid #4caf50;">';
                echo '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">';
                echo '<div><div style="color:#2e7d32;font-weight:700;font-size:14px;">🎉 %' . esc_html($disc) . ' İndirim Uygulandı!</div>';
                echo '<div style="color:#388e3c;font-size:12px;margin-top:4px;">' . $total_qty . ' ürün sepetinizde (' . esc_html($applied_rule['min_qty']) . '+ ürün indirimi)</div></div>';
                echo '<div style="background:#2e7d32;color:#fff;padding:6px 14px;border-radius:20px;font-size:13px;font-weight:700;">−' . $saved . ' kazandınız</div>';
                echo '</div></div></th></tr>';
            }

            if ($next_rule) {
                $next_qty = intval($next_rule['min_qty'] ?? 0);
                $next_disc = floatval($next_rule['discount'] ?? 0);
                $remaining = $next_qty - $total_qty;
                $progress = min(100, ($total_qty / $next_qty) * 100);
                echo '<tr><th colspan="2" style="padding:' . ($applied_rule ? '4' : '10') . 'px 0;"><div style="background:linear-gradient(135deg,#fff3e0,#ffe0b2);border-radius:10px;padding:14px 18px;border-left:4px solid #ff9800;cursor:pointer;" onclick="window.location.href=\'' . esc_url(wc_get_page_permalink('shop')) . '\'">';
                echo '<div style="color:#e65100;font-weight:700;font-size:14px;">🔥 ' . $remaining . ' ürün daha ekleyin → %' . esc_html($next_disc) . ' indirim!</div>';
                echo '<div style="color:#f57c00;font-size:12px;margin-top:4px;">Sepetinize <strong>' . $remaining . ' ürün</strong> daha ekleyin, <strong>%' . esc_html($next_disc) . ' indirime</strong> ulaşın!</div>';
                echo '<div style="background:rgba(255,152,0,0.2);border-radius:20px;height:6px;margin-top:10px;overflow:hidden;"><div style="height:100%;background:#ff9800;border-radius:20px;width:' . $progress . '%;transition:width .3s;"></div></div>';
                echo '</div></th></tr>';
            }
        }
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Otomatik Indirim', 'Otomatik Indirim', 'manage_options', 'webyaz-auto-discount', array($this, 'render_admin'));
    }

    public function render_admin() {
        $opts = self::get_opts();
        if (!is_array($opts['rules'])) $opts['rules'] = array();

        // Kategorileri çek
        $categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header"><h1>🏷️ Otomatik İndirim Kuralları</h1><p>Kupon gerektirmeden otomatik sepet indirimleri tanımlayın</p></div>
            <?php if (isset($_GET['settings-updated'])): ?><div class="webyaz-notice success">Ayarlar kaydedildi!</div><?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('webyaz_auto_discount_group'); ?>
                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title">İndirim Kuralları</h2>
                    <p style="color:#666;font-size:12px;margin:-5px 0 15px;">Her kural bağımsız çalışır. Aktif/Pasif yapabilirsiniz.</p>
                    <div id="wyAutoRules">
                        <?php foreach ($opts['rules'] as $i => $rule):
                            $type = $rule['type'] ?? 'cart_total';
                            $is_active = ($rule['active'] ?? '0') === '1';
                        ?>
                        <div class="wy-rule-card" style="background:#f8f9fa;border:2px solid <?php echo $is_active ? '#4caf50' : '#e0e0e0'; ?>;border-radius:10px;padding:18px;margin-bottom:12px;position:relative;">
                            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
                                <select name="webyaz_auto_discount[rules][<?php echo $i; ?>][type]" style="padding:8px 12px;border-radius:6px;border:1px solid #ddd;" onchange="wyToggleRuleFields(this)">
                                    <option value="cart_total" <?php selected($type, 'cart_total'); ?>>Sepet Tutarına Göre</option>
                                    <option value="cart_qty" <?php selected($type, 'cart_qty'); ?>>Ürün Adedine Göre</option>
                                    <option value="bogo" <?php selected($type, 'bogo'); ?>>1 Al 1 Bedava</option>
                                    <option value="category" <?php selected($type, 'category'); ?>>Kategori: X Al Y Öde</option>
                                </select>

                                <span class="wy-field-total" style="<?php echo $type !== 'cart_total' ? 'display:none;' : ''; ?>">
                                    <input type="number" name="webyaz_auto_discount[rules][<?php echo $i; ?>][min_total]" value="<?php echo esc_attr($rule['min_total'] ?? ''); ?>" placeholder="Min TL" style="width:90px;padding:8px;border-radius:6px;border:1px solid #ddd;">
                                    TL üstü
                                </span>

                                <span class="wy-field-qty" style="<?php echo $type !== 'cart_qty' ? 'display:none;' : ''; ?>">
                                    <input type="number" name="webyaz_auto_discount[rules][<?php echo $i; ?>][min_qty]" value="<?php echo esc_attr($rule['min_qty'] ?? ''); ?>" placeholder="Min adet" style="width:80px;padding:8px;border-radius:6px;border:1px solid #ddd;">
                                    adet üstü
                                </span>

                                <span class="wy-field-discount" style="<?php echo $type === 'bogo' || $type === 'category' ? 'display:none;' : ''; ?>">
                                    %<input type="number" step="0.1" name="webyaz_auto_discount[rules][<?php echo $i; ?>][discount]" value="<?php echo esc_attr($rule['discount'] ?? ''); ?>" placeholder="%" style="width:70px;padding:8px;border-radius:6px;border:1px solid #ddd;"> indirim
                                </span>

                                <span class="wy-field-cat" style="<?php echo $type !== 'category' ? 'display:none;' : ''; ?>">
                                    <select name="webyaz_auto_discount[rules][<?php echo $i; ?>][category_id]" style="padding:8px;border-radius:6px;border:1px solid #ddd;">
                                        <option value="">Kategori Sec</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat->term_id; ?>" <?php selected($rule['category_id'] ?? '', $cat->term_id); ?>><?php echo esc_html($cat->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="number" name="webyaz_auto_discount[rules][<?php echo $i; ?>][buy_qty]" value="<?php echo esc_attr($rule['buy_qty'] ?? '3'); ?>" style="width:50px;padding:8px;border-radius:6px;border:1px solid #ddd;"> Al
                                    <input type="number" name="webyaz_auto_discount[rules][<?php echo $i; ?>][pay_qty]" value="<?php echo esc_attr($rule['pay_qty'] ?? '2'); ?>" style="width:50px;padding:8px;border-radius:6px;border:1px solid #ddd;"> Öde
                                </span>

                                <label style="display:flex;align-items:center;gap:4px;margin-left:auto;cursor:pointer;">
                                    <input type="checkbox" name="webyaz_auto_discount[rules][<?php echo $i; ?>][active]" value="1" <?php checked($rule['active'] ?? '0', '1'); ?> style="accent-color:#4caf50;">
                                    <span style="font-size:12px;font-weight:600;color:<?php echo $is_active ? '#4caf50' : '#999'; ?>;"><?php echo $is_active ? 'Aktif' : 'Pasif'; ?></span>
                                </label>

                                <button type="button" onclick="this.closest('.wy-rule-card').remove();" style="background:#fde8e8;color:#d32f2f;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;font-weight:600;">✕</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="wyAddRule" class="button" style="margin-top:8px;">+ Yeni Kural Ekle</button>
                </div>

                <div style="background:#fff8e1;padding:15px 20px;border-left:4px solid #f9a825;border-radius:0 8px 8px 0;margin-bottom:20px;">
                    <strong>💡 İpuçları:</strong>
                    <ul style="margin:8px 0 0 18px;font-size:13px;color:#555;line-height:1.8;">
                        <li><strong>Sepet Tutarına Göre:</strong> 500 TL üstü %10 indirim gibi kurallar tanımlayın.</li>
                        <li><strong>Ürün Adedine Göre:</strong> 5+ ürün almışsa %15 indirim.</li>
                        <li><strong>1 Al 1 Bedava:</strong> Sepetteki en ucuz ürün otomatik bedava olur.</li>
                        <li><strong>X Al Y Öde:</strong> Belirli kategoride 3 Al 2 Öde gibi kampanyalar.</li>
                    </ul>
                </div>

                <?php submit_button('Kaydet'); ?>
            </form>
        </div>
        <script>
        var wyRuleIdx = <?php echo count($opts['rules']); ?>;
        document.getElementById('wyAddRule').addEventListener('click', function(){
            var html = '<div class="wy-rule-card" style="background:#f8f9fa;border:2px solid #e0e0e0;border-radius:10px;padding:18px;margin-bottom:12px;">';
            html += '<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">';
            html += '<select name="webyaz_auto_discount[rules]['+wyRuleIdx+'][type]" style="padding:8px 12px;border-radius:6px;border:1px solid #ddd;" onchange="wyToggleRuleFields(this)">';
            html += '<option value="cart_total">Sepet Tutarina Gore</option><option value="cart_qty">Urun Adedine Gore</option><option value="bogo">1 Al 1 Bedava</option><option value="category">Kategori: X Al Y Ode</option></select>';
            html += '<span class="wy-field-total"><input type="number" name="webyaz_auto_discount[rules]['+wyRuleIdx+'][min_total]" placeholder="Min TL" style="width:90px;padding:8px;border-radius:6px;border:1px solid #ddd;"> TL ustu</span>';
            html += '<span class="wy-field-qty" style="display:none;"><input type="number" name="webyaz_auto_discount[rules]['+wyRuleIdx+'][min_qty]" placeholder="Min adet" style="width:80px;padding:8px;border-radius:6px;border:1px solid #ddd;"> adet ustu</span>';
            html += '<span class="wy-field-discount">%<input type="number" step="0.1" name="webyaz_auto_discount[rules]['+wyRuleIdx+'][discount]" placeholder="%" style="width:70px;padding:8px;border-radius:6px;border:1px solid #ddd;"> indirim</span>';
            html += '<span class="wy-field-cat" style="display:none;"><select name="webyaz_auto_discount[rules]['+wyRuleIdx+'][category_id]" style="padding:8px;border-radius:6px;border:1px solid #ddd;"><option value="">Kategori Sec</option><?php foreach($categories as $cat): ?><option value="<?php echo $cat->term_id; ?>"><?php echo esc_js($cat->name); ?></option><?php endforeach; ?></select>';
            html += '<input type="number" name="webyaz_auto_discount[rules]['+wyRuleIdx+'][buy_qty]" value="3" style="width:50px;padding:8px;border-radius:6px;border:1px solid #ddd;"> Al <input type="number" name="webyaz_auto_discount[rules]['+wyRuleIdx+'][pay_qty]" value="2" style="width:50px;padding:8px;border-radius:6px;border:1px solid #ddd;"> Ode</span>';
            html += '<label style="display:flex;align-items:center;gap:4px;margin-left:auto;cursor:pointer;"><input type="checkbox" name="webyaz_auto_discount[rules]['+wyRuleIdx+'][active]" value="1" checked style="accent-color:#4caf50;"><span style="font-size:12px;font-weight:600;color:#4caf50;">Aktif</span></label>';
            html += '<button type="button" onclick="this.closest(\'.wy-rule-card\').remove();" style="background:#fde8e8;color:#d32f2f;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;font-weight:600;">✕</button>';
            html += '</div></div>';
            document.getElementById('wyAutoRules').insertAdjacentHTML('beforeend', html);
            wyRuleIdx++;
        });

        function wyToggleRuleFields(sel) {
            var card = sel.closest('.wy-rule-card');
            var v = sel.value;
            card.querySelector('.wy-field-total').style.display = (v==='cart_total') ? '' : 'none';
            card.querySelector('.wy-field-qty').style.display = (v==='cart_qty') ? '' : 'none';
            card.querySelector('.wy-field-discount').style.display = (v==='cart_total'||v==='cart_qty') ? '' : 'none';
            card.querySelector('.wy-field-cat').style.display = (v==='category') ? '' : 'none';
        }
        </script>
        <?php
    }
}

new Webyaz_Auto_Discount();
