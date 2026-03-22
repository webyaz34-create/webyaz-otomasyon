<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Invoice
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('woocommerce_order_status_completed', array($this, 'auto_generate'));
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('wp_ajax_webyaz_generate_invoice', array($this, 'ajax_generate'));
        add_action('wp_ajax_webyaz_download_invoice', array($this, 'ajax_download'));
    }

    public function add_submenu()
    {
        add_submenu_page(
            'webyaz-dashboard',
            'E-Fatura',
            'E-Fatura',
            'manage_options',
            'webyaz-invoice',
            array($this, 'render_admin')
        );
    }

    public function register_settings()
    {
        register_setting('webyaz_invoice_group', 'webyaz_invoice');
    }

    private static function get_defaults()
    {
        return array(
            'auto_generate'    => '0',
            'invoice_prefix'   => 'WBY',
            'invoice_series'   => 'A',
            'next_number'      => '1',
            'note_text'        => '',
            'show_logo'        => '1',
            'show_tc'          => '1',
            'format'           => 'html',
        );
    }

    public static function get($key)
    {
        $opts = wp_parse_args(get_option('webyaz_invoice', array()), self::get_defaults());
        return isset($opts[$key]) ? $opts[$key] : '';
    }

    // --- Fatura numarasi uret ---
    private static function get_next_number()
    {
        $opts = wp_parse_args(get_option('webyaz_invoice', array()), self::get_defaults());
        $prefix = $opts['invoice_prefix'];
        $series = $opts['invoice_series'];
        $num = intval($opts['next_number']);
        $invoice_no = $prefix . $series . str_pad($num, 6, '0', STR_PAD_LEFT);

        $opts['next_number'] = $num + 1;
        update_option('webyaz_invoice', $opts);

        return $invoice_no;
    }

    // --- Siparis tamamlandiginda otomatik fatura ---
    public function auto_generate($order_id)
    {
        if (self::get('auto_generate') !== '1') return;
        $existing = get_post_meta($order_id, '_webyaz_invoice_no', true);
        if (!empty($existing)) return;
        self::generate_invoice($order_id);
    }

    // --- Fatura olustur ---
    public static function generate_invoice($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) return false;

        $invoice_no = self::get_next_number();
        update_post_meta($order_id, '_webyaz_invoice_no', $invoice_no);
        update_post_meta($order_id, '_webyaz_invoice_date', current_time('Y-m-d H:i:s'));

        return $invoice_no;
    }

    // --- AJAX: Fatura olustur ---
    public function ajax_generate()
    {
        check_ajax_referer('webyaz_nonce', 'nonce');
        $order_id = intval($_POST['order_id']);
        $invoice_no = self::generate_invoice($order_id);
        if ($invoice_no) {
            wp_send_json_success(array('invoice_no' => $invoice_no));
        } else {
            wp_send_json_error('Fatura olusturulamadi.');
        }
    }

    // --- AJAX: Fatura indir (HTML) ---
    public function ajax_download()
    {
        if (!current_user_can('manage_options')) wp_die('Yetki yok');
        $order_id = intval($_GET['order_id']);
        $order = wc_get_order($order_id);
        if (!$order) wp_die('Siparis bulunamadi');

        $invoice_no = get_post_meta($order_id, '_webyaz_invoice_no', true);
        if (empty($invoice_no)) wp_die('Fatura bulunamadi');

        $invoice_date = get_post_meta($order_id, '_webyaz_invoice_date', true);
        $company = class_exists('Webyaz_Settings') ? Webyaz_Settings::get_all() : array();
        $customer_type = get_post_meta($order_id, '_webyaz_customer_type', true);

        $primary = '#446084';
        $secondary = '#d26e4b';
        if (class_exists('Webyaz_Colors')) {
            $c = Webyaz_Colors::get_theme_colors();
            $primary = $c['primary'];
            $secondary = $c['secondary'];
        }

        header('Content-Type: text/html; charset=utf-8');
?>
        <!DOCTYPE html>
        <html>

        <head>
            <meta charset="UTF-8">
            <title>Fatura <?php echo esc_html($invoice_no); ?></title>
            <style>
                body {
                    font-family: 'Roboto', Arial, sans-serif;
                    color: #333;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 40px 30px;
                }

                .inv-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    margin-bottom: 30px;
                    padding-bottom: 20px;
                    border-bottom: 3px solid <?php echo $primary; ?>;
                }

                .inv-company {
                    font-size: 22px;
                    font-weight: 900;
                    color: <?php echo $primary; ?>;
                }

                .inv-company p {
                    font-size: 13px;
                    color: #666;
                    font-weight: 400;
                    margin: 4px 0;
                }

                .inv-info {
                    text-align: right;
                }

                .inv-info .inv-no {
                    font-size: 18px;
                    font-weight: 700;
                    color: <?php echo $secondary; ?>;
                }

                .inv-info p {
                    font-size: 13px;
                    color: #666;
                    margin: 3px 0;
                }

                .inv-parties {
                    display: flex;
                    gap: 30px;
                    margin-bottom: 25px;
                }

                .inv-party {
                    flex: 1;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 8px;
                    border-left: 3px solid <?php echo $primary; ?>;
                }

                .inv-party h4 {
                    margin: 0 0 8px;
                    font-size: 14px;
                    color: <?php echo $primary; ?>;
                }

                .inv-party p {
                    margin: 2px 0;
                    font-size: 13px;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }

                th {
                    background: <?php echo $primary; ?>;
                    color: #fff;
                    padding: 10px 12px;
                    text-align: left;
                    font-size: 13px;
                }

                td {
                    padding: 10px 12px;
                    border-bottom: 1px solid #eee;
                    font-size: 13px;
                }

                .inv-total {
                    text-align: right;
                    font-size: 18px;
                    font-weight: 900;
                    color: <?php echo $secondary; ?>;
                    margin-top: 10px;
                }

                .inv-note {
                    margin-top: 30px;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 8px;
                    font-size: 13px;
                    color: #666;
                }

                .inv-footer {
                    margin-top: 40px;
                    text-align: center;
                    font-size: 12px;
                    color: #999;
                    border-top: 1px solid #eee;
                    padding-top: 15px;
                }

                @media print {
                    body {
                        padding: 20px;
                    }

                    .no-print {
                        display: none;
                    }
                }
            </style>
        </head>

        <body>
            <button onclick="window.print()" class="no-print" style="background:<?php echo $secondary; ?>;color:#fff;border:none;padding:10px 24px;border-radius:6px;cursor:pointer;font-weight:700;margin-bottom:20px;">Yazdir / PDF</button>

            <div class="inv-header">
                <div class="inv-company">
                    <?php echo esc_html(!empty($company['company_name']) ? $company['company_name'] : get_bloginfo('name')); ?>
                    <p><?php echo esc_html(!empty($company['company_address']) ? $company['company_address'] : ''); ?></p>
                    <p>Tel: <?php echo esc_html(!empty($company['company_phone']) ? $company['company_phone'] : ''); ?> | E-posta: <?php echo esc_html(!empty($company['company_email']) ? $company['company_email'] : ''); ?></p>
                    <?php if (!empty($company['company_tax_office'])): ?>
                        <p>V.D: <?php echo esc_html($company['company_tax_office']); ?> | V.No: <?php echo esc_html(!empty($company['company_tax_no']) ? $company['company_tax_no'] : ''); ?></p>
                    <?php endif; ?>
                </div>
                <div class="inv-info">
                    <div class="inv-no"><?php echo esc_html($invoice_no); ?></div>
                    <p>Tarih: <?php echo esc_html(date('d.m.Y', strtotime($invoice_date))); ?></p>
                    <p>Siparis: #<?php echo esc_html($order->get_order_number()); ?></p>
                </div>
            </div>

            <div class="inv-parties">
                <div class="inv-party">
                    <h4>Musteri Bilgileri</h4>
                    <p><strong><?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></strong></p>
                    <p><?php echo esc_html($order->get_billing_address_1()); ?></p>
                    <p><?php echo esc_html($order->get_billing_city() . ' / ' . $order->get_billing_state()); ?></p>
                    <p>Tel: <?php echo esc_html($order->get_billing_phone()); ?></p>
                    <?php if ($customer_type === 'kurumsal'): ?>
                        <p>Firma: <?php echo esc_html(get_post_meta($order_id, '_webyaz_firma_adi', true)); ?></p>
                        <p>V.D: <?php echo esc_html(get_post_meta($order_id, '_webyaz_vergi_dairesi', true)); ?> | V.No: <?php echo esc_html(get_post_meta($order_id, '_webyaz_vergi_no', true)); ?></p>
                    <?php else: ?>
                        <?php $tc = get_post_meta($order_id, '_webyaz_tc_kimlik', true);
                        if (!empty($tc)): ?>
                            <p>T.C: <?php echo esc_html($tc); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="inv-party">
                    <h4>Teslimat Adresi</h4>
                    <p><strong><?php echo esc_html($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()); ?></strong></p>
                    <p><?php echo esc_html($order->get_shipping_address_1()); ?></p>
                    <p><?php echo esc_html($order->get_shipping_city() . ' / ' . $order->get_shipping_state()); ?></p>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Urun</th>
                        <th>Adet</th>
                        <th>Birim Fiyat</th>
                        <th>Toplam</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1;
                    foreach ($order->get_items() as $item): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo esc_html($item->get_name()); ?></td>
                            <td><?php echo intval($item->get_quantity()); ?></td>
                            <td><?php echo wc_price($item->get_total() / max(1, $item->get_quantity())); ?></td>
                            <td><?php echo wc_price($item->get_total()); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="text-align:right;">
                <p>Ara Toplam: <?php echo wc_price($order->get_subtotal()); ?></p>
                <?php if (floatval($order->get_shipping_total()) > 0): ?>
                    <p>Kargo: <?php echo wc_price($order->get_shipping_total()); ?></p>
                <?php endif; ?>
                <?php if (floatval($order->get_total_tax()) > 0): ?>
                    <p>KDV: <?php echo wc_price($order->get_total_tax()); ?></p>
                <?php endif; ?>
                <div class="inv-total">Genel Toplam: <?php echo $order->get_formatted_order_total(); ?></div>
            </div>

            <?php $note = self::get('note_text');
            if (!empty($note)): ?>
                <div class="inv-note"><?php echo esc_html($note); ?></div>
            <?php endif; ?>

            <div class="inv-footer">
                Bu belge <?php echo esc_html(!empty($company['company_name']) ? $company['company_name'] : get_bloginfo('name')); ?> tarafindan duzenlenmistir.
            </div>
        </body>

        </html>
    <?php
        exit;
    }

    // --- Siparis sayfasinda meta box ---
    public function add_meta_box()
    {
        add_meta_box(
            'webyaz_invoice_box',
            'E-Fatura',
            array($this, 'meta_box_html'),
            'shop_order',
            'side',
            'high'
        );
    }

    public function meta_box_html($post)
    {
        $invoice_no = get_post_meta($post->ID, '_webyaz_invoice_no', true);
        $invoice_date = get_post_meta($post->ID, '_webyaz_invoice_date', true);

        $primary = '#446084';
        if (class_exists('Webyaz_Colors')) {
            $c = Webyaz_Colors::get_theme_colors();
            $primary = $c['primary'];
        }

        if ($invoice_no) {
            echo '<div style="padding:10px;background:#e8f5e9;border-radius:6px;margin-bottom:10px;">';
            echo '<strong style="color:#2e7d32;">' . esc_html($invoice_no) . '</strong><br>';
            echo '<small>' . esc_html(date('d.m.Y H:i', strtotime($invoice_date))) . '</small>';
            echo '</div>';
            echo '<a href="' . admin_url('admin-ajax.php?action=webyaz_download_invoice&order_id=' . $post->ID) . '" target="_blank" class="button button-primary" style="background:' . $primary . ';border:none;width:100%;text-align:center;">Faturayi Gor / Indir</a>';
        } else {
            echo '<p style="color:#666;font-size:13px;">Bu siparis icin fatura olusturulmamis.</p>';
            echo '<button type="button" class="button button-primary" style="background:' . $primary . ';border:none;width:100%;text-align:center;" onclick="webyazGenerateInvoice(' . $post->ID . ')">Fatura Olustur</button>';
            echo '<script>
            function webyazGenerateInvoice(orderId) {
                jQuery.post(ajaxurl, {
                    action: "webyaz_generate_invoice",
                    nonce: "' . wp_create_nonce('webyaz_nonce') . '",
                    order_id: orderId
                }, function(r) {
                    if (r.success) {
                        alert("Fatura olusturuldu: " + r.data.invoice_no);
                        location.reload();
                    } else {
                        alert("Hata: " + r.data);
                    }
                });
            }
            </script>';
        }
    }

    // --- Admin Page ---
    public function render_admin()
    {
        $opts = wp_parse_args(get_option('webyaz_invoice', array()), self::get_defaults());

        $primary = '#446084';
        $secondary = '#d26e4b';
        if (class_exists('Webyaz_Colors')) {
            $colors = Webyaz_Colors::get_theme_colors();
            $primary = $colors['primary'];
            $secondary = $colors['secondary'];
        }
    ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header" style="background:linear-gradient(135deg,<?php echo $primary; ?>,<?php echo $secondary; ?>);">
                <h1>E-Fatura Ayarlari</h1>
                <p>Otomatik fatura olusturma ve numaralama sistemi</p>
            </div>

            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="webyaz-notice success">Ayarlar kaydedildi!</div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('webyaz_invoice_group'); ?>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title" style="border-bottom-color:<?php echo $secondary; ?>;">Fatura Ayarlari</h2>
                    <div class="webyaz-settings-grid">
                        <div class="webyaz-field">
                            <label>Otomatik Fatura Olustur</label>
                            <select name="webyaz_invoice[auto_generate]">
                                <option value="0" <?php selected($opts['auto_generate'], '0'); ?>>Kapali (Manuel)</option>
                                <option value="1" <?php selected($opts['auto_generate'], '1'); ?>>Aktif (Siparis tamamlaninca)</option>
                            </select>
                        </div>
                        <div class="webyaz-field">
                            <label>Fatura On Eki</label>
                            <input type="text" name="webyaz_invoice[invoice_prefix]" value="<?php echo esc_attr($opts['invoice_prefix']); ?>" placeholder="WBY">
                        </div>
                        <div class="webyaz-field">
                            <label>Seri</label>
                            <input type="text" name="webyaz_invoice[invoice_series]" value="<?php echo esc_attr($opts['invoice_series']); ?>" placeholder="A">
                        </div>
                        <div class="webyaz-field">
                            <label>Sonraki Numara</label>
                            <input type="number" name="webyaz_invoice[next_number]" value="<?php echo esc_attr($opts['next_number']); ?>" min="1">
                        </div>
                    </div>
                </div>

                <div class="webyaz-settings-section">
                    <h2 class="webyaz-section-title" style="border-bottom-color:<?php echo $secondary; ?>;">Fatura Notu</h2>
                    <div class="webyaz-field">
                        <label>Alt Not (faturanin altinda gorunur)</label>
                        <textarea name="webyaz_invoice[note_text]" rows="3" style="width:100%;font-size:13px;border:1px solid #ddd;border-radius:6px;padding:10px;"><?php echo esc_textarea($opts['note_text']); ?></textarea>
                    </div>
                </div>

                <div style="margin-top:20px;">
                    <?php submit_button('Ayarlari Kaydet', 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>
<?php
    }
}

new Webyaz_Invoice();
