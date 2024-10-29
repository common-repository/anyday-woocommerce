<?php
use Adm\AnydayPayment;

class WC_Gateway_Anyday_Payment extends WC_Payment_Gateway {

	private $authorization_token;

	/**
	* Constructor for the gateway.
	*/
	public function __construct()
	{
		$this->id                 = 'anyday_payment_gateway';
		$this->icon               = apply_filters('woocommerce_offline_icon', '');
		$this->has_fields         = false;
		$this->method_title       = __( 'Anyday', 'adm' );
		$this->method_description = __( 'A fair and transparent partial payment solution. Split your payment into monthly installments with no interest or fees.', 'adm' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions', $this->description );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		// Customer Emails
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

	}

	/**
	* Initialize Gateway Settings Form Fields
	*/
	public function init_form_fields()
	{
		$this->form_fields = apply_filters( 'wc_offline_form_fields', array(

			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'adm' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Anyday', 'adm' ),
				'default' => 'yes'
			),

			'title' => array(
				'title'       => __( 'Title', 'adm' ),
				'type'        => 'text',
				'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'adm' ),
				'default'     => __( 'Anyday', 'adm' ),
				'desc_tip'    => true,
			),

			'description' => array(
				'title'       => __( 'Description', 'adm' ),
				'type'        => 'textarea',
				'description' => __( 'Description', 'adm' ),
				'default'     => __( 'A fair and transparent partial payment solution. Split your payment into monthly installments with no interest or fees.', 'adm' ),
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __( 'Instructions', 'adm' ),
				'type'        => 'textarea',
				'description' => __( 'Anyday payment instructions.', 'adm' ),
				'default'     => '',
				'desc_tip'    => true,
			),
		) );
	}


	/**
	* Output for the order received page.
	*/
	public function thankyou_page()
	{
		if ( $this->instructions ) {
			echo wpautop( wptexturize( $this->instructions ) );
		}
	}


	/**
	* Add content to the WC emails.
	*
	* @access public
	* @param WC_Order $order
	* @param bool $sent_to_admin
	* @param bool $plain_text
	*/
	public function email_instructions( $order, $sent_to_admin, $plain_text = false )
	{
		if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'pending' ) ) {
			echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
		}
	}


	/**
	* Process the payment and return the result
	*
	* @param int $order_id
	* @return array
	*/
	public function process_payment( $order_id )
	{
		$order = wc_get_order( $order_id );

		$anyday_authorize_payment = new AnydayPayment;

		$successUrl = $this->get_return_url( $order ) . '&anydayPayment=approved';
		$cancelUrl = wc_get_cart_url() . '?orderId=' . $order_id .'&orderKey=' . $order->get_order_key() . '&anydayPayment=rejected';

		$authorize_url = $anyday_authorize_payment->adm_authorize_payment($order, $successUrl, $cancelUrl);

		if( $authorize_url ) {

			wc_reduce_stock_levels( $order_id );

			if( get_option('adm_order_status_before_authorized_payment') != "default" ) {

				$order->update_status( get_option('adm_order_status_before_authorized_payment') );

			}

			$order->add_order_note( __("The payment must be approved in Anyday portal before Captured or Refunded", "adm") );

			return array(
				'result' 	=> 'success',
				'redirect'	=> ADM_API_BASE_URL . $authorize_url
			);

		}
	}

}
