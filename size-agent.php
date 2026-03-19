<?php
/**
 * Plugin Name: Size Agent
 * Description: Adds a Find My Size widget to WooCommerce product pages and connects to an external sizing API.
 * Version: 1.0.0
 * Author: Size Agent
 * Text Domain: size-agent
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIZE_AGENT_VERSION', '1.0.0' );
define( 'SIZE_AGENT_PLUGIN_FILE', __FILE__ );
define( 'SIZE_AGENT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIZE_AGENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SIZE_AGENT_PLUGIN_DIR . 'includes/class-size-agent-plugin.php';

register_activation_hook( __FILE__, array( 'Size_Agent_Plugin', 'activate' ) );

Size_Agent_Plugin::instance();
