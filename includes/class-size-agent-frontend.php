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
	private static $rendered = false;

	protected static $frontend_instance = null;

	public static function instance() {
		if (null === self::$frontend_instance) {
			self::$frontend_instance = new self();
		}
		return self::$frontend_instance;
	}

	public function init() {
		add_action('wp_enqueue_scripts', array($this, 'register_assets'));

		add_action('woocommerce_before_add_to_cart_button', array($this, 'render_product_page_container_once'));
		add_action('woocommerce_single_product_summary', array($this, 'render_product_page_container_once'), 25);
		add_action('woocommerce_after_add_to_cart_button', array($this, 'render_product_page_container_once'), 5);
		add_filter('elementor/widget/render_content', array($this, 'inject_into_elementor_widget'), 10, 2);
		add_filter('the_content', array($this, 'inject_into_content'), 50);
		add_action('wp_footer', array($this, 'inject_via_footer_script'));

		add_action('wp_ajax_size_agent_render', array($this, 'ajax_render'));
		add_action('wp_ajax_nopriv_size_agent_render', array($this, 'ajax_render'));
	}

	protected function should_render() {
		$settings = $this->get_settings();
		if (empty($settings['product_page_injection'])) {
			return false;
		}
		// Require valid license key
		if (empty($settings['license_key'])) {
			return false;
		}
		if (function_exists('is_product') && is_product()) {
			return true;
		}
		if (is_singular('product')) {
			return true;
		}
		global $post;
		if ($post && $post->post_type === 'product') {
			return true;
		}
		return false;
	}

	public function render_product_page_container_once() {
		if (!$this->should_render()) return;
		if (self::$rendered) return;
		self::$rendered = true;
		$this->render_product_page_container();
	}

	public function inject_into_elementor_widget($content, $widget) {
		if (!$this->should_render()) return $content;
		if (self::$rendered) return $content;
		$name = $widget->get_name();
		if ($name === 'woocommerce-product-add-to-cart' || $name === 'add-to-cart') {
			self::$rendered = true;
			$content .= do_shortcode('[size_agent]');
		}
		return $content;
	}

	public function inject_into_content($content) {
		if (!$this->should_render()) return $content;
		if (self::$rendered) return $content;
		if (strpos($content, 'size-agent-external') !== false) return $content;
		if (strpos($content, '[size_agent]') !== false) return $content;
		self::$rendered = true;
		$widget_html = do_shortcode('[size_agent]');
		if (strpos($content, '</form>') !== false) {
			$content = preg_replace('/(<\/form>)/i', '$1' . $widget_html, $content, 1);
		} else {
			$content .= $widget_html;
		}
		return $content;
	}

	public function inject_via_footer_script() {
		if (!$this->should_render()) return;
		if (self::$rendered) return;
		$product_id = get_the_ID();
		$ajax_url = admin_url('admin-ajax.php');
		?>
		<script>
		(function() {
			if (document.getElementById('size-agent-injected')) return;
			if (document.querySelector('.size-agent-external')) return;
			if (document.getElementById('ns-size-finder-btn')) return;
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
			var productTitle = (
				document.querySelector('.product_title') ||
				document.querySelector('h1.elementor-heading-title') ||
				document.querySelector('h1')
			);
			var titleText = productTitle ? encodeURIComponent(productTitle.innerText.trim()) : '';
			fetch('<?php echo esc_url($ajax_url); ?>?action=size_agent_render&product_id=<?php echo intval($product_id); ?>&product_title=' + titleText)
				.then(function(r) { return r.text(); })
				.then(function(html) { container.innerHTML = html; });
		})();
		</script>
		<?php
	}

	public function ajax_render() {
		$product_id    = intval(isset($_GET['product_id']) ? $_GET['product_id'] : 0);
		$product_title = isset($_GET['product_title']) ? sanitize_text_field(urldecode($_GET['product_title'])) : '';

		if ($product_id) {
			global $post;
			$post = get_post($product_id);
			setup_postdata($post);

			if (empty($product_title)) {
				$product = wc_get_product($product_id);
				if ($product) {
					$product_title = $product->get_name();
				}
			}

			$agent_type      = $this->detect_agent_type($product_title, $product_id);
			$product_context = $this->get_current_product_context($product_id);
			$container_id    = 'size-agent-ajax-' . $product_id;

			echo $this->render_container($container_id, $product_context);
			wp_reset_postdata();
		}
		wp_die();
	}

	protected function get_settings() {
		return Size_Agent_Settings::get_settings();
	}

	protected function detect_agent_type($product_title, $product_id = 0) {
		if (!empty($product_title)) {
			$title_lower = strtolower($product_title);
			foreach (self::SHOE_KEYWORDS as $keyword) {
				if (strpos($title_lower, $keyword) !== false) {
					return 'shoes';
				}
			}
		}

		if ($product_id) {
			$categories = get_the_terms($product_id, 'product_cat');
			if (is_array($categories) && !is_wp_error($categories)) {
				foreach ($categories as $cat) {
					$cat_lower = strtolower($cat->name . ' ' . $cat->slug);
					foreach (self::SHOE_KEYWORDS as $keyword) {
						if (strpos($cat_lower, $keyword) !== false) {
							return 'shoes';
						}
					}
				}
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

	public function register_assets() {
		wp_register_style(
			self::STYLE_HANDLE,
			SIZE_AGENT_PLUGIN_URL . 'assets/css/size-agent.css',
			array(),
			SIZE_AGENT_VERSION
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
		}

		$loader_deps = array();
		if (!empty($scrubs_url)) {
			$loader_deps[] = self::SCRUBS_SCRIPT_HANDLE;
		}
		if (!empty($shoes_url)) {
			$loader_deps[] = self::SHOES_SCRIPT_HANDLE;
		}

		wp_register_script(
			self::LOADER_SCRIPT_HANDLE,
			SIZE_AGENT_PLUGIN_URL . 'assets/js/size-agent.js',
			$loader_deps,
			SIZE_AGENT_VERSION,
			true
		);
	}

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
		$settings    = $this->get_settings();
		$license_key = !empty($settings['license_key']) ? $settings['license_key'] : '';

		return array(
			'agentType'  => $agent_type,
			'licenseKey' => $license_key,
			'apiUrl'     => trailingslashit($this->get_api_base_url($agent_type)) . 'api/size',
			'product'    => array(
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
			!empty($product_context['title']) ? $product_context['title'] : '',
			!empty($product_context['id']) ? $product_context['id'] : 0
		);

		if (!$this->enqueue_assets($agent_type)) {
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
