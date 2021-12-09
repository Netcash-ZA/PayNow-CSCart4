<?php
/**
 * paynow_common.inc
 */

//// Create user agent string
// User agent constituents (for cURL)
define( 'PN_SOFTWARE_NAME', 'CSCart' );
define( 'PN_SOFTWARE_VER', "4.1.3" );
define( 'PN_MODULE_NAME', 'paynow-CSCart4' );
define( 'PN_MODULE_VER', '1.0.0' );

// Features
// - PHP
$pnFeatures = 'PHP '. phpversion() .';';

// - cURL
if( in_array( 'curl', get_loaded_extensions() ) )
{
    define( 'PN_CURL', '' );
    $pnVersion = curl_version();
    $pnFeatures .= ' curl '. $pnVersion['version'] .';';
}
else
    $pnFeatures .= ' nocurl;';

// Create user agrent
define( 'PN_USER_AGENT', PN_SOFTWARE_NAME .'/'. PN_SOFTWARE_VER .' ('. trim( $pnFeatures ) .') '. PN_MODULE_NAME .'/'. PN_MODULE_VER );


// General Defines
define( 'PN_TIMEOUT', 15 );
define( 'PN_EPSILON', 0.01 );

// Messages
    // Error
define( 'PN_ERR_AMOUNT_MISMATCH', 'Amount mismatch' );
define( 'PN_ERR_BAD_ACCESS', 'Bad access of page' );
define( 'PN_ERR_BAD_SOURCE_IP', 'Bad source IP address' );
define( 'PN_ERR_CONNECT_FAILED', 'Failed to connect to Netcash Pay Now' );
define( 'PN_ERR_INVALID_SIGNATURE', 'Security signature mismatch' );
define( 'PN_ERR_NO_SESSION', 'No saved session found for IPN transaction' );
define( 'PN_ERR_ORDER_ID_MISSING_URL', 'Order ID not present in URL' );
define( 'PN_ERR_ORDER_ID_MISMATCH', 'Order ID mismatch' );
define( 'PN_ERR_ORDER_INVALID', 'This order ID is invalid' );
define( 'PN_ERR_ORDER_PROCESSED', 'This order has already been processed' );
define( 'PN_ERR_PDT_FAIL', 'PDT query failed' );
define( 'PN_ERR_PDT_TOKEN_MISSING', 'PDT token not present in URL' );
define( 'PN_ERR_SESSIONID_MISMATCH', 'Session ID mismatch' );
define( 'PN_ERR_UNKNOWN', 'Unkown error occurred' );

    // General
define( 'PN_MSG_OK', 'Payment was successful' );
define( 'PN_MSG_FAILED', 'Payment has failed' );
define( 'PN_MSG_PENDING',
    'The payment is pending. Please note, you will receive another Instant'.
    ' Transaction Notification when the payment status changes to'.
    ' "Completed", or "Failed"' );



function pn_order_id_from_ref($ref) {
    // Get actual order ID from unique ID
    $pieces = explode("_", $ref);
    $order_id = $pieces[0];
    return $order_id;
}

/**
 * Check if this is a 'offline' payment like EFT or retail
 */
function pn_is_offline() {

	/*
	Returns 2 for EFT
	Returns 3 for Retail
	*/
	$offline_methods = [2, 3];

	// If !$accepted, means it's the callback.
	// If $accepted, and in array, means it's the actual called response
	$accepted = isset($_POST['TransactionAccepted']) ? $_POST['TransactionAccepted'] == 'true' : false;
	// $isPending = isset($_POST['Reason']) ? stristr($_POST['Reason'], 'pending') !== false : false;
	$method = isset($_POST['Method']) ? (int) $_POST['Method'] : null;
	pnlog('Checking if offline: ' . print_r(array(
		"Accepted" => $accepted,
		// "Pending" => $isPending,
		"Method" => (int) $method,
	), true));

	return !$accepted && in_array($method, $offline_methods);
	// return (!$accepted || $isPending) && in_array($method, $offline_methods);
}

function pn_do_transaction( $order_id ) {

    if (fn_check_payment_script ( 'paynow.php', $order_id, $processor_data )) {
        $order_info = fn_get_order_info ( $order_id, true );

        $pp_response = array ();
        $paynow_statuses = $processor_data ['processor_params'] ['statuses'];
        pnlog("paynow statuses: " . print_r($paynow_statuses,true));
        $pnError = false;
        $pnErrMsg = '';
        $pnDone = false;
        $pnData = array ();
        $pnParamString = '';
        pnlog ( 'Netcash Pay Now IPN call received' );

        // // Notify Netcash Pay Now that information has been received
        if (! $pnError && ! $pnDone) {
            header ( 'HTTP/1.0 200 OK' );
            flush ();
        }

        // // Get data sent by Netcash Pay Now
        if (! $pnError && ! $pnDone) {
            pnlog ( 'Get posted data' );

            // Posted variables from IPN
            $pnData = pnGetData ();

            pnlog ( 'Netcash Pay Now Data: ' . print_r ( $pnData, true ) );

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

        pnlog ( "Finished checking data against internal order. " );
        pnlog ( "Errors: {$pnError} - {$pnErrMsg}" );

        // // Check status and update order
        if (! $pnError && ! $pnDone) {
            pnlog ( 'Checking status and update order' );

			$reason_text = $pnData ['Reason'];
            $transaction_id = $pnData ['Reference'];

            switch ($pnData ['TransactionAccepted']) {
                case 'true' :
                    pnlog ( '- Complete' );

					// Processed—the payment was received.
					// Complete—all work on the order was completed.
					// Open—the order was placed and created in the store database, but it hasn’t been processed yet.

                    $pp_response ['order_status'] = $paynow_statuses ['completed'];
                    break;
                case 'false':
                    pnlog( "- Failed for reason: {$pnData['Reason']}" );

                    $wasCancelled = stristr($pnData['Reason'], 'cancelled');
                    $wasDeclined = stristr($pnData['Reason'], 'declined');

					if($wasCancelled !== false) {
						// If the user cancelled, redirect to cancel URL.
						pnlog("\t - Order cancelled by user.");
						$pp_response['order_status'] = $paynow_statuses['canceled'];
						$reason_text = "The transaction was cancelled";
					}
					else if ($wasDeclined !== false) {
						// Declined by gateway. Invalid card, etc.
						pnlog("\t - Order declined by gateway.");
						$pp_response['order_status'] = $paynow_statuses['declined'];
						$reason_text = "The transaction was declined by the gateway ";
					} else {
						pnlog("\t - Unknown/Generic failure.");
						$pp_response['order_status'] = $paynow_statuses['failed'];
						// Use gateway message
						$reason_text = $pnData ['Reason'];
					}


                    break;
                default :
                    // If unknown status, do nothing (safest course of action)
                    break;
            }

            // Use reason provided by gateway in user message
            $pp_response ['reason_text'] = $reason_text;
            $pp_response ['transaction_id'] = $transaction_id;
            $pp_response ['customer_email'] = isset($pnData ['email_address']) ? $pnData ['email_address'] : '';

            // $success_url = fn_url ( "checkout.complete?payment=paynow&order_id={$order_info['order_id']}", AREA, 'current' );
            $return_url = fn_url ( "payment_notification.return?payment=paynow&order_id={$order_info['order_id']}", AREA, 'current' );
            $cancel_url = fn_url ( "payment_notification.cancel?payment=paynow&order_id={$order_info['order_id']}", AREA, 'current' );

            // TODO Re-evaluate this code for efficiency, clean up failed/pending duplicate
            $denied_statuses = [
            	isset($paynow_statuses ['denied']) ? $paynow_statuses ['denied'] : null,
            	isset($paynow_statuses ['canceled']) ? $paynow_statuses ['canceled'] : null,
                isset($paynow_statuses ['declined']) ? $paynow_statuses ['declined'] : null,
            ];
            if ( in_array($pp_response ['order_status'], $denied_statuses) ) {
                fn_change_order_status ( $order_id, $pp_response ['order_status'] );
                pnlog ( "Transaction failed/cancelled. Returning to {$cancel_url}" );

                fn_redirect ( $cancel_url . "&Reason=" . $pnData['Reason']);
            } else {
                fn_finish_payment ( $order_id, $pp_response );
                if($order_info && $order_info['user_id']!=0) {
                    fn_login_user($order_info['user_id']);
                }
                pnlog ( "Transaction completed. Returning to {$return_url}" );

                // if guest, return with $success_url?
                fn_redirect ( $return_url );
            }
        }
    }
    exit ();
}

/**
 * pnlog
 *
 * Log function for logging output.
 *
 * @param $msg String Message to log
 * @param $close Boolean Whether to close the log file or not
 */
function pnlog( $msg = '', $close = false )
{
    static $fh = 0;
    global $module;

    // Only log if debugging is enabled
    if( defined('PN_DEBUG') && PN_DEBUG )
    {
        if( $close )
        {
            fclose( $fh );
        }
        else
        {
            // If file doesn't exist, create it
            if( !$fh )
            {
                $pathinfo = pathinfo( __FILE__ );
                $fh = fopen( $pathinfo['dirname'] .'/paynow.log', 'a+' );
            }

            // If file was successfully created
            if( $fh )
            {
                $line = date( 'Y-m-d H:i:s' ) .' : '. $msg ."\n";

                fwrite( $fh, $line );
            }
        }
    }
}

/**
 * pnGetData
 *
 */
function pnGetData()
{
    // Posted variables from IPN
    $pnData = $_POST;

    // Strip any slashes in data
    foreach( $pnData as $key => $val )
        $pnData[$key] = stripslashes( $val );

    // Return "false" if no data was received
    if( sizeof( $pnData ) == 0 )
        return( false );
    else
        return( $pnData );
}

/**
 * pnAmountsEqual
 *
 * Checks to see whether the given amounts are equal using a proper floating
 * point comparison with an Epsilon which ensures that insignificant decimal
 * places are ignored in the comparison.
 *
 * eg. 100.00 is equal to 100.0001
 *
 * @param $amount1 Float 1st amount for comparison
 * @param $amount2 Float 2nd amount for comparison
 */
function pnAmountsEqual( $amount1, $amount2 )
{
    if( abs( floatval( $amount1 ) - floatval( $amount2 ) ) > PN_EPSILON )
        return( false );
    else
        return( true );
}

?>
