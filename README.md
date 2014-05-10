Sage Pay Now - CS-Cart 4 Payment Gateway Module
===============================================

Revision 1.1.2

Introduction
------------

Sage Pay South Africa's Pay Now third party gateway integration for CS-Cart 4. This module gives you to access the Sage Pay Now gateway which in turns lets you process online shopping cart transactions using CS-Cart 4. VISA and MasterCard are supported.

Download and Database Table Installation Instructions
------------------------------------------------

1. Download the files from Github:
* https://github.com/SagePay/PayNow-CSCart4/archive/master.zip

Copy the files into your CS-Cart /app /design folders.

2. Inside the file downloaded above is a file called 'install.sagepaynow.sql'.

This file has to be executed against your CS-Cart installation database.

If you are using a web hosting control panel such as cPanel or Plesk, it will be possible to run this file by using PHPMyAdmin which is built into those panels.

If your database is not hosted you can use an application such as SQLYog to install the database file.

A 30 day trial of SQLYog can be downloaded here: 
https://www.webyog.com/product/downloads

Once SQLYog is running, connect to your database and run the file.


Configuration
-------------

Prerequisites:

You will need:
* Sage Pay Now login credentials
* Sage Pay Now Service key
* CS-Cart admin login credentials

A. Sage Pay Now Gateway Server Configuration Steps:

1. Log into your Sage Pay Now Gateway Server configuration page:
	https://merchant.sagepay.co.za/SiteLogin.aspx
2. Type in your Sage Pay Username, Password, and PIN
2. Click on Account Profile
3. Click Sage Connect
4. Click on Pay Now
5. Click "Active:"
6. Type in your Email address
7. Click "Allow credit card payments:"

8. The Accept and Decline URLs should both be:
	http://cscart_install/index.php

9. It is highly recommended that you "Make test mode active:" while you are still testing your site.

B. CS-Cart Steps:

1. Log into CS-Cart as administrator (http://cscart_installation/admin.php)
2. Navigate to Administration / Payment Methods
3. Click the "+" to add a new payment method
4. Choose Sage Pay Now from the list and then click save.
5. Once you have added the payment method, click on the cog wheel to configure the payment method
6. For template, choose "cc_outside.tpl"
7. Click the 'Configure' tab
8. Enter your Sage Pay Now service key
9. In the order status conversion map, match Completed to Processed and match Failed to Failed.
10. Click 'Save'

You are now ready to transact. Remember to turn of "Make test mode active:" when you are ready to go live.

Here are two screenshots of the CS-Cart settings screen for the Sage Pay Now configuration:
![alt tag](http://cscart.gatewaymodules.com/cscart_screenshot1.png)
![alt tag](http://cscart.gatewaymodules.com/cscart_screenshot2.png)

Revision History
----------------

* 10 May 2014/1.0.1	Added documentation and screenshots
* 19 Apr 2014/1.0.0	First version

Tested with CS-Cart 4 version 4.1.3

Demo Site
---------
There is a demo site if you want to see osCommerce and the Sage Pay Now gateway in action:
http://cscart.gatewaymodules.com

Feedback, issues & feature requests
-----------------------------------
If you have any feedback please contact Sage Pay South Africa or log an issue on GitHub

