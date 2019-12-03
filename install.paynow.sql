REPLACE INTO cscart_payment_processors (`processor`,`processor_script`,`processor_template`,`admin_template`,`callback`,`type`) VALUES ('Netcash Pay Now','paynow.php', 'views/orders/components/payments/cc_outside.tpl','admin_paynow.tpl', 'N', 'P');

REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_paynow_status_map','Netcash Pay Now payment status to CS-Cart order status convertion map');

REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_paynow_paynow','Pay Now Using Netcash Pay Now');
REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_paynow_item_name','Your Order');
REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_paynow_item_description','Shipping, Handling, Discounts and Taxes Included');

REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_service_key','Service Key');
REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_debug','Debug');
REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_true','True');
REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_false','False');
