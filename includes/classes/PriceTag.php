<?php
namespace Adm;

class PriceTag
{
	public function __construct()
	{
		add_action( 'wp_enqueue_scripts', array( $this, 'adm_inject_anyday_split_script' ) );
		add_filter( 'woocommerce_before_add_to_cart_button', array( $this, 'adm_append_anyday_price_tag' ) );
		add_filter( 'woocommerce_proceed_to_checkout', array( $this, 'adm_append_anyday_price_tag' ) );
		add_filter( 'woocommerce_review_order_before_payment', array( $this, 'adm_append_anyday_price_tag' ) );
		add_action( 'wp_head', array( $this, 'adm_price_tag_styles' ), 100 );
		add_filter( 'script_loader_tag', array( $this, 'adm_add_data_attribute' ), 10, 2 );
	}

	/**
	 * Add data attribute to a script
	 * @method adm_add_data_attribute
	 */
	public function adm_add_data_attribute( $tag, $handle )
	{
		if ( 'anyday-split-script' !== $handle ) {
			return $tag;
		}

	   return str_replace( ' src', ' async type="module" src', $tag );
	}

	/**
	 * Load AnyDay JavaScript used for the price tag
	 * @method adm_inject_anyday_split_script
	 */
	public function adm_inject_anyday_split_script()
	{
		if( !$this->checkPluginConditions() ) return;

		wp_enqueue_script( 'anyday-split-script', $this->get_price_tag_js_url(), array(), get_option('adm_pricetag_js_version'), true);
	}

	private function validLimit() {

		$limit = intval( trim(get_option('adm_price_tag_limit')) );
		if(!$limit)
			return true;

		if(is_product()) {
			$product = wc_get_product( get_the_ID() );

			if($product->is_on_sale())
				$price = $product->get_sale_price();
			else
				$price = $product->get_regular_price();
				
			if($product->is_type( 'variable' )) {
				$variations = $product->get_available_variations();
				$first_variation_prices = [];
				foreach ( $variations as $key => $variation ) {
					$first_variation_prices[] = $variation['display_price'];
				}
				$price = max($first_variation_prices);
			}
			if( $price >= $limit ) {
				return true;
			}
			return false;
		} elseif(is_cart() || is_checkout()) {
			if( (float)WC()->cart->total >= $limit ) {
				return true;
			}
			return false;
		}
	}

	private function hideProducts() {
		if(get_option('adm_pricetag_products') == "")
			return false;
		$tags = explode(',', trim(str_replace(' ', '', get_option('adm_pricetag_products')), ''));
		if($tags[0] == "")
			return true;
		if(is_product() && count($tags)) {
			$current_tags = get_the_terms( get_the_ID(), 'product_tag' );
			if ( $current_tags && ! is_wp_error( $current_tags ) ) { 
					foreach ($current_tags as $tag) {
						if(in_array($tag->name, $tags))
							return true;
					}
			}
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Inject the html price tag
	 * @method adm_append_anyday_price_tag
	 * @return  html
	 */
	public function adm_append_anyday_price_tag()
	{
		if( (is_product() && $this->hideProducts())
			|| !$this->checkPluginConditions()
			|| !$this->validLimit() ) 
			return;
		$currency = get_option('woocommerce_currency');
		$lang_locale = get_option('adm_price_format_locale');
		$visibility = ($this->checkPricetagPositonSelector()) ? "display:none" : "display:block";
		$token = $this->getToken();
		$price = $this->getPriceTag();
		try {
			if ( $currency == ADM_CURRENCY ) {
				echo sprintf( '<div 
					class="anyday-price-tag-style-wrapper 
					anyday-price-tag-style-wrapper--price">
						<anyday-price-tag 
							style="%s" 
							%s 
							price-tag-token="%s"
							currency="%s"
							price-format-locale="%s"
							environment="production">
						</anyday-price-tag>
					</div>', $visibility, $price, $token, $currency, $lang_locale );
			}
		} catch (Exception $e) {
			//ignore error
		}
	}

	/**
	 * Load inline css style for the price tag
	 * @method adm_price_tag_styles
	 * @return html
	 */
	public function adm_price_tag_styles()
	{
		$product = wc_get_product( get_the_ID() );

		if( !is_admin() ) {
			if( is_product()  && !$product->is_type( 'variable' ) ) {
				echo sprintf( "<style>.anyday-price-tag-style-wrapper{%s}</style>", get_option('adm_pricetag_product_styles') );
			}elseif ( is_product()  && $product->is_type( 'variable' ) ) {
				echo sprintf( "<style>.anyday-price-tag-style-wrapper{%s}</style>", get_option('adm_pricetag_variant_product_styles') );
			}elseif ( is_cart() ) {
				echo sprintf( "<style>.anyday-price-tag-style-wrapper{%s}</style>", get_option('adm_pricetag_cart_styles') );
			}elseif ( is_checkout() ) {
				echo sprintf( "<style>.anyday-price-tag-style-wrapper{%s}</style>", get_option('adm_pricetag_checkout_styles') );
			}
		}
	}

	/**
	 * Check where the price tag must be loaded
	 * @method checkPluginConditions
	 * @return bool
	 */
	private function checkPluginConditions()
	{
		if( (is_product() && get_option('adm_select_product') == 'enabled') || (is_cart() && get_option('adm_select_cart') == 'enabled') || (is_checkout() && get_option('adm_select_checkout') == 'enabled'))

			return true;
	}

	/**
	 * Check the pricetag selector position
	 * @method checkPricetagSelectorPositon
	 * @return bool
	 */
	private function checkPricetagPositonSelector()
	{
		if( is_product() && !empty( get_option('adm_price_tag_product_selector') ) || is_cart() && !empty( get_option('adm_price_tag_cart_selector') ) || is_checkout() && !empty( get_option('adm_price_tag_checkout_selector') ) ) {
			return true;
		}
	}

	/**
	 * Get the pricetag price selectors
	 * @method getPriceSelector
	 */
	private function getPriceSelector( $product )
	{
		$isOnSale = is_product() && $product->is_on_sale();
		$isSimple = is_product() && !$product->is_type( 'variable' );
		$isVariable = is_product() && $product->is_type( 'variable' );

		if ( $isSimple && !$isOnSale && !empty( get_option('adm_price_tag_price_product_selector') ) ) {
			return get_option('adm_price_tag_price_product_selector');
		} elseif ( $isVariable && !$isOnSale && !empty( get_option('adm_price_tag_price_product_selector') ) ) {
			return get_option('adm_price_tag_price_variable_product_selector');
		} elseif ($isVariable && !empty( get_option('adm_price_tag_sale_price_variable_product_selector') )) {
			return get_option('adm_price_tag_sale_price_variable_product_selector');
		} elseif ($isSimple && !empty( get_option('adm_price_tag_sale_price_product_selector') )){
			return get_option('adm_price_tag_sale_price_product_selector');
		}elseif ( is_cart() && !empty( get_option('adm_price_tag_price_cart_selector') ) ) {
			return get_option('adm_price_tag_price_cart_selector');
		}elseif ( is_checkout() && !empty( get_option('adm_price_tag_price_checkout_selector') ) ) {
			return get_option('adm_price_tag_price_checkout_selector');
		} else {
			return false;
		}
	}

	/**
	 * Load the plugin languages based on user choice, if nothing
	 * matches what is  provided in the $supported_languages array
	 * it loads the default choice from the plugin settings
	 * @method getPluginLocale
	 */
	private function getPluginLocale()
	{
		$supported_languages = array();
		$lang_locale = substr(get_locale(), 0, 2);

		if( in_array($lang_locale, $supported_languages) ) {
			return $lang_locale;
		} else {
			return get_option('adm_language_locale');
		}
	}

	/**
	 * Get token which is stored in the configuration according
	 * to Authentication type setting.
	 * @method getToken
	 * @return string
	 */
	private function getToken() 
	{
		if ( get_option('adm_authentication_type') == 'auth_manual' ) {
			return get_option('adm_manual_pricetag_token');
		} elseif( get_option('adm_authentication_type') == 'auth_account' ) {
			return get_option('adm_pricetag_token');
		}
	}

	/**
	 * Get a price tag html depending on the product type and page on which this book is invoked.
	 * @method getPriceTag
	 * @return html
	 */
	public function getPriceTag()
	{
		$product = wc_get_product( get_the_ID() );
		$price = null;

		if( $this->getPriceSelector( $product ) ) {
			if ( is_product() && $product->is_type( 'variable' ) ) {
				$on_sale = false;
				$variations = $product->get_available_variations();
				foreach ( $variations as $key => $variation ) {
					if(wc_get_product($variation['variation_id'])->is_on_sale()) {
						$on_sale= true;
					}
				}
				if($on_sale) {
					$price = (!empty(trim(get_option('adm_price_tag_sale_price_variable_product_selector')))) ? 'total-price-selector="'.get_option('adm_price_tag_sale_price_variable_product_selector').'"' : 'total-price-selector=".woocommerce-Price-amount.amount"';
				} else {
					$price = (!empty(trim(get_option('adm_price_tag_price_variable_product_selector')))) ? 'total-price-selector="'.get_option('adm_price_tag_price_variable_product_selector').'"'  : 'total-price-selector=".woocommerce-Price-amount.amount"';
				}
			}
		} else {
			if( is_product() ) {
				$price = ($product->get_sale_price())
					? $product->get_sale_price()
					: $product->get_regular_price();
			} else {
				$price = (float)WC()->cart->total;
			}
			$price = 'total-price="'.$price.'"';
		}
		if($price === null) {
			$price = 'total-price-selector="' . $this->getPriceSelector( $product ) . '"';
		}

		return $price;
	}

	/**
	 * This function caches the external JS file to plugin directory. Each version of cached 
	 * file refreshed on a new day which is calculated from PHP date.
	 * @return string
	 */
	public function get_price_tag_js_url() {
		$locale = $this->getPluginLocale();
		$actual_version = get_option('adm_pricetag_js_version');
		$expected_version = date('Ymd');
		$url = ADM_PLUGIN_PATH.'assets/public/js/anyday-price-tag-';
		if(empty($actual_version) || $expected_version !== $actual_version) {
			foreach(array('en', 'da') as $lang) {
				$file_url = 'https://my.anyday.io/webshopPriceTag/anyday-price-tag-'.$lang.'-es2015.js';
				$fp = fopen($url.$lang.'.js', "w+");
				$ch = curl_init($file_url);
				curl_setopt($ch, CURLOPT_FILE, $fp);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				curl_exec($ch);
				$st_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				if($st_code !== 200) {
					fclose($fp);
					break;
				}
				curl_close($ch);
				fclose($fp);
			}
			if ($st_code === 200) {
				update_option('adm_pricetag_js_version', $expected_version);
			} else {
				update_option('adm_pricetag_js_version', '');
			}
		} 
		return ADM_URL . 'assets/public/js/anyday-price-tag-'.$locale.'.js';
	}
}