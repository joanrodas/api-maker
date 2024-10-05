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
        foreach (glob(APIMAKER_PATH . 'Functionality/*.php') as $filename) {
            $class_name = '\\ApiMaker\Functionality\\' . basename($filename, '.php');
            if (class_exists($class_name)) {
                try {
                    new $class_name(APIMAKER_NAME, APIMAKER_VERSION);
                } catch (\Throwable $e) {
                    error_log(sprintf('Error loading class %s from file %s: %s', $class_name, $filename, $e->getMessage()));
                    continue;
                }
            } else {
                error_log(sprintf('Class %s not found in file %s', $class_name, $filename));
            }
        }
    }

    public function loadPluginTextdomain()
    {
        load_plugin_textdomain('api-maker', false, dirname(APIMAKER_BASENAME) . '/languages/');
    }
}
