<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Backup_Notice
{

    public function __construct()
    {
        add_action('admin_notices', array($this, 'show_notice'));
    }

    public function show_notice()
    {
        if (!current_user_can('manage_options')) return;
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'webyaz') === false) return;

        // Entegre Webyaz Backup modulu aktifse uyari gosterme
        if (get_option('webyaz_mod_backup', '0') === '1') return;

        $backup_plugins = array(
            'updraftplus/updraftplus.php',
            'backwpup/backwpup.php',
            'duplicator/duplicator.php',
            'all-in-one-wp-migration/all-in-one-wp-migration.php',
            'webyaz-backup/webyaz-backup.php',
        );
        $has_backup = false;
        foreach ($backup_plugins as $p) {
            if (is_plugin_active($p)) {
                $has_backup = true;
                break;
            }
        }

        if (!$has_backup) {
            echo '<div class="notice notice-warning" style="border-left-color:#ff9800;padding:12px;font-family:Roboto,sans-serif;">';
            echo '<strong style="color:#e65100;">Yedekleme Uyarisi:</strong> Sitenizde aktif bir yedekleme eklentisi bulunamadi. ';
            echo 'Veri kaybi yasamamak icin UpdraftPlus, BackWPup veya benzeri bir eklenti yukleyin.';
            echo '</div>';
        }
    }
}

new Webyaz_Backup_Notice();
