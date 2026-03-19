<?php
if (!defined('ABSPATH')) {
	exit;
}

class Size_Agent_Plugin {
	protected static $instance = null;

	public static function instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate() {
		if (!class_exists('Size_Agent_Settings')) {
			require_once SIZE_AGENT_PLUGIN_DIR . 'includes/class-size-agent-settings.php';
		}

		if (!get_option(Size_Agent_Settings::OPTION_KEY)) {
			add_option(
				Size_Agent_Settings::OPTION_KEY,
				array(
					'widget_script_url'      => '',
					'api_base_url'           => '',
					'product_page_injection' => 0,
					'cleanup_uninstall'      => 0,
				)
			);
		}
	}

	protected function __construct() {
		$this->includes();
		$this->init_components();
	}

	protected function includes() {
		require_once SIZE_AGENT_PLUGIN_DIR . 'includes/class-size-agent-settings.php';
		require_once SIZE_AGENT_PLUGIN_DIR . 'includes/class-size-agent-frontend.php';
		require_once SIZE_AGENT_PLUGIN_DIR . 'includes/class-size-agent-shortcode.php';
	}

	protected function init_components() {
		$settings = new Size_Agent_Settings();
		$settings->init();

		$shortcode = new Size_Agent_Shortcode();
		$shortcode->init();

		$frontend = new Size_Agent_Frontend();
		$frontend->init();
	}
}
