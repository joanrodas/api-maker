<?php

namespace ApiMaker\Components;

class PhpValidator
{
    public static function get_forbidden_functions()
    {
        return [
            // Code execution
            'eval',
            'exec',
            'system',
            'passthru',
            'shell_exec',
            'proc_open',
            'popen',
            'pcntl_exec',

            // File operations (use WP functions instead)
            'file_put_contents',
            'file_get_contents',
            'fopen',
            'fwrite',
            'file',
            'fputcsv',
            'fputs',

            // Network-related (use wp_remote_* functions instead)
            'curl_exec',
            'curl_multi_exec',

            // Information disclosure
            'phpinfo',
            'posix_mkfifo',
            'posix_getlogin',
            'posix_ttyname',
            'getenv',
            'get_current_user',
            'proc_get_status',
            'get_cfg_var',
            'disk_free_space',
            'disk_total_space',
            'diskfreespace',
            'getcwd',
            'getlastmo',
            'getmygid',
            'getmyinode',
            'getmypid',
            'getmyuid',

            // File system operations (use WP functions instead)
            'chgrp',
            'chmod',
            'chown',
            'copy',
            'link',
            'mkdir',
            'rename',
            'rmdir',
            'symlink',
            'tempnam',
            'touch',
            'unlink',
            'parse_ini_file',
            'show_source',

            // Database (use $wpdb methods, prepare statements)
            'mysqli_query',
            'mysqli_real_query',
            'mysqli_multi_query',
            'mysql_query',
            'pg_query',
            'pg_send_query',

            // Miscellaneous
            'set_time_limit',
            'ini_set',
            'mail',  // Use wp_mail() instead
            'proc_nice',
            'proc_terminate',
            'proc_close',
            'pfsockopen',
            'fsockopen',
            'apache_child_terminate',
            'posix_kill',
            'posix_setpgid',
            'posix_setsid',
            'posix_setuid',

            // Extensions
            'dl',

            // Encryption (use WP functions or modern alternatives)
            'mcrypt_encrypt',
            'mcrypt_decrypt',

            // Assertions
            'assert'
        ];
    }
}
