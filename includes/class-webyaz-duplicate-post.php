<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Duplicate_Post {

    public function __construct() {
        add_filter('post_row_actions', array($this, 'add_link'), 10, 2);
        add_filter('page_row_actions', array($this, 'add_link'), 10, 2);
        add_action('admin_action_webyaz_dup_post', array($this, 'duplicate'));
        add_action('admin_notices', array($this, 'notice'));
    }

    public function add_link($actions, $post) {
        if ($post->post_type === 'product') return $actions;
        if (!current_user_can('edit_posts')) return $actions;
        $url = wp_nonce_url(admin_url('admin.php?action=webyaz_dup_post&post=' . $post->ID), 'webyaz_dup_post_' . $post->ID);
        $label = $post->post_type === 'page' ? 'Sayfayi Cogalt' : 'Yaziyi Cogalt';
        $actions['webyaz_dup'] = '<a href="' . esc_url($url) . '" style="color:var(--webyaz-primary,#446084);font-weight:600;">' . $label . '</a>';
        return $actions;
    }

    public function duplicate() {
        if (!isset($_GET['post']) || !isset($_GET['_wpnonce'])) return;
        $id = intval($_GET['post']);
        if (!wp_verify_nonce($_GET['_wpnonce'], 'webyaz_dup_post_' . $id)) return;
        if (!current_user_can('edit_posts')) return;

        $post = get_post($id);
        if (!$post) return;

        $new = array(
            'post_title' => $post->post_title . ' (Kopya)',
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status' => 'draft',
            'post_type' => $post->post_type,
            'post_author' => get_current_user_id(),
            'comment_status' => $post->comment_status,
            'ping_status' => $post->ping_status,
            'menu_order' => $post->menu_order,
            'post_parent' => $post->post_parent,
        );
        $new_id = wp_insert_post($new);
        if (is_wp_error($new_id)) return;

        $meta = get_post_meta($id);
        if ($meta) {
            foreach ($meta as $key => $values) {
                if (in_array($key, array('_edit_lock', '_edit_last', '_wp_old_slug'))) continue;
                foreach ($values as $val) {
                    add_post_meta($new_id, $key, maybe_unserialize($val));
                }
            }
        }

        $taxonomies = get_object_taxonomies($post->post_type);
        foreach ($taxonomies as $tax) {
            $terms = wp_get_object_terms($id, $tax, array('fields' => 'slugs'));
            wp_set_object_terms($new_id, $terms, $tax);
        }

        $thumb_id = get_post_thumbnail_id($id);
        if ($thumb_id) set_post_thumbnail($new_id, $thumb_id);

        $type_label = $post->post_type === 'page' ? 'sayfa' : 'yazi';
        wp_redirect(admin_url('post.php?action=edit&post=' . $new_id . '&webyaz_dup_type=' . $type_label));
        exit;
    }

    public function notice() {
        if (isset($_GET['webyaz_dup_type'])) {
            $type = sanitize_text_field($_GET['webyaz_dup_type']);
            echo '<div class="notice notice-success is-dismissible" style="border-left-color:#446084;"><p style="font-family:Roboto,sans-serif;"><strong>Webyaz:</strong> ' . ucfirst($type) . ' basariyla cogaltildi! Duzenleyip yayinlayabilirsiniz.</p></div>';
        }
    }
}

new Webyaz_Duplicate_Post();
