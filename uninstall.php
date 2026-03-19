<?php
/**
 * Uninstall cleanup for Size Agent.
 *
 * @package SizeAgent
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'size_agent_settings', array() );
if ( is_array( $settings ) && ! empty( $settings['cleanup_uninstall'] ) ) {
	delete_option( 'size_agent_settings' );
}
