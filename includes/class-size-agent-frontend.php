<?php
if (!defined('ABSPATH')) {
	exit;
}

class Size_Agent_Frontend {
	const SCRIPT_HANDLE        = 'size-agent-widget';
	const LOADER_SCRIPT_HANDLE = 'size-agent-loader';
	const STYLE_HANDLE         = 'size-agent-style';

	public function init() {
		add_action('wp_enqueue_scripts', array($this, 'register_assets'));

		if ($this->should_inject_on_product_pages()) {
			add_action('woocommerce_before_add_to_cart_button', array($this, 'render_product_page_container'));
		}
	}

	protected function get_settings() {
		return Size_Agent_Settings::get_settings();
	}

	protected function get_widget_script_url() {
		$settings = $this->get_settings();
		return !empty($settings['widget_script_url']) ? esc_url_raw($settings['widget_script_url']) : '';
	}

	protected function get_api_base_url() {
		$settings = $this->get_settings();
		return !empty($settings['api_base_url']) ? esc_url_raw($settings['api_base_url']) : '';
	}

	protected function should_inject_on_product_pages() {
		if (!function_exists('is_product')) {
			return false;
		}

		$settings = $this->get_settings();
		return !empty($settings['product_page_injection']);
	}

	public function register_assets() {
		$widget_script_url = $this->get_widget_script_url();

		wp_register_style(
			self::STYLE_HANDLE,
			SIZE_AGENT_PLUGIN_URL . 'assets/css/size-agent.css',
			array(),
			SIZE_AGENT_VERSION
		);

		wp_register_script(
			self::LOADER_SCRIPT_HANDLE,
			SIZE_AGENT_PLUGIN_URL . 'assets/js/size-agent.js',
			array(),
			SIZE_AGENT_VERSION,
			true
		);

		if (empty($widget_script_url)) {
			return;
		}

		wp_register_script(
			self::SCRIPT_HANDLE,
			$widget_script_url,
			array(),
			SIZE_AGENT_VERSION,
			true
		);

		wp_script_add_data(self::SCRIPT_HANDLE, 'defer', true);
		wp_script_add_data(self::LOADER_SCRIPT_HANDLE, 'defer', true);
	}

	protected function enqueue_assets() {
		$widget_script_url = $this->get_widget_script_url();

		if (empty($widget_script_url)) {
			return false;
		}

		wp_enqueue_style(self::STYLE_HANDLE);
		wp_enqueue_script(self::SCRIPT_HANDLE);
		wp_enqueue_script(self::LOADER_SCRIPT_HANDLE);

		return true;
	}

	protected function get_current_product_context($product = null) {
		$data = array(
			'id'        => 0,
			'title'     => '',
			'sku'       => '',
			'permalink' => '',
			'image'     => '',
			'brand'     => '',
		);

		if (function_exists('wc_get_product') && is_numeric($product)) {
			$product = wc_get_product($product);
		}

		if (!$product && function_exists('wc_get_product') && function_exists('is_product') && is_product()) {
			$product = wc_get_product(get_the_ID());
		}

		if (!$product || !is_object($product)) {
			return $data;
		}

		$data['id']        = method_exists($product, 'get_id') ? (int) $product->get_id() : 0;
		$data['title']     = method_exists($product, 'get_name') ? (string) $product->get_name() : '';
		$data['sku']       = method_exists($product, 'get_sku') ? (string) $product->get_sku() : '';
		$data['permalink'] = method_exists($product, 'get_permalink') ? (string) $product->get_permalink() : '';
		$data['image']     = method_exists($product, 'get_image_id') && $product->get_image_id()
			? (string) wp_get_attachment_url($product->get_image_id())
			: '';

		$data['brand'] = $this->detect_brand($data['id']);

		return $data;
	}

	protected function detect_brand($product_id) {
		if (!$product_id) {
			return '';
		}

		$taxonomies_to_try = array('product_brand', 'pa_brand', 'brand');

		foreach ($taxonomies_to_try as $taxonomy) {
			if (!taxonomy_exists($taxonomy)) {
				continue;
			}

			$terms = get_the_terms($product_id, $taxonomy);
			if (is_array($terms) && !empty($terms) && !is_wp_error($terms)) {
				return $terms[0]->name;
			}
		}

		return '';
	}

	protected function build_mount_config($product_context = array()) {
		return array(
			'apiUrl'  => trailingslashit($this->get_api_base_url()) . 'api/size',
			'product' => array(
				'id'        => !empty($product_context['id']) ? (int) $product_context['id'] : 0,
				'title'     => !empty($product_context['title']) ? (string) $product_context['title'] : '',
				'sku'       => !empty($product_context['sku']) ? (string) $product_context['sku'] : '',
				'permalink' => !empty($product_context['permalink']) ? (string) $product_context['permalink'] : '',
				'image'     => !empty($product_context['image']) ? (string) $product_context['image'] : '',
				'brand'     => !empty($product_context['brand']) ? (string) $product_context['brand'] : '',
			),
		);
	}

	protected function render_container($container_id, $product_context = array()) {
		if (!$this->enqueue_assets()) {
			return '';
		}

		$config = $this->build_mount_config($product_context);

		wp_add_inline_script(
			self::LOADER_SCRIPT_HANDLE,
			'window.SizeAgentMountQueue = window.SizeAgentMountQueue || []; window.SizeAgentMountQueue.push(' . wp_json_encode(array(
				'containerId' => $container_id,
				'config'      => $config,
			)) . ');',
			'before'
		);

		return '<div id="' . esc_attr($container_id) . '" class="size-agent-external"></div>';
	}

	public function render_product_page_container() {
		$product_context = $this->get_current_product_context();
		$container_id    = 'size-agent-product-' . (!empty($product_context['id']) ? (int) $product_context['id'] : wp_rand(1000, 999999));

		echo $this->render_container($container_id, $product_context);
	}

	public function get_shortcode_markup($atts = array()) {
		$product_id      = !empty($atts['product_id']) ? absint($atts['product_id']) : 0;
		$product_context = $this->get_current_product_context($product_id);
		$container_id    = 'size-agent-shortcode-' . (!empty($product_context['id']) ? (int) $product_context['id'] : wp_rand(1000, 999999));

		return $this->render_container($container_id, $product_context);
	}
}
