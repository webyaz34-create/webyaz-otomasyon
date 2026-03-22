<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Product_Desc
{

    public function __construct()
    {
        add_filter('woocommerce_product_tabs', array($this, 'custom_desc_tab'), 98);
    }

    public function custom_desc_tab($tabs)
    {
        $tabs['description'] = array(
            'title'    => 'Urun Detaylari & Iletisim',
            'priority' => 10,
            'callback' => array($this, 'render_tab'),
        );
        return $tabs;
    }

    public function render_tab()
    {
        global $product, $post;

        $title = $product->get_name();
        $manual_content = $post->post_content;

        // Ayarlardan bilgileri cek
        $site = Webyaz_Settings::get('company_name');
        if (empty($site)) $site = get_bloginfo('name');
        $address = Webyaz_Settings::get('company_address');
        $phone = Webyaz_Settings::get('company_phone');
        $email = Webyaz_Settings::get('company_email');
        $whatsapp = Webyaz_Settings::get('whatsapp_number');

        $socials = array(
            'instagram' => Webyaz_Settings::get('social_instagram'),
            'facebook' => Webyaz_Settings::get('social_facebook'),
            'youtube' => Webyaz_Settings::get('social_youtube'),
            'twitter' => Webyaz_Settings::get('social_twitter'),
            'tiktok' => Webyaz_Settings::get('social_tiktok'),
            'linkedin' => Webyaz_Settings::get('social_linkedin'),
        );

        // Tema renklerini cek
        $colors = Webyaz_Colors::get_theme_colors();
        $primary = $colors['primary'];
        $secondary = $colors['secondary'];

        // Kontrast hesapla: koyu zeminde beyaz, acik zeminde koyu
        $is_primary_dark = $this->is_dark_color($primary);
        $on_primary = $is_primary_dark ? '#ffffff' : '#111111';
        $on_secondary = $this->is_dark_color($secondary) ? '#ffffff' : '#111111';
        $accent_on_primary = $is_primary_dark ? '#ffffff' : $secondary;
        $dark_class = $is_primary_dark ? 'wz-dark' : 'wz-light';
?>

        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

        <style>
            .wz-desc {
                font-family: 'Roboto', sans-serif;
                color: #111;
                line-height: 1.7;
                max-width: 1000px;
            }

            /* Baslik */
            .wz-header {
                border-left: 8px solid <?php echo $primary; ?>;
                padding: 15px 25px;
                margin-bottom: 35px;
                background: linear-gradient(90deg, #f4f6f9 0%, #fff 100%);
                border-radius: 0 10px 10px 0;
            }

            .wz-h2 {
                font-size: 2rem;
                color: <?php echo $primary; ?>;
                font-weight: 900;
                margin: 0;
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            .wz-sub {
                font-size: 1.15rem;
                color: <?php echo $secondary; ?>;
                font-weight: 500;
                margin-top: 8px;
            }

            /* Manuel Icerik Kutusu */
            .wz-custom-box {
                border: 3px dashed <?php echo $primary; ?>;
                background: #fffaf5;
                padding: 35px;
                border-radius: 15px;
                margin-bottom: 40px;
                text-align: center;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
                position: relative;
            }

            .wz-custom-box h1,
            .wz-custom-box h2,
            .wz-custom-box h3 {
                color: <?php echo $secondary; ?> !important;
                font-weight: 900;
                text-transform: uppercase;
                margin-bottom: 15px;
            }

            .wz-custom-box p {
                color: #111;
                font-size: 1.15rem;
                line-height: 1.8;
                font-weight: 500;
            }

            .wz-custom-box strong,
            .wz-custom-box b {
                color: <?php echo $secondary; ?>;
            }

            .wz-custom-box::before {
                content: '\f05a';
                font-family: 'Font Awesome 5 Free';
                font-weight: 900;
                position: absolute;
                top: -25px;
                left: 50%;
                transform: translateX(-50%);
                background: <?php echo $primary; ?>;
                color: <?php echo $on_primary; ?>;
                width: 50px;
                height: 50px;
                border: 3px solid #fff;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
            }

            /* Varsayilan Metin */
            .wz-default-text {
                margin-bottom: 25px;
                text-align: justify;
                font-size: 1.05rem;
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                border: 1px solid #eee;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
            }

            .wz-default-text p {
                margin-bottom: 15px;
            }

            /* Ozellik Grid */
            .wz-feat-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin: 40px 0;
            }

            .wz-feat-box {
                background: #fff;
                border: 2px solid #f0f0f0;
                padding: 25px 15px;
                border-radius: 12px;
                text-align: center;
                transition: 0.3s;
                overflow: hidden;
            }

            .wz-feat-box:hover {
                transform: translateY(-7px);
                border-color: <?php echo $secondary; ?>;
                box-shadow: 0 10px 20px rgba(0, 0, 0, 0.06);
            }

            .wz-fi {
                font-size: 2.5rem;
                color: <?php echo $primary; ?>;
                margin-bottom: 15px;
                transition: 0.3s;
            }

            .wz-feat-box:hover .wz-fi {
                color: <?php echo $secondary; ?>;
            }

            .wz-feat-title {
                font-weight: 900;
                color: <?php echo $primary; ?>;
                display: block;
                margin-bottom: 8px;
                text-transform: uppercase;
                font-size: 1rem;
            }

            /* Magaza Daveti */
            .wz-invite {
                border: 3px dashed <?php echo $secondary; ?>;
                background: #fffdf9;
                padding: 30px;
                border-radius: 15px;
                margin: 40px 0;
                text-align: center;
                position: relative;
            }

            .wz-invite::before {
                content: '\f54e';
                font-family: 'Font Awesome 5 Free';
                font-weight: 900;
                position: absolute;
                top: -25px;
                left: 50%;
                transform: translateX(-50%);
                background: <?php echo $secondary; ?>;
                color: #fff;
                width: 50px;
                height: 50px;
                border: 3px solid #fff;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.4rem;
            }

            .wz-inv-title {
                color: <?php echo $primary; ?>;
                font-weight: 900;
                font-size: 1.4rem;
                text-transform: uppercase;
                display: block;
                margin: 15px 0;
            }

            /* Hizmet Kutusu */
            .wz-services {
                background: #fff;
                border-radius: 10px;
                padding: 30px;
                margin: 40px 0;
                border: 2px solid #eee;
            }

            .wz-serv-head {
                color: <?php echo $primary; ?>;
                font-weight: 800;
                font-size: 1.3rem;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
            }

            .wz-check li {
                position: relative;
                padding-left: 30px;
                margin-bottom: 12px;
                font-weight: 500;
                list-style: none;
                color: #111;
            }

            .wz-check li::before {
                content: '\f058';
                font-family: 'Font Awesome 5 Free';
                font-weight: 900;
                color: <?php echo $secondary; ?>;
                position: absolute;
                left: 0;
                top: 3px;
                font-size: 1.1rem;
            }

            /* Toptan Satis */
            .wz-wholesale {
                background: linear-gradient(135deg, <?php echo $primary; ?>, <?php echo $this->adjust_color($primary, 30); ?>);
                color: <?php echo $on_primary; ?>;
                padding: 35px;
                border-radius: 12px;
                margin: 40px 0;
                display: flex;
                align-items: center;
                gap: 30px;
                box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
                border: 2px solid <?php echo $secondary; ?>;
            }

            .wz-ws-icon {
                font-size: 4rem;
                color: <?php echo $accent_on_primary; ?>;
            }

            .wz-ws-content h3 {
                color: <?php echo $on_primary; ?>;
                margin: 0 0 10px;
                font-weight: 900;
                text-transform: uppercase;
                font-size: 1.5rem;
                letter-spacing: 1px;
            }

            .wz-ws-content p {
                color: <?php echo $on_primary; ?>;
                opacity: 0.85;
                margin: 0;
                font-size: 1.1rem;
                line-height: 1.6;
            }

            /* Iletisim */
            .wz-contact-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin: 40px 0;
            }

            .wz-contact-card {
                background: #fff;
                border: 1px solid #eee;
                border-radius: 10px;
                padding: 25px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.03);
            }

            .wz-card-head {
                font-weight: 900;
                color: <?php echo $primary; ?>;
                text-transform: uppercase;
                font-size: 1.1rem;
                border-bottom: 3px solid <?php echo $secondary; ?>;
                padding-bottom: 10px;
                display: inline-block;
                margin-bottom: 15px;
            }

            .wz-addr-row {
                display: flex;
                margin-bottom: 12px;
                font-size: 1rem;
                color: #111;
                align-items: flex-start;
            }

            .wz-addr-icon {
                color: <?php echo $secondary; ?>;
                width: 25px;
                margin-top: 4px;
                font-size: 1.1rem;
            }

            .wz-socials a {
                display: inline-flex;
                width: 45px;
                height: 45px;
                background: <?php echo $primary; ?>;
                color: #fff;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                margin-right: 10px;
                text-decoration: none;
                transition: 0.3s;
                font-size: 1.3rem;
                border: 2px solid <?php echo $primary; ?>;
            }

            .wz-socials a:hover {
                background: #fff;
                color: <?php echo $secondary; ?>;
                border-color: <?php echo $secondary; ?>;
                transform: translateY(-3px);
            }

            /* Form */
            .wz-form-wrap {
                background: #fff;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
                border-radius: 15px;
                overflow: hidden;
                margin-top: 30px;
                border-top: 6px solid <?php echo $secondary; ?>;
            }

            .wz-form-top {
                background: <?php echo $primary; ?>;
                color: <?php echo $on_primary; ?>;
                padding: 30px;
                text-align: center;
            }

            .wz-form-top h3 {
                color: <?php echo $on_primary; ?> !important;
            }

            .wz-form-top p {
                color: <?php echo $on_primary; ?> !important;
                opacity: 0.85;
            }

            .wz-form-in {
                padding: 35px;
            }

            .wz-form-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 25px;
                margin-bottom: 25px;
            }

            .wz-inp {
                width: 100%;
                padding: 15px;
                border: 2px solid #eee;
                border-radius: 8px;
                font-family: 'Roboto', sans-serif;
                font-size: 1rem;
                transition: 0.3s;
                box-sizing: border-box;
            }

            .wz-inp:focus {
                border-color: <?php echo $secondary; ?>;
                outline: none;
                box-shadow: 0 0 0 4px <?php echo $secondary; ?>22;
            }

            .wz-btn {
                background: <?php echo $secondary; ?>;
                color: #fff;
                border: none;
                padding: 18px 40px;
                border-radius: 50px;
                font-weight: 900;
                text-transform: uppercase;
                cursor: pointer;
                width: 100%;
                font-size: 1.2rem;
                transition: 0.3s;
                box-shadow: 0 10px 20px <?php echo $secondary; ?>44;
                font-family: 'Roboto', sans-serif;
            }

            .wz-btn:hover {
                background: <?php echo $primary; ?>;
                transform: translateY(-3px);
                box-shadow: 0 15px 30px rgba(0, 0, 0, 0.25);
            }

            @media(max-width:768px) {

                .wz-wholesale,
                .wz-contact-grid {
                    grid-template-columns: 1fr;
                    flex-direction: column;
                    text-align: center;
                }

                .wz-form-row {
                    grid-template-columns: 1fr;
                }

                .wz-h2 {
                    font-size: 1.5rem;
                }

                .wz-custom-box {
                    padding: 25px 15px;
                }
            }
        </style>

        <div class="wz-desc <?php echo $dark_class; ?>">

            <!-- BASLIK -->
            <div class="wz-header">
                <h2 class="wz-h2"><?php echo esc_html($title); ?></h2>
                <div class="wz-sub"><?php echo esc_html($site); ?> Kalite Guvencesiyle</div>
            </div>

            <!-- ICERIK -->
            <?php if (!empty(trim($manual_content))): ?>
                <div class="wz-custom-box">
                    <?php echo do_shortcode(wpautop($manual_content)); ?>
                </div>
            <?php else: ?>
                <div class="wz-default-text">
                    <p>
                        <strong><?php echo esc_html($title); ?></strong>, <strong><?php echo esc_html($site); ?></strong> guvencesiyle
                        en yuksek kalite standartlarinda hazirlanmaktadir. Urunlerimiz titizlikle secilmis materyallerden uretilmekte
                        ve sizlere en iyi alisveris deneyimini sunmayi hedeflemektedir.
                    </p>
                </div>
            <?php endif; ?>

            <!-- MAGAZA DAVETI -->
            <?php if ($address): ?>
                <div class="wz-invite">
                    <span class="wz-inv-title">SHOWROOM / MAGAZAMIZA BEKLERIZ!</span>
                    <div style="color:#111;font-size:1.1rem;">
                        Urunlerimizi yakindan gormek ve incelemek ister misiniz?
                        <br><strong><?php echo esc_html($site); ?></strong> magazasini ziyaret ederek, uzman ekibimizle tanisabilirsiniz.
                    </div>
                </div>
            <?php endif; ?>

            <!-- OZELLIK GRID -->
            <div class="wz-feat-grid">
                <div class="wz-feat-box">
                    <i class="fas fa-shipping-fast wz-fi"></i>
                    <span class="wz-feat-title">Hizli Kargo</span>
                    <span style="font-size:13px;color:#111;">Siparisleriniz en kisa surede kargoya verilir.</span>
                </div>
                <div class="wz-feat-box">
                    <i class="fas fa-shield-alt wz-fi"></i>
                    <span class="wz-feat-title">Guvenli Odeme</span>
                    <span style="font-size:13px;color:#111;">256-bit SSL ve 3D Secure ile korunur.</span>
                </div>
                <div class="wz-feat-box">
                    <i class="fas fa-undo-alt wz-fi"></i>
                    <span class="wz-feat-title">Kolay Iade</span>
                    <span style="font-size:13px;color:#111;">14 gun icinde kosulsuz iade garantisi.</span>
                </div>
                <div class="wz-feat-box">
                    <i class="fas fa-headset wz-fi"></i>
                    <span class="wz-feat-title">7/24 Destek</span>
                    <span style="font-size:13px;color:#111;">WhatsApp ve e-posta ile her zaman yaninizdayiz.</span>
                </div>
            </div>

            <!-- HIZMET AYRICALIKLARI -->
            <div class="wz-services">
                <div class="wz-serv-head"><i class="fas fa-star" style="color:<?php echo esc_attr($secondary); ?>;margin-right:10px;"></i> <?php echo esc_html($site); ?> Hizmet Ayricaliklari</div>
                <ul class="wz-check">
                    <li><strong>Guvenli Paketleme:</strong> Urunleriniz ozenle paketlenerek, hasar riski olmadan kargoya verilir.</li>
                    <li><strong>Kalite Kontrol:</strong> Kargolanmadan once her urun detayli kalite kontrolunden gecirilir.</li>
                    <li><strong>Hizli Teslimat:</strong> Genis stok yapisayla siparisler hizla hazirlanir ve kargoya verilir.</li>
                    <li><strong>Musteri Memnuniyeti:</strong> Satis sonrasi destek ile her zaman yaninizdayiz.</li>
                    <li><strong>Ucretsiz Kargo:</strong> Belirli tutar uzerindeki siparislerde ucretsiz kargo firsati.</li>
                </ul>
            </div>

            <!-- TOPTAN SATIS -->
            <div class="wz-wholesale">
                <div class="wz-ws-icon"><i class="fas fa-boxes"></i></div>
                <div class="wz-ws-content">
                    <h3>TOPTAN SATIS & PROJE COZUMLERI</h3>
                    <p>Toplu alim yapmak ister misiniz? Istediginiz urun ve adetleri bize iletin,
                        <strong>size ozel toptan fiyat teklifimizi</strong> hemen sunalim.
                    </p>
                </div>
            </div>

            <!-- ILETISIM -->
            <div class="wz-contact-grid">
                <?php if ($address || $phone || $email): ?>
                    <div class="wz-contact-card">
                        <span class="wz-card-head">ILETISIM BILGILERI</span>
                        <?php if ($address): ?>
                            <div class="wz-addr-row">
                                <div class="wz-addr-icon"><i class="fas fa-map-marker-alt"></i></div>
                                <div><?php echo esc_html($address); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($phone): ?>
                            <div class="wz-addr-row">
                                <div class="wz-addr-icon"><i class="fas fa-phone-alt"></i></div>
                                <div><strong><a href="tel:<?php echo esc_attr($phone); ?>" style="color:inherit;text-decoration:none;"><?php echo esc_html($phone); ?></a></strong></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($email): ?>
                            <div class="wz-addr-row">
                                <div class="wz-addr-icon"><i class="fas fa-envelope"></i></div>
                                <div><a href="mailto:<?php echo esc_attr($email); ?>" style="color:inherit;text-decoration:none;"><?php echo esc_html($email); ?></a></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php
                $has_social = false;
                foreach ($socials as $v) {
                    if ($v) {
                        $has_social = true;
                        break;
                    }
                }
                if ($has_social):
                ?>
                    <div class="wz-contact-card" style="text-align:center;display:flex;flex-direction:column;justify-content:center;">
                        <span class="wz-card-head" style="border:none;margin-bottom:10px;">BIZI TAKIP EDIN</span>
                        <p style="font-size:0.9rem;margin-bottom:20px;">En yeni urunlerimizi, kampanyalari ve firsatlari kacirmayin.</p>
                        <div class="wz-socials">
                            <?php
                            $social_icons = array(
                                'instagram' => 'fab fa-instagram',
                                'facebook' => 'fab fa-facebook-f',
                                'youtube' => 'fab fa-youtube',
                                'twitter' => 'fab fa-x-twitter',
                                'tiktok' => 'fab fa-tiktok',
                                'linkedin' => 'fab fa-linkedin-in',
                            );
                            foreach ($socials as $key => $url):
                                if (empty($url)) continue;
                            ?>
                                <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><i class="<?php echo $social_icons[$key]; ?>"></i></a>
                            <?php endforeach; ?>
                            <?php if ($whatsapp): ?>
                                <a href="https://wa.me/<?php echo esc_attr($whatsapp); ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- WHATSAPP FORM -->
            <?php if ($whatsapp): ?>
                <div class="wz-form-wrap" id="wz-contact-form">
                    <div class="wz-form-top">
                        <h3 style="margin:0;font-size:1.5rem;font-weight:900;">BIZE ULASIN</h3>
                        <p style="margin:5px 0 0;opacity:0.8;font-size:0.9rem;">Sorulariniz, ozel talepleriniz veya toptan alim icin bize yazmaktan cekinmeyin.</p>
                    </div>
                    <div class="wz-form-in">
                        <div class="wz-form-row">
                            <input type="text" id="wz_name" class="wz-inp" placeholder="Adiniz Soyadiniz">
                            <input type="tel" id="wz_phone" class="wz-inp" placeholder="Telefon Numaraniz">
                        </div>
                        <div class="wz-form-row">
                            <input type="email" id="wz_email" class="wz-inp" placeholder="E-Posta Adresiniz">
                            <input type="text" id="wz_subject" class="wz-inp" value="<?php echo esc_attr($title); ?> Hakkinda Bilgi" placeholder="Konu">
                        </div>
                        <div style="margin-bottom:20px;">
                            <textarea id="wz_msg" class="wz-inp" rows="5" placeholder="Mesajiniz..."></textarea>
                        </div>
                        <button onclick="wzSendWhatsApp()" class="wz-btn"><i class="fab fa-whatsapp" style="margin-right:8px;"></i> WHATSAPP ILE GONDER</button>
                    </div>
                </div>

                <script>
                    function wzSendWhatsApp() {
                        var name = document.getElementById('wz_name').value;
                        var phone = document.getElementById('wz_phone').value;
                        var email = document.getElementById('wz_email').value;
                        var subject = document.getElementById('wz_subject').value;
                        var msg = document.getElementById('wz_msg').value;

                        if (name === "" || msg === "") {
                            alert("Lutfen en az Ad Soyad ve Mesaj alanlarini doldurunuz.");
                            return;
                        }

                        var text = "*<?php echo esc_js($site); ?> - Iletisim Formu*%0A" +
                            "--------------------------------%0A" +
                            "*Ad Soyad:* " + name + "%0A" +
                            "*Telefon:* " + phone + "%0A" +
                            "*E-posta:* " + email + "%0A" +
                            "*Konu:* " + subject + "%0A" +
                            "--------------------------------%0A" +
                            "*Mesaj:*%0A" + msg;

                        window.open("https://wa.me/<?php echo esc_js($whatsapp); ?>?text=" + text, '_blank');
                    }
                </script>
            <?php endif; ?>

        </div>
<?php
    }

    private function adjust_color($hex, $amount)
    {
        $hex = ltrim($hex, '#');
        $r = max(0, min(255, hexdec(substr($hex, 0, 2)) + $amount));
        $g = max(0, min(255, hexdec(substr($hex, 2, 2)) + $amount));
        $b = max(0, min(255, hexdec(substr($hex, 4, 2)) + $amount));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private function is_dark_color($hex)
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        // Luminance formulu (WCAG)
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return $luminance < 0.5;
    }
}

new Webyaz_Product_Desc();
