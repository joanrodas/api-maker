<?php

namespace ApiMaker\Includes;

class Lyfecycle
{
    public static function activate($network_wide)
    {
        do_action('ApiMaker/setup', $network_wide);
    }

    public static function deactivate($network_wide)
    {
        do_action('ApiMaker/deactivation', $network_wide);
    }

    public static function uninstall()
    {
        do_action('ApiMaker/cleanup');
    }
}
