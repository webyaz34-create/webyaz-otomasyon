<?php
if (!defined('ABSPATH')) exit;
if (class_exists('Webyaz_Backup_Export')) return;

class Webyaz_Backup_Export
{

    /**
     * Adim adim yedekleme - her adim ayri AJAX cagrisi ile calisir
     * step: init, database, siteinfo, themes, plugins, uploads, rootfiles, zip, cleanup
     */
    public static function run_step($step, $context = array())
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        switch ($step) {
            case 'init':
                return self::step_init();
            case 'database':
                return self::step_database($context);
            case 'siteinfo':
                return self::step_siteinfo($context);
            case 'themes':
                return self::step_copy_dir($context, 'themes', WP_CONTENT_DIR . '/themes/');
            case 'plugins':
                return self::step_copy_dir($context, 'plugins', WP_CONTENT_DIR . '/plugins/');
            case 'uploads':
                return self::step_copy_dir($context, 'uploads', WP_CONTENT_DIR . '/uploads/');
            case 'rootfiles':
                return self::step_rootfiles($context);
            case 'zip':
                return self::step_zip($context);
            case 'cleanup':
                return self::step_cleanup($context);
            default:
                return new WP_Error('invalid_step', 'Gecersiz adim: ' . $step);
        }
    }

    private static function step_init()
    {
        $timestamp = date('Y-m-d_H-i-s');
        $site_name = sanitize_file_name(get_bloginfo('name'));
        $backup_name = 'webyaz-backup-' . $site_name . '-' . $timestamp;
        $temp_dir = WBAK_BACKUP_DIR . $backup_name . '/';

        if (!wp_mkdir_p($temp_dir)) {
            return new WP_Error('dir_fail', 'Gecici klasor olusturulamadi.');
        }

        wp_mkdir_p($temp_dir . 'files/');
        wp_mkdir_p($temp_dir . 'files/root/');

        return array(
            'status' => 'ok',
            'context' => array(
                'backup_name' => $backup_name,
                'temp_dir' => $temp_dir,
            ),
        );
    }

    private static function step_database($ctx)
    {
        $temp_dir = $ctx['temp_dir'];
        global $wpdb;

        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        if (empty($tables)) return new WP_Error('no_tables', 'Veritabaninda tablo bulunamadi.');

        $sql = "-- Webyaz Backup Database Dump\n";
        $sql .= "-- Date: " . current_time('mysql') . "\n";
        $sql .= "-- Site: " . site_url() . "\n\n";
        $sql .= "SET NAMES 'utf8mb4';\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        $table_count = count($tables);
        foreach ($tables as $table) {
            $table_name = $table[0];
            $create = $wpdb->get_row("SHOW CREATE TABLE `{$table_name}`", ARRAY_N);
            if (!$create) continue;

            $sql .= "DROP TABLE IF EXISTS `{$table_name}`;\n";
            $sql .= $create[1] . ";\n\n";

            $rows = $wpdb->get_results("SELECT * FROM `{$table_name}`", ARRAY_A);
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $col_list = '`' . implode('`,`', $columns) . '`';
                $batch = array();
                $batch_count = 0;
                foreach ($rows as $row) {
                    $values = array();
                    foreach ($row as $val) {
                        if ($val === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $wpdb->_real_escape($val) . "'";
                        }
                    }
                    $batch[] = '(' . implode(',', $values) . ')';
                    $batch_count++;
                    if ($batch_count >= 100) {
                        $sql .= "INSERT INTO `{$table_name}` ({$col_list}) VALUES\n" . implode(",\n", $batch) . ";\n";
                        $batch = array();
                        $batch_count = 0;
                    }
                }
                if (!empty($batch)) {
                    $sql .= "INSERT INTO `{$table_name}` ({$col_list}) VALUES\n" . implode(",\n", $batch) . ";\n";
                }
                $sql .= "\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        file_put_contents($temp_dir . 'database.sql', $sql);

        return array(
            'status' => 'ok',
            'detail' => $table_count . ' tablo yedeklendi',
            'context' => $ctx,
        );
    }

    private static function step_siteinfo($ctx)
    {
        $temp_dir = $ctx['temp_dir'];
        $site_info = array(
            'site_url' => site_url(),
            'home_url' => home_url(),
            'wp_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'backup_date' => current_time('mysql'),
            'db_prefix' => $GLOBALS['wpdb']->prefix,
            'charset' => get_bloginfo('charset'),
            'plugin_version' => WBAK_VERSION,
            'abspath' => ABSPATH,
            'content_dir' => WP_CONTENT_DIR,
        );
        file_put_contents($temp_dir . 'site-info.json', json_encode($site_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return array(
            'status' => 'ok',
            'detail' => 'Site bilgileri kaydedildi',
            'context' => $ctx,
        );
    }

    private static function step_copy_dir($ctx, $name, $source_path)
    {
        $temp_dir = $ctx['temp_dir'];
        $dest = $temp_dir . 'files/' . $name . '/';

        if (!is_dir($source_path)) {
            return array(
                'status' => 'ok',
                'detail' => ucfirst($name) . ' klasoru bulunamadi, atlandi',
                'context' => $ctx,
            );
        }

        self::copy_dir($source_path, $dest);
        $count = self::count_files($dest);

        return array(
            'status' => 'ok',
            'detail' => ucfirst($name) . ': ' . $count . ' dosya kopyalandi',
            'context' => $ctx,
        );
    }

    private static function step_rootfiles($ctx)
    {
        $temp_dir = $ctx['temp_dir'];
        $files_dir = $temp_dir . 'files/root/';
        $root_files = array('wp-config.php', '.htaccess');
        $copied = 0;
        foreach ($root_files as $rf) {
            $src = ABSPATH . $rf;
            if (file_exists($src)) {
                copy($src, $files_dir . $rf);
                $copied++;
            }
        }

        // mu-plugins
        $mu_src = WP_CONTENT_DIR . '/mu-plugins/';
        if (is_dir($mu_src)) {
            $mu_dest = $temp_dir . 'files/mu-plugins/';
            self::copy_dir($mu_src, $mu_dest);
        }

        return array(
            'status' => 'ok',
            'detail' => $copied . ' config dosyasi + mu-plugins kopyalandi',
            'context' => $ctx,
        );
    }

    private static function step_zip($ctx)
    {
        $temp_dir = $ctx['temp_dir'];
        $backup_name = $ctx['backup_name'];
        $zip_file = WBAK_BACKUP_DIR . $backup_name . '.wbak';

        if (!class_exists('ZipArchive')) {
            return new WP_Error('no_zip', 'ZipArchive destegi bulunamadi.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return new WP_Error('zip_fail', 'ZIP dosyasi olusturulamadi.');
        }

        $source = realpath($temp_dir);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $file_count = 0;
        foreach ($iterator as $item) {
            $relative = str_replace($source . DIRECTORY_SEPARATOR, '', $item->getRealPath());
            $relative = str_replace('\\', '/', $relative);
            if ($item->isDir()) {
                $zip->addEmptyDir($relative);
            } else {
                $zip->addFile($item->getRealPath(), $relative);
                $file_count++;
            }
        }

        $zip->close();

        $size = filesize($zip_file);
        $ctx['zip_file'] = $zip_file;
        $ctx['zip_size'] = $size;

        return array(
            'status' => 'ok',
            'detail' => $file_count . ' dosya arsivlendi (' . self::format_size($size) . ')',
            'context' => $ctx,
        );
    }

    private static function step_cleanup($ctx)
    {
        $temp_dir = $ctx['temp_dir'];
        self::cleanup($temp_dir);

        return array(
            'status' => 'done',
            'detail' => 'Yedek tamamlandi!',
            'context' => $ctx,
            'result' => array(
                'file' => $ctx['zip_file'],
                'name' => $ctx['backup_name'] . '.wbak',
                'size' => $ctx['zip_size'],
            ),
        );
    }

    // ---- Eski tek-seferde yedek (geriye uyumluluk) ----
    public static function create_backup()
    {
        $init = self::run_step('init');
        if (is_wp_error($init)) return $init;
        $ctx = $init['context'];

        $db = self::run_step('database', $ctx);
        if (is_wp_error($db)) return $db;

        self::run_step('siteinfo', $ctx);
        self::run_step('themes', $ctx);
        self::run_step('plugins', $ctx);
        self::run_step('uploads', $ctx);
        self::run_step('rootfiles', $ctx);

        $zip = self::run_step('zip', $ctx);
        if (is_wp_error($zip)) return $zip;
        $ctx = $zip['context'];

        self::run_step('cleanup', $ctx);

        return array(
            'file' => $ctx['zip_file'],
            'name' => $ctx['backup_name'] . '.wbak',
            'size' => $ctx['zip_size'],
        );
    }

    // ---- Yardimci fonksiyonlar ----
    private static function copy_dir($src, $dst)
    {
        if (!is_dir($src)) return;
        wp_mkdir_p($dst);
        $skip = array('.', '..', 'webyaz-backups', 'cache', 'upgrade', 'ai1wm-backups', 'updraft', 'backwpup');
        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if (in_array($file, $skip)) continue;
            $s = $src . '/' . $file;
            $d = $dst . '/' . $file;
            if (is_dir($s)) {
                self::copy_dir($s, $d);
            } else {
                if (filesize($s) > 100 * 1024 * 1024) continue;
                @copy($s, $d);
            }
        }
        closedir($dir);
    }

    private static function count_files($dir)
    {
        if (!is_dir($dir)) return 0;
        $count = 0;
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iter as $f) {
            if ($f->isFile()) $count++;
        }
        return $count;
    }

    private static function cleanup($dir)
    {
        if (!is_dir($dir)) return;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $f) {
            if ($f->isDir()) @rmdir($f->getRealPath());
            else @unlink($f->getRealPath());
        }
        @rmdir($dir);
    }

    private static function format_size($bytes)
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }
}
