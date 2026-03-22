<?php
if (!defined('ABSPATH')) exit;
if (class_exists('Webyaz_Backup_Import')) return;

class Webyaz_Backup_Import
{

    /**
     * Adim adim geri yukleme - her adim ayri AJAX cagrisi ile calisir
     * step: extract, database, files, search_replace, admin, cleanup
     */
    public static function run_restore_step($step, $context = array())
    {
        set_time_limit(900);
        ini_set('memory_limit', '512M');

        switch ($step) {
            case 'extract':
                return self::step_extract($context);
            case 'database':
                return self::step_database_restore($context);
            case 'files':
                return self::step_files_restore($context);
            case 'search_replace':
                return self::step_search_replace($context);
            case 'admin':
                return self::step_create_admin($context);
            case 'cleanup':
                return self::step_cleanup_restore($context);
            default:
                return new WP_Error('invalid_step', 'Gecersiz adim: ' . $step);
        }
    }

    private static function step_extract($ctx)
    {
        $file_path = $ctx['file_path'];
        if (!file_exists($file_path)) {
            return new WP_Error('no_file', 'Yedek dosyasi bulunamadi: ' . $file_path);
        }

        $temp_dir = WBAK_BACKUP_DIR . 'restore-' . time() . '/';
        wp_mkdir_p($temp_dir);

        $extract = self::extract_zip($file_path, $temp_dir);
        if (is_wp_error($extract)) {
            self::cleanup($temp_dir);
            return $extract;
        }

        $info_file = $temp_dir . 'site-info.json';
        if (!file_exists($info_file)) {
            self::cleanup($temp_dir);
            return new WP_Error('invalid', 'Gecersiz yedek dosyasi - site-info.json bulunamadi.');
        }
        $old_info = json_decode(file_get_contents($info_file), true);

        $ctx['temp_dir'] = $temp_dir;
        $ctx['old_site_url'] = $old_info['site_url'];
        $ctx['old_home_url'] = $old_info['home_url'];
        $ctx['old_prefix'] = $old_info['db_prefix'];
        $ctx['new_site_url'] = site_url();
        $ctx['new_home_url'] = home_url();

        return array(
            'status' => 'ok',
            'detail' => 'Yedek dosyasi basariyla acildi',
            'context' => $ctx,
        );
    }

    private static function step_database_restore($ctx)
    {
        $temp_dir = $ctx['temp_dir'];
        $db_file = $temp_dir . 'database.sql';

        if (!file_exists($db_file)) {
            return array(
                'status' => 'ok',
                'detail' => 'Veritabani dosyasi bulunamadi, atlandi',
                'context' => $ctx,
            );
        }

        $db_result = self::import_database(
            $db_file,
            $ctx['old_site_url'],
            $ctx['new_site_url'],
            $ctx['old_home_url'],
            $ctx['new_home_url'],
            $ctx['old_prefix']
        );

        if (is_wp_error($db_result)) {
            self::cleanup($temp_dir);
            return $db_result;
        }

        return array(
            'status' => 'ok',
            'detail' => 'Veritabani basariyla geri yuklendi',
            'context' => $ctx,
        );
    }

    private static function step_files_restore($ctx)
    {
        $temp_dir = $ctx['temp_dir'];
        $files_dir = $temp_dir . 'files/';

        if (!is_dir($files_dir)) {
            return array(
                'status' => 'ok',
                'detail' => 'Dosya klasoru bulunamadi, atlandi',
                'context' => $ctx,
            );
        }

        $files_result = self::restore_files($files_dir);
        if (is_wp_error($files_result)) {
            return $files_result;
        }

        return array(
            'status' => 'ok',
            'detail' => 'Dosyalar basariyla geri yuklendi (temalar, eklentiler, medya)',
            'context' => $ctx,
        );
    }

    private static function step_search_replace($ctx)
    {
        $old_site = $ctx['old_site_url'];
        $new_site = $ctx['new_site_url'];
        $old_home = $ctx['old_home_url'];
        $new_home = $ctx['new_home_url'];
        $replaced = 0;

        if ($old_site !== $new_site || $old_home !== $new_home) {
            self::search_replace_db($old_site, $new_site);
            self::search_replace_db($old_home, $new_home);
            $replaced += 2;

            $old_path = parse_url($old_site, PHP_URL_PATH) ?: '';
            $new_path = parse_url($new_site, PHP_URL_PATH) ?: '';
            if ($old_path !== $new_path) {
                self::search_replace_db($old_path, $new_path);
                $replaced++;
            }

            $old_domain = parse_url($old_site, PHP_URL_HOST);
            $new_domain = parse_url($new_site, PHP_URL_HOST);
            if ($old_domain !== $new_domain) {
                self::search_replace_db($old_domain, $new_domain);
                $replaced++;
            }
        }

        $detail = $replaced > 0
            ? $replaced . ' URL degisikligi yapildi (' . $old_site . ' → ' . $new_site . ')'
            : 'URL degisikligi gerekmedi (ayni site)';

        return array(
            'status' => 'ok',
            'detail' => $detail,
            'context' => $ctx,
        );
    }

    private static function step_create_admin($ctx)
    {
        $detail = 'Admin hesabi islemi atlandi';

        if (!empty($ctx['new_admin_user']) && !empty($ctx['new_admin_pass'])) {
            $email = !empty($ctx['new_admin_email']) ? $ctx['new_admin_email'] : '';
            self::create_admin_user($ctx['new_admin_user'], $ctx['new_admin_pass'], $email);
            $detail = 'Yeni admin hesabi olusturuldu: ' . $ctx['new_admin_user'];
        }

        self::fix_permalinks();

        return array(
            'status' => 'ok',
            'detail' => $detail,
            'context' => $ctx,
        );
    }

    private static function step_cleanup_restore($ctx)
    {
        $temp_dir = $ctx['temp_dir'];
        self::cleanup($temp_dir);

        return array(
            'status' => 'done',
            'detail' => 'Geri yukleme basariyla tamamlandi!',
            'context' => $ctx,
        );
    }


    public static function restore_backup($file_path, $options = array())
    {
        set_time_limit(900);
        ini_set('memory_limit', '512M');

        $defaults = array(
            'new_admin_user' => '',
            'new_admin_pass' => '',
            'new_admin_email' => '',
        );
        $options = wp_parse_args($options, $defaults);

        if (!file_exists($file_path)) {
            return new WP_Error('no_file', 'Yedek dosyasi bulunamadi: ' . $file_path);
        }

        $temp_dir = WBAK_BACKUP_DIR . 'restore-' . time() . '/';
        wp_mkdir_p($temp_dir);

        $extract = self::extract_zip($file_path, $temp_dir);
        if (is_wp_error($extract)) {
            self::cleanup($temp_dir);
            return $extract;
        }

        $info_file = $temp_dir . 'site-info.json';
        if (!file_exists($info_file)) {
            self::cleanup($temp_dir);
            return new WP_Error('invalid', 'Gecersiz yedek dosyasi - site-info.json bulunamadi.');
        }
        $old_info = json_decode(file_get_contents($info_file), true);

        $new_site_url = site_url();
        $new_home_url = home_url();
        $old_site_url = $old_info['site_url'];
        $old_home_url = $old_info['home_url'];
        $old_prefix = $old_info['db_prefix'];

        $db_file = $temp_dir . 'database.sql';
        if (file_exists($db_file)) {
            $db_result = self::import_database($db_file, $old_site_url, $new_site_url, $old_home_url, $new_home_url, $old_prefix);
            if (is_wp_error($db_result)) {
                self::cleanup($temp_dir);
                return $db_result;
            }
        }

        $files_dir = $temp_dir . 'files/';
        if (is_dir($files_dir)) {
            $files_result = self::restore_files($files_dir);
            if (is_wp_error($files_result)) {
                self::cleanup($temp_dir);
                return $files_result;
            }
        }

        if ($old_site_url !== $new_site_url || $old_home_url !== $new_home_url) {
            self::search_replace_db($old_site_url, $new_site_url);
            self::search_replace_db($old_home_url, $new_home_url);

            $old_path = parse_url($old_site_url, PHP_URL_PATH) ?: '';
            $new_path = parse_url($new_site_url, PHP_URL_PATH) ?: '';
            if ($old_path !== $new_path) {
                self::search_replace_db($old_path, $new_path);
            }

            $old_domain = parse_url($old_site_url, PHP_URL_HOST);
            $new_domain = parse_url($new_site_url, PHP_URL_HOST);
            if ($old_domain !== $new_domain) {
                self::search_replace_db($old_domain, $new_domain);
            }
        }

        if (!empty($options['new_admin_user']) && !empty($options['new_admin_pass'])) {
            self::create_admin_user($options['new_admin_user'], $options['new_admin_pass'], $options['new_admin_email']);
        }

        self::fix_permalinks();

        self::cleanup($temp_dir);

        return array('success' => true, 'message' => 'Site basariyla geri yuklendi!');
    }

    private static function extract_zip($file_path, $temp_dir)
    {
        // Oncelik: WordPress unzip_file fonksiyonu
        if (function_exists('WP_Filesystem')) {
            WP_Filesystem();
            $result = unzip_file($file_path, $temp_dir);
            if (is_wp_error($result)) {
                // WordPress basarisiz olursa ZipArchive dene
            } else {
                return true;
            }
        }

        // ZipArchive ile ac
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            $res = $zip->open($file_path);
            if ($res === true) {
                $zip->extractTo($temp_dir);
                $zip->close();
                return true;
            }
            return new WP_Error('zip_error', 'ZIP dosyasi acilamadi. Hata kodu: ' . $res);
        }

        return new WP_Error('no_zip', 'Sunucuda ZIP acma destegi bulunamadi. ZipArchive veya WP_Filesystem gerekli.');
    }

    private static function import_database($sql_file, $old_site, $new_site, $old_home, $new_home, $old_prefix)
    {
        global $wpdb;

        $sql = file_get_contents($sql_file);
        if (empty($sql)) return new WP_Error('empty_sql', 'SQL dosyasi bos.');

        $new_prefix = $wpdb->prefix;
        if ($old_prefix !== $new_prefix) {
            $sql = str_replace(
                array("`{$old_prefix}", "'{$old_prefix}"),
                array("`{$new_prefix}", "'{$new_prefix}"),
                $sql
            );
        }

        $sql = str_replace($old_site, $new_site, $sql);
        $sql = str_replace($old_home, $new_home, $sql);

        $queries = self::split_sql($sql);
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($queries as $query) {
            $query = trim($query);
            if (empty($query) || strpos($query, '--') === 0) continue;
            $wpdb->query($query);
        }

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
        return true;
    }

    private static function split_sql($sql)
    {
        $queries = array();
        $current = '';
        $in_string = false;
        $escape = false;
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];
            if ($escape) {
                $current .= $char;
                $escape = false;
                continue;
            }
            if ($char === '\\') {
                $current .= $char;
                $escape = true;
                continue;
            }
            if ($char === "'" && !$in_string) {
                $in_string = true;
                $current .= $char;
                continue;
            }
            if ($char === "'" && $in_string) {
                $in_string = false;
                $current .= $char;
                continue;
            }
            if ($char === ';' && !$in_string) {
                $queries[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        if (trim($current)) $queries[] = $current;
        return $queries;
    }

    private static function restore_files($files_dir)
    {
        $mappings = array(
            'themes' => WP_CONTENT_DIR . '/themes/',
            'plugins' => WP_CONTENT_DIR . '/plugins/',
            'uploads' => WP_CONTENT_DIR . '/uploads/',
            'mu-plugins' => WP_CONTENT_DIR . '/mu-plugins/',
        );

        foreach ($mappings as $name => $dest) {
            $src = $files_dir . $name . '/';
            if (!is_dir($src)) continue;
            self::copy_dir($src, $dest);
        }

        $root_dir = $files_dir . 'root/';
        if (is_dir($root_dir)) {
            $root_files = array('.htaccess');
            foreach ($root_files as $rf) {
                $src = $root_dir . $rf;
                if (file_exists($src)) {
                    @copy($src, ABSPATH . $rf);
                }
            }
        }

        return true;
    }

    private static function search_replace_db($search, $replace)
    {
        if ($search === $replace || empty($search)) return;
        global $wpdb;

        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        foreach ($tables as $table) {
            $table_name = $table[0];
            $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}`", ARRAY_A);

            foreach ($columns as $col) {
                $col_name = $col['Field'];
                $type = strtolower($col['Type']);
                if (strpos($type, 'int') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false || strpos($type, 'decimal') !== false) continue;

                $wpdb->query($wpdb->prepare(
                    "UPDATE `{$table_name}` SET `{$col_name}` = REPLACE(`{$col_name}`, %s, %s) WHERE `{$col_name}` LIKE %s",
                    $search,
                    $replace,
                    '%' . $wpdb->esc_like($search) . '%'
                ));
            }
        }

        self::fix_serialized_data($search, $replace);
    }

    private static function fix_serialized_data($search, $replace)
    {
        global $wpdb;

        $rows = $wpdb->get_results("SELECT option_id, option_value FROM {$wpdb->options} WHERE option_value LIKE '%{$wpdb->esc_like($search)}%'", ARRAY_A);
        foreach ($rows as $row) {
            $val = $row['option_value'];
            $unserialized = @unserialize($val);
            if ($unserialized !== false) {
                $fixed = self::recursive_replace($unserialized, $search, $replace);
                $new_val = serialize($fixed);
                if ($new_val !== $val) {
                    $wpdb->update($wpdb->options, array('option_value' => $new_val), array('option_id' => $row['option_id']));
                }
            }
        }

        $meta_tables = array($wpdb->postmeta, $wpdb->usermeta, $wpdb->termmeta);
        foreach ($meta_tables as $mt) {
            $rows = $wpdb->get_results("SELECT meta_id, meta_value FROM {$mt} WHERE meta_value LIKE '%{$wpdb->esc_like($search)}%'", ARRAY_A);
            foreach ($rows as $row) {
                $val = $row['meta_value'];
                $unserialized = @unserialize($val);
                if ($unserialized !== false) {
                    $fixed = self::recursive_replace($unserialized, $search, $replace);
                    $new_val = serialize($fixed);
                    if ($new_val !== $val) {
                        $wpdb->update($mt, array('meta_value' => $new_val), array('meta_id' => $row['meta_id']));
                    }
                }
            }
        }
    }

    private static function recursive_replace($data, $search, $replace)
    {
        if (is_string($data)) {
            return str_replace($search, $replace, $data);
        }
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::recursive_replace($value, $search, $replace);
            }
        }
        if (is_object($data)) {
            foreach ($data as $key => $value) {
                $data->$key = self::recursive_replace($value, $search, $replace);
            }
        }
        return $data;
    }

    private static function create_admin_user($username, $password, $email = '')
    {
        global $wpdb;

        if (empty($email)) $email = $username . '@admin.local';

        $existing = get_user_by('login', $username);
        if ($existing) {
            wp_set_password($password, $existing->ID);
            $existing->set_role('administrator');
            return $existing->ID;
        }

        $user_id = wp_insert_user(array(
            'user_login' => $username,
            'user_pass' => $password,
            'user_email' => $email,
            'role' => 'administrator',
            'display_name' => $username,
        ));

        if (!is_wp_error($user_id)) {
            if (is_multisite()) {
                grant_super_admin($user_id);
            }
        }

        return $user_id;
    }

    private static function fix_permalinks()
    {
        global $wpdb;
        $wpdb->update($wpdb->options, array('option_value' => ''), array('option_name' => 'rewrite_rules'));
        flush_rewrite_rules(true);
    }

    private static function copy_dir($src, $dst)
    {
        if (!is_dir($src)) return;
        wp_mkdir_p($dst);
        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;
            $s = $src . '/' . $file;
            $d = $dst . '/' . $file;
            if (is_dir($s)) {
                self::copy_dir($s, $d);
            } else {
                @copy($s, $d);
            }
        }
        closedir($dir);
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

    public static function get_available_backups()
    {
        $backups = array();
        if (!is_dir(WBAK_BACKUP_DIR)) return $backups;

        $files = glob(WBAK_BACKUP_DIR . '*.wbak');
        if ($files) {
            foreach ($files as $file) {
                $backups[] = array(
                    'name' => basename($file),
                    'path' => $file,
                    'size' => filesize($file),
                    'date' => filemtime($file),
                );
            }
            usort($backups, function ($a, $b) {
                return $b['date'] - $a['date'];
            });
        }
        return $backups;
    }
}
