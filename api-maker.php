<?php

/**
 * @wordpress-plugin
 * Plugin Name:       API Maker
 * Plugin URI:        https://sirvelia.com/
 * Description:       Create REST API endpoints easily from the admin dashboard.
 * Version:           1.0.0
 * Author:            Sirvelia
 * Author URI:        https://sirvelia.com/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       api-maker
 * Domain Path:       /languages
 */

if (!defined('WPINC')) {
    die('YOU SHALL NOT PASS!');
}

// PLUGIN CONSTANTS
define('APIMAKER_NAME', 'api-maker');
define('APIMAKER_VERSION', '1.0.0');
define('APIMAKER_PATH', plugin_dir_path(__FILE__));
define('APIMAKER_BASENAME', plugin_basename(__FILE__));
define('APIMAKER_URL', plugin_dir_url(__FILE__));

// AUTOLOAD
if (file_exists(APIMAKER_PATH . 'vendor/autoload.php')) {
    require_once APIMAKER_PATH . 'vendor/autoload.php';
}

// LYFECYCLE
register_activation_hook(__FILE__, [ApiMaker\Includes\Lyfecycle::class, 'activate']);
register_deactivation_hook(__FILE__, [ApiMaker\Includes\Lyfecycle::class, 'deactivate']);
register_uninstall_hook(__FILE__, [ApiMaker\Includes\Lyfecycle::class, 'uninstall']);

// LOAD ALL FILES
$loader = new ApiMaker\Includes\Loader();
