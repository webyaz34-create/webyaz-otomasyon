<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Bulk_Product {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_media'));
        add_action('wp_ajax_webyaz_bulk_import', array($this, 'ajax_import'));
    }

    public function enqueue_media($hook) {
        if (strpos($hook, 'webyaz-bulk-product') !== false) {
            wp_enqueue_media();
        }
    }

    public function add_submenu() {
        add_submenu_page('webyaz-dashboard', 'Toplu Urun Ekle', 'Toplu Urun Ekle', 'manage_options', 'webyaz-bulk-product', array($this, 'render_admin'));
    }

    public function ajax_import() {
        check_ajax_referer('webyaz_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Yetki yok');

        $products = json_decode(stripslashes($_POST['products']), true);
        if (!is_array($products) || empty($products)) wp_send_json_error('Urun verisi bos');

        $success = 0;
        $errors = array();
        $default_status = sanitize_text_field($_POST['status'] ?? 'publish');

        foreach ($products as $idx => $p) {
            $title = sanitize_text_field($p['title'] ?? '');
            $price = sanitize_text_field($p['price'] ?? '');
            if (empty($title) || empty($price)) {
                $errors[] = 'Satir ' . ($idx + 1) . ': Baslik veya fiyat bos';
                continue;
            }

            $post_id = wp_insert_post(array(
                'post_title' => $title,
                'post_content' => wp_kses_post($p['description'] ?? ''),
                'post_excerpt' => sanitize_textarea_field($p['short_desc'] ?? ''),
                'post_status' => $default_status,
                'post_type' => 'product',
            ));

            if (is_wp_error($post_id)) {
                $errors[] = 'Satir ' . ($idx + 1) . ': ' . $post_id->get_error_message();
                continue;
            }

            $sale = sanitize_text_field($p['sale_price'] ?? '');
            update_post_meta($post_id, '_regular_price', $price);
            if ($sale) update_post_meta($post_id, '_sale_price', $sale);
            update_post_meta($post_id, '_price', $sale ? $sale : $price);
            update_post_meta($post_id, '_stock_status', 'instock');
            update_post_meta($post_id, '_manage_stock', 'no');
            update_post_meta($post_id, '_visibility', 'visible');
            wp_set_object_terms($post_id, 'simple', 'product_type');

            $sku = sanitize_text_field($p['sku'] ?? '');
            if ($sku) update_post_meta($post_id, '_sku', $sku);

            $stock = sanitize_text_field($p['stock'] ?? '');
            if ($stock !== '') {
                update_post_meta($post_id, '_manage_stock', 'yes');
                update_post_meta($post_id, '_stock', intval($stock));
            }

            $cat = sanitize_text_field($p['category'] ?? '');
            if ($cat) {
                $term = term_exists($cat, 'product_cat');
                if (!$term) $term = wp_insert_term($cat, 'product_cat');
                if (!is_wp_error($term)) {
                    $tid = is_array($term) ? $term['term_id'] : $term;
                    wp_set_object_terms($post_id, intval($tid), 'product_cat');
                }
            }

            $image_url = esc_url_raw($p['image'] ?? '');
            if ($image_url) {
                $attach_id = attachment_url_to_postid($image_url);
                if ($attach_id) set_post_thumbnail($post_id, $attach_id);
            }

            $success++;
        }

        wp_send_json_success(array(
            'success' => $success,
            'errors' => $errors,
            'total' => count($products),
        ));
    }

    public function render_admin() {
        ?>
        <div class="webyaz-admin-wrap">
            <div class="webyaz-admin-header">
                <h1>Toplu Urun Ekle</h1>
                <p>CSV yukleyerek veya tablodan yazarak toplu urun ekleyin</p>
            </div>

            <div class="webyaz-settings-section">
                <h2 class="webyaz-section-title">1. CSV Dosyasi Yukle (Opsiyonel)</h2>
                <p style="color:#666;font-size:13px;margin-bottom:10px;">CSV sutunlari: <code>Urun Adi, Fiyat, Indirimli Fiyat, SKU, Stok, Kategori, Kisa Aciklama, Gorsel URL</code></p>
                <div style="display:flex;gap:10px;align-items:center;">
                    <input type="file" id="wbBulkCsv" accept=".csv" style="font-size:13px;">
                    <button type="button" id="wbBulkCsvLoad" class="button" style="font-weight:600;">CSV Yukle</button>
                    <a href="#" id="wbBulkSample" style="font-size:12px;color:#446084;">Ornek CSV Indir</a>
                </div>
            </div>

            <div class="webyaz-settings-section">
                <h2 class="webyaz-section-title">2. Excel'den Yapistir</h2>
                <p style="color:#666;font-size:13px;margin-bottom:10px;">Excel'den satirlari sec, kopyala (Ctrl+C), asagidaki kutuya yapistir (Ctrl+V). Sutun sirasi: <code>Urun Adi | Fiyat | Ind.Fiyat | SKU | Stok | Kategori | Kisa Aciklama</code></p>
                <textarea id="wbBulkPaste" rows="4" style="width:100%;padding:12px;border:2px dashed #ccc;border-radius:10px;font-family:Roboto,sans-serif;font-size:13px;resize:vertical;" placeholder="Excel'den kopyaladiginiz satirlari buraya yapistirin..."></textarea>
                <button type="button" id="wbBulkPasteBtn" class="button" style="margin-top:8px;font-weight:600;">Tabloya Aktar</button>
            </div>

            <div class="webyaz-settings-section">
                <h2 class="webyaz-section-title">3. Toplu Gorsel Eslestir</h2>
                <p style="color:#666;font-size:13px;margin-bottom:10px;">Gorselleri toplu secin, sirayla urunlere atanir. 1. gorsel 1. urune, 2. gorsel 2. urune...</p>
                <button type="button" id="wbBulkImgAll" class="button" style="font-weight:600;padding:8px 18px;">Toplu Gorsel Sec (Medya)</button>
                <span id="wbBulkImgCount" style="margin-left:10px;color:#888;font-size:13px;"></span>
            </div>

            <div class="webyaz-settings-section">
                <h2 class="webyaz-section-title">4. Urun Tablosu</h2>
                <div style="display:flex;gap:10px;margin-bottom:14px;align-items:center;flex-wrap:wrap;">
                    <label>Varsayilan Durum:
                        <select id="wbBulkStatus" style="margin-left:6px;">
                            <option value="publish">Yayinda</option>
                            <option value="draft">Taslak</option>
                        </select>
                    </label>
                    <button type="button" id="wbBulkAddRow" class="button" style="font-weight:600;">+ Satir Ekle</button>
                    <button type="button" id="wbBulkAdd5" class="button">+ 5 Satir</button>
                    <button type="button" id="wbBulkClear" class="button" style="color:#d32f2f;">Tabloyu Temizle</button>
                </div>
                <div style="overflow-x:auto;">
                    <table id="wbBulkTable" style="width:100%;border-collapse:collapse;font-family:Roboto,sans-serif;font-size:13px;">
                        <thead>
                            <tr style="background:#f5f5f5;">
                                <th style="padding:10px 8px;border:1px solid #e0e0e0;font-weight:700;white-space:nowrap;">#</th>
                                <th style="padding:10px 8px;border:1px solid #e0e0e0;font-weight:700;min-width:180px;">Urun Adi *</th>
                                <th style="padding:10px 8px;border:1px solid #e0e0e0;font-weight:700;width:90px;">Fiyat *</th>
                                <th style="padding:10px 8px;border:1px solid #e0e0e0;font-weight:700;width:90px;">Ind. Fiyat</th>
                                <th style="padding:10px 8px;border:1px solid #e0e0e0;font-weight:700;width:80px;">SKU</th>
                                <th style="padding:10px 8px;border:1px solid #e0e0e0;font-weight:700;width:60px;">Stok</th>
                                <th style="padding:10px 8px;border:1px solid #e0e0e0;font-weight:700;min-width:120px;">Kategori</th>
                                <th style="padding:10px 8px;border:1px solid #e0e0e0;font-weight:700;min-width:150px;">Kisa Aciklama</th>
                                <th style="padding:10px 8px;border:1px solid #e0e0e0;font-weight:700;width:80px;">Gorsel</th>
                                <th style="padding:10px 8px;border:1px solid #e0e0e0;font-weight:700;width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="wbBulkBody"></tbody>
                    </table>
                </div>
            </div>

            <div style="margin-top:20px;display:flex;gap:12px;align-items:center;">
                <button type="button" id="wbBulkImport" class="webyaz-btn webyaz-btn-primary" style="padding:14px 32px;font-size:15px;font-weight:700;">Urunleri Ekle</button>
                <span id="wbBulkStatus2" style="font-size:14px;color:#666;"></span>
            </div>
            <div id="wbBulkResult" style="display:none;margin-top:16px;padding:16px;border-radius:10px;font-size:14px;"></div>
        </div>

        <script>
        jQuery(document).ready(function($){
            var rowIdx = 0;

            function addRow(data) {
                data = data || {};
                rowIdx++;
                var html = '<tr>';
                html += '<td style="padding:6px;border:1px solid #e0e0e0;text-align:center;color:#999;">'+rowIdx+'</td>';
                html += '<td style="padding:4px;border:1px solid #e0e0e0;"><input type="text" data-field="title" value="'+(data.title||'')+'" style="width:100%;padding:6px;border:1px solid #eee;border-radius:4px;font-size:13px;"></td>';
                html += '<td style="padding:4px;border:1px solid #e0e0e0;"><input type="number" data-field="price" value="'+(data.price||'')+'" step="0.01" style="width:100%;padding:6px;border:1px solid #eee;border-radius:4px;font-size:13px;"></td>';
                html += '<td style="padding:4px;border:1px solid #e0e0e0;"><input type="number" data-field="sale_price" value="'+(data.sale_price||'')+'" step="0.01" style="width:100%;padding:6px;border:1px solid #eee;border-radius:4px;font-size:13px;"></td>';
                html += '<td style="padding:4px;border:1px solid #e0e0e0;"><input type="text" data-field="sku" value="'+(data.sku||'')+'" style="width:100%;padding:6px;border:1px solid #eee;border-radius:4px;font-size:13px;"></td>';
                html += '<td style="padding:4px;border:1px solid #e0e0e0;"><input type="number" data-field="stock" value="'+(data.stock||'')+'" style="width:100%;padding:6px;border:1px solid #eee;border-radius:4px;font-size:13px;"></td>';
                html += '<td style="padding:4px;border:1px solid #e0e0e0;"><input type="text" data-field="category" value="'+(data.category||'')+'" style="width:100%;padding:6px;border:1px solid #eee;border-radius:4px;font-size:13px;"></td>';
                html += '<td style="padding:4px;border:1px solid #e0e0e0;"><input type="text" data-field="short_desc" value="'+(data.short_desc||'')+'" style="width:100%;padding:6px;border:1px solid #eee;border-radius:4px;font-size:13px;"></td>';
                html += '<td style="padding:4px;border:1px solid #e0e0e0;text-align:center;"><input type="hidden" data-field="image" value="'+(data.image||'')+'"><div class="wb-bulk-img-prev" style="width:40px;height:40px;border-radius:6px;margin:0 auto;'+(data.image ? 'background:url('+data.image+') center/cover;' : 'background:#f0f0f0;')+'"></div><button type="button" class="wb-bulk-img-btn" style="font-size:11px;color:#fff;border:none;background:#446084;cursor:pointer;margin-top:4px;padding:4px 10px;border-radius:4px;font-weight:600;">Sec</button></td>';
                html += '<td style="padding:4px;border:1px solid #e0e0e0;text-align:center;"><button type="button" onclick="jQuery(this).closest(\'tr\').remove();" style="background:#f44336;color:#fff;border:none;border-radius:4px;padding:4px 8px;cursor:pointer;font-weight:700;">&times;</button></td>';
                html += '</tr>';
                $('#wbBulkBody').append(html);
            }

            for (var i=0; i<3; i++) addRow();

            $('#wbBulkAddRow').on('click', function(){ addRow(); });
            $('#wbBulkAdd5').on('click', function(){ for(var i=0;i<5;i++) addRow(); });
            $('#wbBulkClear').on('click', function(){ if(confirm('Tablo temizlensin mi?')){ $('#wbBulkBody').empty(); rowIdx=0; } });

            $(document).on('click', '.wb-bulk-img-btn', function(e){
                e.preventDefault();
                var td = $(this).closest('td');
                var frame = wp.media({title:'Urun Gorseli Sec',button:{text:'Sec'},multiple:false,library:{type:'image'}});
                frame.on('select', function(){
                    var a = frame.state().get('selection').first().toJSON();
                    td.find('input[data-field="image"]').val(a.url);
                    td.find('.wb-bulk-img-prev').css('background', 'url('+a.url+') center/cover');
                });
                frame.open();
            });

            // Excel yapistir
            $('#wbBulkPasteBtn').on('click', function(){
                var text = $('#wbBulkPaste').val().trim();
                if (!text) { alert('Kutu bos. Once Excel\'den kopyalayip yapistirin.'); return; }
                var lines = text.split('\n');
                var count = 0;
                for (var i=0; i<lines.length; i++){
                    var line = lines[i].trim();
                    if (!line) continue;
                    var cols = line.split('\t');
                    if (!cols[0] || !cols[0].trim()) continue;
                    addRow({
                        title: (cols[0]||'').trim(),
                        price: (cols[1]||'').trim(),
                        sale_price: (cols[2]||'').trim(),
                        sku: (cols[3]||'').trim(),
                        stock: (cols[4]||'').trim(),
                        category: (cols[5]||'').trim(),
                        short_desc: (cols[6]||'').trim()
                    });
                    count++;
                }
                $('#wbBulkPaste').val('');
                alert(count + ' satir tabloya aktarildi!');
            });

            // Toplu gorsel eslestir
            $('#wbBulkImgAll').on('click', function(e){
                e.preventDefault();
                var frame = wp.media({title:'Toplu Gorsel Sec (Sirayla secin: 1.gorsel=1.urun)',button:{text:'Secilenleri Ata'},multiple:true,library:{type:'image'}});
                frame.on('select', function(){
                    var images = frame.state().get('selection').toJSON();
                    // Onizleme goster
                    var previewHtml = '<div style="margin-top:12px;"><strong>Eslestirme Onizleme:</strong><div id="wbBulkImgPreview" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">';
                    images.forEach(function(img, idx){
                        previewHtml += '<div style="text-align:center;font-size:10px;color:#666;"><img src="'+img.url+'" style="width:50px;height:50px;object-fit:cover;border-radius:6px;border:1px solid #ddd;display:block;">'+(idx+1)+'. urun</div>';
                    });
                    previewHtml += '</div><button type="button" id="wbBulkImgApply" class="button" style="margin-top:8px;font-weight:600;background:#446084;color:#fff;border:none;padding:8px 18px;">Onayla ve Ata</button>';
                    previewHtml += ' <button type="button" id="wbBulkImgReverse" class="button" style="margin-top:8px;">Siralamayi Ters Cevir</button></div>';
                    $('#wbBulkImgCount').html(images.length + ' gorsel secildi');
                    $('#wbBulkImgAll').after(previewHtml);

                    window._wbBulkImages = images;

                    $(document).on('click', '#wbBulkImgReverse', function(){
                        window._wbBulkImages.reverse();
                        var prev = $('#wbBulkImgPreview');
                        prev.empty();
                        window._wbBulkImages.forEach(function(img, idx){
                            prev.append('<div style="text-align:center;font-size:10px;color:#666;"><img src="'+img.url+'" style="width:50px;height:50px;object-fit:cover;border-radius:6px;border:1px solid #ddd;display:block;">'+(idx+1)+'. urun</div>');
                        });
                    });

                    $(document).on('click', '#wbBulkImgApply', function(){
                        var imgs = window._wbBulkImages;
                        var rows = $('#wbBulkBody tr');
                        var matched = 0;
                        imgs.forEach(function(img, idx){
                            if (idx < rows.length) {
                                var td = $(rows[idx]).find('td').eq(-2);
                                td.find('input[data-field="image"]').val(img.url);
                                td.find('.wb-bulk-img-prev').css('background', 'url('+img.url+') center/cover');
                                matched++;
                            }
                        });
                        $('#wbBulkImgCount').text(matched + ' gorsel basariyla atandi!').css('color','#2e7d32');
                        $('#wbBulkImgPreview').parent().remove();
                    });
                });
                frame.open();
            });

            $('#wbBulkSample').on('click', function(e){
                e.preventDefault();
                var csv = 'Urun Adi,Fiyat,Indirimli Fiyat,SKU,Stok,Kategori,Kisa Aciklama,Gorsel URL\n';
                csv += 'Ornek Tisort,199.90,149.90,TSH-001,50,Tisortler,Premium pamuklu tisort,\n';
                csv += 'Ornek Pantolon,349.90,,PNT-001,30,Pantolonlar,Slim fit pantolon,\n';
                var blob = new Blob([csv], {type:'text/csv'});
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'ornek-urunler.csv';
                a.click();
            });

            $('#wbBulkCsvLoad').on('click', function(){
                var file = document.getElementById('wbBulkCsv').files[0];
                if (!file) { alert('Lutfen CSV dosyasi secin.'); return; }
                var reader = new FileReader();
                reader.onload = function(e){
                    var lines = e.target.result.split('\n');
                    var start = 0;
                    if (lines[0] && lines[0].toLowerCase().indexOf('urun') !== -1) start = 1;
                    $('#wbBulkBody').empty(); rowIdx = 0;
                    for (var i=start; i<lines.length; i++){
                        var cols = lines[i].split(',');
                        if (!cols[0] || !cols[0].trim()) continue;
                        addRow({
                            title: cols[0] ? cols[0].trim().replace(/^"|"$/g,'') : '',
                            price: cols[1] ? cols[1].trim().replace(/^"|"$/g,'') : '',
                            sale_price: cols[2] ? cols[2].trim().replace(/^"|"$/g,'') : '',
                            sku: cols[3] ? cols[3].trim().replace(/^"|"$/g,'') : '',
                            stock: cols[4] ? cols[4].trim().replace(/^"|"$/g,'') : '',
                            category: cols[5] ? cols[5].trim().replace(/^"|"$/g,'') : '',
                            short_desc: cols[6] ? cols[6].trim().replace(/^"|"$/g,'') : '',
                        });
                    }
                };
                reader.readAsText(file, 'UTF-8');
            });

            $('#wbBulkImport').on('click', function(){
                var btn = $(this);
                var products = [];
                $('#wbBulkBody tr').each(function(){
                    var row = {};
                    $(this).find('input[data-field]').each(function(){
                        row[$(this).data('field')] = $(this).val().trim();
                    });
                    if (row.title && row.price) products.push(row);
                });
                if (!products.length) { alert('Eklenecek urun bulunamadi. Urun adi ve fiyat zorunludur.'); return; }
                if (!confirm(products.length + ' urun eklenecek. Devam edilsin mi?')) return;

                btn.prop('disabled', true).text('Ekleniyor...');
                $('#wbBulkStatus2').text('');
                $.post(webyaz_ajax.ajax_url, {
                    action: 'webyaz_bulk_import',
                    nonce: webyaz_ajax.nonce,
                    products: JSON.stringify(products),
                    status: $('#wbBulkStatus').val()
                }, function(res){
                    btn.prop('disabled', false).text('Urunleri Ekle');
                    var r = res.data;
                    var div = $('#wbBulkResult');
                    if (r.success > 0) {
                        div.css({display:'block', background:'#e8f5e9', color:'#2e7d32'});
                        div.html('<strong>'+r.success+'/'+r.total+'</strong> urun basariyla eklendi!');
                    }
                    if (r.errors && r.errors.length) {
                        var errHtml = '<div style="margin-top:8px;color:#d32f2f;font-size:12px;">';
                        r.errors.forEach(function(e){ errHtml += '<div>'+e+'</div>'; });
                        errHtml += '</div>';
                        div.append(errHtml);
                        div.css('display','block');
                    }
                });
            });
        });
        </script>
        <?php
    }
}

new Webyaz_Bulk_Product();
