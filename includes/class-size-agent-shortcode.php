<?php
if (!defined('ABSPATH')) {
	exit;
}

class Size_Agent_Shortcode {
	public function init() {
		add_shortcode('size_agent', array($this, 'render'));
	}

	public function render($atts = array()) {
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
			),
			$atts,
			'size_agent'
		);

		if (!class_exists('Size_Agent_Frontend')) {
			return '';
		}

		$frontend = new Size_Agent_Frontend();
		return $frontend->get_shortcode_markup($atts);
	}
}
