<?php
/**
 * Plugin Name: Force HTTP To HTTPS Redirect
 * Description: Redirects all HTTP requests to HTTPS
 * Version: 1.0.0
 * Author: William Patton
 */

add_action( 'template_redirect', function () {
	// Only process if not already on HTTPS
	if ( ! is_ssl() && ! wp_doing_ajax() && ! wp_doing_cron() ) {
		// Get the current URL
		$current_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		// Create the HTTPS version
		$https_url = str_replace( 'http://', 'https://', $current_url );
		// Redirect with 301 (permanent) status
		wp_redirect( $https_url, 301 );
		exit;
	}
} );
