<?php
namespace Adm;

class Settings extends \WC_Settings_Page
{
	public function __construct()
	{
		$this->id    = 'anydaypricetag';
	    $this->label = __( 'Anyday', 'adm' );

	    add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
	    add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
	    add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
	    add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );
			add_action( 'admin_footer', array( $this , 'adm_setting_js' ), 25 );
	}

	private function set_authentication() {
	  if ( get_option('adm_authentication_type') == 'auth_manual' 
			&& !empty(trim(get_option('adm_manual_prod_api_key'))) 
			&& !empty(trim(get_option('adm_manual_test_api_key'))) 
			&& !empty(trim(get_option('adm_private_key')))
		) {
			update_option( 'adm_manual_authenticated', 'true' );
			update_option( 'adm_merchant_authenticated', 'false' );
		} else {
			update_option( 'adm_manual_authenticated', 'false' );
		}
	}

	/**
	 * Define the plugin sections
	 *@method get_sections
	 */
	public function get_sections()
	{
			$this->set_authentication();
			$sections = array(
				'' => __( 'Anyday Merchant Authentication', 'adm' ),
			);
	    $sections['adm_general_setting'] = __( 'Anyday Payment Gateway Settings', 'adm' );
	    $sections['adm_pricetag_settings'] = __( 'Anyday Pricetag Settings', 'adm' );

			if ( get_option('adm_merchant_authenticated') != 'true' 
				&& get_option( 'adm_manual_authenticated' ) != 'true' ) {
				unset( $sections['adm_general_setting'] );
				unset( $sections['adm_pricetag_settings'] );
			}

	    return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}

	/**
	 * Register the plugin settings per section
	 * @method get_settings
	 */
	public function get_settings( $current_section = '' )
	{
		switch ( $current_section ) {
			case 'adm_general_setting':
				return $this->adm_get_payment_gateway_settings( $current_section );
				break;
			case 'adm_pricetag_settings':
				return $this->adm_get_pricetag_settings( $current_section );
				break;
			case '':
				return $this->adm_get_auth_setting( $current_section );
				break;
		}
	}

	/**
	 * function to add activate setting toggle for anyday payment gateway
	 * This setting adds up before actual plugin setting in $this->output function of this file.
	 * @method get_initialize_setting
	 */
	public function get_initialize_setting() {
		$gateway = 
			(WC()->payment_gateways->payment_gateways()['anyday_payment_gateway']) 
			? WC()->payment_gateways->payment_gateways()['anyday_payment_gateway'] 
			: null;
		if(is_null($gateway)) {
			return;
		}
		$method_title = $gateway->get_method_title() ? $gateway->get_method_title() : $gateway->get_title();
		echo '<h2>Anyday Payment Gateway</h2><table class="form-table"><tbody><tr valign="top"><th class="titledesc">Activate</th><td class="forminp">';
		echo '<a class="wc-payment-gateway-method-toggle-enabled" href="' 
		. esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . strtolower( $gateway->id ) ) ) . '">';
		if ( wc_string_to_bool( $gateway->enabled ) ) {
			/* Translators: %s Payment gateway name. */
			echo '<span class="woocommerce-input-toggle woocommerce-input-toggle--enabled" aria-label="' 
			. esc_attr( sprintf( __( 'The "%s" payment method is currently enabled', 'woocommerce' ), $method_title ) ) . '">' 
			. esc_attr__( 'Yes', 'woocommerce' ) . '</span>';
		} else {
			/* Translators: %s Payment gateway name. */
			echo '<span class="woocommerce-input-toggle woocommerce-input-toggle--disabled" aria-label="' 
			. esc_attr( sprintf( __( 'The "%s" payment method is currently disabled', 'woocommerce' ), $method_title ) ) 
			. '">' . esc_attr__( 'No', 'woocommerce' ) . '</span>';
		}
		echo '</a></td></tr></tbody></table>';
	}

	/**
	 * Define the auth plugin settings
	 *@method adm_get_auth_setting
	 */
	private function adm_get_auth_setting() {
		$gateway_settings = array(
			array(
				'name'	=> __( 'Merchant Authentication', 'adm' ),
				'type'	=> 'title',
				'id'	=> 'adm_general_options',
			),
			"authentication_type" => array(
				'type'	=> 'select',
				'id'	=> 'adm_authentication_type',
				'name'	=> __( 'Authentication Type', 'adm' ),
				'options'	=> array(
					'auth_manual'	=> __( 'Manual', 'adm' ),
					'auth_account'	=> __( 'Anyday Merchant Account', 'adm' )
				),
				'class'    => 'wc-enhanced-select',
				'desc_tip' => __( 'Choose a method how to authenticate in order to save the API keys and Pricetag token', 'adm' ),
				'default'  => 'auth_account'
			),
			"merchant_username" => array(),
			"merchant_password" => array(),
			"prod_api_key" => array(),
			"test_api_key" => array(),
			"adm_private_key" => array(),
			array(
				'type' => 'sectionend',
				'id'   => 'pricetag_checkout_page_end'
			)
		);

		$gateway_settings['prod_api_key']['type']	= 'textarea';
		$gateway_settings['prod_api_key']['id']		= 'adm_manual_prod_api_key';
		$gateway_settings['prod_api_key']['name']	= __( 'Anyday Production API key', 'adm' );
		$gateway_settings['test_api_key']['type']	= 'textarea';
		$gateway_settings['test_api_key']['id']		= 'adm_manual_test_api_key';
		$gateway_settings['test_api_key']['name']	= __( 'Anyday Test API key', 'adm' );
		$gateway_settings['merchant_username']['type']		= 'text';
		$gateway_settings['merchant_username']['id']		= 'adm_merchant_username';
		$gateway_settings['merchant_username']['name']		= __( 'Merchant Username', 'adm' );
		$gateway_settings['merchant_username']['desc_tip'] 	= __( 'Enter your Anyday merchant account username', 'adm' );
		$gateway_settings['merchant_password']['type']		= 'password';
		$gateway_settings['merchant_password']['id']		= 'adm_merchant_password';
		$gateway_settings['merchant_password']['name']		= __( 'Merchant Password', 'adm' );
		$gateway_settings['merchant_password']['desc_tip'] 	= __( 'Enter your Anyday merchant account password', 'adm' );
		$gateway_settings['adm_private_key']['type']	= 'text';
		$gateway_settings['adm_private_key']['id']		= 'adm_private_key';
		$gateway_settings['adm_private_key']['name']	= __( 'Anyday Private key', 'adm' );
		$this->set_authentication();

		$settings = apply_filters( 'adm_general_section', $gateway_settings );
		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );
	}

	/**
	 * Define the prictag plugin settings
	 *@method adm_get_pricetag_settings
	 */
	private function adm_get_pricetag_settings( $current_section )
	{
		$settings_array = array(
			array(
				'name'	=> __( 'General Settings', 'adm' ),
				'type'	=> 'title',
				'id'	=> 'adm_general_options',
			),
			array(
				'type'	=> 'text',
				'id'	=> 'adm_manual_pricetag_token',
				'name'	=> __( 'Anyday Pricetag token', 'adm' ),
			),
			array(
				'type'	=> 'select',
				'id'	=> 'adm_language_locale',
				'name'	=> __( 'Language Localization', 'adm' ),
				'options'	=> array(
					'da'	=> __( 'da', 'adm' ),
					'en'	=> __( 'en', 'adm' )
				),
				'class'    => 'wc-enhanced-select',
				'desc_tip' => __( 'Choose the Anyday Pricetag language', 'adm' ),
				'default'  => 'da',
			),
			array(
				'type'	=> 'select',
				'id'	=> 'adm_price_format_locale',
				'name'	=> __( 'Price Format Locale', 'adm' ),
				'options'	=> array(
					'da'	=> __( 'da', 'adm' ),
					'en'	=> __( 'en', 'adm' )
				),
				'class'    => 'wc-enhanced-select',
				'desc_tip' => __( 'Choose the Anyday Pricetag format locale', 'adm' ),
				'default'  => 'da',
			),
			array(
				'type'	=> 'text',
				'id'	=> 'adm_price_tag_limit',
				'name'	=> __( 'Minimum Price Limit', 'adm' ),
				'desc_tip' => __( 'The Anyday Pricetag will appear on all amounts equal to or above the specified limit', 'adm' ),
			),
			array(
				'type'	=> 'textarea',
				'id'	=> 'adm_pricetag_products',
				'name'	=> __( 'Hide On Product Tags', 'adm' ),
				'desc_tip' => __( 'Enter products comma seperated values', 'adm' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'pricetag_general_settings_end'
			),
			array(
				'name'	=> __( 'Product Page', 'adm' ),
				'type'	=> 'title',
				'id'	=> 'adm_product_page_title',
			),
			array(
				'type'	=> 'select',
				'id'	=> 'adm_select_product',
				'name'	=> __( 'Visibility', 'adm' ),
				'options'	=> array(
					'enabled'	=> __( 'Enabled', 'adm' ),
					'disabled'	=> __( 'Disabled', 'adm' )
				),
				'class'    => 'wc-enhanced-select',
				'desc_tip' => __( 'Disable/enable the Anyday Pricetag on product page', 'adm' ),
				'default'  => 'enabled',
			),
			array(
				'type'	=> 'text',
				'id'	=> 'adm_price_tag_product_selector',
				'name'	=> __( 'Position Selector', 'adm' ),
				'desc_tip' => __( 'Choose a CSS selector before which the Anyday Pricetag will be loaded', 'adm' ),
			),
			array(
				'type'	=> 'text',
				'id'	=> 'adm_price_tag_price_product_selector',
				'name'	=> __( 'Product Price Selector', 'adm' ),
				'desc_tip' => __( 'Choose a CSS selector from where the price will be taken', 'adm' ),
			),
			array(
				'type'	=> 'text',
				'id'	=> 'adm_price_tag_sale_price_product_selector',
				'name'	=> __( 'Sale Product Price Selector', 'adm' ),
				'desc_tip' => __( 'Choose a CSS selector from where the price will be taken.', 'adm' ),
			),
			array(
				'type'	=> 'textarea',
				'id'	=> 'adm_pricetag_product_styles',
				'name'	=> __( 'Styles', 'adm' ),
				'desc_tip' => __( 'Enter any valid CSS to update the Anyday Pricetag wrapper element. Pricetag font styles will inherit from these styles if specified.', 'adm' ),
			),
			array(
				'type'	=> 'text',
				'id'	=> 'adm_price_tag_variant_product_selector',
				'name'	=> __( 'Variant Product Position Selector', 'adm' ),
				'desc_tip' => __( 'Choose a CSS selector before which the pricetag will be loaded', 'adm' ),
			),
			array(
				'type'	=> 'text',
				'id'	=> 'adm_price_tag_price_variable_product_selector',
				'name'	=> __( 'Variant Product Price Selector', 'adm' ),
				'desc_tip' => __( 'Choose a CSS selector from where the price will be taken', 'adm' ),
			),
			array(
				'type'	=> 'text',
				'id'	=> 'adm_price_tag_sale_price_variable_product_selector',
				'name'	=> __( 'Sale Variant Product Price Selector', 'adm' ),
				'desc_tip' => __( 'Choose a CSS selector from where the price will be taken', 'adm' ),
			),
			array(
				'type'	=> 'textarea',
				'id'	=> 'adm_pricetag_variant_product_styles',
				'name'	=> __( 'Variant Styles', 'adm' ),
				'desc_tip' => __( 'Enter any valid CSS to update the Anyday Pricetag wrapper element. Pricetag font styles will inherit from these styles if specified.', 'adm' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'pricetag_product_page_end'
			),
			array(
				'name'	=> __( 'Cart Page', 'adm' ),
				'type'	=> 'title',
				'id'	=> 'adm_cart_page_title',
			),
			array(
				'type'	=> 'select',
				'id'	=> 'adm_select_cart',
				'name'	=> __( 'Visibility', 'adm' ),
				'options'	=> array(
					'enabled'	=> __( 'Enabled', 'adm' ),
					'disabled'	=> __( 'Disabled', 'adm' )
				),
				'class'    => 'wc-enhanced-select',
				'desc_tip' => __( 'Disable/enable the Anyday Pricetag on cart page', 'adm' ),
				'default'  => 'enabled',
			),
			array(
				'type'	=> 'text',
				'id'	=> 'adm_price_tag_cart_selector',
				'name'	=> __( 'Position Selector', 'adm' ),
				'desc_tip' => __( 'Choose a CSS selector before which the Anyday Pricetag will be loaded', 'adm' ),
			),
			array(
				'type'	=> 'text',
				'id'	=> 'adm_price_tag_price_cart_selector',
				'name'	=> __( 'Price Selector', 'adm' ),
				'desc_tip' => __( 'Choose a CSS selector from where the price will be taken', 'adm' ),
			),
			array(
				'type'	=> 'textarea',
				'id'	=> 'adm_pricetag_cart_styles',
				'name'	=> __( 'Styles', 'adm' ),
				'desc_tip' => __( 'Enter any valid CSS to update the Anyday Pricetag wrapper element. Pricetag font styles will inherit from these styles if specified.', 'adm' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'pricetag_cart_page_end'
			),
			array(
				'name'	=> __( 'Checkout Page', 'adm' ),
				'type'	=> 'title',
				'id'	=> 'adm_checkout_page_title',
			),
			array(
				'type'	=> 'select',
				'id'	=> 'adm_select_checkout',
				'name'	=> __( 'Visibility', 'adm' ),
				'options'	=> array(
					'enabled'	=> __( 'Enabled', 'adm' ),
					'disabled'	=> __( 'Disabled', 'adm' )
				),
				'class'    => 'wc-enhanced-select',
				'desc_tip' => __( 'Disable/enable the Anyday Pricetag on checkout page', 'adm' ),
				'default'  => 'enabled',
			),
			array(
				'type'	=> 'text',
				'id'	=> 'adm_price_tag_checkout_selector',
				'name'	=> __( 'Position Selector', 'adm' ),
				'desc_tip' => __( 'Choose a CSS selector before which the Anyday Pricetag will be loaded', 'adm' ),
			),
			array(
				'type'	=> 'text',
				'id'	=> 'adm_price_tag_price_checkout_selector',
				'name'	=> __( 'Price Selector', 'adm' ),
				'desc_tip' => __( 'Choose a CSS selector from where the price will be taken', 'adm' ),
			),
			array(
				'type'	=> 'textarea',
				'id'	=> 'adm_pricetag_checkout_styles',
				'name'	=> __( 'Styles', 'adm' ),
				'desc_tip' => __( 'Enter any valid CSS to update the Anyday Pricetag wrapper element. Pricetag font styles will inherit from these styles if specified.', 'adm' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'pricetag_checkout_page_end'
			)
			);
		if (get_option('adm_authentication_type') == 'auth_account') {
			unset($settings_array[1]);
		}
		$settings = apply_filters( 'adm_general_section', $settings_array );
		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
	}

	/**
	 * Define the plugin paymnet gateway settings
	 *@method adm_get_payment_gateway_settings
	 */
	private function adm_get_payment_gateway_settings( $current_section )
	{
		$gateway_settings = array(
			array(
				'name'	=> __( 'General Settings', 'adm' ),
				'type'	=> 'title',
				'id'	=> 'adm_general_options',
			),
			array(
				'type'	=> 'select',
				'id'	=> 'adm_environment',
				'name'	=> __( 'Mode', 'adm' ),
				'options'	=> array(
					'live'	=> __( 'Live', 'adm' ),
					'test'	=> __( 'Test', 'adm' )
				),
				'class'    => 'wc-enhanced-select',
				'desc_tip' => __( 'Choose Anyday Environment', 'adm' ),
				'default'  => 'live',
			),
			array(
				'type'	=> 'select',
				'id'	=> 'adm_module_error_log',
				'name'	=> __( 'Error Log', 'adm' ),
				'options'	=> array(
					'enabled'	=> __( 'Enabled', 'adm' ),
					'disabled'	=> __( 'Disabled', 'adm' )
				),
				'class'    => 'wc-enhanced-select',
				'desc_tip' => __( 'Log each Anyday API error in a debug.log file which is located in the plugin root directory', 'adm' ),
				'default'  => 'disabled',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'pricetag_checkout_page_end'
			),
			array(
				'name'	=> __( 'Order Statuses', 'adm' ),
				'type'	=> 'title',
				'id'	=> 'adm_payment_gateway_order_statuses',
			),
			"adm_order_status_before_authorized_payment" => array(
				'type'	=> 'select',
				'id'	=> 'adm_order_status_before_authorized_payment',
				'name'	=> __( 'Before Payment is Authorized', 'adm' ),
				'options'	=> array(
					'default'	=> __( 'Default', 'adm' )
				),
				'class'    => 'wc-enhanced-select',
				'desc_tip' => __( 'Choose an Order Status for the Woocommerce order before the payment is authorized in Anyday portal', 'adm' ),
				'default'  => 'default',
			),
			"adm_order_status_after_authorized_payment" => array(
				'type'	=> 'select',
				'id'	=> 'adm_order_status_after_authorized_payment',
				'name'	=> __( 'After Payment is Authorized', 'adm' ),
				'options'	=> array(
					'default'	=> __( 'Default', 'adm' )
				),
				'class'    => 'wc-enhanced-select',
				'desc_tip' => __( 'Choose an Order Status for the Woocommerce order after the payment is authorized in Anyday portal', 'adm' ),
				'default'  => 'default',
			),
			"adm_order_status_after_captured_payment" => array(
				'type'	=> 'select',
				'id'	=> 'adm_order_status_after_captured_payment',
				'name'	=> __( 'After Payment is Captured', 'adm' ),
				'options'	=> array(
					'default'	=> __( 'Default', 'adm' )
				),
				'class'    => 'wc-enhanced-select',
				'desc_tip' => __( 'Choose an Order Status for the Woocommerce order after the payment is fully captured in the order details page', 'adm' ),
				'default'  => 'default',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'order_statuses_section_end'
			)
		);

		$order_statuses = wc_get_order_statuses();

		foreach( $order_statuses as $key => $order_status ) {

			if( $key != "wc-refunded" ) {
				$gateway_settings['adm_order_status_before_authorized_payment']['options'][$key] = $order_status;
				$gateway_settings['adm_order_status_after_authorized_payment']['options'][$key] = $order_status;
				$gateway_settings['adm_order_status_after_captured_payment']['options'][$key] = $order_status;
			}
			
		}

		$settings = apply_filters( 'adm_general_section', $gateway_settings );

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
	}

	/**
	 * Output the plugin settings
	 *@method output
	 */
	public function output()
	{
	    global $current_section;
	    $settings = $this->get_settings( $current_section );
			if($current_section == 'adm_general_setting')
				$this->get_initialize_setting();
	    \WC_Admin_Settings::output_fields( $settings );
	}

	/**
	 * Save the plugin settings
	 *@method save
	 */
	public function save() {

		global $current_section;

		$settings = $this->get_settings( $current_section );

		\WC_Admin_Settings::save_fields( $settings );

		if ( $current_section == "" && get_option('adm_authentication_type') == 'auth_account' ) {

			$auth = new MerchantAuthentication;

			$merchant_authentication = $auth->adm_merchant_authenticate( $current_section, $settings );

			if ( $merchant_authentication === true ) {

				update_option( 'adm_merchant_authenticated', 'true' );
				update_option( 'adm_manual_authenticated', 'false' );
				add_action( 'admin_notices', function() {
							echo '<div id="message" class="notice notice-success is-dismissible"><p><strong>'
							. __( "Merchant authenticaton successful.", "adm" ) .'</strong></p></div>';
					});
	
			} else {
				update_option( 'adm_merchant_authenticated', 'false' );
				add_action( 'admin_notices', function() use ( $merchant_authentication ) {
							echo '<div id="message" class="notice notice-error is-dismissible">
							<p><strong>'. __( "An error occurred. Please contact Anyday support.", "adm" ) .'</strong></p>
							<p>'. __( sanitize_text_field( $merchant_authentication ), "adm" ) .'</p>
							</div>';
					});
			}

			update_option( 'adm_merchant_password', 'Silence' );


		} elseif ( $current_section == "" && get_option('adm_authentication_type') == 'auth_manual' ) {
			if( !empty(get_option('adm_authentication_type')) && !empty(get_option('adm_authentication_type')) ) {
				update_option( 'adm_manual_authenticated', 'true' );
				update_option( 'adm_merchant_authenticated', 'false' );
			}
		}

	}

	public function adm_setting_js() {
		?>
		<script type="text/javascript" id="adm_setting_js">
			(function () {
				function toggle_auth_setting(isonload = false) {
					if(jQuery('#adm_authentication_type').val() == 'auth_manual') {
						jQuery('#adm_merchant_username').closest('tr').hide();
						jQuery('#adm_merchant_password').closest('tr').hide();
						if(!isonload) {
							jQuery('#adm_manual_prod_api_key').val('');
							jQuery('#adm_manual_test_api_key').val('');
							jQuery('#adm_private_key').val('');
						}
						jQuery('#adm_manual_prod_api_key').closest('tr').show();
						jQuery('#adm_manual_test_api_key').closest('tr').show();
						jQuery('#adm_private_key').closest('tr').show();
					} else {
						jQuery('#adm_merchant_username').closest('tr').show();
						jQuery('#adm_merchant_password').closest('tr').show();
						jQuery('#adm_manual_prod_api_key').closest('tr').hide();
						jQuery('#adm_manual_test_api_key').closest('tr').hide();
						jQuery('#adm_private_key').closest('tr').hide();
					}
				}
				toggle_auth_setting(true);
				jQuery('#adm_authentication_type').change(function() {
					toggle_auth_setting(false);
				});
			})();
		</script>
	<?php
	}
}
