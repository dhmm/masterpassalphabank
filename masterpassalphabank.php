<?php
//Direct access not allowed
if (!defined('ABSPATH')){
  exit;
}

/*
Plugin Name: MasterPass - AlphaBank plugin
Plugin URI: https://github.com/dhmm
Description: Payment gateway for woocommerce where we can use MasterPass payment option via AplhaBank
Version: 1.0
Author: Nteli Chasan Moustafa Moutlou
Author URL: https://github.com/dhmm/masterpassalpha
*/

/*
For shortcut we'll use [mpab] whicih means "MasterPass AlphaBank"
*/
//Add out plugin loader to wordpress hooks
add_action('plugins_loaded' , 'mpab_init', 0);

function mpab_init() {
  //If woocomeerce isn't installed dont init the plugin
  if(!class_exists('WC_Payment_gateway')) return;

  //Include our gateway
  require_once('gateway.php');

  //Add our class towoocommerce methods
  add_filter('woocommerce_payment_gateways' , 'add_masterpassalphabankgateway'); 
  function add_masterpassalphabankgateway( $methods ) {
    $methods[] = 'WC_MasterPass_AlphaBank';
    return $methods;
  }

}
