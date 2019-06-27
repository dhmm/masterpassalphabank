<?php
//Direct access not allowed
if (!defined('ABSPATH')){
  exit;
}

/**
 * @class       WC_MasterPass_AlphaBank
 * @extends     WC_Payment_gateway
 * @version     1.0
 * @package     WooCommerce/Classes/Payment
 * @author      Nteli Chasan Moustafa Moutlou / www.github.com/dhmm
 */
class WC_MasterPass_AlphaBank extends WC_Payment_gateway {
  public function __construct() {
    $this->initGateway();

    $this->initFormFields();
    $this->init_settings();

    $this->getSettings();

    // Customer Emails
    add_action( 'woocommerce_email_before_order_table', array( $this, 'emailInstructions' ), 10, 3 );
		
		//Actions
		add_action('woocommerce_receipt_' . $this->id, array( $this, 'receiptPage' ));
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_thankyou_alpha', array( $this, 'thankYouPage' ) );
		// Payment listener/API hook
    add_action('woocommerce_api_wc_masterpass_alphabank', array($this, 'checkResponse'));
    
    $this->setInstallmentsArray();
   
  }
  private function initGateway() {
    $this->id   = 'masterpassalpha';
    $this->icon = apply_filters('woocommerce_cod_icon' , '');
    $this->method_title   = __('MasterPass - AlphaBank' , 'woocommerce');
    $this->method_description = __('MasterPass AlphaBank paymnet gateway' , 'woocommerce');
  }
  private function getSettings() {
    $this->title                  = $this->get_option( 'title' );
    $this->description            = $this->get_option( 'description' );
    $this->instructions           = $this->get_option( 'instructions', $this->description );
    $this->MerchantId             = $this->get_option('MerchantId');
    $this->Secret                 = $this->get_option('Secret');
    $this->AlphaBankUrl           = $this->get_option('testmode') === 'yes' ? "https://alpha.test.modirum.com/vpos/shophandlermpi" : "https://www.alphaecommerce.gr/vpos/shophandlermpi";
    $this->InstallmentsActive     = $this->get_option('installmentsActive') === 'yes' ? true : false;
    $this->autosubmitPaymentForm  = $this->get_option('autosubmitPaymentForm') === 'yes' ? true : false;
  }
  private function setInstallmentsArray() {
    $this->installmentsArray = Array(100 => 4, 200 => 8, 300 => 12);
  }

  protected function getAlphaArgs( $order, $uniqid, $installments ) {		
		$return = WC()->api_request_url( 'WC_MasterPass_AlphaBank' );
		$address = array(
				'address_1'     => ( WC()->version >= '3.0.0' ) ? $order->get_billing_address_1() : $order->billing_address_1,
        'address_2'     => ( WC()->version >= '3.0.0' ) ? $order->get_billing_address_2() : $order->billing_address_2,
        'city'          => ( WC()->version >= '3.0.0' ) ? $order->get_billing_city() : $order->billing_city,
        'state'         => ( WC()->version >= '3.0.0' ) ? $order->get_billing_state() : $order->billing_state,
        'postcode'      => ( WC()->version >= '3.0.0' ) ? $order->get_billing_postcode() : $order->billing_postcode,
        'country'       => ( WC()->version >= '3.0.0' ) ? $order->get_billing_country() : $order->billing_country
    );
		
		$lang = 'el';
		if (substr(get_locale(), 0, 2) == 'en') {
			$lang = 'en';
		}
		
		$args = array(
			'mid'         => $this->MerchantId,
			'lang'        => $lang,
			'orderid'     => $uniqid . 'AlphaBankOrder' .  ( ( WC()->version >= '3.0.0' ) ? $order->get_id() : $order->id ),
			'orderDesc'   => 'Name: ' . $order->get_formatted_billing_full_name() . ' Address: ' . implode(",", $address) ,
			'orderAmount' => wc_format_decimal($order->get_total(), 2, false),
			'currency'    => 'EUR',
      'payerEmail'  => ( WC()->version >= '3.0.0' ) ? $order->get_billing_email() : $order->billing_email ,
      'payMethod'   => 'auto:MasterPass'
		);
		
		if ($installments > 0) {
			$args['extInstallmentoffset'] = 0;
			$args['extInstallmentperiod'] = $installments;
		};
		
		$args = array_merge($args, array(
			'confirmUrl' => add_query_arg( 'confirm', ( WC()->version >= '3.0.0' ) ? $order->get_id() : $order->id , $return),
			'cancelUrl'  => add_query_arg( 'cancel', ( WC()->version >= '3.0.0' ) ? $order->get_id() : $order->id , $return), 
		));
				
		return apply_filters( 'woocommerce_masterpass_alpha_args', $args , $order );
  }
  public function initFormFields() {
    $shipping_methods = array();

    if ( is_admin() )
      foreach ( WC()->shipping()->load_shipping_methods() as $method ) {
        $shipping_methods[ $method->id ] = $method->get_title();
      }

    $this->form_fields = array(
    'enabled' => array(
      'title'       => __( 'Enable Alpha Bank', 'woocommerce' ),
      'label'       => __( 'Enabled', 'woocommerce' ),
      'type'        => 'checkbox',
      'description' => '',
      'default'     => 'no'
    ),
    'title' => array(
      'title'       => __( 'Title', 'woocommerce' ),
      'type'        => 'text',
      'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
      'default'     => __( 'Alpha Bank', 'woocommerce' ),
      'desc_tip'    => true,
    ),
    'description' => array(
      'title'       => __( 'Description', 'woocommerce' ),
      'type'        => 'textarea',
      'description' => __( 'Payment method description that the customer will see on your website.', 'woocommerce' ),
      'default'     => __( 'Πληρωμή μέσω Alpha Bank', 'woocommerce' ),
      'desc_tip'    => true,
    ),
    'instructions' => array(
      'title'       => __( 'Instructions', 'woocommerce' ),
      'type'        => 'textarea',
      'description' => __( 'Instructions that will be added to the thank you page.', 'woocommerce' ),
      'default'     => __( 'Πληρωμή μέσω Alpha Bank', 'woocommerce' ),
      'desc_tip'    => true,
    ),
    'testmode' => array(
      'title'       => __( 'Test mode', 'woocommerce' ),
      'label'       => __( 'Enable test mode', 'woocommerce' ),
      'type'        => 'checkbox',
      'description' => 'uncheck this to disable test mode',
      'default'     => 'yes'
    ),
    'MerchantId' => array(
                  'title' => __('Alpha Bank Merchant ID', 'woocommerce'),
                  'type' => 'text',
                  'description' => __('Enter Your Alpha Bank Merchant ID', 'woocommerce'),
                  'default' => '',
                  'desc_tip' => true
    ),
    'Secret' => array(
                  'title' => __('Alpha Bank Secret Code', 'woocommerce'),
                  'type' => 'text',
                  'description' => __('Enter Your Alpha Bank Secret Code', 'woocommerce'),
                  'default' => '',
                  'desc_tip' => true
    ),
    'installmentsActive' => array(
                  'title' => __('Enable installments?', 'woocommerce'),
                  'type' => 'checkbox',
                  'description' => __('Check this to enable installments', 'woocommerce'),
                  'default' => 'no'
    ),
    'autosubmitPaymentForm' => array(
      'title'       => __( 'Auto-submit payment form', 'woocommerce' ),
      'label'       => __( 'Enable', 'woocommerce' ),
      'type'        => 'checkbox',
      'description' => 'If you check this, buyers will be re-directed to the payment gateway automatically. ',
      'default'     => 'no'
    )
    );
  }
  public function emailInstructions( $order, $sentToAdmin ,$plainText = false) {
		if ( $this->instructions && ! $sentToAdmin && $this->id === $order->payment_method ) {
			echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
		}
  }
  public function receiptPage($orderId) {
		echo '<p>' . __('Click the payment button to complete your payment with MasterPass.', 'woocommerce') . '</p>';
		$order = wc_get_order( $orderId );
		$uniqid = uniqid();
						
		$form_data = $this->getAlphaArgs($order, $uniqid, 0);
		$digest = base64_encode(sha1(implode("", array_merge($form_data, array('secret' => $this->Secret))), true));

		$html_form_fields = array();
		foreach ($form_data as $key => $value) {
			$html_form_fields[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr($value).'" />';
		}
		
		?>

		<?php if ( $this->autosubmitPaymentForm ) :?>

		<script type="text/javascript">

		jQuery(document).ready(function(){
  
		    var alphabank_payment_form = document.getElementById('shopform1');
			alphabank_payment_form.style.visibility="hidden";
			alphabank_payment_form.submit();

		});

		<?php endif;?>

		</script>
				<form id="shopform1" name="shopform1" method="POST" action="<?php echo $this->AlphaBankUrl ?>" accept-charset="UTF-8" >
			<?php foreach($html_form_fields as $field)
				echo $field;
			?>
			<input type="hidden" name="digest" value="<?php echo $digest ?>"/>			
			<?php	
				if ($this->InstallmentsActive) {
					$this->installments(wc_format_decimal($order->get_total(), 2, false), $uniqid, $order); 
				}
			?>
			
			<input type="submit" class="button alt" id="submit_twocheckout_payment_form" value="<?php echo __( 'Pay via MasterPass on Alpha bank', 'woocommerce' ) ?>" /> 
			<a class="button cancel" href="<?php echo esc_url( $order->get_cancel_order_url() )?>"><?php echo __( 'Cancel order &amp; restore cart', 'woocommerce' )?></a>
			
		</form>		
		<?php
		
		
		$order->update_status( 'pending', __( 'Sent request to Alpha bank with orderID: ' . $form_data['orderid'] , 'woocommerce' ) );
  }
  public function thankYouPage() {
		if ( $this->instructions ) {
        	echo wpautop( wptexturize( $this->instructions ) );
		}
  }
  public function checkResponse() { 
		$required_response = array(
			'mid' => '',
			'orderid' => '',
			'status' => '',
			'orderAmount' => '',
			'currency' => '',
			'paymentTotal' => ''
		);
		
		$notrequired_response = array(
			'message' => '',
			'riskScore' => '',
			'payMethod' => '',
			'txId' => '',
			'sequence' => '',
			'seqTxId' => '',
			'paymentRef' => '' 
		);
		
		if (!isset($_REQUEST['digest'])){
			wp_die( 'Alpha Bank Request Failure', 'Alpha Bank Gateway', array( 'response' => 500 ) );
		}
		
		foreach ($required_response as $key => $value) {
			if (isset($_REQUEST[$key])){
				$required_response[$key] = $_REQUEST[$key];
			}
			else{
				// required parameter not set 
				wp_die( 'Alpha Bank Request Failure', 'Alpha Bank Gateway', array( 'response' => 500 ) );
			}
		}
		
		foreach ($notrequired_response as $key => $value) {
			if (isset($_REQUEST[$key])){
				$required_response[$key] = $_REQUEST[$key];
			}
			else{
			}
		}

		$string_form_data = array_merge($required_response, array('secret' => $this->Secret));
		$digest = base64_encode(sha1(implode("", $string_form_data), true));
		
		if ($digest != $_REQUEST['digest']){
			wp_die( 'Alpha Bank Digest Error', 'Alpha Bank Gateway', array( 'response' => 500 ) );
		}
		
		if(isset($_REQUEST['cancel'])){
			$order = wc_get_order(wc_clean($_REQUEST['cancel']));
			if (isset($order)){
				$order->add_order_note('Alpha Bank Payment <strong>' . $required_response['status'] . '</strong>. txId: ' . $required_response['txId'] . '. ' . $required_response['message'] );
				wp_redirect( $order->get_cancel_order_url_raw());
				exit();
			}
		}
		else if (isset($_REQUEST['confirm'])){
			$order = wc_get_order(wc_clean($_REQUEST['confirm']));
			if (isset($order)){
				if ($required_response['orderAmount'] == wc_format_decimal($order->get_total(), 2, false)){
					$order->add_order_note('Alpha Bank Payment <strong>' . $required_response['status'] . '</strong>. txId: ' . $required_response['txId'] . '. payMethod: ' . $required_response['payMethod']. '. paymentRef: ' . $required_response['paymentRef'] . '. ' . $required_response['message'] );
					$order->payment_complete('Alpha Bank Payment ' . $required_response['status'] . '. txId: ' . $required_response['txId'] );
					wp_redirect($this->get_return_url( $order ));
					exit();
				}
				else{
					$order->add_order_note('Payment received with incorrect amount. Alpha Bank Payment <strong>' . $required_response['status'] . '</strong>. '. $required_response['message'] );
				}
			}
		}
		
		// something went wrong so die
		wp_die( 'Unspecified Error', 'Payment Gateway error', array( 'response' => 500 ) );
  }
  public function process_payment( $orderId ) {
		$order = wc_get_order( $orderId );

		 return array(
		 	'result' 	=> 'success',
		 	'redirect'	=> $order->get_checkout_payment_url( true ) // $this->get_return_url( $order )
		);
	}
    
}