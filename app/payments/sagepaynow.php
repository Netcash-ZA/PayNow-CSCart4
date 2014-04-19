<?php
/**
 * sagepaynow.php
 */

use Tygh\Http;
use Tygh\Registry;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if (empty($processor_data)) 
{
    $order_id = $_POST['m_payment_id'];
    $order_info = fn_get_order_info( $order_id );
    $processor_data = fn_get_processor_data( $order_info['payment_id'] );
}

define( 'PN_DEBUG', (bool)$processor_data['processor_params']['debug'] );

include 'sagepaynow/sagepaynow_common.inc';

$sagepaynow_service_key = $processor_data['processor_params']['service_key'];
    
// Return from Sage Pay Now website
if( defined('PAYMENT_NOTIFICATION') ) 
{
    if( $mode == 'notify' && !empty( $_REQUEST['order_id'] )) 
    {
        
        if (fn_check_payment_script('sagepaynow.php', $_POST['m_payment_id'], $processor_data)) 
        {  
            $pp_response = array();
            $sagepaynow_statuses = $processor_data['processor_params']['statuses'];
            $pnError = false;
            $pnErrMsg = '';
            $pnDone = false;
            $pnData = array();      
            $pnParamString = '';            
            pnlog( 'Sage Pay Now IPN call received' );

            //// Notify Sage Pay Now that information has been received
            if( !$pnError && !$pnDone )
            {
                header( 'HTTP/1.0 200 OK' );
                flush();
            }
        
            //// Get data sent by Sage Pay Now
            if( !$pnError && !$pnDone )
            {
                pnlog( 'Get posted data' );
            
                // Posted variables from IPN
                $pnData = pnGetData();
            
                pnlog( 'Sage Pay Now Data: '. print_r( $pnData, true ) );
            
                if( $pnData === false )
                {
                    $pnError = true;
                    $pnErrMsg = PN_ERR_BAD_ACCESS;
                }
            }
           
            // Get internal cart
            if( !$pnError && !$pnDone )
            {                   
                pnlog( "Purchase:\n". print_r( $order_info, true )  );
            }
            
            // Check data against internal order
            if( !$pnError && !$pnDone )
            {
               // pnlog( 'Check data against internal order' );
        
                // Check order amount
                if( !pnAmountsEqual( $pnData['amount_gross'], fn_format_price( $order_info['total'] , $processor_data['processor_params']['currency'] ) ) )
                {
                    $pnError = true;
                    $pnErrMsg = PN_ERR_AMOUNT_MISMATCH;
                }          
                
            }
            
            //// Check status and update order
            if( !$pnError && !$pnDone )
            {
                pnlog( 'Check status and update order' );
        
                
                $transaction_id = $pnData['pn_payment_id'];
        
                switch( $pnData['payment_status'] )
                {
                    case 'COMPLETE':
                        pnlog( '- Complete' );
                        $pp_response['order_status'] = $sagepaynow_statuses['completed'];                        
                        break;
        
                    case 'FAILED':
                        pnlog( '- Failed' );                       
                        $pp_response['order_status'] = $sagepaynow_statuses['denied'];        
                        break;
        
                    case 'PENDING':
                        pnlog( '- Pending' );                   
                        $pp_response['order_status'] = $sagepaynow_statuses['pending'];
                        break;
        
                    default:
                        // If unknown status, do nothing (safest course of action)
                    break;
                }
                
                
                $pp_response['reason_text'] = $pnData['payment_status'];
                $pp_response['transaction_id'] = $transaction_id;
                $pp_response['customer_email'] = $pnData['email_address'];
                
                if ($pp_response['order_status'] == $paypal_statuses['pending']) 
                {
                    fn_change_order_status($order_id, $pp_response['order_status']);
                } 
                else 
                {
                    fn_finish_payment($order_id, $pp_response);
                                    
                }
            }
        }
        exit;

    } elseif ($mode == 'return') {
        if (fn_check_payment_script('sagepaynow.php', $_REQUEST['order_id'])) {
            $order_info = fn_get_order_info($_REQUEST['order_id'], true);

            if ($order_info['status'] == STATUS_INCOMPLETED_ORDER) 
            {
                fn_change_order_status($_REQUEST['order_id'], 'O', '', false);

            }

            if (fn_allowed_for('MULTIVENDOR')) 
            {
                if ($order_info['status'] == STATUS_PARENT_ORDER) 
                {
                    $child_orders = db_get_hash_single_array("SELECT order_id, status FROM ?:orders WHERE parent_order_id = ?i", array('order_id', 'status'), $_REQUEST['order_id']);

                    foreach ($child_orders as $order_id => $order_status) 
                    {
                        if ($order_status == STATUS_INCOMPLETED_ORDER) {
                            fn_change_order_status($order_id, 'O', '', false);
                        }
                    }
                }
            }
        }
        fn_order_placement_routines('route', $_REQUEST['order_id'], false);

    } 
    elseif ( $mode == 'cancel') 
    {
        $order_info = fn_get_order_info( $_REQUEST['order_id'] );

        $pp_response['order_status'] = 'N';
        $pp_response["reason_text"] = __('text_transaction_cancelled');

        fn_finish_payment( $_REQUEST['order_id'], $pp_response, false);
        fn_order_placement_routines( 'route', $_REQUEST['order_id']);
    }

} else {


    $total = fn_format_price( $order_info['total'] , $processor_data['processor_params']['currency'] );
    $m_payment_id = $order_info['order_id'];
    $return_url = fn_url("payment_notification.return?payment=sagepaynow&order_id=$m_payment_id", AREA, 'current');
    $cancel_url = fn_url("payment_notification.cancel?payment=sagepaynow&order_id=$m_payment_id", AREA, 'current');
    $notify_url = fn_url("payment_notification.notify?payment=sagepaynow&order_id=$m_payment_id", AREA, 'current'); 

    $payArray = array(                
                'service_key'  =>$sagepaynow_service_key,
                'return_url'    =>$return_url,
                'cancel_url'    =>$cancel_url,
                'notify_url'    =>$notify_url,
                'name_first'    =>$order_info['b_firstname'],
                'name_last'     =>$order_info['b_lastname'],
                'email_address' =>$order_info['email'],
                'm_payment_id'  =>$m_payment_id,
                'amount'        =>$total,
                'item_name'     =>__('text_sagepaynow_item_name') .' - '. $order_info['order_id'],
                'item_description'=>__('total_product_cost')
            );

    $secureString = '';
    foreach($payArray as $k=>$v)
    {
        $secureString .= $k.'='.urlencode(trim($v)).'&';              
    }
    $secureString = substr( $secureString, 0, -1 );
    
    $securityHash = md5($secureString);

    $payArray['signature'] = $securityHash;
    $inputs = '';
    foreach( $payArray as $k=>$v )
    {
       $inputs .=  "<input type='hidden' name='$k' value='$v' />\n";
    }

    $msg = fn_get_lang_var('text_cc_processor_connection');
    $msg = str_replace('[processor]', 'SagePayNow', $msg);
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
exit;
