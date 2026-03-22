<?php
if (!defined('ABSPATH')) exit;

class Webyaz_QA {

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('woocommerce_product_tabs', array($this, 'add_tab'));
        add_action('wp_ajax_webyaz_qa_submit', array($this, 'ajax_submit'));
        add_action('wp_ajax_nopriv_webyaz_qa_submit', array($this, 'ajax_submit'));
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post_webyaz_qa', array($this, 'save_answer'));
    }

    public function register_post_type() {
        register_post_type('webyaz_qa', array(
            'labels' => array(
                'name' => 'Urun Sorulari',
                'singular_name' => 'Soru',
                'menu_name' => 'Urun Sorulari',
                'all_items' => 'Tum Sorular',
                'edit_item' => 'Soruyu Duzenle',
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'webyaz-dashboard',
            'supports' => array('title', 'editor'),
            'capability_type' => 'post',
        ));
    }

    public function add_tab($tabs) {
        $tabs['webyaz_qa'] = array(
            'title' => 'Soru & Cevap',
            'priority' => 35,
            'callback' => array($this, 'tab_content'),
        );
        return $tabs;
    }

    public function tab_content() {
        global $product;
        $pid = $product->get_id();

        $questions = get_posts(array(
            'post_type' => 'webyaz_qa',
            'meta_key' => '_webyaz_qa_product',
            'meta_value' => $pid,
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        ?>
        <div class="webyaz-qa-section">
            <?php if (!empty($questions)): ?>
                <div class="webyaz-qa-list">
                    <?php foreach ($questions as $q):
                        $answer = get_post_meta($q->ID, '_webyaz_qa_answer', true);
                        $author = get_post_meta($q->ID, '_webyaz_qa_author', true);
                    ?>
                    <div class="webyaz-qa-item">
                        <div class="webyaz-qa-question">
                            <span class="webyaz-qa-icon">S</span>
                            <div>
                                <strong><?php echo esc_html($q->post_title); ?></strong>
                                <small><?php echo esc_html($author); ?> - <?php echo get_the_date('d.m.Y', $q); ?></small>
                            </div>
                        </div>
                        <?php if ($answer): ?>
                        <div class="webyaz-qa-answer">
                            <span class="webyaz-qa-icon webyaz-qa-a">C</span>
                            <div><?php echo wp_kses_post($answer); ?></div>
                        </div>
                        <?php else: ?>
                        <div class="webyaz-qa-answer webyaz-qa-pending">
                            <span class="webyaz-qa-icon">C</span>
                            <div><em>Yanitlanmayi bekliyor...</em></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="webyaz-qa-form-wrap">
                <h4>Soru Sorun</h4>
                <form id="webyazQaForm">
                    <input type="hidden" name="product_id" value="<?php echo $pid; ?>">
                    <input type="text" name="qa_name" placeholder="Adiniz" required>
                    <input type="email" name="qa_email" placeholder="E-posta (yayinlanmaz)" required>
                    <textarea name="qa_question" rows="3" placeholder="Sorunuzu yazin..." required></textarea>
                    <button type="submit">Gonder</button>
                    <div class="webyaz-qa-msg" id="webyazQaMsg" style="display:none;"></div>
                </form>
            </div>
        </div>
        <script>
        (function(){
            var form = document.getElementById('webyazQaForm');
            if (!form) return;
            form.addEventListener('submit', function(e){
                e.preventDefault();
                var fd = new FormData(form);
                fd.append('action', 'webyaz_qa_submit');
                var btn = form.querySelector('button');
                btn.disabled = true;
                btn.textContent = 'Gonderiliyor...';
                fetch(webyaz_ajax.ajax_url, {method:'POST', body:fd})
                .then(function(r){return r.json();})
                .then(function(data){
                    var msg = document.getElementById('webyazQaMsg');
                    msg.style.display = 'block';
                    if (data.success) {
                        msg.textContent = 'Sorunuz gonderildi. Yanitlandiktan sonra yayinlanacaktir.';
                        msg.style.color = '#22863a';
                        form.reset();
                    } else {
                        msg.textContent = data.data.message || 'Hata olustu.';
                        msg.style.color = '#d32f2f';
                    }
                    btn.disabled = false;
                    btn.textContent = 'Gonder';
                });
            });
        })();
        </script>
        <?php
    }

    public function ajax_submit() {
        $pid = intval($_POST['product_id']);
        $name = sanitize_text_field($_POST['qa_name']);
        $email = sanitize_email($_POST['qa_email']);
        $question = sanitize_textarea_field($_POST['qa_question']);

        if (empty($name) || empty($email) || empty($question) || !$pid) {
            wp_send_json_error(array('message' => 'Tum alanlari doldurun.'));
        }

        $post_id = wp_insert_post(array(
            'post_type' => 'webyaz_qa',
            'post_title' => $question,
            'post_status' => 'publish',
            'post_content' => '',
        ));

        if ($post_id) {
            update_post_meta($post_id, '_webyaz_qa_product', $pid);
            update_post_meta($post_id, '_webyaz_qa_author', $name);
            update_post_meta($post_id, '_webyaz_qa_email', $email);
            update_post_meta($post_id, '_webyaz_qa_answer', '');
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Soru gonderilemedi.'));
        }
    }

    public function add_meta_box() {
        add_meta_box('webyaz_qa_details', 'Soru Detaylari', array($this, 'meta_box_html'), 'webyaz_qa', 'normal', 'high');
    }

    public function meta_box_html($post) {
        $product_id = get_post_meta($post->ID, '_webyaz_qa_product', true);
        $author = get_post_meta($post->ID, '_webyaz_qa_author', true);
        $email = get_post_meta($post->ID, '_webyaz_qa_email', true);
        $answer = get_post_meta($post->ID, '_webyaz_qa_answer', true);
        $product_name = '';
        if ($product_id && function_exists('wc_get_product')) {
            $p = wc_get_product($product_id);
            if ($p) $product_name = $p->get_name();
        }
        wp_nonce_field('webyaz_qa_nonce', 'webyaz_qa_nonce_field');
        ?>
        <table class="form-table">
            <tr><th>Urun</th><td><strong><?php echo esc_html($product_name); ?></strong> (ID: <?php echo $product_id; ?>)</td></tr>
            <tr><th>Soran</th><td><?php echo esc_html($author); ?> (<?php echo esc_html($email); ?>)</td></tr>
            <tr><th>Soru</th><td><em><?php echo esc_html($post->post_title); ?></em></td></tr>
            <tr>
                <th>Cevap</th>
                <td><textarea name="webyaz_qa_answer" rows="5" style="width:100%;font-size:14px;"><?php echo esc_textarea($answer); ?></textarea></td>
            </tr>
        </table>
        <?php
    }

    public function save_answer($post_id) {
        if (!isset($_POST['webyaz_qa_nonce_field']) || !wp_verify_nonce($_POST['webyaz_qa_nonce_field'], 'webyaz_qa_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (isset($_POST['webyaz_qa_answer'])) {
            update_post_meta($post_id, '_webyaz_qa_answer', wp_kses_post($_POST['webyaz_qa_answer']));
        }
    }
}

new Webyaz_QA();
