<?php

/**
 * Load whatever is neccessarry for the current system to function
 */
function pn_load_system() {
	$php_value = phpversion();
	if (version_compare($php_value, '5.3.0') == -1) {
	    echo 'Currently installed PHP version (' . $php_value . ') is not supported. Minimal required PHP version is  5.3.0.';
	    die();
	}

	define('AREA', 'C');

	try {
	    // require(dirname(__FILE__) . '/../../../init.php');
	    require( '../../../init.php' );
	    // fn_dispatch();
	} catch (Tygh\Exceptions\AException $e) {
	    $e->output();
	}

	// require( '../../../index.php' );
	// require( '../../../init.php' );
}

/**
 * Load PayNow functions/files
 */
function pn_load_paynow() {
	// require_once '../paynow.php';
	require_once 'paynow_common.inc';
	return;
}

/**
 * Get the URL we'll redirect users to when coming back from the gateway (for when they choose EFT/Retail)
 */
function pn_get_redirect_url() {
	// $url_for_redirect = fn_url ( "orders" );
	// $url_for_redirect =  "../../../orders";
	$url_for_redirect =  "../../../index.php?dispatch=orders.search";
	return $url_for_redirect;
}

// Load System
pn_load_system();

// Load PayNow
pn_load_paynow();

// Redirect URL for users using EFT/Retail payments to notify them the order's pending
$url_for_redirect = pn_get_redirect_url();

$offline = pn_is_offline();
pnlog("IS OFFLINE? " . ($offline ? 'Yes' : 'No') );

if( isset($_POST) && !empty($_POST) && !$offline ) {

	// This is the notification coming in!
	// Act as an IPN request and forward request to Credit Card method.
	// Logic is exactly the same
	pn_do_transaction( pn_order_id_from_ref($_POST['Reference']) );
	die();

} else {
	// Probably calling the "redirect" URL,
	// OR, the payment is still Pending, e.g., EFT
	pnlog(__FILE__ . " POST: " . print_r($_REQUEST, true) );
	pnlog(__FILE__ . ' Probably calling the "redirect" URL');

	if( $url_for_redirect ) {
		header ( "Location: {$url_for_redirect}" );
	} else {
	    die( "No 'redirect' URL set." );
	}
}

die( PN_ERR_BAD_ACCESS );
