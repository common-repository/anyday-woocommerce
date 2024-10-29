<?php
namespace Adm;

class Core
{
	protected $plugin_name;
	protected $version;

    public function __construct()
    {
    $this->version = ADM_VERSION;
		$this->plugin_name = 'vconnect-anyday-module';

		add_filter( 'woocommerce_get_settings_pages', array( $this, 'adm_add_settings' ), 15 );
		if( get_option('woocommerce_currency') == ADM_CURRENCY ) {
			add_filter( 'woocommerce_payment_gateways', array( $this, 'anyday_payment_gateway_add_to_gateways') );
			add_filter( 'woocommerce_gateway_icon', array( $this, 'adm_payment_gateway_icons' ), 10, 2 );
		}
		add_filter( 'plugin_action_links_' . $this->plugin_name, array( $this, 'anyday_payment_gateway_plugin_links' ) );
		add_action( 'admin_init', array( $this, 'adm_load_plugin' ) );
		add_filter( "plugin_action_links_" . ADM_PLUGIN_BASE_NAME, array( $this, 'adm_plugin_settings_link' ) );

		new Assets();
		new PriceTag();
		new MerchantAuthentication();
		new AnydayWooOrder();
		new AnydayPayment();
		new AnydayRest();

		add_action( 'admin_init', array( $this, 'adm_admin_notices' ) );
    }

    /**
	 * Add settings link to the plugin description on the plugins pages
	 *@method adm_plugin_settings_link
	 */
	public function adm_plugin_settings_link( $links )
	{
		$settings_link = '<a href="'. home_url() . '/wp-admin/admin.php?page=wc-settings&tab=anydaypricetag">Settings</a>';

		array_unshift( $links, $settings_link );

		return $links;

	}

	/**
	 * Show admin notices
	 *@method adm_admin_notices
	 */
    public function adm_admin_notices()
    {
			if( get_option('adm_authentication_type') == 'auth_manual' && get_option('adm_merchant_authenticated') == 'false' && (empty(get_option('adm_manual_prod_api_key')) || empty(get_option('adm_manual_test_api_key')) || empty(get_option('adm_manual_pricetag_token')) ) ) {

				add_action( 'admin_notices', function() {
							echo '<div id="message" class="notice notice-warning">
							<p><strong>'. __( "The Anyday Production API key, Anyday Test API key and Anyday Pricetag token fields are mandatory. Please make sure to save the correct values. In case you do not have them contact Anyday support. ", "adm" ) .'</strong></p>
							</div>';
					});

			}

			if ( strpos(esc_url_raw($_SERVER['REQUEST_URI']), 'tab=anydaypricetag') !== false ) {

				if( get_option('adm_manual_authenticated') == 'true'
					&& !empty(get_option('adm_manual_prod_api_key'))
					&& !empty(get_option('adm_manual_test_api_key'))
					&& !empty(get_option('adm_private_key'))
				) {

					add_action( 'admin_notices', function() {
						echo '<div id="message" class="notice notice-warning">
						<p><strong>'. __( "You have authenticated manually!", "adm" ) .'</strong></p>
						</div>';
					});

				} elseif ( get_option('adm_merchant_authenticated') == 'true' ) {

					add_action( 'admin_notices', function() {
						echo '<div id="message" class="notice notice-warning">
						<p><strong>'. __( "You have authenticated with your Anyday merchant account!", "adm" ) .'</strong></p>
						</div>';
					});

				}

				if(
						(
							$_SERVER['REQUEST_METHOD'] === 'GET'
							&& get_option('adm_merchant_authenticated') === 'false'
							&& get_option('adm_manual_authenticated') === 'false'
						)
						||
						(
							get_option('adm_authentication_type') === 'auth_manual'
							&& (
								empty(get_option('adm_manual_prod_api_key'))
								|| empty(get_option('adm_manual_test_api_key'))
								|| empty(get_option('adm_private_key'))
							)
						)
					) {
					add_action( 'admin_notices', function() {
						echo '<div id="message" class="notice notice-error">
						<p><strong>'. __( "Anyday plugin needs authentication!", "adm" ) .'</strong></p>
						</div>';
					});
				}

			}


			if( get_option('adm_environment') == 'test' ) {

				add_action( 'admin_notices', function() {
							echo '<div id="message" class="notice notice-warning">
							<p><strong>'. __( "Your Anyday environment is set to Test Mode. Do not fulfill or ship any orders placed with Anyday! Change to Live Mode to begin accepting orders:", "adm" ) . ' <a href="' . admin_url( 'admin.php?page=wc-settings&tab=anydaypricetag&section' ) . '">'. __( "Anyday Payment Gateway Settings", "adm" ) .'</a></strong></p>
							</div>';
					});

			}
    }

    /**
     * Load the plugin settings
     *@method adm_add_settings
     */
	public function adm_add_settings($settings)
	{
		new Settings();
		return $settings;
	}

	/**
     * Register the Anyday payment gateway
     *@method anyday_payment_gateway_add_to_gateways
     */
    public function anyday_payment_gateway_add_to_gateways( $gateways )
	{
		if((WC()->cart && (float)WC()->cart->total >= 300 && (float)WC()->cart->total <= 30000) || is_admin()) {
			$gateways[] = 'WC_Gateway_Anyday_Payment';
		}

		return $gateways;
	}

	/**
	 * Create link for the Anyday Payment Gateway configurations
	 *@method anyday_payment_gateway_plugin_links
	 *@return array
	 */
	public function anyday_payment_gateway_plugin_links( $links )
	{
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=anyday_payment_gateway' ) . '">' . __( 'Configure', 'adm' ) . '</a>'
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Load the login form upon plugin activation
	 *@method adm_load_plugin
	 *@return html
	 */
	public function adm_load_plugin()
	{
	    if ( is_admin() && get_option( 'activated_plugin' ) == ADM_PLUGIN_SLUG ) {

	        delete_option( 'activated_plugin' );

	        wp_redirect( home_url() . "/wp-admin/admin.php?page=wc-settings&tab=anydaypricetag&section" );

	        exit;
	    }
	}

	/**
	 * Add Anyday logo in the checkout payment method
	 *@method adm_payment_gateway_icons
	 */
	public function adm_payment_gateway_icons( $icon, $gateway_id )
	{

    foreach( WC()->payment_gateways->get_available_payment_gateways() as $gateway ) {

    	if( $gateway->id == $gateway_id ) {

            $title = $gateway->get_title();

            break;
        }
    }

    if( $gateway_id == 'anyday_payment_gateway' )
	    $icon = '<img src="'. ADM_URL .'assets/public/images/ANYDAY-Split-Logo-Black-SVG.svg" />';

	    return $icon;
	}
}
