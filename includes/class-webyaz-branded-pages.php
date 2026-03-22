<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Branded_Pages {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_init', array($this, 'handle_actions'));
        add_shortcode('webyaz_contact_form', array($this, 'contact_form_shortcode'));
        add_action('wp_ajax_webyaz_contact_submit', array($this, 'handle_contact_submit'));
        add_action('wp_ajax_nopriv_webyaz_contact_submit', array($this, 'handle_contact_submit'));
        add_action('wp_head', array($this, 'output_page_seo'));
        add_action('wp_head', array($this, 'output_page_css'));
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Kurumsal Sayfalar', 'Kurumsal Sayfalar', 'manage_options', 'webyaz-branded-pages', array($this, 'render_admin'));
    }

    // ── ACTIONS ──
    public function handle_actions() {
        if (!isset($_POST['webyaz_branded_action'])) return;
        if (!wp_verify_nonce($_POST['_wpnonce_branded'], 'webyaz_branded')) return;
        if (!current_user_can('manage_options')) return;

        $action = sanitize_text_field($_POST['webyaz_branded_action']);

        if ($action === 'create') {
            $title   = sanitize_text_field($_POST['webyaz_branded_title']);
            $content = wp_kses_post($_POST['webyaz_branded_content']);
            $status  = isset($_POST['webyaz_branded_status']) && $_POST['webyaz_branded_status'] === 'draft' ? 'draft' : 'publish';
            $order   = intval($_POST['webyaz_branded_order'] ?? 0);
            if (!empty($title)) {
                $page_id = wp_insert_post(array(
                    'post_title'   => $title,
                    'post_content' => $content,
                    'post_status'  => $status,
                    'post_type'    => 'page',
                    'menu_order'   => $order,
                ));
                if ($page_id && !is_wp_error($page_id)) {
                    update_post_meta($page_id, '_webyaz_branded', '1');
                    // Featured image
                    if (!empty($_POST['webyaz_branded_image'])) {
                        set_post_thumbnail($page_id, intval($_POST['webyaz_branded_image']));
                    }
                    // Icon
                    if (!empty($_POST['webyaz_branded_icon'])) {
                        update_post_meta($page_id, '_webyaz_icon', sanitize_text_field($_POST['webyaz_branded_icon']));
                    }
                    // Custom CSS
                    if (!empty($_POST['webyaz_branded_css'])) {
                        update_post_meta($page_id, '_webyaz_custom_css', wp_strip_all_tags($_POST['webyaz_branded_css']));
                    }
                    // SEO
                    if (!empty($_POST['webyaz_seo_title'])) {
                        update_post_meta($page_id, '_webyaz_seo_title', sanitize_text_field($_POST['webyaz_seo_title']));
                    }
                    if (!empty($_POST['webyaz_seo_desc'])) {
                        update_post_meta($page_id, '_webyaz_seo_desc', sanitize_text_field($_POST['webyaz_seo_desc']));
                    }
                }
            }
            wp_redirect(admin_url('admin.php?page=webyaz-branded-pages&done=1'));
            exit;
        }

        if ($action === 'delete') {
            $page_id = intval($_POST['webyaz_branded_page_id']);
            if ($page_id && get_post_meta($page_id, '_webyaz_branded', true) === '1') {
                wp_trash_post($page_id);
            }
            wp_redirect(admin_url('admin.php?page=webyaz-branded-pages&deleted=1'));
            exit;
        }

        if ($action === 'clone') {
            $src_id = intval($_POST['webyaz_clone_id']);
            $src = get_post($src_id);
            if ($src && get_post_meta($src_id, '_webyaz_branded', true) === '1') {
                $new_id = wp_insert_post(array(
                    'post_title'   => $src->post_title . ' (Kopya)',
                    'post_content' => $src->post_content,
                    'post_status'  => 'draft',
                    'post_type'    => 'page',
                    'menu_order'   => $src->menu_order,
                ));
                if ($new_id && !is_wp_error($new_id)) {
                    update_post_meta($new_id, '_webyaz_branded', '1');
                    $metas = array('_webyaz_icon', '_webyaz_custom_css', '_webyaz_seo_title', '_webyaz_seo_desc');
                    foreach ($metas as $mk) {
                        $v = get_post_meta($src_id, $mk, true);
                        if ($v) update_post_meta($new_id, $mk, $v);
                    }
                    $thumb = get_post_thumbnail_id($src_id);
                    if ($thumb) set_post_thumbnail($new_id, $thumb);
                }
            }
            wp_redirect(admin_url('admin.php?page=webyaz-branded-pages&cloned=1'));
            exit;
        }

        if ($action === 'toggle_status') {
            $page_id = intval($_POST['webyaz_toggle_id']);
            $post = get_post($page_id);
            if ($post && get_post_meta($page_id, '_webyaz_branded', true) === '1') {
                $new_status = ($post->post_status === 'publish') ? 'draft' : 'publish';
                wp_update_post(array('ID' => $page_id, 'post_status' => $new_status));
            }
            wp_redirect(admin_url('admin.php?page=webyaz-branded-pages'));
            exit;
        }
    }

    // ── SEO Output ──
    public function output_page_seo() {
        if (!is_page()) return;
        $pid = get_the_ID();
        if (get_post_meta($pid, '_webyaz_branded', true) !== '1') return;
        $title = get_post_meta($pid, '_webyaz_seo_title', true);
        $desc  = get_post_meta($pid, '_webyaz_seo_desc', true);
        if ($title) echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        if ($desc) {
            echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
        }
    }

    // ── Custom CSS Output ──
    public function output_page_css() {
        if (!is_page()) return;
        $pid = get_the_ID();
        $css = get_post_meta($pid, '_webyaz_custom_css', true);
        if ($css) echo '<style>' . $css . '</style>' . "\n";
    }

    // ── Contact Form Shortcode ──
    public function contact_form_shortcode($atts) {
        $atts = shortcode_atts(array('baslik' => 'Bize Ulasin', 'email' => ''), $atts);
        $uid = 'wzc' . wp_rand(1000,9999);
        ob_start();
        ?>
        <div id="<?php echo $uid; ?>" style="max-width:640px;margin:30px auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
            <h3 style="font-size:22px;font-weight:700;margin:0 0 20px;color:#333;"><?php echo esc_html($atts['baslik']); ?></h3>
            <form onsubmit="return wzContactSend(event,'<?php echo $uid; ?>')">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <input type="text" name="wz_name" placeholder="Ad Soyad *" required style="padding:12px 16px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;">
                    <input type="email" name="wz_email" placeholder="E-posta *" required style="padding:12px 16px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;">
                </div>
                <input type="text" name="wz_phone" placeholder="Telefon" style="width:100%;padding:12px 16px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;margin-bottom:12px;box-sizing:border-box;">
                <input type="text" name="wz_subject" placeholder="Konu" style="width:100%;padding:12px 16px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;margin-bottom:12px;box-sizing:border-box;">
                <textarea name="wz_message" rows="5" placeholder="Mesajiniz *" required style="width:100%;padding:12px 16px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;margin-bottom:14px;box-sizing:border-box;resize:vertical;"></textarea>
                <input type="hidden" name="wz_to" value="<?php echo esc_attr($atts['email']); ?>">
                <button type="submit" style="background:linear-gradient(135deg,#446084,#2c3e6b);color:#fff;border:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;transition:0.3s;">Gonder</button>
                <div class="wz-form-msg" style="margin-top:12px;display:none;padding:12px;border-radius:8px;font-size:14px;"></div>
            </form>
        </div>
        <script>
        function wzContactSend(e,id){
            e.preventDefault();
            var f=e.target, d=new FormData(f), msg=document.querySelector('#'+id+' .wz-form-msg');
            d.append('action','webyaz_contact_submit');
            d.append('nonce','<?php echo wp_create_nonce('wz_contact'); ?>');
            msg.style.display='block'; msg.style.background='#e3f2fd'; msg.style.color='#1565c0'; msg.textContent='Gonderiliyor...';
            fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',body:d})
            .then(r=>r.json()).then(r=>{
                if(r.success){msg.style.background='#e8f5e9';msg.style.color='#2e7d32';msg.textContent='Mesajiniz gonderildi!';f.reset();}
                else{msg.style.background='#fce4ec';msg.style.color='#c62828';msg.textContent=r.data||'Hata olustu.';}
            }).catch(()=>{msg.style.background='#fce4ec';msg.style.color='#c62828';msg.textContent='Baglanti hatasi.';});
            return false;
        }
        </script>
        <?php
        return ob_get_clean();
    }

    public function handle_contact_submit() {
        check_ajax_referer('wz_contact', 'nonce');
        $name    = sanitize_text_field($_POST['wz_name'] ?? '');
        $email   = sanitize_email($_POST['wz_email'] ?? '');
        $phone   = sanitize_text_field($_POST['wz_phone'] ?? '');
        $subject = sanitize_text_field($_POST['wz_subject'] ?? '');
        $message = sanitize_textarea_field($_POST['wz_message'] ?? '');
        $to      = sanitize_email($_POST['wz_to'] ?? '');
        if (!$to) $to = get_option('admin_email');
        if (empty($name) || empty($email) || empty($message)) {
            wp_send_json_error('Zorunlu alanları doldurun.');
        }
        $body  = "Ad: $name\nE-posta: $email\n";
        if ($phone) $body .= "Telefon: $phone\n";
        $body .= "Konu: $subject\n\nMesaj:\n$message";
        $sent = wp_mail($to, '[İletişim] ' . ($subject ?: $name), $body, array('Reply-To: ' . $email));
        if ($sent) wp_send_json_success(); else wp_send_json_error('E-posta gonderilemedi.');
    }

    // ── Ready Templates ──
    public static function get_templates() {
        return array(
            'hakkimizda' => array(
                'title' => 'Hakkimizda',
                'icon'  => 'dashicons-info',
                'content' => '<p style="text-align:center;font-size:16px;color:#555;line-height:1.8;margin-bottom:30px;">Kalite ve guvenin adresi. Yillardir musterilerimize en iyi hizmeti sunuyoruz. Sektorumuzde edindigimiz deneyim ve bilgi birikimi ile her zaman en iyisini sunmayi hedefliyoruz.</p>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:30px;">
<div style="text-align:center;padding:25px 15px;background:#f8f9fa;border-radius:12px;"><div style="font-size:32px;margin-bottom:8px;">🎯</div><h4 style="margin:0 0 6px;font-size:15px;">Misyonumuz</h4><p style="font-size:13px;color:#666;margin:0;">Musterilerimize en kaliteli urunleri uygun fiyatlarla sunmak.</p></div>
<div style="text-align:center;padding:25px 15px;background:#f8f9fa;border-radius:12px;"><div style="font-size:32px;margin-bottom:8px;">👁️</div><h4 style="margin:0 0 6px;font-size:15px;">Vizyonumuz</h4><p style="font-size:13px;color:#666;margin:0;">Sektorun lider markasi olmak ve global pazara acilmak.</p></div>
<div style="text-align:center;padding:25px 15px;background:#f8f9fa;border-radius:12px;"><div style="font-size:32px;margin-bottom:8px;">💎</div><h4 style="margin:0 0 6px;font-size:15px;">Degerlerimiz</h4><p style="font-size:13px;color:#666;margin:0;">Guven, kalite, musteri memnuniyeti ve yenilikcilik.</p></div>
</div>

<h3 style="text-align:center;font-size:18px;margin:30px 0 16px;">Rakamlarla Biz</h3>
<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;text-align:center;">
<div style="padding:18px 10px;background:#f0f4f8;border-radius:10px;"><div style="font-size:24px;font-weight:800;color:#1565c0;">10+</div><div style="font-size:11px;color:#666;">Yillik Deneyim</div></div>
<div style="padding:18px 10px;background:#f0f8f0;border-radius:10px;"><div style="font-size:24px;font-weight:800;color:#2e7d32;">50K+</div><div style="font-size:11px;color:#666;">Mutlu Musteri</div></div>
<div style="padding:18px 10px;background:#fef8f0;border-radius:10px;"><div style="font-size:24px;font-weight:800;color:#e65100;">1000+</div><div style="font-size:11px;color:#666;">Urun Cesidi</div></div>
<div style="padding:18px 10px;background:#fef0f2;border-radius:10px;"><div style="font-size:24px;font-weight:800;color:#c62828;">%98</div><div style="font-size:11px;color:#666;">Memnuniyet</div></div>
</div>',
            ),
            'iletisim' => array(
                'title' => 'Iletisim',
                'icon'  => 'dashicons-phone',
                'content' => '<p style="text-align:center;font-size:15px;color:#555;margin-bottom:24px;">Sorulariniz, onerileriniz ve sikayet/talepleriniz icin bize asagidaki kanallardan ulasabilirsiniz.</p>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:30px;">
<div style="text-align:center;padding:24px 15px;background:#f8f9fa;border-radius:12px;border:1px solid #eee;"><div style="font-size:28px;margin-bottom:8px;">📞</div><h4 style="margin:0 0 6px;font-size:14px;">Telefon</h4><p style="font-size:14px;color:#666;margin:0;">0(XXX) XXX XX XX</p></div>
<div style="text-align:center;padding:24px 15px;background:#f8f9fa;border-radius:12px;border:1px solid #eee;"><div style="font-size:28px;margin-bottom:8px;">📧</div><h4 style="margin:0 0 6px;font-size:14px;">E-posta</h4><p style="font-size:14px;color:#666;margin:0;">info@siteniz.com</p></div>
<div style="text-align:center;padding:24px 15px;background:#f8f9fa;border-radius:12px;border:1px solid #eee;"><div style="font-size:28px;margin-bottom:8px;">📍</div><h4 style="margin:0 0 6px;font-size:14px;">Adres</h4><p style="font-size:14px;color:#666;margin:0;">Sehir, Ilce, Mahalle</p></div>
</div>

[webyaz_contact_form baslik="Iletisim Formu"]',
            ),
            'bayilik' => array(
                'title' => 'Bayilik Basvurusu',
                'icon'  => 'dashicons-store',
                'content' => '<p style="text-align:center;font-size:15px;color:#555;margin-bottom:24px;">Buyuyen agimiza katilin, birlikte kazanalim. Turkiye genelinde bayi agimizi genisletiyoruz.</p>

<h3 style="font-size:17px;margin:0 0 14px;">Bayi Avantajlari</h3>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:30px;">
<div style="display:flex;gap:10px;padding:16px;background:#fff8e1;border-radius:10px;border-left:4px solid #ff6f00;"><span style="font-size:22px;">💰</span><div><strong style="font-size:14px;">Ozel Bayi Fiyatlari</strong><p style="margin:4px 0 0;font-size:13px;color:#666;">Perakende fiyatin altinda ozel fiyatlandirma</p></div></div>
<div style="display:flex;gap:10px;padding:16px;background:#fff8e1;border-radius:10px;border-left:4px solid #ff6f00;"><span style="font-size:22px;">🚚</span><div><strong style="font-size:14px;">Ucretsiz Kargo</strong><p style="margin:4px 0 0;font-size:13px;color:#666;">Tum siparislerde ucretsiz kargo imkani</p></div></div>
<div style="display:flex;gap:10px;padding:16px;background:#fff8e1;border-radius:10px;border-left:4px solid #ff6f00;"><span style="font-size:22px;">📦</span><div><strong style="font-size:14px;">Stok Garantisi</strong><p style="margin:4px 0 0;font-size:13px;color:#666;">En populer urunlerde surekli stok</p></div></div>
<div style="display:flex;gap:10px;padding:16px;background:#fff8e1;border-radius:10px;border-left:4px solid #ff6f00;"><span style="font-size:22px;">🎓</span><div><strong style="font-size:14px;">Egitim Destegi</strong><p style="margin:4px 0 0;font-size:13px;color:#666;">Urun ve satis egitimi destegli</p></div></div>
</div>

[webyaz_contact_form baslik="Bayilik Basvuru Formu"]',
            ),
            'kariyer' => array(
                'title' => 'Kariyer',
                'icon'  => 'dashicons-businessman',
                'content' => '<p style="text-align:center;font-size:15px;color:#555;margin-bottom:24px;">Dinamik ekibimize katilin, birlikte buyuyelim. Gelisime acik, yetenekli adaylari ariyoruz.</p>

<h3 style="font-size:17px;margin:0 0 14px;">Neden Biz?</h3>
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:30px;">
<div style="text-align:center;padding:20px 12px;background:#f3e5f5;border-radius:12px;"><div style="font-size:26px;margin-bottom:6px;">🚀</div><strong style="font-size:14px;">Hizli Kariyer</strong><p style="font-size:12px;color:#666;margin:6px 0 0;">Performansa dayali hizli yukselme</p></div>
<div style="text-align:center;padding:20px 12px;background:#f3e5f5;border-radius:12px;"><div style="font-size:26px;margin-bottom:6px;">🏖️</div><strong style="font-size:14px;">Esnek Calisma</strong><p style="font-size:12px;color:#666;margin:6px 0 0;">Uzaktan ve esnek calisma imkani</p></div>
<div style="text-align:center;padding:20px 12px;background:#f3e5f5;border-radius:12px;"><div style="font-size:26px;margin-bottom:6px;">🎯</div><strong style="font-size:14px;">Egitim</strong><p style="font-size:12px;color:#666;margin:6px 0 0;">Surekli gelisim ve egitim programlari</p></div>
</div>

[webyaz_contact_form baslik="Is Basvuru Formu"]',
            ),
            'ekibimiz' => array(
                'title' => 'Ekibimiz',
                'icon'  => 'dashicons-groups',
                'content' => '<p style="text-align:center;font-size:15px;color:#555;margin-bottom:24px;">Basarimizin arkasindaki guclu ekip.</p>
<p style="text-align:center;font-size:12px;color:#999;margin-bottom:20px;"><em>Resimleri degistirmek icin editorde resme tiklayin ve "Medya Ekle" ile yeni gorsel secin.</em></p>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
<div style="text-align:center;padding:24px 15px;background:#fff;border:1px solid #e0e0e0;border-radius:12px;">
<img src="https://ui-avatars.com/api/?name=Ahmet+Yilmaz&size=120&background=1565c0&color=fff&rounded=true&bold=true" alt="Ahmet Yilmaz" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin:0 auto 12px;display:block;">
<h4 style="margin:0 0 4px;font-size:14px;">Ahmet Yilmaz</h4><p style="font-size:11px;color:#999;margin:0 0 6px;">Kurucu &amp; CEO</p><p style="font-size:12px;color:#666;margin:0;">10 yillik deneyim.</p></div>
<div style="text-align:center;padding:24px 15px;background:#fff;border:1px solid #e0e0e0;border-radius:12px;">
<img src="https://ui-avatars.com/api/?name=Ayse+Demir&size=120&background=2e7d32&color=fff&rounded=true&bold=true" alt="Ayse Demir" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin:0 auto 12px;display:block;">
<h4 style="margin:0 0 4px;font-size:14px;">Ayse Demir</h4><p style="font-size:11px;color:#999;margin:0 0 6px;">Pazarlama Muduru</p><p style="font-size:12px;color:#666;margin:0;">Dijital strateji uzmani.</p></div>
<div style="text-align:center;padding:24px 15px;background:#fff;border:1px solid #e0e0e0;border-radius:12px;">
<img src="https://ui-avatars.com/api/?name=Mehmet+Kaya&size=120&background=c62828&color=fff&rounded=true&bold=true" alt="Mehmet Kaya" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin:0 auto 12px;display:block;">
<h4 style="margin:0 0 4px;font-size:14px;">Mehmet Kaya</h4><p style="font-size:11px;color:#999;margin:0 0 6px;">Teknoloji Muduru</p><p style="font-size:12px;color:#666;margin:0;">E-ticaret altyapi uzmani.</p></div>
</div>',
            ),
        );
    }

    // ── Design Blocks ──
    public static function get_blocks() {
        return array(
            'hero' => array('title' => 'Hero Banner', 'icon' => '🖼️', 'html' => '<div style="text-align:center;padding:60px 30px;background:linear-gradient(135deg,#446084,#2c3e6b);border-radius:16px;color:#fff;margin:20px 0;"><h2 style="font-size:36px;margin:0 0 16px;font-weight:800;">Baslik Metniniz</h2><p style="font-size:18px;opacity:0.9;max-width:600px;margin:0 auto 24px;">Alt aciklama metninizi buraya yazin.</p><a href="#" style="display:inline-block;background:#fff;color:#446084;padding:14px 36px;border-radius:8px;font-weight:700;text-decoration:none;font-size:15px;">Hemen Basla</a></div>'),
            'features' => array('title' => 'Ozellikler Grid', 'icon' => '⭐', 'html' => '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin:20px 0;"><div style="text-align:center;padding:30px 20px;background:#f8f9fa;border-radius:12px;"><div style="font-size:36px;margin-bottom:10px;">🚀</div><h4 style="margin:0 0 8px;">Ozellik 1</h4><p style="font-size:13px;color:#666;">Aciklama metni buraya gelecek.</p></div><div style="text-align:center;padding:30px 20px;background:#f8f9fa;border-radius:12px;"><div style="font-size:36px;margin-bottom:10px;">💡</div><h4 style="margin:0 0 8px;">Ozellik 2</h4><p style="font-size:13px;color:#666;">Aciklama metni buraya gelecek.</p></div><div style="text-align:center;padding:30px 20px;background:#f8f9fa;border-radius:12px;"><div style="font-size:36px;margin-bottom:10px;">🎯</div><h4 style="margin:0 0 8px;">Ozellik 3</h4><p style="font-size:13px;color:#666;">Aciklama metni buraya gelecek.</p></div></div>'),
            'stats' => array('title' => 'Istatistikler', 'icon' => '📊', 'html' => '<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px;text-align:center;margin:20px 0;"><div style="padding:24px;background:linear-gradient(135deg,#e3f2fd,#bbdefb);border-radius:10px;"><div style="font-size:32px;font-weight:800;color:#1565c0;">100+</div><div style="font-size:13px;color:#666;">Baslik</div></div><div style="padding:24px;background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border-radius:10px;"><div style="font-size:32px;font-weight:800;color:#2e7d32;">500+</div><div style="font-size:13px;color:#666;">Baslik</div></div><div style="padding:24px;background:linear-gradient(135deg,#fff3e0,#ffe0b2);border-radius:10px;"><div style="font-size:32px;font-weight:800;color:#e65100;">1K+</div><div style="font-size:13px;color:#666;">Baslik</div></div><div style="padding:24px;background:linear-gradient(135deg,#fce4ec,#f8bbd0);border-radius:10px;"><div style="font-size:32px;font-weight:800;color:#c62828;">%99</div><div style="font-size:13px;color:#666;">Baslik</div></div></div>'),
            'team' => array('title' => 'Ekip Kartlari', 'icon' => '👥', 'html' => '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin:20px 0;"><div style="text-align:center;padding:30px 20px;background:#fff;border:2px solid #e0e0e0;border-radius:12px;"><img src="https://ui-avatars.com/api/?name=Ad+Soyad&size=120&background=1565c0&color=fff&rounded=true&bold=true" alt="Ekip Uyesi" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin:0 auto 12px;display:block;"><h4 style="margin:0 0 4px;">Isim Soyisim</h4><p style="font-size:11px;color:#999;margin:0 0 8px;">Gorev / Unvan</p><p style="font-size:13px;color:#666;">Kisa aciklama.</p></div><div style="text-align:center;padding:30px 20px;background:#fff;border:2px solid #e0e0e0;border-radius:12px;"><img src="https://ui-avatars.com/api/?name=Ad+Soyad&size=120&background=2e7d32&color=fff&rounded=true&bold=true" alt="Ekip Uyesi" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin:0 auto 12px;display:block;"><h4 style="margin:0 0 4px;">Isim Soyisim</h4><p style="font-size:11px;color:#999;margin:0 0 8px;">Gorev / Unvan</p><p style="font-size:13px;color:#666;">Kisa aciklama.</p></div><div style="text-align:center;padding:30px 20px;background:#fff;border:2px solid #e0e0e0;border-radius:12px;"><img src="https://ui-avatars.com/api/?name=Ad+Soyad&size=120&background=c62828&color=fff&rounded=true&bold=true" alt="Ekip Uyesi" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin:0 auto 12px;display:block;"><h4 style="margin:0 0 4px;">Isim Soyisim</h4><p style="font-size:11px;color:#999;margin:0 0 8px;">Gorev / Unvan</p><p style="font-size:13px;color:#666;">Kisa aciklama.</p></div></div>'),
            'testimonials' => array('title' => 'Musteri Yorumlari', 'icon' => '💬', 'html' => '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin:20px 0;"><div style="padding:24px;background:#f8f9fa;border-radius:12px;border-left:4px solid #4caf50;"><p style="font-size:14px;color:#333;font-style:italic;margin:0 0 12px;">"Cok kaliteli urunler ve hizli kargo. Kesinlikle tavsiye ederim."</p><div style="font-size:13px;font-weight:600;color:#666;">— Ahmet Y.</div></div><div style="padding:24px;background:#f8f9fa;border-radius:12px;border-left:4px solid #2196f3;"><p style="font-size:14px;color:#333;font-style:italic;margin:0 0 12px;">"Musteri hizmetleri mukemmel, her sorunumu hemen cozduler."</p><div style="font-size:13px;font-weight:600;color:#666;">— Fatma S.</div></div></div>'),
            'cta' => array('title' => 'CTA Butonu', 'icon' => '🔘', 'html' => '<div style="text-align:center;padding:50px 30px;background:linear-gradient(135deg,#ff6f00,#e65100);border-radius:16px;color:#fff;margin:20px 0;"><h3 style="font-size:26px;margin:0 0 12px;font-weight:800;">Harekete Gecin!</h3><p style="font-size:16px;opacity:0.9;margin:0 0 24px;">Firsati kacirmayin, hemen satin alin.</p><a href="#" style="display:inline-block;background:#fff;color:#e65100;padding:14px 40px;border-radius:8px;font-weight:700;text-decoration:none;font-size:16px;">Hemen Satin Al</a></div>'),
            'contact_form' => array('title' => 'Iletisim Formu', 'icon' => '📝', 'html' => '[webyaz_contact_form baslik="Bize Yazin"]'),
        );
    }

    // ── ADMIN RENDER ──
    public function render_admin() {
        wp_enqueue_media();
        $templates = self::get_templates();
        $blocks    = self::get_blocks();
        $branded   = get_posts(array('post_type' => 'page', 'meta_key' => '_webyaz_branded', 'meta_value' => '1', 'posts_per_page' => -1, 'orderby' => 'menu_order', 'order' => 'ASC'));
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Kurumsal Sayfalar</h1>
                <p>Profesyonel kurumsal sayfalar olusturun. Hazir sablonlar, tasarim bloklari ve gorsel editor ile.</p>
            </div>

            <?php if (isset($_GET['done'])): ?><div class="webyaz-notice success">Sayfa basariyla olusturuldu!</div><?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?><div class="webyaz-notice success">Sayfa silindi.</div><?php endif; ?>
            <?php if (isset($_GET['cloned'])): ?><div class="webyaz-notice success">Sayfa kopyalandi (taslak olarak).</div><?php endif; ?>

            <!-- ── HAZIR SABLONLAR ── -->
            <div class="webyaz-settings-section">
                <h2 class="webyaz-section-title">📋 Hazir Sablonlar</h2>
                <p style="color:#666;font-size:13px;margin-bottom:14px;">Tek tikla profesyonel sayfa olusturun. Sablon secince editor otomatik dolar.</p>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <?php foreach ($templates as $tkey => $tpl): ?>
                        <button type="button" class="webyaz-btn webyaz-btn-outline" onclick="wzLoadTemplate('<?php echo $tkey; ?>')" style="padding:10px 18px;font-size:13px;display:flex;align-items:center;gap:6px;">
                            <span class="dashicons <?php echo esc_attr($tpl['icon']); ?>" style="font-size:16px;"></span>
                            <?php echo esc_html($tpl['title']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── SAYFA OLUSTURMA FORMU ── -->
            <div class="webyaz-settings-section" style="margin-top:20px;">
                <h2 class="webyaz-section-title">✏️ Yeni Sayfa Olustur</h2>
                <form method="post" id="wzBrandedForm">
                    <?php wp_nonce_field('webyaz_branded', '_wpnonce_branded'); ?>
                    <input type="hidden" name="webyaz_branded_action" value="create">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
                        <div>
                            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Sayfa Basligi *</label>
                            <input type="text" name="webyaz_branded_title" id="wzBrandedTitle" required placeholder="ornek: Hakkimizda" style="width:100%;padding:11px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Durum & Sira</label>
                            <div style="display:flex;gap:10px;">
                                <select name="webyaz_branded_status" style="flex:1;padding:11px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;">
                                    <option value="publish">Yayinda</option>
                                    <option value="draft">Taslak</option>
                                </select>
                                <input type="number" name="webyaz_branded_order" value="0" min="0" style="width:80px;padding:11px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;" placeholder="Sira">
                            </div>
                        </div>
                    </div>

                    <!-- Tasarim Bloklari -->
                    <div style="margin-bottom:14px;">
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">🧩 Tasarim Bloku Ekle</label>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <?php foreach ($blocks as $bkey => $block): ?>
                                <button type="button" onclick="wzInsertBlock('<?php echo $bkey; ?>')" title="<?php echo esc_attr($block['title']); ?>" style="padding:6px 14px;border:1px solid #ddd;border-radius:6px;background:#fff;cursor:pointer;font-size:12px;display:flex;align-items:center;gap:4px;transition:0.2s;" onmouseover="this.style.background='#f0f4f8'" onmouseout="this.style.background='#fff'">
                                    <?php echo $block['icon']; ?> <?php echo esc_html($block['title']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Ekip Uyesi Olusturucu -->
                    <div style="margin-bottom:18px;background:#f8f0ff;border:2px solid #e1bee7;border-radius:12px;padding:18px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                            <label style="font-weight:700;font-size:14px;color:#7b1fa2;">👥 Ekip Uyesi Olusturucu</label>
                            <button type="button" onclick="wzAddTeamMember()" style="background:#7b1fa2;color:#fff;border:none;padding:7px 16px;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;">+ Ekip Uyesi Ekle</button>
                        </div>
                        <div id="wzTeamList"></div>
                        <div id="wzTeamActions" style="display:none;margin-top:12px;text-align:right;">
                            <button type="button" onclick="wzInsertTeamToEditor()" style="background:#4caf50;color:#fff;border:none;padding:10px 24px;border-radius:8px;font-size:13px;cursor:pointer;font-weight:600;">✅ Editore Ekle</button>
                        </div>
                    </div>

                    <!-- WP Editor -->
                    <div style="margin-bottom:18px;">
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Sayfa Icerigi</label>
                        <?php wp_editor('', 'webyaz_branded_content', array(
                            'textarea_name' => 'webyaz_branded_content',
                            'textarea_rows' => 15,
                            'media_buttons' => true,
                            'teeny'         => false,
                            'quicktags'     => true,
                        )); ?>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
                        <!-- One Cikan Gorsel -->
                        <div>
                            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">🖼️ One Cikan Gorsel</label>
                            <input type="hidden" name="webyaz_branded_image" id="wzBrandedImg" value="">
                            <div id="wzImgPreview" style="width:100%;min-height:120px;border:2px dashed #ddd;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;background:#fafafa;overflow:hidden;" onclick="wzSelectImage()">
                                <span id="wzImgText" style="color:#999;font-size:13px;">Gorsel secmek icin tiklayin</span>
                                <img id="wzImgTag" src="" style="max-width:100%;max-height:200px;display:none;border-radius:8px;">
                            </div>
                            <button type="button" onclick="document.getElementById('wzBrandedImg').value='';document.getElementById('wzImgTag').style.display='none';document.getElementById('wzImgText').style.display='block';" style="margin-top:6px;background:none;border:none;color:#c62828;font-size:11px;cursor:pointer;">Gorseli Kaldir</button>
                        </div>
                        <!-- Sayfa Ikonu -->
                        <div>
                            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">🎨 Sayfa Ikonu</label>
                            <input type="text" name="webyaz_branded_icon" id="wzBrandedIcon" placeholder="dashicons-admin-home" style="width:100%;padding:11px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;box-sizing:border-box;margin-bottom:8px;">
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <?php
                                $icons = array('dashicons-admin-home','dashicons-store','dashicons-groups','dashicons-phone','dashicons-businessman','dashicons-welcome-learn-more','dashicons-info','dashicons-awards','dashicons-heart','dashicons-location','dashicons-building','dashicons-portfolio');
                                foreach ($icons as $ic): ?>
                                    <button type="button" onclick="document.getElementById('wzBrandedIcon').value='<?php echo $ic; ?>'" style="width:32px;height:32px;border:1px solid #ddd;border-radius:6px;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;" title="<?php echo $ic; ?>"><span class="dashicons <?php echo $ic; ?>" style="font-size:16px;color:#446084;"></span></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- SEO -->
                    <div style="background:#f0f4f8;border-radius:10px;padding:18px;margin-bottom:18px;">
                        <label style="font-weight:600;font-size:14px;display:block;margin-bottom:12px;">🔍 SEO Ayarlari</label>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label style="font-size:12px;color:#666;display:block;margin-bottom:4px;">Meta Baslik</label>
                                <input type="text" name="webyaz_seo_title" placeholder="Sayfa basligi | Siteniz" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;box-sizing:border-box;">
                            </div>
                            <div>
                                <label style="font-size:12px;color:#666;display:block;margin-bottom:4px;">Meta Aciklama</label>
                                <input type="text" name="webyaz_seo_desc" placeholder="Sayfa hakkinda kisa aciklama..." maxlength="160" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;box-sizing:border-box;">
                            </div>
                        </div>
                    </div>

                    <!-- Ozel CSS -->
                    <div style="margin-bottom:18px;">
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">🎨 Ozel CSS (bu sayfaya ozel)</label>
                        <textarea name="webyaz_branded_css" rows="4" placeholder=".entry-content h2 { color: #446084; }" style="width:100%;padding:12px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;box-sizing:border-box;font-family:monospace;resize:vertical;"></textarea>
                    </div>

                    <button type="submit" class="webyaz-btn webyaz-btn-primary" style="padding:14px 32px;font-size:15px;">
                        <span class="dashicons dashicons-plus-alt" style="margin-right:6px;vertical-align:middle;"></span> Sayfa Olustur
                    </button>
                </form>
            </div>

            <!-- ── MEVCUT SAYFALAR ── -->
            <?php if ($branded): ?>
            <div class="webyaz-settings-section" style="margin-top:24px;">
                <h2 class="webyaz-section-title">📄 Mevcut Kurumsal Sayfalar (<?php echo count($branded); ?>)</h2>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="background:#f5f5f5;">
                                <th style="padding:10px 14px;text-align:left;border-bottom:2px solid #e0e0e0;">Sayfa</th>
                                <th style="padding:10px 14px;text-align:left;border-bottom:2px solid #e0e0e0;">Tarih</th>
                                <th style="padding:10px 14px;text-align:center;border-bottom:2px solid #e0e0e0;">Durum</th>
                                <th style="padding:10px 14px;text-align:center;border-bottom:2px solid #e0e0e0;">Sira</th>
                                <th style="padding:10px 14px;text-align:center;border-bottom:2px solid #e0e0e0;">Islemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($branded as $bp):
                                $icon = get_post_meta($bp->ID, '_webyaz_icon', true);
                            ?>
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:10px 14px;font-weight:600;">
                                    <?php if ($icon): ?><span class="dashicons <?php echo esc_attr($icon); ?>" style="font-size:14px;margin-right:5px;color:#446084;vertical-align:middle;"></span><?php endif; ?>
                                    <?php echo esc_html($bp->post_title); ?>
                                </td>
                                <td style="padding:10px 14px;color:#888;"><?php echo date_i18n('d.m.Y', strtotime($bp->post_date)); ?></td>
                                <td style="padding:10px 14px;text-align:center;">
                                    <?php if ($bp->post_status === 'publish'): ?>
                                        <span style="background:#4caf5022;color:#4caf50;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;">Yayinda</span>
                                    <?php else: ?>
                                        <span style="background:#ff980022;color:#ff9800;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;">Taslak</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:10px 14px;text-align:center;color:#888;"><?php echo $bp->menu_order; ?></td>
                                <td style="padding:10px 14px;text-align:center;">
                                    <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
                                        <a href="<?php echo get_permalink($bp->ID); ?>" target="_blank" style="background:#446084;color:#fff;text-decoration:none;padding:4px 10px;border-radius:5px;font-size:11px;">Gor</a>
                                        <a href="<?php echo get_edit_post_link($bp->ID, 'raw'); ?>" style="background:#ff9800;color:#fff;text-decoration:none;padding:4px 10px;border-radius:5px;font-size:11px;">Duzenle</a>
                                        <!-- Toggle Status -->
                                        <form method="post" style="display:inline;">
                                            <?php wp_nonce_field('webyaz_branded', '_wpnonce_branded'); ?>
                                            <input type="hidden" name="webyaz_branded_action" value="toggle_status">
                                            <button type="submit" name="webyaz_toggle_id" value="<?php echo $bp->ID; ?>" style="background:<?php echo $bp->post_status === 'publish' ? '#ff9800' : '#4caf50'; ?>;color:#fff;border:none;padding:4px 10px;border-radius:5px;font-size:11px;cursor:pointer;"><?php echo $bp->post_status === 'publish' ? 'Taslak' : 'Yayinla'; ?></button>
                                        </form>
                                        <!-- Clone -->
                                        <form method="post" style="display:inline;">
                                            <?php wp_nonce_field('webyaz_branded', '_wpnonce_branded'); ?>
                                            <input type="hidden" name="webyaz_branded_action" value="clone">
                                            <button type="submit" name="webyaz_clone_id" value="<?php echo $bp->ID; ?>" style="background:#9c27b0;color:#fff;border:none;padding:4px 10px;border-radius:5px;font-size:11px;cursor:pointer;">Kopyala</button>
                                        </form>
                                        <!-- Delete -->
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Bu sayfayi silmek istediginize emin misiniz?');">
                                            <?php wp_nonce_field('webyaz_branded', '_wpnonce_branded'); ?>
                                            <input type="hidden" name="webyaz_branded_action" value="delete">
                                            <button type="submit" name="webyaz_branded_page_id" value="<?php echo $bp->ID; ?>" style="background:#f44336;color:#fff;border:none;padding:4px 10px;border-radius:5px;font-size:11px;cursor:pointer;">Sil</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <script>
        // Templates data
        var wzTemplates = <?php echo json_encode(array_map(function($t) { return array('title' => $t['title'], 'content' => $t['content']); }, $templates)); ?>;
        var wzBlocks = <?php echo json_encode(array_map(function($b) { return $b['html']; }, $blocks)); ?>;

        function wzLoadTemplate(key) {
            var t = wzTemplates[key];
            if (!t) return;
            if (!confirm('Sablon yuklensin mi? Mevcut icerik degistirilecek.')) return;
            document.getElementById('wzBrandedTitle').value = t.title;
            // Set content in WP editor
            if (typeof tinymce !== 'undefined' && tinymce.get('webyaz_branded_content')) {
                tinymce.get('webyaz_branded_content').setContent(t.content);
            }
            var ta = document.getElementById('webyaz_branded_content');
            if (ta) ta.value = t.content;
        }

        function wzInsertBlock(key) {
            var html = wzBlocks[key];
            if (!html) return;
            if (typeof tinymce !== 'undefined' && tinymce.get('webyaz_branded_content')) {
                tinymce.get('webyaz_branded_content').execCommand('mceInsertContent', false, html);
            } else {
                var ta = document.getElementById('webyaz_branded_content');
                if (ta) {
                    var pos = ta.selectionStart || ta.value.length;
                    ta.value = ta.value.substring(0, pos) + html + ta.value.substring(pos);
                }
            }
        }

        function wzSelectImage() {
            var frame = wp.media({title: 'Gorsel Sec', multiple: false, library: {type: 'image'}});
            frame.on('select', function() {
                var a = frame.state().get('selection').first().toJSON();
                document.getElementById('wzBrandedImg').value = a.id;
                var img = document.getElementById('wzImgTag');
                img.src = a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url;
                img.style.display = 'block';
                document.getElementById('wzImgText').style.display = 'none';
            });
            frame.open();
        }

        // ── Team Member Builder ──
        var wzTeamCount = 0;
        function wzAddTeamMember() {
            wzTeamCount++;
            var id = 'wzTeam' + wzTeamCount;
            var html = '<div id="' + id + '" style="display:flex;gap:14px;align-items:flex-start;background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:14px;margin-bottom:10px;">';
            html += '<div style="flex-shrink:0;">';
            html += '<div id="' + id + '_photo" onclick="wzPickTeamPhoto(\'' + id + '\')" style="width:80px;height:80px;border-radius:50%;background:#e1bee7;display:flex;align-items:center;justify-content:center;cursor:pointer;overflow:hidden;border:3px dashed #ce93d8;transition:0.3s;" onmouseover="this.style.borderColor=\'#7b1fa2\'" onmouseout="this.style.borderColor=\'#ce93d8\'">';
            html += '<span id="' + id + '_photoText" style="font-size:11px;color:#7b1fa2;text-align:center;line-height:1.3;">📷<br>Resim Sec</span>';
            html += '<img id="' + id + '_photoImg" src="" style="width:100%;height:100%;object-fit:cover;display:none;">';
            html += '</div>';
            html += '<input type="hidden" id="' + id + '_photoUrl">';
            html += '</div>';
            html += '<div style="flex:1;display:flex;flex-direction:column;gap:8px;">';
            html += '<input type="text" id="' + id + '_name" placeholder="Ad Soyad *" style="padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;">';
            html += '<input type="text" id="' + id + '_role" placeholder="Gorevi (ornek: Pazarlama Muduru)" style="padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;">';
            html += '<input type="text" id="' + id + '_desc" placeholder="Kisa aciklama (opsiyonel)" style="padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;">';
            html += '</div>';
            html += '<button type="button" onclick="wzRemoveTeamMember(\'' + id + '\')" style="background:none;border:none;color:#e53935;font-size:18px;cursor:pointer;padding:4px;" title="Kaldir">✕</button>';
            html += '</div>';
            document.getElementById('wzTeamList').insertAdjacentHTML('beforeend', html);
            document.getElementById('wzTeamActions').style.display = 'block';
        }

        function wzRemoveTeamMember(id) {
            var el = document.getElementById(id);
            if (el) el.remove();
            if (document.getElementById('wzTeamList').children.length === 0) {
                document.getElementById('wzTeamActions').style.display = 'none';
            }
        }

        function wzPickTeamPhoto(id) {
            var frame = wp.media({title: 'Ekip Uyesi Fotografi', multiple: false, library: {type: 'image'}});
            frame.on('select', function() {
                var a = frame.state().get('selection').first().toJSON();
                var url = a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url;
                document.getElementById(id + '_photoUrl').value = url;
                var img = document.getElementById(id + '_photoImg');
                img.src = url;
                img.style.display = 'block';
                document.getElementById(id + '_photoText').style.display = 'none';
            });
            frame.open();
        }

        function wzInsertTeamToEditor() {
            var items = document.getElementById('wzTeamList').children;
            if (items.length === 0) return;
            var cards = '';
            for (var i = 0; i < items.length; i++) {
                var id = items[i].id;
                var name = document.getElementById(id + '_name').value || 'Isim Soyisim';
                var role = document.getElementById(id + '_role').value || '';
                var desc = document.getElementById(id + '_desc').value || '';
                var photoUrl = document.getElementById(id + '_photoUrl').value;
                var photoHtml;
                if (photoUrl) {
                    photoHtml = '<img src="' + photoUrl + '" alt="' + name + '" style="width:90px;height:90px;border-radius:50%;object-fit:cover;margin:0 auto 12px;display:block;border:3px solid #e0e0e0;">';
                } else {
                    photoHtml = '<div style="width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,#e3f2fd,#bbdefb);margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:32px;">👤</div>';
                }
                cards += '<div style="text-align:center;padding:24px 15px;background:#fff;border:1px solid #e0e0e0;border-radius:12px;">';
                cards += photoHtml;
                cards += '<h4 style="margin:0 0 4px;font-size:15px;">' + name + '</h4>';
                if (role) cards += '<p style="font-size:12px;color:#999;margin:0 0 6px;">' + role + '</p>';
                if (desc) cards += '<p style="font-size:12px;color:#666;margin:0;">' + desc + '</p>';
                cards += '</div>';
            }
            var cols = items.length <= 2 ? items.length : 3;
            var html = '<div style="display:grid;grid-template-columns:repeat(' + cols + ',1fr);gap:16px;margin:20px 0;">' + cards + '</div>';
            if (typeof tinymce !== 'undefined' && tinymce.get('webyaz_branded_content')) {
                tinymce.get('webyaz_branded_content').execCommand('mceInsertContent', false, html);
            } else {
                var ta = document.getElementById('webyaz_branded_content');
                if (ta) ta.value += html;
            }
            document.getElementById('wzTeamList').innerHTML = '';
            document.getElementById('wzTeamActions').style.display = 'none';
            wzTeamCount = 0;
            alert('Ekip kartlari editore eklendi!');
        }
        </script>
        <?php
    }
}

new Webyaz_Branded_Pages();
