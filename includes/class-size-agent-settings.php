<?php
if (!defined('ABSPATH')) {
	exit;
}

class Size_Agent_Settings {
	const OPTION_KEY = 'size_agent_settings';
	const PAGE_SLUG  = 'size-agent';

	public function init() {
		add_action('admin_menu', array($this, 'add_settings_page'));
		add_action('admin_init', array($this, 'register_settings'));
	}

	public static function get_settings() {
		$defaults = array(
			'widget_script_url'     => '',
			'api_base_url'          => '',
			'product_page_injection'=> 0,
			'cleanup_uninstall'     => 0,
		);

		$saved = get_option(self::OPTION_KEY, array());

		if (!is_array($saved)) {
			$saved = array();
		}

		return wp_parse_args($saved, $defaults);
	}

	public function add_settings_page() {
		add_options_page(
			__('Size Agent', 'size-agent'),
			__('Size Agent', 'size-agent'),
			'manage_options',
			self::PAGE_SLUG,
			array($this, 'render_settings_page')
		);
	}

	public function register_settings() {
		register_setting(
			'size_agent_settings_group',
			self::OPTION_KEY,
			array($this, 'sanitize_settings')
		);

		add_settings_section(
			'size_agent_main_section',
			__('External Widget Settings', 'size-agent'),
			array($this, 'render_section_description'),
			self::PAGE_SLUG
		);

		add_settings_field(
			'widget_script_url',
			__('Widget Script URL', 'size-agent'),
			array($this, 'render_widget_script_url_field'),
			self::PAGE_SLUG,
			'size_agent_main_section'
		);

		add_settings_field(
			'api_base_url',
			__('External API Base URL', 'size-agent'),
			array($this, 'render_api_base_url_field'),
			self::PAGE_SLUG,
			'size_agent_main_section'
		);

		add_settings_field(
			'product_page_injection',
			__('Enable on Product Pages', 'size-agent'),
			array($this, 'render_product_page_injection_field'),
			self::PAGE_SLUG,
			'size_agent_main_section'
		);

		add_settings_field(
			'cleanup_uninstall',
			__('Cleanup on Uninstall', 'size-agent'),
			array($this, 'render_cleanup_uninstall_field'),
			self::PAGE_SLUG,
			'size_agent_main_section'
		);
	}

	public function sanitize_settings($input) {
		$current = self::get_settings();

		$output = array(
			'widget_script_url'      => '',
			'api_base_url'           => '',
			'product_page_injection' => 0,
			'cleanup_uninstall'      => 0,
		);

		if (isset($input['widget_script_url'])) {
			$output['widget_script_url'] = esc_url_raw(trim((string) $input['widget_script_url']));
		}

		if (isset($input['api_base_url'])) {
			$output['api_base_url'] = esc_url_raw(trim((string) $input['api_base_url']));
		}

		$output['product_page_injection'] = !empty($input['product_page_injection']) ? 1 : 0;
		$output['cleanup_uninstall']      = !empty($input['cleanup_uninstall']) ? 1 : 0;

		if (!empty($input['widget_script_url']) && empty($output['widget_script_url'])) {
			add_settings_error(
				self::OPTION_KEY,
				'invalid_widget_script_url',
				__('Please enter a valid Widget Script URL.', 'size-agent')
			);
			$output['widget_script_url'] = $current['widget_script_url'];
		}

		if (!empty($input['api_base_url']) && empty($output['api_base_url'])) {
			add_settings_error(
				self::OPTION_KEY,
				'invalid_api_base_url',
				__('Please enter a valid External API Base URL.', 'size-agent')
			);
			$output['api_base_url'] = $current['api_base_url'];
		}

		return $output;
	}

	public function render_section_description() {
		echo '<p>' . esc_html__('This plugin is a thin wrapper for your external Nursing Shoes size widget and API.', 'size-agent') . '</p>';
	}

	public function render_widget_script_url_field() {
		$settings = self::get_settings();
		?>
		<input
			type="url"
			class="regular-text"
			name="<?php echo esc_attr(self::OPTION_KEY); ?>[widget_script_url]"
			value="<?php echo esc_attr($settings['widget_script_url']); ?>"
			placeholder="https://nursing-shoes-size-agent.vercel.app/size-finder.js"
		/>
		<p class="description">
			<?php esc_html_e('Public URL to the external widget JavaScript file.', 'size-agent'); ?>
		</p>
		<?php
	}

	public function render_api_base_url_field() {
		$settings = self::get_settings();
		?>
		<input
			type="url"
			class="regular-text"
			name="<?php echo esc_attr(self::OPTION_KEY); ?>[api_base_url]"
			value="<?php echo esc_attr($settings['api_base_url']); ?>"
			placeholder="https://nursing-shoes-size-agent.vercel.app"
		/>
		<p class="description">
			<?php esc_html_e('Base URL for the external size recommendation API.', 'size-agent'); ?>
		</p>
		<?php
	}

	public function render_product_page_injection_field() {
		$settings = self::get_settings();
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr(self::OPTION_KEY); ?>[product_page_injection]"
				value="1"
				<?php checked(1, (int) $settings['product_page_injection']); ?>
			/>
			<?php esc_html_e('Automatically inject the widget container on WooCommerce single product pages.', 'size-agent'); ?>
		</label>
		<?php
	}

	public function render_cleanup_uninstall_field() {
		$settings = self::get_settings();
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr(self::OPTION_KEY); ?>[cleanup_uninstall]"
				value="1"
				<?php checked(1, (int) $settings['cleanup_uninstall']); ?>
			/>
			<?php esc_html_e('Delete plugin settings when the plugin is uninstalled.', 'size-agent'); ?>
		</label>
		<?php
	}

	public function render_settings_page() {
		if (!current_user_can('manage_options')) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Size Agent Settings', 'size-agent'); ?></h1>

			<?php settings_errors(self::OPTION_KEY); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields('size_agent_settings_group');
				do_settings_sections(self::PAGE_SLUG);
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
