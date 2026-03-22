<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Photo_Reviews {

    public function __construct() {
        add_action('comment_form_logged_in_after', array($this, 'add_photo_field'));
        add_action('comment_form_after_fields', array($this, 'add_photo_field'));
        add_action('comment_post', array($this, 'save_photo'));
        add_filter('comment_text', array($this, 'display_photo'), 99, 2);
    }

    public function add_photo_field() {
        if (!is_product()) return;
        ?>
        <div class="webyaz-review-photo-field" style="margin:10px 0;">
            <label style="font-family:'Roboto',sans-serif;font-size:14px;font-weight:600;display:block;margin-bottom:6px;">Fotograf Ekle (istege bagli)</label>
            <input type="file" name="webyaz_review_photo" accept="image/*" style="font-size:13px;">
            <small style="display:block;color:#888;margin-top:4px;">Maks 2MB, JPG/PNG/WebP</small>
        </div>
        <?php
        add_action('comment_form', function() {
            echo '<script>document.querySelector("#commentform").setAttribute("enctype","multipart/form-data");</script>';
        });
    }

    public function save_photo($comment_id) {
        if (empty($_FILES['webyaz_review_photo']['tmp_name'])) return;

        $file = $_FILES['webyaz_review_photo'];
        if ($file['size'] > 2 * 1024 * 1024) return;

        $allowed = array('image/jpeg', 'image/png', 'image/webp', 'image/gif');
        if (!in_array($file['type'], $allowed)) return;

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload('webyaz_review_photo', 0);
        if (!is_wp_error($attachment_id)) {
            update_comment_meta($comment_id, '_webyaz_review_photo', $attachment_id);
        }
    }

    public function display_photo($text, $comment = null) {
        if (!$comment) return $text;
        $photo_id = get_comment_meta($comment->comment_ID, '_webyaz_review_photo', true);
        if (!$photo_id) return $text;

        $img = wp_get_attachment_image_url($photo_id, 'medium');
        if (!$img) return $text;

        $full = wp_get_attachment_image_url($photo_id, 'full');
        $text .= '<div class="webyaz-review-photo" style="margin-top:10px;">';
        $text .= '<a href="' . esc_url($full) . '" target="_blank" rel="noopener">';
        $text .= '<img src="' . esc_url($img) . '" alt="Yorum Gorseli" style="max-width:200px;border-radius:8px;border:1px solid #eee;">';
        $text .= '</a></div>';
        return $text;
    }
}

new Webyaz_Photo_Reviews();
