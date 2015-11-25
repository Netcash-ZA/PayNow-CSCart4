<?php
/**
 * sagepaynow_common.inc
 */

//// Create user agent string
// User agent constituents (for cURL)
define( 'PN_SOFTWARE_NAME', 'CSCart' );
define( 'PN_SOFTWARE_VER', "4.1.3" );
define( 'PN_MODULE_NAME', 'SagePayNow-CSCart4' );
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
define( 'PN_ERR_CONNECT_FAILED', 'Failed to connect to Sage Pay Now' );
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

function pn_do_transaction( $order_id ) {

    if (fn_check_payment_script ( 'sagepaynow.php', $order_id, $processor_data )) {
        $order_info = fn_get_order_info ( $order_id, true );

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

        pnlog ( "Finished checking data against internal order. " );
        pnlog ( "Errors: {$pnError} - {$pnErrMsg}" );

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
//                  case "PENDING" :
//                      pnlog ( '- Pending' );
//                      $pp_response ['order_status'] = $sagepaynow_statuses ['pending'];
//                      break;
                default :
                    // If unknown status, do nothing (safest course of action)
                    break;
            }

            $pp_response ['reason_text'] = $pnData ['Reason'];
            $pp_response ['transaction_id'] = $transaction_id;
            $pp_response ['customer_email'] = $pnData ['email_address'];

            $return_url = fn_url ( "payment_notification.return?payment=sagepaynow&order_id={$order_info['order_id']}", AREA, 'current' );
            $cancel_url = fn_url ( "payment_notification.cancel?payment=sagepaynow&order_id={$order_info['order_id']}", AREA, 'current' );

            // TODO Re-evaluate this code for efficiency, clean up failed/pending duplicate
            if ($pp_response ['order_status'] == $sagepaynow_statuses ['denied']) {
                fn_change_order_status ( $order_id, $pp_response ['order_status'] );
                pnlog ( "Transaction failed. Returning to {$cancel_url}" );

                fn_redirect ( $cancel_url . "&Reason=" . $pnData['Reason']);
            } else {
                fn_finish_payment ( $order_id, $pp_response );
                pnlog ( "Transaction completed. Returning to {$return_url}" );
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
    if( PN_DEBUG )
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
                $fh = fopen( $pathinfo['dirname'] .'/sagepaynow.log', 'a+' );
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
