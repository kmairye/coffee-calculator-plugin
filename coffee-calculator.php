<?php

/**
 * Plugin Name: 		Coffee Calculator
 * Description: 		Going to process beans? Enabling Coffee Calculator will provide an API serving the data needed to calculate exactly how much coffee you need to process.*Note: This plugin is part of a bachelor project. 2020*

 * Version: 			1.0.0
 * Requires at least:	5.5
 * Requires PHP: 		^7.3
 * Author: 				Kamille Mai Rye
 * License: 			GPL v2 or later
 * License URI:			https://www.gnu.org/licenses/gpl-2.0.html
 * 
 */


// If this file is called directly, abort.
if( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle CORS
function init_cors( $value ) {
	$origin_url = '';

	// Check environment
	if( ENVIRONMENT === 'production' ) {
		$origin_url = 'https://coffee-calculator-fd5bd.web.app/';
	}

	header( 'Access-Control-Allow-Origin: ' . $origin_url );
	header( 'Access-Control-Allow-Methods: GET' );
	header( 'Access-Control-Allow-Credentials: true' );

	return $value;
}

add_action( 'rest_api_init', function(){
	remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
	add_filter( 'rest_pre_serve_request', 'init_cors' );
}, 15 );

// Require the main class file
require_once __DIR__ . '/includes/class-coffee-calculator.php';
function run_coffee_calculator() {
	new Coffee_Calculator();
}

// Run the plugin since it's relying on hooksclass-coffee-calculator
run_coffee_calculator();
