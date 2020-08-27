<?php
/**
 * paynow.php
 *
 * All in one script for submitting the form and handling the callback
 *
 */
use Tygh\Http;
use Tygh\Registry;

if (! defined ( 'BOOTSTRAP' )) {
	die ( 'Access denied' );
}

include 'paynow/paynow_common.inc';
$order_id = null;
if (empty ( $processor_data )) {
	$ref = isset($_POST ['Reference']) ? $_POST ['Reference'] : null;
	$order_id = pn_order_id_from_ref($ref);

	$order_info = fn_get_order_info ( $order_id );
	$processor_data = fn_get_processor_data ( $order_info ['payment_id'] );
}

define ( 'PN_DEBUG', ( bool ) $processor_data ['processor_params'] ['debug'] );

pnlog ( "Including paynow.php from app/payments/paynow" );
pnlog ("Processor params: " . print_r($processor_data ['processor_params'],true));

$paynow_service_key = $processor_data ['processor_params'] ['service_key'];
$do_tokenization = $processor_data ['processor_params'] ['do_tokenization'];

// Return (callback) from the Netcash Pay Now website
// Scroll the bottom to see form submit code
if (defined ( 'PAYMENT_NOTIFICATION' )) {

	// CC callback will have &order_id={ID} set
	// A notification for retail/EFT will have it set as Reference
	$order_id = isset($_REQUEST ['order_id']) ? $_REQUEST ['order_id'] : null;

	if( !$order_id ) {
		$order_id = isset($_POST['Reference']) ? pn_order_id_from_ref($_POST ['Reference']) : null;
	}

	if ($mode == 'notify' && $order_id !== null) {

		pn_do_transaction($order_id);

	} elseif ($mode == 'return') {
		pnlog("Mode == return");
		if (fn_check_payment_script ( 'paynow.php', $_REQUEST ['order_id'] )) {
			$order_info = fn_get_order_info ( $_REQUEST ['order_id'], true );

			if ($order_info ['status'] == STATUS_INCOMPLETED_ORDER) {
				fn_change_order_status ( $_REQUEST ['order_id'], 'O', '', false );
			}

			if (fn_allowed_for ( 'MULTIVENDOR' )) {
				if ($order_info ['status'] == STATUS_PARENT_ORDER) {
					$child_orders = db_get_hash_single_array ( "SELECT order_id, status FROM ?:orders WHERE parent_order_id = ?i", array (
							'order_id',
							'status'
					), $_REQUEST ['order_id'] );

					foreach ( $child_orders as $order_id => $order_status ) {
						if ($order_status == STATUS_INCOMPLETED_ORDER) {
							fn_change_order_status ( $order_id, 'O', '', false );
						}
					}
				}
			}
		}
		fn_order_placement_routines ( 'route', $_REQUEST ['order_id'], false );
	} elseif ($mode == 'cancel') {
		// TODO Evaluate when this code is called
		pnlog("Mode == cancel");
		$order_info = fn_get_order_info ( $_REQUEST ['order_id'] );
		$Reason = $_REQUEST['Reason'];

		$pp_response ['order_status'] = 'N';
		//$pp_response ["reason_text"] = __ ( 'text_transaction_cancelled' );
		$pp_response ["reason_text"] = $Reason;
		pnlog("Reason for transaction failure:" . $Reason);

		fn_finish_payment ( $_REQUEST ['order_id'], $pp_response, false );
		fn_order_placement_routines ( 'route', $_REQUEST ['order_id'] );
	}
} else {
	// The form is about to be submitted to Netcash Pay Now
	$total = fn_format_price ( $order_info ['total'], $processor_data ['processor_params'] ['currency'] );
	$m_payment_id = $order_info ['order_id'];

	// Create an unique order ID
	$m_payment_id = $m_payment_id . "_" . date("Ymds");

	$return_url = fn_url ( "payment_notification.return?payment=paynow&order_id={$order_info['order_id']}", AREA, 'current' );
	$cancel_url = fn_url ( "payment_notification.cancel?payment=paynow&order_id={$order_info['order_id']}", AREA, 'current' );
	$notify_url = fn_url ( "payment_notification.notify?payment=paynow&order_id={$order_info['order_id']}", AREA, 'current' );

	$callback_url = "dispatch=payment_notification.notify&payment=paynow&order_id={$order_info['order_id']}";

	$customerName = "{$order_info['b_firstname']} {$order_info['b_lastname']}";
	$orderID = $order_info['order_id'];
	$customerID = $order_info['user_id']; // TODO: Not sure if this customer ID is correct..
	$sageGUID = "88950107-bea8-4e83-b54a-edbfff19e49a";

	// $order_info['b_phone'] and $order_info['s_phone'] values also exist
	// 10 characters, starting with 0.
	// No intl numbers
	$phone = isset($order_info['phone']) ? str_replace(["+","-","(",")"], "", $order_info['phone']) : "";
	// Remove "27"
	$formattedPhone = substr($phone, 0, 2) == '27' ? ("0" . substr($phone, 2)) : $phone;

	$payArray = array (
		'm1' => $paynow_service_key,
		'm2' => $sageGUID,//'24ade73c-98cf-47b3-99be-cc7b867b3080',
		'm5' => $return_url,
		// 'm6' => $cancel_url,
		// 'm6' => $notify_url,
		// 'm10' => $callback_url,
		// 'first_name' => $order_info ['b_firstname'],
		// 'last_name' => $order_info ['b_lastname'],
		'm9' => $order_info ['email'],
		'm11' => $formattedPhone,
		'p2' => $m_payment_id,
		'p4' => $total,
		// 18 Aug '14 modifed P3
		// 'p3' => __ ( 'text_paynow_item_name' ) . ' - ' . $order_info ['order_id'],

		'm6' => __ ( 'text_paynow_item_name' ) . ' (' . $order_info ['b_firstname'] . ' ' . $order_info ['b_lastname'] . ' - Order #' . $order_info ['order_id'] . ')',
		'description' => __ ( 'total_product_cost' ),

		'p3' => "{$customerName} | {$orderID}",
		// 'm3' => "$sageGUID",
		'm4' => "{$customerID}",
		'm14' => (bool) $do_tokenization ? "1" : "0",

	);

	$inputs = '';
	foreach ( $payArray as $k => $v ) {
		$inputs .= "<input type='hidden' name='$k' value='$v' />\n";
	}

	$msg = fn_get_lang_var ( 'text_cc_processor_connection' );
	$msg = str_replace ( '[processor]', 'Netcash Pay Now', $msg );

	pnlog ( "payArray: " . print_r ( $payArray, true ) );

	echo <<<EOT
    <html>
    <body onLoad="document.paynow_form.submit();">
    <form action="https://paynow.netcash.co.za/site/paynow.aspx" method="post" name="paynow_form">
    $inputs

    </form>
    <div align=center>{$msg}</div>
    </body>
    </html>
EOT;
}
exit ();
