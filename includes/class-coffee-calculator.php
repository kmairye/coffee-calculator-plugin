<?php
/************************************************************
 * 
 * COFFEE CALCULATOR
 * This is the main class creating the api response for 
 * further calculations in the stand-alone frontend application
 * 
 ************************************************************/

 class Coffee_Calculator {

	 public function __construct() {
		add_action('rest_api_init', array( $this, 'register_custom_routes' ));
	}

	public function register_custom_routes() {
		 register_rest_route( 'cc-calc/v1', 'processing-orders', array(
			 'methods'	=> 'GET',
			 'callback'	=> array( $this, 'send_calculator_request_response' )
		 ) );

		 register_rest_route( 'cc-stats/v1', 'completed-orders', array(
			 'methods' => 'GET',
			 'callback' => array( $this, 'send_statistics_request_response' )
		 ) );
	 }

	 public function send_statistics_request_response(\WP_REST_Request $request) {
		// From = 1 month ago
		$default_from_date = mktime(0, 0, 0, date("m")-1, date("d"),   date("Y"));
		// To = today
		$default_to_date = strtotime(date('Y-m-d'));
		
		if( $_GET['from-date'] ) {
			if( !$this->validate_date($_GET['from-date']) ) {
				return new WP_Error( 'invalid_date', 'Invalid date', array( 'status' => 404 ) );
			}
			// Set default date to be that of the input
			$default_from_date = strtotime($_GET['from-date']);
		}
		if( $_GET['to-date'] ) {
			if( !$this->validate_date($_GET['to-date']) ) {
				return new WP_Error( 'invalid_date', 'Invalid date', array( 'status' => 404 ) );
			}
			// Set default date to be that of the input
			$default_to_date = strtotime($_GET['to-date']);
		}

		 $response_obj = new stdClass();
		 $response_obj->status = 'ok';
		 $response_obj->completed_orders = $this->get_wp_orders('wc-completed', $default_from_date, $default_to_date);
		 return $response_obj;
	 }

	 public function send_calculator_request_response(\WP_REST_Request $request) {
		// Handle the default date value = today if no user input
		$date_of_roasting = date('Y-m-d');
		if( $_GET['date'] ) {
			if( !$this->validate_date($_GET['date']) ) {
				return new WP_Error( 'invalid_date', 'Invalid date', array( 'status' => 404 ) );
			}
			// Set default date to be that of the input
			$date_of_roasting = $_GET['date'];
		}
		$response_obj = new stdClass();
		$response_obj->status = 'ok';
		$response_obj->input_date = $date_of_roasting;
		$response_obj->processing_orders = $this->get_wp_orders('wc-processing');
		$response_obj->renewing_subscriptions = $this->get_renewing_subscriptions( $date_of_roasting );
		return $response_obj;
	 }

	#####################################################################
	############################## HELPERS ##############################
	#####################################################################

	public function get_wp_orders($status, $from = 0, $to = 0) {
		$orders = array();
		$wc_orders = wc_get_orders( array(
			'status' => $status
		));
		foreach( $wc_orders as $wc_order ) {
			if($status === 'wc-completed') {
				$date_created_obj 	= $wc_order->get_date_created();
				$date_created_unix 	= strtotime( $date_created_obj );
				if( $date_created_unix > $from && $date_created_unix < $to ) {
					$order_obj = new stdClass();
					$order_obj->id = $wc_order->get_id();
					$order_obj->status = $wc_order->get_status();
					$order_obj->date_created = $wc_order->get_date_created();
					$order_obj->items = $this->get_order_items( $wc_order );
					$orders[] = $order_obj;
					}
			} else {
				$order_obj = new stdClass();
				$order_obj->id = $wc_order->get_id();
				$order_obj->status = $wc_order->get_status();
				$order_obj->created_via = $wc_order->get_created_via();
				$order_obj->date_created = $wc_order->get_date_created();
				$order_obj->items = $this->get_order_items( $wc_order );
				$orders[] = $order_obj;
			}
		}

		return $orders;
	}

	public function get_renewing_subscriptions( $date_of_roasting ) {
		$subscriptions = [];
		$wcs_subscriptions = wcs_get_subscriptions( array('subscription_status' => 'active' ) );
		foreach( $wcs_subscriptions as $wcs_subscription ) {
			$next_payment_raw = $wcs_subscription->get_date('next_payment');
			$next_payment_ymd = substr($next_payment_raw, 0, 10);
			$next_payment_unix = strtotime($next_payment_ymd);
			$date_of_roasting_unix = strtotime($date_of_roasting);
			if( $next_payment_unix > $date_of_roasting_unix ) {
				continue;
			} else {
				$subscription_obj = new stdClass();
				$subscription_obj->id = $wcs_subscription->get_id();
				$subscription_obj->status = $wcs_subscription->get_status();
				$subscription_obj->next_payment = $wcs_subscription->get_date('next_payment');
				$subscription_obj->created_via = 'subscription';
				$subscription_obj->date_created = $wcs_subscription->get_date_created();
				$subscription_obj->items = $this->get_order_items( $wcs_subscription );
			}
			$subscriptions[] = $subscription_obj;
		}
		return $subscriptions;
	}

	public function get_order_items( $order ) {
		$items_array = [];
		$items = $order->get_items();
		foreach( $items as $item ) {
			$item_obj = new stdClass();
			$wc_product   			= get_product( $item->get_product_id() );
			$wc_variation 			= get_product( $item->get_variation_id() );
			if( $this->has_coffee_category( $wc_product ) ) {
				$item_obj->product_id 		= $item->get_product_id();
				$item_obj->variation_id 	= $item->get_variation_id();
				$item_obj->quantity 		= $item->get_quantity();
				$item_obj->preparation_type = $item->get_meta('pa_preparation');
				$item_obj->type				= $wc_product->get_attribute('pa_ctype');
				$item_obj->customer_type    = $wc_product->get_attribute('pa_customer-type');
				if( $wc_variation ) {
					$item_obj->weight 		= $wc_variation->get_weight();
					$item_obj->sku 			= $wc_variation->get_sku();
				}
				$items_array[] 				= $item_obj;
			}
		}
		return $items_array;
	}

	public function has_coffee_category( $product ) {
		$category_slugs = array();
		$category_ids = $product->get_category_ids();
		foreach( $category_ids as $category_id ) {
			$terms = get_terms(array(
				'taxonomy' 			=> 'product_cat',
				'term_taxonomy_id'	=> $category_id
			));
			foreach( $terms as $term ) {
				$category_slugs[] = $term->slug;
			}
		}
		if( !in_array( 'coffee', $category_slugs ) ) {
			return false;
		}
		return true;
	}

	public function validate_date($date, $format = 'Y-m-d') {
		$d = DateTime::createFromFormat($format, $date);
		return $d && $d->format($format) === $date;
	}
 }
