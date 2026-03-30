<?php
/**
 * Plugin Name: Size Agent
 * Description: AI-powered sizing widget for WooCommerce — supports scrubs and footwear.
 * Version: 2.0.0
 * Author: Size Agent
 * Text Domain: size-agent
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIZE_AGENT_VERSION', '2.0.0' );
define( 'SIZE_AGENT_PLUGIN_FILE', __FILE__ );
define( 'SIZE_AGENT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIZE_AGENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// ── GitHub Auto-Updater ──────────────────────────────────────────────────────
// Uses Plugin Update Checker by YahnisElsts.
// Library lives in: lib/plugin-update-checker/
// GitHub repo must be PUBLIC for this to work without a token.
$size_agent_puc = SIZE_AGENT_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
if ( file_exists( $size_agent_puc ) ) {
	require_once $size_agent_puc;

	$size_agent_updater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/scrubdepotcanada-pixel/Wordpress-Size-Agent-Plugin',
		__FILE__,
		'size-agent'
	);

	// Tell the updater to use GitHub Releases (tagged versions)
	$size_agent_updater->getVcsApi()->enableReleaseAssets();
}
// ────────────────────────────────────────────────────────────────────────────

require_once SIZE_AGENT_PLUGIN_DIR . 'includes/class-size-agent-plugin.php';
register_activation_hook( __FILE__, array( 'Size_Agent_Plugin', 'activate' ) );
Size_Agent_Plugin::instance();
