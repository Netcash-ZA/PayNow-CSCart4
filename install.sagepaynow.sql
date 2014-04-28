REPLACE INTO cscart_payment_processors (`processor`,`processor_script`,`processor_template`,`admin_template`,`callback`,`type`) VALUES ('Sage Pay Now','sagepaynow.php', 'views/orders/components/payments/cc_outside.tpl','admin_sagepaynow.tpl', 'N', 'P');

REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_sagepaynow_status_map','Sage Pay Now payment status to CS-Cart order status convertion map');

REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_sagepaynow_paynow','Pay Now Using Sage Pay Now');
REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_sagepaynow_item_name','Your Order');
REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_sagepaynow_item_description','Shipping, Handling, Discounts and Taxes Included');

REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_debug','Debug');
REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_true','True');
REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_false','False');
