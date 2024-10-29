<?php
namespace Adm;

class Deactivator
{
    public function deactivate()
    {
	update_option( 'adm_merchant_authenticated', 'false' );
	update_option( 'adm_manual_authenticated', 'false' );
    	update_option( 'adm_manual_prod_api_key', '' );
    	update_option( 'adm_manual_test_api_key', '' );
    	update_option( 'adm_manual_pricetag_token', '' );
    	update_option( 'adm_authentication_type', 'auth_account' );
    	update_option( 'adm_merchant_password', '' );
	update_option( 'adm_pricetag_js_version', '' );
    }
}