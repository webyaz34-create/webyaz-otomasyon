<?php
/**
 * Webyaz Master Kalkanı (Tam Otomasyon)
 * Toggle açıldığında:
 * 1. "Altyapı Koruması" sayfasını otomatik oluşturur (shortcode ile).
 * 2. Tuşları ve sağ tıkı yakalar, güvenlik sayfasına yönlendirir.
 * 3. Sol menüye "Webyaz Güvenlik" durum sayfasını ekler.
 * Toggle kapatıldığında sayfa yayından kaldırılır.
 * Kısa Kod: [webyaz_guvenlik_duvari]
 */

if (!defined('ABSPATH')) exit;

// ==========================================
// 0. BÖLÜM: OTOMATİK SAYFA OLUŞTURMA (ANINDA)
// ==========================================
function wy_guvenlik_sayfa_olustur() {
    $sayfa = get_page_by_path('altyapi-korumasi');

    if ($sayfa) {
        // Sayfa var ama taslak/çöp ise tekrar yayımla
        if ($sayfa->post_status !== 'publish') {
            wp_update_post(array(
                'ID' => $sayfa->ID,
                'post_status' => 'publish',
            ));
        }
        return;
    }

    // Sayfa yoksa oluştur
    $sayfa_id = wp_insert_post(array(
        'post_title'   => 'Altyapı Koruması',
        'post_name'    => 'altyapi-korumasi',
        'post_content' => '[webyaz_guvenlik_duvari]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_author'  => get_current_user_id() ? get_current_user_id() : 1,
    ));

    if ($sayfa_id && !is_wp_error($sayfa_id)) {
        // Flatsome Blank Page şablon ata
        update_post_meta($sayfa_id, '_wp_page_template', 'page-blank.php');
        // URL'nin anında çalışması için rewrite kurallarını yenile
        flush_rewrite_rules();
    }
}

// Dosya yüklendiği anda çalıştır (toggle açıldığında anında sayfa oluşur)
wy_guvenlik_sayfa_olustur();

// ==========================================
// 1. BÖLÜM: TUŞ YAKALAYICI VE YÖNLENDİRİCİ
// ==========================================
add_action('wp_footer', 'wy_klavye_kalkanini_ekle', 99);
function wy_klavye_kalkanini_ekle() {
    if ( ! current_user_can('administrator') ) {
        ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var webyazGüvenlikSayfasi = window.location.origin + "/altyapi-korumasi"; 
                document.addEventListener('contextmenu', function(e) { e.preventDefault(); });
                document.onkeydown = function(e) {
                    if (e.keyCode == 123) { e.preventDefault(); window.location.href = webyazGüvenlikSayfasi; return false; } // F12
                    if (e.ctrlKey && e.keyCode == 85) { e.preventDefault(); window.location.href = webyazGüvenlikSayfasi; return false; } // CTRL+U
                    if (e.ctrlKey && e.shiftKey && e.keyCode == 73) { e.preventDefault(); window.location.href = webyazGüvenlikSayfasi; return false; } // CTRL+SHIFT+I
                    if (e.ctrlKey && e.shiftKey && e.keyCode == 74) { e.preventDefault(); window.location.href = webyazGüvenlikSayfasi; return false; } // CTRL+SHIFT+J
                    if (e.ctrlKey && e.keyCode == 83) { e.preventDefault(); window.location.href = webyazGüvenlikSayfasi; return false; } // CTRL+S
                };
            });
        </script>
        <?php
    }
}

// ==========================================
// 2. BÖLÜM: DİNAMİK TASARIM (KISA KOD)
// ==========================================
add_shortcode('webyaz_guvenlik_duvari', 'wy_kesin_guvenlik_sayfasi');
function wy_kesin_guvenlik_sayfasi() {
    $ana_renk = get_theme_mod('color_primary', '#021B35');
    $ikincil_renk = get_theme_mod('color_secondary', '#f9a825');
    ob_start();
    ?>
    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 60vh; text-align: center; font-family: 'Poppins', sans-serif; padding: 40px 20px; background-color: #ffffff;">
        <svg style="width: 120px; height: 120px; margin-bottom: 25px; fill: <?php echo esc_attr($ana_renk); ?>;" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
            <path style="fill: <?php echo esc_attr($ikincil_renk); ?>;" d="M10 16l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/>
        </svg>
        <h1 style="color: <?php echo esc_attr($ana_renk); ?>; font-size: 36px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 15px;">Erişim Engellendi</h1>
        <p style="color: #444; font-size: 18px; max-width: 650px; line-height: 1.7; margin-bottom: 35px;">
            Bu web sitesinin altyapısı, kaynak kodları ve veritabanı mimarisi <a href="https://webyaz.com.tr" target="_blank" style="color: <?php echo esc_attr($ana_renk); ?>; font-weight: 700; text-decoration: none;">Webyaz Otomasyon Sistemleri</a> tarafından üst düzey güvenlik protokolleriyle korunmaktadır.<br><br>Sistem kodlarını inceleme, kopyalama veya tersine mühendislik girişimleri güvenlik duvarımız tarafından tespit edilerek engellenmiştir.
        </p>
        <a href="/" style="display: inline-block; background-color: <?php echo esc_attr($ana_renk); ?>; color: #ffffff !important; padding: 16px 35px; font-size: 16px; font-weight: 600; text-transform: uppercase; border-radius: 8px; text-decoration: none; border: 2px solid <?php echo esc_attr($ana_renk); ?>;">Güvenli Bağlantıya Dön</a>
    </div>
    <?php
    return ob_get_clean();
}

// ==========================================
// 3. BÖLÜM: YÖNETİCİ PANELİ MENÜSÜ & DURUM
// ==========================================
add_action('admin_menu', 'wy_guvenlik_menusu_ekle');
function wy_guvenlik_menusu_ekle() {
    add_menu_page('Webyaz Güvenlik Duvarı', 'Webyaz Güvenlik', 'manage_options', 'webyaz-guvenlik', 'wy_guvenlik_talimatlari_sayfasi', 'dashicons-shield', 3);
}

function wy_guvenlik_talimatlari_sayfasi() {
    $sayfa = get_page_by_path('altyapi-korumasi');
    $sayfa_aktif = ($sayfa && $sayfa->post_status === 'publish');
    $sayfa_url = $sayfa ? get_permalink($sayfa->ID) : '#';
    $sayfa_edit = $sayfa ? get_edit_post_link($sayfa->ID) : '#';
    ?>
    <div class="wrap" style="background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 800px; margin-top: 20px;">
        <h1 style="color: #021B35; border-bottom: 2px solid #f9a825; padding-bottom: 15px; margin-top: 0; font-family: sans-serif;">🛡️ Webyaz Güvenlik Duvarı</h1>
        <p style="font-size: 16px; color: #444; line-height: 1.6;">Bu modül, sitenizin kaynak kodlarına (CTRL+U, F12, Sağ Tık) erişilmesini engeller ve meraklıları kurumsal bir uyarı sayfasına yönlendirir.</p>

        <!-- DURUM KARTI -->
        <div style="background: <?php echo $sayfa_aktif ? '#e8f5e9' : '#fce4ec'; ?>; border: 2px solid <?php echo $sayfa_aktif ? '#4caf50' : '#e57373'; ?>; border-radius: 12px; padding: 20px 25px; margin: 25px 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <span style="font-size: 36px;"><?php echo $sayfa_aktif ? '✅' : '⚠️'; ?></span>
                <div>
                    <div style="font-size: 17px; font-weight: 700; color: <?php echo $sayfa_aktif ? '#2e7d32' : '#c62828'; ?>;">
                        <?php echo $sayfa_aktif ? 'Güvenlik Kalkanı AKTİF' : 'Sayfa Bulunamadı'; ?>
                    </div>
                    <div style="font-size: 13px; color: #666; margin-top: 3px;">
                        <?php echo $sayfa_aktif
                            ? '"Altyapı Koruması" sayfası yayında. Tüm korumalar otomatik olarak çalışıyor.'
                            : 'Otomasyon sayfayı oluşturamadı. Lütfen sayfayı kontrol edin.'; ?>
                    </div>
                </div>
            </div>
            <?php if ($sayfa_aktif): ?>
                <div style="display: flex; gap: 8px;">
                    <a href="<?php echo esc_url($sayfa_url); ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 5px; background: #2e7d32; color: #fff; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600;">👁️ Sayfayı Gör</a>
                    <a href="<?php echo esc_url($sayfa_edit); ?>" style="display: inline-flex; align-items: center; gap: 5px; background: #fff; color: #333; border: 1px solid #ccc; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600;">✏️ Düzenle</a>
                </div>
            <?php endif; ?>
        </div>
        
        <h2 style="color: #021B35; margin-top: 35px; font-family: sans-serif;">🔒 Engellenen İşlemler</h2>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 15px;">
            <div style="background: #f8f9fa; border-radius: 10px; padding: 16px; text-align: center;">
                <div style="font-size: 24px; margin-bottom: 6px;">🖱️</div>
                <div style="font-size: 13px; font-weight: 600; color: #333;">Sağ Tık</div>
                <div style="font-size: 11px; color: #999;">Engellendi</div>
            </div>
            <div style="background: #f8f9fa; border-radius: 10px; padding: 16px; text-align: center;">
                <div style="font-size: 24px; margin-bottom: 6px;">🔧</div>
                <div style="font-size: 13px; font-weight: 600; color: #333;">F12 / DevTools</div>
                <div style="font-size: 11px; color: #999;">Engellendi</div>
            </div>
            <div style="background: #f8f9fa; border-radius: 10px; padding: 16px; text-align: center;">
                <div style="font-size: 24px; margin-bottom: 6px;">📄</div>
                <div style="font-size: 13px; font-weight: 600; color: #333;">CTRL+U Kaynak</div>
                <div style="font-size: 11px; color: #999;">Engellendi</div>
            </div>
            <div style="background: #f8f9fa; border-radius: 10px; padding: 16px; text-align: center;">
                <div style="font-size: 24px; margin-bottom: 6px;">🔍</div>
                <div style="font-size: 13px; font-weight: 600; color: #333;">CTRL+SHIFT+I</div>
                <div style="font-size: 11px; color: #999;">Engellendi</div>
            </div>
            <div style="background: #f8f9fa; border-radius: 10px; padding: 16px; text-align: center;">
                <div style="font-size: 24px; margin-bottom: 6px;">🖥️</div>
                <div style="font-size: 13px; font-weight: 600; color: #333;">CTRL+SHIFT+J</div>
                <div style="font-size: 11px; color: #999;">Engellendi</div>
            </div>
            <div style="background: #f8f9fa; border-radius: 10px; padding: 16px; text-align: center;">
                <div style="font-size: 24px; margin-bottom: 6px;">💾</div>
                <div style="font-size: 13px; font-weight: 600; color: #333;">CTRL+S Kaydet</div>
                <div style="font-size: 11px; color: #999;">Engellendi</div>
            </div>
        </div>

        <h2 style="color: #021B35; margin-top: 35px; font-family: sans-serif;">📋 Kısa Kod</h2>
        <p style="font-size: 14px; color: #666; margin-bottom: 12px;">İsterseniz bu kısa kodu farklı sayfalarda da kullanabilirsiniz:</p>
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
            <code id="wyKisaKodAlan" style="background: #eef2f5; padding: 12px 20px; border-radius: 6px; font-size: 18px; color: #d63031; font-weight: bold; border: 1px solid #ddd;">[webyaz_guvenlik_duvari]</code>
            <button id="wyKopyalaBtn" onclick="wyKoduKopyala()" style="background: #021B35; color: #ffffff; border: none; padding: 12px 25px; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">📋 Kodu Kopyala</button>
        </div>

        <div style="background: #fff8e1; padding: 20px; border-left: 5px solid #f9a825; border-radius: 0 8px 8px 0; margin-top: 35px;">
            <h4 style="margin: 0 0 10px 0; color: #021B35; font-size: 16px;">🎨 Dinamik Renkler Hakkında Not:</h4>
            <p style="margin: 0; color: #555; font-size: 15px; line-height: 1.6;">Kalkan sayfasındaki ikon ve buton renkleri, Flatsome <strong>Tema Ayarları > Style > Colors</strong> bölümündeki <em>Primary (Ana)</em> ve <em>Secondary (İkincil)</em> renkleri otomatik olarak çeker.</p>
        </div>

        <div style="background: #e3f2fd; padding: 20px; border-left: 5px solid #1976d2; border-radius: 0 8px 8px 0; margin-top: 15px;">
            <h4 style="margin: 0 0 10px 0; color: #0d47a1; font-size: 16px;">⚙️ Tam Otomasyon:</h4>
            <p style="margin: 0; color: #555; font-size: 15px; line-height: 1.6;">Bu modül toggle ile açıldığında "Altyapı Koruması" sayfasını <strong>otomatik oluşturur</strong>. Toggle kapatıldığında tüm korumalar devre dışı kalır. Manuel işlem gerekmez.</p>
        </div>
    </div>

    <script>
    function wyKoduKopyala() {
        var kopyaMetni = document.getElementById("wyKisaKodAlan").innerText;
        navigator.clipboard.writeText(kopyaMetni).then(function() {
            var btn = document.getElementById("wyKopyalaBtn");
            btn.innerHTML = "✅ Kopyalandı!";
            btn.style.backgroundColor = "#27ae60"; 
            setTimeout(function() {
                btn.innerHTML = "📋 Kodu Kopyala";
                btn.style.backgroundColor = "#021B35";
            }, 2000);
        }).catch(function(err) {
            console.error('Kopyalama hatası:', err);
            alert("Kopyalama başarısız oldu, lütfen manuel seçin.");
        });
    }
    </script>
    <?php
}
