<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Help {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'), 20);
    }

    public function add_submenu() {
        add_submenu_page(
            'webyaz-dashboard',
            'Yardım',
            '❓ Yardım',
            'manage_woocommerce',
            'webyaz-help',
            array($this, 'render_page')
        );
    }

    public function render_page() {
        $primary = '#446084';
        $secondary = '#d26e4b';
        if (class_exists('Webyaz_Colors')) {
            $colors = Webyaz_Colors::get_theme_colors();
            $primary = $colors['primary'];
            $secondary = $colors['secondary'];
        }

        $groups = array(
            'urun' => array(
                'label' => 'Ürün Yönetimi',
                'icon'  => '📦',
                'items' => array(
                    array('title' => 'Ürün Sayfası Tasarım', 'desc' => 'Tek ürün sayfasının görsel düzenini özelleştirir.', 'how' => 'Webyaz → Ürün Sayfası Tasarım sayfasından galeri boyutu, renk seçici stili gibi ayarları yapın. Değişiklikler anında ön yüze yansır.'),
                    array('title' => 'Ürün Videosu', 'desc' => 'Ürün galerisine YouTube/Vimeo videosu ekler.', 'how' => 'Ürün düzenle → "Ürün Videosu" kutusuna video linkini yapıştırın. Video, galeri sonuna otomatik eklenir.'),
                    array('title' => 'Ürün Açıklama', 'desc' => 'Premium tasarımlı ürün açıklama alanı.', 'how' => 'Ürün düzenle → "Webyaz Açıklama" kutusundan sekmeli, ikonlu açıklama düzenleyin.'),
                    array('title' => 'Ürün Tabları', 'desc' => 'Teslimat, İade & Değişim gibi sabit bilgi tabları.', 'how' => 'Webyaz → Ayarlar sayfasından tab içeriklerini düzenleyin. Tüm ürünlerde ortak gösterilir.'),
                    array('title' => 'Ürün Rozetleri', 'desc' => 'Ürün görseli üzerinde Yeni, İndirimde, Tükeniyor etiketleri.', 'how' => 'Webyaz → Ürün Rozetleri → 5 stil, renk, boyut, konum seçin. Kategori veya tekil ürün hedeflenebilir.'),
                    array('title' => 'Beden Tablosu', 'desc' => 'Ürün sayfasında beden rehberi popup\'ı.', 'how' => 'Webyaz → Beden Tablosu → Yeni tablo ekle. Satır/sütun belirleyin, ölçüleri girin, tabloya ürün veya kategori atayın.'),
                    array('title' => 'Beden & Renk', 'desc' => 'Beden, renk, ayakkabı numarası ve satış birimi nitelikleri.', 'how' => 'Ürün düzenle → "Webyaz Nitelikler" kutusu. Her niteliği aktif edip seçim yapın. + butonuyla özel beden/renk eklenebilir.'),
                    array('title' => 'Fotoğraflı Yorum', 'desc' => 'Yorumlara fotoğraf ekleme.', 'how' => 'Otomatik çalışır. Müşteri yorum formunda "Fotoğraf Ekle" butonu görünür.'),
                    array('title' => 'Soru-Cevap', 'desc' => 'Ürün sayfasında soru-cevap bölümü.', 'how' => 'Gelen soruları WooCommerce → Ürün Soruları sayfasından onaylayın ve yanıtlayın.'),
                    array('title' => 'Ürün Karşılaştırma', 'desc' => 'Ürünleri yan yana karşılaştırma.', 'how' => 'Otomatik çalışır. Ürün kartlarında "Karşılaştır" butonu görünür, en fazla 4 ürün karşılaştırılabilir.'),
                    array('title' => 'Favoriler', 'desc' => 'Kalp ikonu ile istek listesi.', 'how' => 'Otomatik çalışır. Müşteri Hesabım → Favorilerim sekmesinden görüntüler. Giriş yapılmadan çerez ile de çalışır.'),
                    array('title' => 'Sosyal Kanıt', 'desc' => '"X kişi bakıyor" ve satış sayacı.', 'how' => 'Webyaz → Sosyal Kanıt ayarlarından gerçek veya simüle edilmiş sayıları belirleyin.'),
                    array('title' => 'Son Bakılan Ürünler', 'desc' => 'Son incelenen ürünleri listeler.', 'how' => 'Shortcode: [webyaz_recently_viewed] — UX Builder veya sayfa editörüne ekleyin.'),
                    array('title' => 'Önceki Alınanlar', 'desc' => 'Daha önce alınan ürünleri gösterir.', 'how' => 'Otomatik çalışır. Giriş yapmış müşterilere mağaza sayfasında gösterilir.'),
                    array('title' => 'Birlikte Satın Alınanlar', 'desc' => 'Ürün önerileri.', 'how' => 'WooCommerce çapraz satış ürünleriyle otomatik çalışır.'),
                    array('title' => 'Ekstra Hizmetler', 'desc' => 'Opsiyonel ücretli hizmet seçenekleri.', 'how' => 'Webyaz → Ekstra Hizmetler → Hizmet Ekle. Hizmet adı, fiyatı ve atanacağı ürün/kategoriyi belirleyin.'),
                    array('title' => 'Stok Sayacı', 'desc' => 'Düşük stokta "Son X adet!" uyarısı.', 'how' => 'Stok eşik değerini ayarlayın, düşük stoklu ürünlerde otomatik uyarı gösterilir.'),
                    array('title' => 'Stok Bildirimi', 'desc' => 'Stok gelince e-posta bildirimi.', 'how' => 'Otomatik çalışır. Tükenmiş ürünlerde "Stok gelince haber ver" formu görünür.'),
                    array('title' => 'Otomatik Etiketler', 'desc' => 'İndirimli, yeni, best-seller etiketleri.', 'how' => 'Webyaz → Otomatik Etiketler sayfasından kuralları (indirim oranı, satış adedi) ayarlayın.'),
                    array('title' => 'Geri Sayım', 'desc' => 'Kampanya zamanlayıcı.', 'how' => 'İndirim bitiş tarihi olan ürünlerde otomatik çalışır.'),
                    array('title' => 'Hızlı Ürün Ekle', 'desc' => 'Basitleştirilmiş hızlı ürün formu.', 'how' => 'Webyaz → Hızlı Ürün Ekle → Ad, fiyat, kategori, görsel doldurarak hızlıca oluşturun.'),
                    array('title' => 'Toplu Ürün Ekle', 'desc' => 'Çoklu ürün ekleme formu.', 'how' => 'Webyaz → Toplu Ürün Ekle → Satır satır ürün bilgisi girin, toplu kaydedin.'),
                    array('title' => 'Toplu Düzenle', 'desc' => 'Toplu fiyat, stok, kategori değiştirme.', 'how' => 'Webyaz → Toplu Düzenle → Tablo görünümünde in-line düzenleme yapın. CSV dışa aktarma desteği var.'),
                    array('title' => 'Ön Sipariş', 'desc' => 'Stoğa gelmeden ön sipariş alma.', 'how' => 'Ürün düzenle → Stok durumunu "Ön Sipariş" yapın. Tahmini tarih ve özel buton metni ayarlanabilir.'),
                    array('title' => 'Tahmini Teslimat', 'desc' => 'Ürün sayfasında teslimat tarihi.', 'how' => 'Ürün bazında veya genel ayarlardan gün sayısı girin.'),
                    array('title' => 'Uyumlu Ürünler', 'desc' => 'Aksesuar ve uyumlu ürün önerileri.', 'how' => 'Ürün düzenle → "Uyumlu Ürünler" kutusundan ürün seçin.'),
                ),
            ),
            'siparis' => array(
                'label' => 'Sipariş & Kargo',
                'icon'  => '🚚',
                'items' => array(
                    array('title' => 'Ödeme Formu', 'desc' => 'Bireysel/Kurumsal sekmeli checkout formu.', 'how' => 'Otomatik çalışır. TC/Vergi No alanları otomatik ayrışır.'),
                    array('title' => 'Sipariş Durumları', 'desc' => 'Özel sipariş durumları oluşturma.', 'how' => 'Webyaz → Sipariş Durumları → Yeni durum ekleyin, renk ve isim belirleyin.'),
                    array('title' => 'Sipariş Takip', 'desc' => 'Kargo takip numarası ekleme.', 'how' => 'Sipariş detay sayfasında "Takip No" alanına numarayı girin. Müşteri Hesabım sayfasından kargosunu takip eder.'),
                    array('title' => 'Sipariş Notu', 'desc' => 'Müşteriye özel mesaj gönderme.', 'how' => 'Sipariş detay → "Müşteriye Not" alanından mesaj yazın.'),
                    array('title' => 'Sipariş WhatsApp', 'desc' => 'Otomatik WhatsApp mesajı.', 'how' => 'Webyaz → Sipariş WhatsApp → Hangi durumda mesaj gideceğini ve şablonu ayarlayın.'),
                    array('title' => 'Kargo Entegrasyonu', 'desc' => 'Kargo firmaları entegrasyonu.', 'how' => 'Webyaz → Kargo Entegrasyonu → Varsayılan kargo firmasını seçin, takip butonu rengini ayarlayın.'),
                    array('title' => 'Kargo Barı', 'desc' => 'Ücretsiz kargoya kalan tutar barı.', 'how' => 'Webyaz → Kargo Barı → Ücretsiz kargo limitini ve bar renklerini ayarlayın.'),
                    array('title' => 'E-Fatura', 'desc' => 'Otomatik fatura oluşturma ve yazdırma.', 'how' => 'Webyaz → E-Fatura → Firma bilgilerini girin. Sipariş listesinden "Fatura" butonuyla yazdırın.'),
                    array('title' => 'İade Yönetimi', 'desc' => 'İade talebi ve onay sistemi.', 'how' => 'Webyaz → İade Talepleri → Gelen talepleri onaylayın/reddedin. Müşteri Hesabım → İade Taleplerim sekmesinden takip eder.'),
                    array('title' => 'Hediye Seçenekleri', 'desc' => 'Hediye paketi ve not ekleme.', 'how' => 'Otomatik çalışır. Checkout sayfasında "Hediye Paketi" seçeneği görünür.'),
                ),
            ),
            'pazarlama' => array(
                'label' => 'Pazarlama & Satış',
                'icon'  => '📈',
                'items' => array(
                    array('title' => 'SEO', 'desc' => 'Schema, Open Graph, meta etiketleri.', 'how' => 'Webyaz → SEO → Site geneli başlık şablonu, açıklama, logo ve OG ayarları. Ürünlerde Product Schema otomatik eklenir.'),
                    array('title' => 'Analytics & İzleme', 'desc' => 'GA4, GTM, Facebook Pixel entegrasyonu.', 'how' => 'Webyaz → Analytics → İlgili alanlara tracking ID\'lerini yapıştırın.'),
                    array('title' => 'Kupon Popup', 'desc' => 'Ziyaretçiye indirim kuponu popup\'ı.', 'how' => 'Webyaz → Kupon Popup → Kupon kodu, gecikme süresi ve tasarımı ayarlayın.'),
                    array('title' => 'Sepet Hatırlatma', 'desc' => 'Terk edilen sepet e-postası.', 'how' => 'Webyaz → Sepet Hatırlatma → Bekleme süresi, e-posta şablonu ve kupon kodunu ayarlayın.'),
                    array('title' => 'Upsell Popup', 'desc' => 'Sepete ekleme öneri popup\'ı.', 'how' => 'Otomatik çalışır. WooCommerce upsell ürünlerinden beslenir.'),
                    array('title' => 'Toplu İndirim', 'desc' => 'Çoklu alış indirimi.', 'how' => 'Webyaz → Toplu İndirim → Adet aralığı ve indirim oranı/tutarı belirleyin.'),
                    array('title' => 'Otomatik İndirim', 'desc' => 'Sepet tutarı, BOGO, X Al Y Öde kuralları.', 'how' => 'Webyaz → Otomatik İndirim → Kural tipi seçin → koşulları ayarlayın → kaydedin.'),
                    array('title' => 'Kupon Yönetici', 'desc' => 'Toplu kupon oluşturma ve istatistik.', 'how' => 'Webyaz → Kupon Yönetici → Toplu Oluştur butonu ile adet, ön ek, indirim, son kullanma belirleyin.'),
                    array('title' => 'Sadakat Puanı', 'desc' => 'Puan kazan, puanla indirim kullan.', 'how' => 'Webyaz → Sadakat Puanı → Puan oranını ve TL dönüşümünü ayarlayın. Müşteri Hesabım → Sadakat Puanlarım sekmesinden takip eder.'),
                    array('title' => 'Referans Sistemi', 'desc' => 'Arkadaşını davet et, iki taraf kazansın.', 'how' => 'Webyaz → Referans Sistemi → Kupon/indirim ayarlayın. Müşteri Hesabım sayfasından referans linkini paylaşır.'),
                    array('title' => 'Kâr Analizi', 'desc' => 'Alış fiyatı ve kâr/zarar takibi.', 'how' => 'Webyaz → Kâr Analizi. Ürün düzenle → "Alış Fiyatı" doldurun. Rapor sayfasında detaylı tablo görünür.'),
                    array('title' => 'SMS Bildirim', 'desc' => 'Sipariş durumunda otomatik SMS.', 'how' => 'Webyaz → SMS Bildirim → API bilgilerini girin, durumları ve şablonu ayarlayın.'),
                    array('title' => 'Kayan Yazı', 'desc' => 'Kayan duyuru/promosyon şeridi.', 'how' => 'Shortcode: [webyaz_marquee]. Webyaz → Kayan Yazı ayarlarından metin, hız ve renk ayarlayın.'),
                    array('title' => 'Canlı Arama', 'desc' => 'AJAX ile anlık arama sonuçları.', 'how' => 'Otomatik çalışır. Varsayılan arama kutusunun yerine geçer.'),
                ),
            ),
            'tasarim' => array(
                'label' => 'Tasarım & İçerik',
                'icon'  => '🎨',
                'items' => array(
                    array('title' => 'Kurumsal Sayfalar', 'desc' => 'Premium tasarımlı kurumsal sayfalar.', 'how' => 'Webyaz → Kurumsal Sayfalar → Yeni Sayfa Ekle. Hazır şablonlardan seçin veya sıfırdan tasarlayın.'),
                    array('title' => 'Story Menü', 'desc' => 'Instagram tarzı kategori menüsü.', 'how' => 'Webyaz → Story Menü → Kategori ve görselleri ayarlayın. Shortcode: [webyaz_story] — ana sayfaya ekleyin.'),
                    array('title' => 'Footer', 'desc' => 'Özel footer tasarımı.', 'how' => 'Webyaz → Footer Ayarları → Logo, sosyal medya, menü linkleri ve telif hakkı metni düzenleyin.'),
                    array('title' => 'Mobil Bar', 'desc' => 'Mobil alt navigasyon barı.', 'how' => 'Webyaz → Mobil Menü → Buton sırası, ikonları ve bağlantıları ayarlayın.'),
                    array('title' => 'İletişim Butonları', 'desc' => 'Yüzen sosyal medya & iletişim butonları.', 'how' => 'Webyaz → İletişim Butonları → WhatsApp, telefon, e-posta gibi kanalları ekleyin.'),
                    array('title' => 'WhatsApp Butonu', 'desc' => 'Canlı destek butonu.', 'how' => 'Webyaz → Ayarlar → WhatsApp numarasını girin. Mesai saatleri ve karşılama mesajı ayarlanabilir.'),
                    array('title' => 'Canlı Destek', 'desc' => 'Çoklu kanal destek butonu.', 'how' => 'Webyaz → Canlı Destek → Kanalları ekleyin, sıralayın ve konum ayarlayın.'),
                    array('title' => 'E-posta Şablonları', 'desc' => 'WooCommerce e-posta tasarımı.', 'how' => 'Webyaz → E-posta Şablonu → Logo URL\'sini medya kütüphanesinden seçin, renkleri ayarlayın.'),
                    array('title' => 'Özel CSS', 'desc' => 'Siteye özel CSS kodları.', 'how' => 'Webyaz → Özel CSS → Kod editörüne CSS yazın ve kaydedin.'),
                    array('title' => 'Çerez Uyarısı', 'desc' => 'KVKK çerez bildirimi.', 'how' => 'Webyaz → Çerez Bildirimi → Metin, buton yazısı ve renkleri özelleştirin.'),
                    array('title' => 'Bakım Modu', 'desc' => 'Siteyi bakıma alma.', 'how' => 'Webyaz → Bakım Modu → Bakım mesajı ve arka plan görselini ayarlayın. "Aktif Et" ile bakıma alın.'),
                    array('title' => 'Mağaza Türkçesi', 'desc' => 'WooCommerce Türkçeleştirme + tasarım.', 'how' => 'Otomatik çalışır. Ek ayar gerekmez.'),
                ),
            ),
            'kullanici' => array(
                'label' => 'Kullanıcı & Üyelik',
                'icon'  => '👥',
                'items' => array(
                    array('title' => 'B2B Bayi Sistemi', 'desc' => 'Bayi yönetimi, bakiye ve özel fiyatlandırma.', 'how' => 'Webyaz → B2B Yönetimi → Bayi başvuru formu, bakiye yükleme ve fiyat grupları ayarlayın. Bayiler Hesabım sayfasından bakiye görür.'),
                    array('title' => 'Üyelik Sistemi', 'desc' => 'Sayfa/yazı kısıtlama ve üyelik planları.', 'how' => 'Webyaz → Üyelik Sistemi → Planlar oluşturun. Sayfa düzenle → "Üyelik Kısıtlama" kutusundan plan atayın.'),
                    array('title' => 'Partner Sistemi', 'desc' => 'Ortaklık ve komisyon yönetimi.', 'how' => 'Webyaz → Partner Yönetimi → Partnerlere kupon oluşturun, komisyon oranı belirleyin. Partnerler Hesabım → Partner Paneli sekmesinden takip eder.'),
                    array('title' => 'Rol Yönetimi', 'desc' => 'Kullanıcı rolü menü kısıtlamaları.', 'how' => 'Webyaz → Rol Yönetimi → Gizlenecek menüleri ve widget\'ları seçin.'),
                    array('title' => 'Müşteri Cari', 'desc' => 'Toplam harcama takibi ve otomatik kupon.', 'how' => 'Webyaz → Müşteri Cari → Harcama eşiği, kupon tutarı ve tekrar ayarlayın. Müşteri Hesabım → Cari Hesabım sekmesinden takip eder.'),
                    array('title' => 'Destek Sistemi', 'desc' => 'Müşteri destek talebi ve cevaplama.', 'how' => 'Webyaz → Destek Talepleri → Gelen talepleri görüntüleyin ve yanıtlayın. Müşteriler Hesabım → Destek Taleplerim sekmesinden yeni talep oluşturur.'),
                ),
            ),
            'guvenlik' => array(
                'label' => 'Güvenlik',
                'icon'  => '🛡️',
                'items' => array(
                    array('title' => 'Anti-Bot', 'desc' => 'Sahte kayıtları engelleme.', 'how' => 'Otomatik çalışır. Honeypot ve zaman kontrolü ile bot kayıtlar filtrelenir.'),
                    array('title' => 'Brute Force Koruma', 'desc' => 'Giriş denemesi sınırlama.', 'how' => 'Webyaz → Brute Force → Deneme limiti ve kilitleme süresini ayarlayın.'),
                    array('title' => 'Güvenlik Kalkanı', 'desc' => 'Kaynak kod ve sağ tık engelleme.', 'how' => 'Otomatik çalışır. F12, Ctrl+U, sağ tık menüsü devre dışı kalır.'),
                ),
            ),
            'araclar' => array(
                'label' => 'Araçlar & Entegrasyon',
                'icon'  => '🔧',
                'items' => array(
                    array('title' => 'Yedekleme', 'desc' => 'Tam site yedek al & geri yükle.', 'how' => 'Webyaz → Yedek & Geri Yükle → "Yedek Oluştur" butonuyla .wbak indirin. "Geri Yükle" tabında dosya seçerek geri yükleyin.'),
                    array('title' => 'XML Yönetimi', 'desc' => 'XML/Excel ürün içe/dışa aktarma.', 'how' => 'Webyaz → XML Yönetimi → Toptancı Ekle → Link yapıştır → Analiz Et → Eşleştir → Aktar. Excel/CSV tab\'ından dosya yükleyin.'),
                    array('title' => 'Pazaryeri', 'desc' => 'Trendyol & Hepsiburada entegrasyonu.', 'how' => 'Webyaz → Pazaryeri → API bilgilerini girin, kategori eşleştirme yapın.'),
                    array('title' => 'WebP Dönüştürme', 'desc' => 'Otomatik resim optimizasyonu.', 'how' => 'Otomatik çalışır. Yeni yüklenen görseller WebP\'ye çevrilir.'),
                    array('title' => 'Toplu WebP', 'desc' => 'Mevcut resimleri topluca WebP\'ye çevirme.', 'how' => 'Webyaz → Toplu WebP → "Dönüştür" butonuna basın.'),
                    array('title' => 'Büyük Dosya Yükleme', 'desc' => 'Medya yükleme limitini artırma.', 'how' => 'Webyaz → Büyük Dosya Yükle → Parça parça yükleme desteğiyle 256 MB\'a kadar dosya yükleyin.'),
                    array('title' => 'Yazı Kopyala', 'desc' => 'Tek tıkla yazı/sayfa kopyalama.', 'how' => 'Otomatik çalışır. Yazılar/Sayfalar listesinde "Kopyala" butonu görünür.'),
                    array('title' => 'Otomatik Güncelleme', 'desc' => 'Eklenti güncelleme bildirimi ve tek tık güncelleme.', 'how' => 'Güncelleme bildirimi geldiğinde "Güncelle" butonuna basın.'),
                ),
            ),
        );

        ?>
        <div class="webyaz-admin-wrap" style="max-width:960px;">
            <div class="webyaz-admin-header">
                <h1>❓ Kullanım Rehberi</h1>
                <p>Tüm Webyaz modüllerinin nasıl kullanıldığını öğrenin</p>
            </div>

            <!-- Arama -->
            <div style="margin-bottom:20px;">
                <input type="text" id="wyHelpSearch" placeholder="🔍 Modül ara..." style="width:100%;padding:12px 16px;border:1px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;">
            </div>

            <!-- Tab Başlıkları -->
            <div id="wyHelpTabs" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:20px;">
                <?php $first = true; foreach ($groups as $gkey => $g): ?>
                <button type="button" class="wy-help-tab <?php echo $first ? 'wy-help-tab-active' : ''; ?>" data-group="<?php echo $gkey; ?>"
                    style="padding:10px 18px;border:1px solid <?php echo $first ? $primary : '#ddd'; ?>;border-radius:8px;background:<?php echo $first ? $primary : '#fff'; ?>;color:<?php echo $first ? '#fff' : '#555'; ?>;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px;">
                    <span style="font-size:16px;"><?php echo $g['icon']; ?></span> <?php echo $g['label']; ?>
                    <span style="background:<?php echo $first ? 'rgba(255,255,255,0.25)' : '#eee'; ?>;padding:2px 8px;border-radius:10px;font-size:11px;"><?php echo count($g['items']); ?></span>
                </button>
                <?php $first = false; endforeach; ?>
            </div>

            <!-- Tab İçerikleri -->
            <?php $first = true; foreach ($groups as $gkey => $g): ?>
            <div class="wy-help-panel" data-group="<?php echo $gkey; ?>" style="<?php echo !$first ? 'display:none;' : ''; ?>">
                <?php foreach ($g['items'] as $item): ?>
                <div class="wy-help-item" data-search="<?php echo esc_attr(mb_strtolower($item['title'] . ' ' . $item['desc'] . ' ' . $item['how'])); ?>"
                     style="background:#fff;border:1px solid #e0e0e0;border-radius:10px;padding:18px 20px;margin-bottom:10px;transition:box-shadow .2s;">
                    <div style="display:flex;align-items:flex-start;gap:12px;">
                        <div style="flex:1;">
                            <div style="font-size:14px;font-weight:700;color:#333;margin-bottom:4px;"><?php echo esc_html($item['title']); ?></div>
                            <div style="font-size:12px;color:#999;margin-bottom:8px;"><?php echo esc_html($item['desc']); ?></div>
                            <div style="font-size:13px;color:#444;line-height:1.7;background:#f8f9fa;padding:10px 14px;border-radius:6px;border-left:3px solid <?php echo $primary; ?>;">
                                <?php echo esc_html($item['how']); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php $first = false; endforeach; ?>

            <!-- Ek Bilgi -->
            <div style="background:linear-gradient(135deg,#fff3e0,#ffe0b2);border-radius:10px;padding:20px;margin-top:20px;border-left:4px solid #e65100;">
                <h3 style="margin:0 0 10px;font-size:15px;color:#e65100;">💡 İpuçları</h3>
                <ul style="margin:0;padding-left:18px;font-size:13px;color:#333;line-height:2;">
                    <li>Modüllerin çoğunun kendi ayar sayfası sol menüde <strong>Webyaz</strong> altında listelenir.</li>
                    <li>Kontrol Panelinden toggle ile kapattığınız modüllerin verileri silinmez, tekrar açınca ayarlar korunur.</li>
                    <li>Renkler <strong>Flatsome → Tema Ayarları</strong>'ndan otomatik alınır.</li>
                    <li>Sorunuz olduğunda <strong>Hesabım → Destek Taleplerim</strong> sekmesinden yeni talep oluşturabilirsiniz.</li>
                </ul>
            </div>
        </div>

        <style>
        .wy-help-tab:hover{opacity:0.85;}
        .wy-help-item:hover{box-shadow:0 2px 12px rgba(0,0,0,0.06);}
        .wy-help-item.wy-hidden{display:none!important;}
        </style>

        <script>
        (function(){
            var primary = '<?php echo $primary; ?>';
            // Tab değiştirme
            document.querySelectorAll('.wy-help-tab').forEach(function(tab){
                tab.addEventListener('click', function(){
                    document.querySelectorAll('.wy-help-tab').forEach(function(t){
                        t.classList.remove('wy-help-tab-active');
                        t.style.background = '#fff'; t.style.color = '#555'; t.style.borderColor = '#ddd';
                        var badge = t.querySelector('span:last-child');
                        if(badge) badge.style.background = '#eee';
                    });
                    this.classList.add('wy-help-tab-active');
                    this.style.background = primary; this.style.color = '#fff'; this.style.borderColor = primary;
                    var badge = this.querySelector('span:last-child');
                    if(badge) badge.style.background = 'rgba(255,255,255,0.25)';

                    var group = this.getAttribute('data-group');
                    document.querySelectorAll('.wy-help-panel').forEach(function(p){
                        p.style.display = p.getAttribute('data-group') === group ? 'block' : 'none';
                    });
                    // Arama temizle
                    document.getElementById('wyHelpSearch').value = '';
                    document.querySelectorAll('.wy-help-item').forEach(function(i){ i.classList.remove('wy-hidden'); });
                });
            });

            // Arama
            document.getElementById('wyHelpSearch').addEventListener('input', function(){
                var q = this.value.toLowerCase().trim();
                if(!q){
                    document.querySelectorAll('.wy-help-item').forEach(function(i){ i.classList.remove('wy-hidden'); });
                    return;
                }
                // Tüm panelleri göster
                document.querySelectorAll('.wy-help-panel').forEach(function(p){ p.style.display = 'block'; });
                document.querySelectorAll('.wy-help-tab').forEach(function(t){
                    t.classList.remove('wy-help-tab-active');
                    t.style.background = '#fff'; t.style.color = '#555'; t.style.borderColor = '#ddd';
                });
                document.querySelectorAll('.wy-help-item').forEach(function(item){
                    var search = item.getAttribute('data-search');
                    item.classList.toggle('wy-hidden', search.indexOf(q) === -1);
                });
            });
        })();
        </script>
        <?php
    }
}

new Webyaz_Help();
