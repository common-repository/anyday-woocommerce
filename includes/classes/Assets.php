<?php
namespace Adm;

class Assets
{
    public function __construct()
    {
        add_action( 'wp_enqueue_scripts', array( $this, 'adm_enque_public_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'adm_enqueue_admin_scripts' ) );
    }

    public function adm_enqueue_admin_scripts()
    {
    	wp_enqueue_script( 'anyday-admin-javascript', ADM_URL . 'assets/admin/js/anyday-admin.js', array(), false, true );
        wp_localize_script( 'anyday-admin-javascript', 'anyday', array(
            "ajaxUrl" => admin_url( 'admin-ajax.php' ),
            "capturePrompt" => __( "Enter an amount to be captured. You may enter an integer e.g. 1500 or 250, or you may enter decimals e.g. 1.500,00 or 250,00. If you enter a decimal in the thousandths place, you must use two decimals. e.g. 1.500,00", "adm" ),
            "capturePromptValidation" => __( "Please enter numeric value!", "adm" ),
            "cancelConfirmation" => __( "Are you sure you want to cancel this order? This action cannot be undone.", "adm" ),
            "refundConfirmation" => __( "Are you sure you want to refund this order? This action cannot be undone. Enter an amount to be refunded. You may enter an integer e.g. 1500 or 250, or you may enter decimals e.g. 1.500,00 or 250,00. If you enter a decimal in the thousandths place, you must use two decimals. e.g. 1.500,00", "adm" )
        ));

        wp_enqueue_style( 'anyday-admin-stylesheet', ADM_URL . 'assets/admin/css/anyday-admin.css' );
    }

    public function adm_enque_public_scripts()
    {
        $position_selector = '';
        $product = wc_get_product( get_the_ID() );
        
        if ( is_product() && $product->is_type( 'variable' ) === false && !empty( get_option('adm_price_tag_product_selector') ) ) {
            $position_selector = get_option('adm_price_tag_product_selector');
        }elseif ( is_product() && $product->is_type( 'variable' ) === true && !empty( get_option('adm_price_tag_variant_product_selector') ) ) {
            $position_selector = get_option('adm_price_tag_variant_product_selector');
        }elseif ( is_cart() && !empty( get_option('adm_price_tag_cart_selector') ) ) {
            $position_selector = get_option('adm_price_tag_cart_selector');
        }elseif ( is_checkout() && !empty( get_option('adm_price_tag_checkout_selector') ) ) {
            $position_selector = get_option('adm_price_tag_checkout_selector');
        }

        wp_enqueue_script( 'anyday-public-javascript', ADM_URL . 'assets/public/js/anyday-public.js', array(), false, true );
        wp_localize_script( 'anyday-public-javascript', 'anyday', array(
            "positionSelector" => $position_selector,
            "limit" => get_option('adm_price_tag_limit')
        ));

        wp_enqueue_style( 'anyday-public-stylesheet', ADM_URL . 'assets/public/css/anyday-public.css' );

    }
}