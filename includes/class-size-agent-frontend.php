<?php
if (!defined('ABSPATH')) {
	exit;
}

class Size_Agent_Frontend {
	const SCRUBS_SCRIPT_HANDLE = 'size-agent-scrubs-widget';
	const SHOES_SCRIPT_HANDLE  = 'size-agent-shoes-widget';
	const LOADER_SCRIPT_HANDLE = 'size-agent-loader';
	const STYLE_HANDLE         = 'size-agent-style';

	// Keywords that identify a footwear product
	const SHOE_KEYWORDS = array(
		'shoe', 'shoes', 'boot', 'boots', 'sandal', 'sandals',
		'clog', 'clogs', 'sneaker', 'sneakers', 'loafer', 'loafers',
		'heel', 'heels', 'flat', 'flats', 'mule', 'mules',
		'slipper', 'slippers', 'pump', 'pumps', 'oxford', 'oxfords',
	);

	// Tracks whether the widget has already been rendered to avoid duplicates
	private $rendered = false;

	public function init() {
		add_action('wp_enqueue_scripts', array($this, 'register_assets'));

		if ($this->should_inject_on_product_pages()) {
			// Method 1: Standard WooCommerce hooks
			add_action('woocommerce_before_add_to_cart_button', array($this, 'render_product_page_container_once'));
			add_action('woocommerce_single_product_summary', array($this, 'render_product_page_container_once'), 25);
			add_action('woocommerce_after_add_to_cart_button', array($this, 'render_product_page_container_once'), 5);

			// Method 2: Elementor widget render hook
			add_filter('elementor/widget/render_content', array($this, 'inject_into_elementor_widget'), 10, 2);

			// Method 3: the_content filter for product pages
			add_filter('the_content', array($this, 'inject_into_content'), 50);

			// Method 4: JavaScript injection fallback via wp_footer
			add_action('wp_footer', array($this, 'inject_via_footer_script'));

			// AJAX handler for JS injection
			add_action('wp_ajax_size_agent_render', array($this, 'ajax_render'));
			add_action('wp_ajax_nopriv_size_agent_render', array($this, 'ajax_render'));
		}
	}

	public function render_product_page_container_once() {
		if ($this->rendered) return;
		$this->rendered = true;
		$this->render_product_page_container();
	}

	// Method 2: Elementor widget hook
	public function inject_into_elementor_widget($content, $widget) {
		if ($this->rendered) return $content;
		$name = $widget->get_name();
		if ($name === 'woocommerce-product-add-to-cart' || $name === 'add-to-cart') {
			$this->rendered = true;
			$content .= do_shortcode('[size_agent]');
		}
		return $content;
	}

	// Method 3: the_content filter
	public function inject_into_content($content) {
		if ($this->rendered) return $content;
		if (!is_singular('product')) return $content;
		if (strpos($content, 'size-agent-external') !== false) return $content;
		if (strpos($content, '[size_agent]') !== false) return $content;

		$this->rendered = true;
		$widget_html = do_shortcode('[size_agent]');

		if (strpos($content, '</form>') !== false) {
			$content = preg_replace('/(<\/form>)/i', '$1' . $widget_html, $content, 1);
		} else {
			$content .= $widget_html;
		}
		return $content;
	}

	// Method 4: JavaScript footer injection
	public function inject_via_footer_script() {
		if ($this->rendered) return;
		if (!is_singular('product')) return;
		$product_id = get_the_ID();
		$ajax_url = admin_url('admin-ajax.php');
		?>
		<script>
		(function() {
			if (document.getElementById('size-agent-injected')) return;
			var target = document.querySelector(
				'.elementor-add-to-cart, form.cart, .single_add_to_cart_button'
			);
			if (!target) return;
			var container = document.createElement('div');
			container.id = 'size-agent-injected';
			var insertAfter = target.closest('form') || target.closest('.elementor-widget') || target;
			if (insertAfter && insertAfter.parentNode) {
				insertAfter.parentNode.insertBefore(container, insertAfter.nextSibling);
			}
			fetch('<?php echo esc_url($ajax_url); ?>?action=size_agent_render&product_id=<?php echo intval($product_id); ?>')
				.then(function(r) { return r.text(); })
				.then(function(html) { container.innerHTML = html; });
		})();
		</script>
		<?php
	}

	// AJAX handler for JS injection
	public function ajax_render() {
		$product_id = intval(isset($_GET['product_id']) ? $_GET['product_id'] : 0);
		if ($product_id) {
			global $post;
			$post = get_post($product_id);
			setup_postdata($post);
			echo do_shortcode('[size_agent]');
			wp_reset_postdata();
		}
		wp_die();
	}

	protected function get_settings() {
		return Size_Agent_Settings::get_settings();
	}

	/**
	 * Detect whether a product is footwear or scrubs based on its title.
	 * Returns 'shoes' or 'scrubs'.
	 */
	protected function detect_agent_type($product_title) {
		if (empty($product_title)) {
			return 'scrubs';
		}

		$title_lower = strtolower($product_title);

		foreach (self::SHOE_KEYWORDS as $keyword) {
			if (strpos($title_lower, $keyword) !== false) {
				return 'shoes';
			}
		}

		return 'scrubs';
	}

	protected function get_widget_script_url($agent_type = 'scrubs') {
		$settings = $this->get_settings();

		if ($agent_type === 'shoes') {
			return !empty($settings['shoes_widget_script_url'])
				? esc_url_raw($settings['shoes_widget_script_url'])
				: '';
		}

		return !empty($settings['scrubs_widget_script_url'])
			? esc_url_raw($settings['scrubs_widget_script_url'])
			: '';
	}

	protected function get_api_base_url($agent_type = 'scrubs') {
		$settings = $this->get_settings();

		if ($agent_type === 'shoes') {
			return !empty($settings['shoes_api_base_url'])
				? esc_url_raw($settings['shoes_api_base_url'])
				: '';
		}

		return !empty($settings['scrubs_api_base_url'])
			? esc_url_raw($settings['scrubs_api_base_url'])
			: '';
	}

	protected function should_inject_on_product_pages() {
		if (!function_exists('is_product')) {
			return false;
		}

		$settings = $this->get_settings();
		return !empty($settings['product_page_injection']);
	}

	public function register_assets() {
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

		$scrubs_url = $this->get_widget_script_url('scrubs');
		if (!empty($scrubs_url)) {
			wp_register_script(
				self::SCRUBS_SCRIPT_HANDLE,
				$scrubs_url,
				array(),
				SIZE_AGENT_VERSION,
				true
			);
			wp_script_add_data(self::SCRUBS_SCRIPT_HANDLE, 'defer', true);
		}

		$shoes_url = $this->get_widget_script_url('shoes');
		if (!empty($shoes_url)) {
			wp_register_script(
				self::SHOES_SCRIPT_HANDLE,
				$shoes_url,
				array(),
				SIZE_AGENT_VERSION,
				true
			);
			wp_script_add_data(self::SHOES_SCRIPT_HANDLE, 'defer', true);
		}

		wp_script_add_data(self::LOADER_SCRIPT_HANDLE, 'defer', true);
	}

	/**
	 * Enqueue the correct widget script for the given agent type.
	 * Returns false if the script URL is not configured.
	 */
	protected function enqueue_assets($agent_type = 'scrubs') {
		$widget_script_url = $this->get_widget_script_url($agent_type);

		if (empty($widget_script_url)) {
			return false;
		}

		wp_enqueue_style(self::STYLE_HANDLE);

		$handle = $agent_type === 'shoes'
			? self::SHOES_SCRIPT_HANDLE
			: self::SCRUBS_SCRIPT_HANDLE;

		wp_enqueue_script($handle);
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
		$data['brand']     = $this->detect_brand($data['id']);

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

	protected function build_mount_config($product_context = array(), $agent_type = 'scrubs') {
		return array(
			'agentType' => $agent_type,
			'apiUrl'    => trailingslashit($this->get_api_base_url($agent_type)) . 'api/size',
			'product'   => array(
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
		$agent_type = $this->detect_agent_type(
			!empty($product_context['title']) ? $product_context['title'] : ''
		);

		if (!$this->enqueue_assets($agent_type)) {
			// Debug: show a visible message if script URL is not configured
			if (defined('WP_DEBUG') && WP_DEBUG) {
				return '<div class="size-agent-status is-error">Size Agent: No widget script URL configured for agent type "' . esc_html($agent_type) . '".</div>';
			}
			return '';
		}

		$config = $this->build_mount_config($product_context, $agent_type);

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
