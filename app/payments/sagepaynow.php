<?php
/**
 * sagepaynow.php
 *
 * All in one script for submitting the form and handling the callback
 *
 */
use Tygh\Http;
use Tygh\Registry;

if (! defined ( 'BOOTSTRAP' )) {
	die ( 'Access denied' );
}

function pn_order_id_from_ref($ref) {
	// Get actual order ID from unique ID
	$pieces = explode("_", $ref);
	$order_id = $pieces[0];
	return $order_id;
}

$order_id = null;
if (empty ( $processor_data )) {
	$order_id = pn_order_id_from_ref($_POST ['Reference']);

	$order_info = fn_get_order_info ( $order_id );
	$processor_data = fn_get_processor_data ( $order_info ['payment_id'] );
}

define ( 'PN_DEBUG', ( bool ) $processor_data ['processor_params'] ['debug'] );

include 'sagepaynow/sagepaynow_common.inc';
pnlog ( "Including sagepaynow.php from app/payments/sagepaynow" );
pnlog ("Processor params: " . print_r($processor_data ['processor_params'],true));

$sagepaynow_service_key = $processor_data ['processor_params'] ['service_key'];

// Return (callback) from the Sage Pay Now website
// Scroll the bottom to see form submit code
if (defined ( 'PAYMENT_NOTIFICATION' )) {

	// CC callback will have &order_id={ID} set
	// A notification for retail/EFT will have it set as Reference
	$order_id = isset($_REQUEST ['order_id']) ? $_REQUEST ['order_id'] : null;

	if( !$order_id ) {
		$order_id = isset($_POST['Reference']) ? pn_order_id_from_ref($_POST ['Reference']) : null;
	}

	$pn_callback = $mode == 'notify' || $_POST['TransactionAccepted'] == true;
	// CC notification or EFT or none?

	if ($pn_callback && $order_id !== null) {

		if (fn_check_payment_script ( 'sagepaynow.php', $order_id, $processor_data )) {
			$pp_response = array ();
			$sagepaynow_statuses = $processor_data ['processor_params'] ['statuses'];
			pnlog("sagepaynow statuses: " . print_r($sagepaynow_statuses,true));
			$pnError = false;
			$pnErrMsg = '';
			$pnDone = false;
			$pnData = array ();
			$pnParamString = '';
			pnlog ( 'Sage Pay Now IPN call received' );

			// // Notify Sage Pay Now that information has been received
			if (! $pnError && ! $pnDone) {
				header ( 'HTTP/1.0 200 OK' );
				flush ();
			}

			// // Get data sent by Sage Pay Now
			if (! $pnError && ! $pnDone) {
				pnlog ( 'Get posted data' );

				// Posted variables from IPN
				$pnData = pnGetData ();

				pnlog ( 'Sage Pay Now Data: ' . print_r ( $pnData, true ) );

				if ($pnData === false) {
					$pnError = true;
					$pnErrMsg = PN_ERR_BAD_ACCESS;
				}
			}

			// Get internal cart
			if (! $pnError && ! $pnDone) {
				// TODO The line below shows too much data
				// pnlog ( "Purchase:\n" . print_r ( $order_info, true ) );
			}

			pnlog ( "Checking data against internal order..." );

			// Check data against internal order
			if (! $pnError && ! $pnDone) {
				// pnlog( 'Check data against internal order' );

				// Check order amount
				if (! pnAmountsEqual ( $pnData ['Amount'], fn_format_price ( $order_info ['total'], $processor_data ['processor_params'] ['currency'] ) )) {
					$pnError = true;
					$pnErrMsg = PN_ERR_AMOUNT_MISMATCH;
				}
			}

			pnlog ( "Finished checking data against internal order." );

			// // Check status and update order
			if (! $pnError && ! $pnDone) {
				pnlog ( 'Checking status and update order' );

				$transaction_id = $pnData ['Reference'];

				switch ($pnData ['TransactionAccepted']) {
					case 'true' :
						pnlog ( '- Complete' );
						$pp_response ['order_status'] = $sagepaynow_statuses ['completed'];
						break;
					case 'false':
                        pnlog( '- Failed' );
                        $pp_response['order_status'] = $sagepaynow_statuses['denied'];
                        break;
					// TODO Remove not used section
					//case 'false' :
// 					case "PENDING" :
// 						pnlog ( '- Pending' );
// 						$pp_response ['order_status'] = $sagepaynow_statuses ['pending'];
// 						break;
					default :
						// If unknown status, do nothing (safest course of action)
						break;
				}

				$pp_response ['reason_text'] = $pnData ['Reason'];
				$pp_response ['transaction_id'] = $transaction_id;
				$pp_response ['customer_email'] = $pnData ['email_address'];

				// TODO Re-evaluate this code for efficiency, clean up failed/pending duplicate
				if ($pp_response ['order_status'] == $sagepaynow_statuses ['denied']) {
					fn_change_order_status ( $order_id, $pp_response ['order_status'] );
					pnlog ( "Transaction failed" );
					fn_redirect ( $pnData ['Extra3'] . "&Reason=" . $pnData['Reason']);
				} else {
					fn_finish_payment ( $order_id, $pp_response );
					pnlog ( "Transaction completed" );
					fn_redirect ( $pnData ['Extra2'] );
				}
			}
		}
		exit ();
	} elseif ($mode == 'return') {
		pnlog("Mode == return");
		if (fn_check_payment_script ( 'sagepaynow.php', $_REQUEST ['order_id'] )) {
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
	// The form is about to be submitted to Sage Pay Now
	$total = fn_format_price ( $order_info ['total'], $processor_data ['processor_params'] ['currency'] );
	$m_payment_id = $order_info ['order_id'];

	// Create an unique order ID
	$m_payment_id = $m_payment_id . "_" . date("Ymds");

	$return_url = fn_url ( "payment_notification.return?payment=sagepaynow&order_id=$m_payment_id", AREA, 'current' );
	$cancel_url = fn_url ( "payment_notification.cancel?payment=sagepaynow&order_id=$m_payment_id", AREA, 'current' );
	$notify_url = fn_url ( "payment_notification.notify?payment=sagepaynow&order_id=$m_payment_id", AREA, 'current' );

	$callback_url = "dispatch=payment_notification.notify&payment=sagepaynow&order_id=$m_payment_id";

	$customerName = "{$order_info['b_firstname']} {$order_info['b_lastname']}";
	$orderID = $order_info['order_id'];
	$customerID = $order_info['user_id']; // TODO: Not sure if this customer ID is correct..
	$sageGUID = "TBC";

	$payArray = array (
			'm1' => $sagepaynow_service_key,
			'm2' => '24ade73c-98cf-47b3-99be-cc7b867b3080',
			'm5' => $return_url,
			'm6' => $cancel_url,
			// 'm6' => $notify_url,
			'm10' => $callback_url,
			'first_name' => $order_info ['b_firstname'],
			'last_name' => $order_info ['b_lastname'],
			'email_address' => $order_info ['email'],
			'p2' => $m_payment_id,
			'p4' => $total,
            // 18 Aug '14 modifed P3
            // 'p3' => __ ( 'text_sagepaynow_item_name' ) . ' - ' . $order_info ['order_id'],

            'm6' => __ ( 'text_sagepaynow_item_name' ) . ' (' . $order_info ['b_firstname'] . ' ' . $order_info ['b_lastname'] . ' - Order #' . $order_info ['order_id'] . ')',
			'description' => __ ( 'total_product_cost' ),

			'p3' => "{$customerName} | {$orderID}",
			'm3' => "$sageGUID",
			'm4' => "{$customerID}",
	);

	$inputs = '';
	foreach ( $payArray as $k => $v ) {
		$inputs .= "<input type='hidden' name='$k' value='$v' />\n";
	}

	$msg = fn_get_lang_var ( 'text_cc_processor_connection' );
	$msg = str_replace ( '[processor]', 'Sage Pay Now', $msg );

	pnlog ( "payArray: " . print_r ( $payArray, true ) );

	echo <<<EOT
    <html>
    <body onLoad="document.sagepaynow_form.submit();">
    <form action="https://paynow.sagepay.co.za/site/paynow.aspx" method="post" name="sagepaynow_form">
    $inputs

    </form>
    <div align=center>{$msg}</div>
    </body>
    </html>
EOT;
}
exit ();
