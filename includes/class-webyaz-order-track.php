<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Order_Track {

    public function __construct() {
        add_action('init', array($this, 'register_shortcode'));
        add_action('wp_ajax_webyaz_track_order', array($this, 'ajax_track'));
        add_action('wp_ajax_nopriv_webyaz_track_order', array($this, 'ajax_track'));
    }

    public function register_shortcode() {
        add_shortcode('webyaz_siparis_takip', array($this, 'shortcode_html'));
    }

    public function shortcode_html() {
        ob_start();
        ?>
        <div class="webyaz-order-track">
            <h2 class="webyaz-ot-title">Siparis Takip</h2>
            <p class="webyaz-ot-desc">Siparis numaranizi ve e-posta adresinizi girerek kargonuzu takip edin.</p>
            <form id="webyazTrackForm" class="webyaz-ot-form">
                <div class="webyaz-ot-fields">
                    <input type="text" id="webyazTrackOrderId" placeholder="Siparis Numarasi" required>
                    <input type="email" id="webyazTrackEmail" placeholder="E-posta Adresi" required>
                </div>
                <button type="submit" class="webyaz-ot-btn">Sorgula</button>
            </form>
            <div id="webyazTrackResult" style="display:none;"></div>
        </div>
        <script>
        document.getElementById('webyazTrackForm').addEventListener('submit', function(e){
            e.preventDefault();
            var oid = document.getElementById('webyazTrackOrderId').value.trim();
            var email = document.getElementById('webyazTrackEmail').value.trim();
            var res = document.getElementById('webyazTrackResult');
            res.innerHTML = '<p style="text-align:center;color:#888;">Sorgulanıyor...</p>';
            res.style.display = 'block';
            var fd = new FormData();
            fd.append('action', 'webyaz_track_order');
            fd.append('nonce', webyaz_ajax.nonce);
            fd.append('order_id', oid);
            fd.append('email', email);
            fetch(webyaz_ajax.ajax_url, {method:'POST', body:fd})
            .then(function(r){return r.json();})
            .then(function(d){
                if(d.success){
                    var o=d.data;
                    var html='<div class="webyaz-ot-result">';
                    html+='<div class="webyaz-ot-status-badge" style="background:'+o.status_color+';">'+o.status+'</div>';
                    html+='<div class="webyaz-ot-info"><strong>Siparis No:</strong> #'+o.id+'</div>';
                    html+='<div class="webyaz-ot-info"><strong>Tarih:</strong> '+o.date+'</div>';
                    html+='<div class="webyaz-ot-info"><strong>Toplam:</strong> '+o.total+'</div>';
                    if(o.tracking_code){
                        html+='<div class="webyaz-ot-info"><strong>Kargo Takip:</strong> '+o.tracking_code+'</div>';
                    }
                    if(o.items && o.items.length>0){
                        html+='<div class="webyaz-ot-items"><strong>Urunler:</strong><ul>';
                        o.items.forEach(function(it){html+='<li>'+it.name+' x'+it.qty+'</li>';});
                        html+='</ul></div>';
                    }
                    html+='</div>';
                    res.innerHTML=html;
                } else {
                    res.innerHTML='<div class="webyaz-ot-error">'+d.data+'</div>';
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    public function ajax_track() {
        check_ajax_referer('webyaz_nonce', 'nonce');
        $order_id = intval($_POST['order_id'] ?? 0);
        $email = sanitize_email($_POST['email'] ?? '');

        if (!$order_id || !$email) {
            wp_send_json_error('Siparis numarasi ve e-posta gereklidir.');
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order || strtolower($order->get_billing_email()) !== strtolower($email)) {
            wp_send_json_error('Siparis bulunamadi. Bilgilerinizi kontrol edin.');
            return;
        }

        $status_map = array(
            'pending' => array('Odeme Bekleniyor', '#ff9800'),
            'processing' => array('Hazirlaniyor', '#2196f3'),
            'on-hold' => array('Beklemede', '#9e9e9e'),
            'completed' => array('Tamamlandi', '#4caf50'),
            'cancelled' => array('Iptal Edildi', '#f44336'),
            'refunded' => array('Iade Edildi', '#9c27b0'),
            'failed' => array('Basarisiz', '#d32f2f'),
            'shipped' => array('Kargoya Verildi', '#00bcd4'),
        );
        $st = $order->get_status();
        $status_info = isset($status_map[$st]) ? $status_map[$st] : array($st, '#666');

        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = array('name' => $item->get_name(), 'qty' => $item->get_quantity());
        }

        $tracking = get_post_meta($order_id, '_webyaz_tracking_code', true);

        wp_send_json_success(array(
            'id' => $order_id,
            'status' => $status_info[0],
            'status_color' => $status_info[1],
            'date' => $order->get_date_created()->date_i18n('d.m.Y H:i'),
            'total' => $order->get_formatted_order_total(),
            'tracking_code' => $tracking ?: '',
            'items' => $items,
        ));
    }
}

new Webyaz_Order_Track();
