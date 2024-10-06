<?php

namespace ApiMaker\Components;

use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;

class PhpValidator
{

    public static function validate_php($code)
    {
        $parser = (new ParserFactory)->createForVersion(PhpVersion::getHostVersion());
        try {
            $ast = $parser->parse('<?php ' . $code);
            return self::check_dangerous_functions($ast);
        } catch (Error $e) {
            return new \WP_Error('invalid_syntax', 'Syntax error: ' . $e->getMessage());
        }
    }

    public static function get_dangerous_functions()
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

    public static function check_dangerous_functions($ast)
    {
        $dangerous_functions = self::get_dangerous_functions();

        $traverser = new NodeTraverser();

        $traverser->addVisitor(new class($dangerous_functions) extends NodeVisitorAbstract {
            private $dangerous_functions;
            private $found_dangerous_function;

            public function __construct($dangerous_functions)
            {
                $this->dangerous_functions = $dangerous_functions;
            }

            public function enterNode(\PhpParser\Node $node)
            {
                if ($node instanceof \PhpParser\Node\Expr\FuncCall && $node->name instanceof \PhpParser\Node\Name) {
                    $function_name = (string) $node->name;
                    if (in_array($function_name, $this->dangerous_functions)) {
                        $this->found_dangerous_function = $function_name;
                    }
                }

                // Check for JavaScript/HTML (e.g., script/iframe tags in echo, print, eval statements)
                if (
                    $node instanceof \PhpParser\Node\Expr\Eval_
                    || $node instanceof \PhpParser\Node\Expr\Print_
                    || $node instanceof \PhpParser\Node\Expr\FuncCall
                ) {

                    if ($node->expr instanceof \PhpParser\Node\Scalar\String_) {
                        $value = $node->expr->value;

                        // Check for JavaScript/HTML tags within the string
                        if (
                            preg_match('/<script\b[^>]*>(.*?)<\/script>/is', $value)
                            || preg_match('/<iframe\b[^>]*>(.*?)<\/iframe>/is', $value)
                        ) {
                            $this->found_dangerous_function = 'JavaScript or iframe detected in code.';
                            return;
                        }
                    }
                }

                // Special handling for Echo_ statement (exprs is an array)
                if ($node instanceof \PhpParser\Node\Stmt\Echo_) {
                    foreach ($node->exprs as $expr) {
                        if ($expr instanceof \PhpParser\Node\Scalar\String_) {
                            $value = $expr->value;

                            // Check for JavaScript/HTML tags within the string
                            if (
                                preg_match('/<script\b[^>]*>(.*?)<\/script>/is', $value)
                                || preg_match('/<iframe\b[^>]*>(.*?)<\/iframe>/is', $value)
                            ) {
                                $this->found_dangerous_function = 'JavaScript or iframe detected in code.';
                                return;
                            }
                        }
                    }
                }
            }

            public function afterTraverse(array $nodes)
            {
                if ($this->found_dangerous_function) {
                    throw new \Exception(esc_html('Disallowed function used: ' . $this->found_dangerous_function));
                }
            }
        });

        try {
            $traverser->traverse($ast);
        } catch (\Exception $e) {
            return new \WP_Error('dangerous_function', $e->getMessage());
        }
    }
}
