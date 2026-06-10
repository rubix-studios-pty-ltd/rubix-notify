<?php
/**
 * Plugin Name: Rubix Notify
 * Plugin URI: https://wordpress.org/plugins/rubix-notify
 * Description: Send WordPress login alerts to ntfy when users sign in, helping site owners and administrators monitor access activity.
 * Version: 1.0.1
 * Author: Rubix Studios
 * Author URI: https://rubixstudios.com.au
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rubix-notify
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * 
 * @package rubix-notify
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NTFY_FILE', __FILE__);
define('NTFY_PATH', plugin_dir_path(NTFY_FILE));
define('NTFY_URL', plugin_dir_url(NTFY_FILE));

require_once NTFY_PATH . 'includes/class-crypto.php';
require_once NTFY_PATH . 'includes/class-template.php';
require_once NTFY_PATH . 'includes/class-database.php';
require_once NTFY_PATH . 'includes/class-notifier.php';
require_once NTFY_PATH . 'includes/class-rest.php';
require_once NTFY_PATH . 'includes/class-admin.php';

register_activation_hook(
    __FILE__,
    ['Ntfy_Database', 'activate']
);

add_action('plugins_loaded', static function (): void {
    Ntfy_Notifier::init();
    Ntfy_Rest::init();

    if (is_admin()) {
        Ntfy_Admin::init();
    }
});