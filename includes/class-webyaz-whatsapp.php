<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Whatsapp {

    public function __construct() {
        add_action('wp_footer', [$this, 'render_button']);
    }

    public function render_button() {
        if (get_option('webyaz_mod_whatsapp', '1') !== '1') return;
        $number = Webyaz_Settings::get('whatsapp_number');
        if (empty($number)) return;

        $message = Webyaz_Settings::get('whatsapp_message');
        $online_text = Webyaz_Settings::get('whatsapp_online_text');
        $offline_text = Webyaz_Settings::get('whatsapp_offline_text');
        $start = Webyaz_Settings::get('whatsapp_start_hour');
        $end = Webyaz_Settings::get('whatsapp_end_hour');

        $now = current_time('H:i');
        $is_online = ($now >= $start && $now <= $end);
        $status_text = $is_online ? $online_text : $offline_text;
        $status_class = $is_online ? 'online' : 'offline';

        $url = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $number) . '?text=' . urlencode($message);
        ?>
        <div class="webyaz-wa-widget <?php echo $status_class; ?>" id="webyazWaWidget">
            <div class="webyaz-wa-popup" id="webyazWaPopup" style="display:none;">
                <div class="webyaz-wa-popup-header">
                    <div class="webyaz-wa-popup-avatar">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="#fff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492l4.624-1.467A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818c-2.168 0-4.19-.587-5.932-1.61l-.425-.254-2.742.87.885-2.685-.278-.442A9.772 9.772 0 012.182 12c0-5.418 4.4-9.818 9.818-9.818 5.418 0 9.818 4.4 9.818 9.818 0 5.418-4.4 9.818-9.818 9.818z"/></svg>
                    </div>
                    <div class="webyaz-wa-popup-info">
                        <div class="webyaz-wa-popup-name"><?php echo esc_html(Webyaz_Settings::get('company_name') ?: get_bloginfo('name')); ?></div>
                        <div class="webyaz-wa-popup-status"><?php echo esc_html($status_text); ?></div>
                    </div>
                    <button class="webyaz-wa-popup-close" id="webyazWaClose">&times;</button>
                </div>
                <div class="webyaz-wa-popup-body">
                    <div class="webyaz-wa-bubble">
                        Merhaba! <?php echo esc_html($status_text); ?>
                    </div>
                </div>
                <a href="<?php echo esc_url($url); ?>" target="_blank" class="webyaz-wa-popup-btn">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="#fff" style="margin-right:8px;vertical-align:middle;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                    Sohbete Basla
                </a>
            </div>
            <button class="webyaz-wa-fab" id="webyazWaFab" title="WhatsApp">
                <svg viewBox="0 0 24 24" width="32" height="32" fill="#fff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492l4.624-1.467A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818c-2.168 0-4.19-.587-5.932-1.61l-.425-.254-2.742.87.885-2.685-.278-.442A9.772 9.772 0 012.182 12c0-5.418 4.4-9.818 9.818-9.818 5.418 0 9.818 4.4 9.818 9.818 0 5.418-4.4 9.818-9.818 9.818z"/></svg>
                <span class="webyaz-wa-dot"></span>
            </button>
        </div>
        <?php
    }
}

new Webyaz_Whatsapp();
