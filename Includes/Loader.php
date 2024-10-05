<?php

namespace ApiMaker\Includes;

class Loader
{
    public function __construct()
    {
        $this->loadDependencies();

        add_action('plugins_loaded', [$this, 'loadPluginTextdomain']);
    }

    private function loadDependencies()
    {
        //FUNCTIONALITY CLASSES
        foreach (glob(APIMAKER_PATH . 'Functionality/*.php') as $filename) {
            $class_name = '\\ApiMaker\Functionality\\' . basename($filename, '.php');
            if (class_exists($class_name)) {
                try {
                    new $class_name(APIMAKER_NAME, APIMAKER_VERSION);
                } catch (\Throwable $e) {
                    pb_log($e);
                    continue;
                }
            }
        }
    }

    public function loadPluginTextdomain()
    {
        load_plugin_textdomain('api-maker', false, dirname(APIMAKER_BASENAME) . '/languages/');
    }
}
