<?php
namespace Adm;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7;

class AnydayPayment
{
	private $client;
	private $authorization_token;
	private $headers;

	public function __construct()
	{
		// Capture AnyDay payment
		add_action( 'wp_ajax_adm_capture_payment', array( $this, 'adm_capture_payment' ) );
		add_action( 'wp_ajax_nopriv_adm_capture_payment', array( $this, 'adm_capture_payment' ) );

		// Cancel AnyDay payment
		add_action( 'wp_ajax_adm_cancel_payment', array( $this, 'adm_cancel_payment' ) );
		add_action( 'wp_ajax_nopriv_adm_cancel_payment', array( $this, 'adm_cancel_payment' ) );

		// Refund AnyDay payment
		add_action( 'wp_ajax_adm_refund_payment', array( $this, 'adm_refund_payment' ) );
		add_action( 'wp_ajax_nopriv_adm_refund_payment', array( $this, 'adm_refund_payment' ) );

		$environment = get_option('adm_environment');

		$this->authorization_token = $this->adm_get_api_key( $environment );

		$this->headers = [
			'Content-Type' => 'application/json',
			'Authorization' => 'Bearer ' .  $this->authorization_token
		];
		$this->client = new Client();
	}

	/**
	 * Get the API key bases on the enviroment and authentication type
	 */
	private function adm_get_api_key( $environment )
	{
		if ( get_option('adm_authentication_type') === 'auth_manual' ) {

			if( get_option('adm_manual__authenticated') === 'false' ) {
				return '';
			}

			if ( $environment == 'live' ) {

				return get_option('adm_manual_prod_api_key');

			} elseif ( $environment == 'test' ) {

				return get_option('adm_manual_test_api_key');

			}

		} elseif ( get_option('adm_authentication_type') === 'auth_account' ) {

			if( get_option('adm_merchant_authenticated') === 'false' ) {
				return '';
			}

			if ( $environment == 'live' ) {

				return get_option('adm_api_key');

			} elseif ( $environment == 'test' ) {

				return get_option('adm_test_api_key');

			}
		}
	}

	/**
	 * Authorize a payment / bulk payments
	 *@method adm_authorize_payment
	 *@param  object $order
	 */
	public function adm_authorize_payment($order, $successURL, $cancelURL)
	{
		
		try {

			$secret_key = get_option('adm_private_key');

			$body = [
				"headers" => $this->headers,
				"json" => [
				"amount" => $order->get_total(),
				"currency" => get_option('woocommerce_currency'),
				"orderId" => $order->get_order_number(),
				"successRedirectUrl" => $successURL,
				"cancelRedirectUrl" => $cancelURL,
				"refererUrl" => get_site_url()
				]
			];

			if(!empty($secret_key)) {
				$body["json"]["callbackUrl"] = get_site_url(null, $this->getWebhookPath());
			}

			$response = $this->client->request('POST', ADM_API_BASE_URL . ADM_API_ORDERS_BASE_PATH, $body);


			$response = json_decode( $response->getBody()->getContents() );

			if( $response->errorCode === 0 ) {

				update_post_meta( $order->get_id(), 'anyday_payment_transaction', wc_clean( $response->purchaseOrderId ) );
				update_post_meta( $order->get_id(), 'anyday_payment_last_status', ANYDAY_STATUS_PENDING );
				$this->handled( $order, $response->purchaseOrderId );

				return $response->checkoutUrl;
			}

		} catch ( RequestException $e ) {

			$this->adm_log_anyday_error( Psr7\str( $e->getResponse() ) );

			$order->update_status( 'failed', __( 'Anyday payment failed!', 'adm' ) );

		}
	}

	/**
	 * Capture a payment / bulk payments
	 *@method adm_capture_payment
	 *@return json
	 */
	public function adm_capture_payment($order_id = null, $amount = null, $isWooCommerce = false)
	{
		$success = false;
		$id = ($order_id) ? $order_id : $_POST['orderId'];
		$order = wc_get_order( $id );
		$amount = ($amount) ? $amount : $_POST['amount'];
		$response = $this->adm_api_capture( $order, $amount );

		if ( $response ) {

			if ( !$this->handled( $order, $response->transactionId ) ) {
				update_post_meta($order->get_id(), 'anyday_payment_last_status', ANYDAY_STATUS_CAPTURE);
				update_post_meta( $order->get_id(), $response->transactionId . '_anyday_captured_payment', wc_clean( $amount ) );
				$message =  __( 'Anyday: Payment captured successful.<br/>An amount %1$s %2$s has been captured.', 'adm' );
				$order->add_order_note(
					sprintf(
						wp_kses( $message, array( 'br' => array() ) ),
						number_format($amount, 2, ',', '.'),
						$order->get_currency()
					)
				);

				if(!$isWooCommerce) {
					if( get_option('adm_order_status_after_captured_payment') != "default" ) {

						$order->update_status( get_option('adm_order_status_after_captured_payment') );

					} else {

						$order->update_status( 'completed' );

					}
				}
				$order->add_order_note( __( date("Y-m-d, h:i:sa") . ' - Captured amount: ' . number_format($amount, 2, ',', '.') . get_option('woocommerce_currency'), 'adm') );
				}
			if( $isWooCommerce )
				return true;
			$success = true;
		}

		if($order_id) {
			return ($success) ? true : false;
		} else {
			if($success) {
				echo json_encode( ["success" => __('Anyday payment successfully captured.', 'adm')] ) ;
			} else {
			  echo json_encode( ["error" => __('Payment could not be captured. Please contact Anyday support.', 'adm')] );
			}
			exit;
		}
	}

	/**
	 * Request to Anyday API to capture a payment amount
	 *@method adm_api_capture
	 */
	public function adm_api_capture( $order, $amount )
	{
		try {

			$response = $this->client->request('POST', ADM_API_BASE_URL . ADM_API_ORDERS_BASE_PATH . '/' . get_post_meta( $order->get_id(), 'anyday_payment_transaction' )[0] . '/capture', [
				'headers' => $this->headers,
			    "json" => [
					"amount" => (float)$amount
			  ]
			]);

			$response = json_decode( $response->getBody()->getContents() );

			if( $response->errorCode === 0 )

			return $response;

		} catch ( RequestException $e ) {

			$this->adm_log_anyday_error( Psr7\str($e->getResponse()) );

		}
	}

	/**
	 * Cancel a payment / bulk payments
	 *@method adm_cancel_payment
	 *@return json
	 */
	public function adm_cancel_payment($order_id = null, $isWooCommerce = false)
	{
		$success = false;
		$id = ($order_id) ? $order_id : $_POST['orderId'];
		$order = wc_get_order( $id );

		try {

			$response = $this->client->request('POST', ADM_API_BASE_URL . ADM_API_ORDERS_BASE_PATH . '/' . get_post_meta( $order->get_id(), 'anyday_payment_transaction' )[0] . '/cancel', [
				'headers' => $this->headers
			]);

			$response = json_decode( $response->getBody()->getContents() );

			if( $response->errorCode === 0 ) {
				if (!$this->handled($order, $response->transactionId)) {
					update_post_meta($order->get_id(), 'anyday_payment_last_status', ANYDAY_STATUS_CANCEL);
					wc_increase_stock_levels($order->get_id());

					$comment = __('Anyday payment cancelled!', 'adm');

					if(!$isWooCommerce)
						$order->update_status('cancelled', $comment);
				}

				if($isWooCommerce)
			 		return true;
				$success = true;
			}

		} catch ( RequestException $e ) {

			$this->adm_log_anyday_error( Psr7\str($e->getResponse()) );

		}


		if($order_id) {
			return ($success) ? true : false;
		} else {
			if($success) {
				echo json_encode( ["success" => __('Anyday payment successfully cancelled.', 'adm')] ) ;
			} else {
				echo json_encode( ["error" => __('Payment could not be cancelled. Please contact Anyday support.', 'adm')] );
			}
			exit;
		}
	}


	/**
	 * Request to Anyday API to capture a payment amount
	 *@method adm_api_capture
	 */
	public function adm_api_refund( $order, $amount )
	{
		try {

			$response = $this->client->request('POST', ADM_API_BASE_URL . ADM_API_ORDERS_BASE_PATH . '/' . get_post_meta( $order->get_id(), 'anyday_payment_transaction' )[0] . '/refund', [
				'headers' => $this->headers,
			    "json" => [
					"amount" => (float)$amount
			    ]
			]);

			$response = json_decode( $response->getBody()->getContents() );

			if( $response->errorCode === 0 )

			return $response;

		} catch ( RequestException $e ) {

			$this->adm_log_anyday_error( Psr7\str($e->getResponse()) );

		}
	}

	/**
	 * Refund a payment
	 *@method adm_refund_payment
	 *@return json
	 */
	public function adm_refund_payment($order_id = null, $amount = null, $isWooCommerce = false)
	{
		$success = false;
		$id = ($order_id) ? $order_id : $_POST['orderId'];
		$order = wc_get_order( $id );
		$amount = ($amount) ? $amount : $_POST['amount'];
		$response = $this->adm_api_refund( $order, $amount );

		if( $response ) {

			if (!$this->handled($order, $response->transactionId)) {
				update_post_meta($order->get_id(), 'anyday_payment_last_status', ANYDAY_STATUS_REFUND);
				update_post_meta($order->get_id(), $response->transactionId . '_anyday_refunded_payment', wc_clean($amount));
				$comment =  __('Anyday payment refunded!', 'adm');
				if( !$isWooCommerce )
					$order->update_status('wc-adm-refunded', $comment);

				$order->add_order_note(__(date("Y-m-d, h:i:sa") . ' - Refunded amount: ' . number_format($amount, 2, ',', ' ') . get_option('woocommerce_currency'), 'adm'));
			}
			if($isWooCommerce)
			 return true;
			$success = true;

		}

		if($order_id) {
			return ($success) ? true : false;
		} else {
			if($success) {
				echo json_encode( ["success" => __('Anyday payment successfully refunded.', 'adm')] ) ;
			} else {
				echo json_encode( ["error" => __('Payment could not be refunded. Please contact Anyday support.', 'adm')] );
			}
			exit;
		}
	}

	public function adm_api_get_order( $order ) {
		try {

			$response = $this->client->request('GET', ADM_API_BASE_URL . ADM_API_ORDERS_BASE_PATH . '?id=' . get_post_meta( $order->get_id(), 'anyday_payment_transaction' )[0] . '', [
				'headers' => $this->headers
			]);

			$response = json_decode( $response->getBody()->getContents() );

			return $response;

		} catch ( RequestException $e ) {

			$this->adm_log_anyday_error( Psr7\str($e->getResponse()) );

		}
	}

	/**
	 * Write Anyday event in a file for debuging purposes
	 */
	private function adm_log_anyday_error( $message )
	{
		$contents = "";
		if ( get_option('adm_module_error_log') == 'enabled' ) {

			$contents .= "$message\n";

			file_put_contents( ADM_PATH . "/debug.log", $contents, FILE_APPEND | LOCK_EX );

		}

	}

	/**
	 * Returns webhook path which accepts request data in JSON format.
	 * @return string
	 */
	private function getWebhookPath() {
		return '/wp-json/' . AnydayRest::ENDPOINT_NAMESPACE . '/' . AnydayRest::ENDPOINT;
	}

	/**
	 * @todo THIS IS DUPLICATE FUNCTION IN CLASS Adm/AnydayEvent, need to refactor
	 *
	 * Checks if transaction as been handled before or not by checking transaction id's in order metadata.
	 * If it is handled previously then returns true otherwise save the transaction id to order metadata
	 * and returns false.
	 * @param void
	 * @return boolean
	 */
	private function handled($order, $transaction_id) {
		$order_data = $order->get_meta('anyday_payment_transactions');
		if( !empty($order_data) && in_array($transaction_id, $order_data) )  {
			return true;
		}
		$txn = get_post_meta($order->get_id(), 'anyday_payment_transactions', true);
		if(!is_array($txn)) {
			$txn = array();
		}
		array_push($txn, $transaction_id);
		update_post_meta($order->get_id(), 'anyday_payment_transactions', $txn);
		return false;
	}
}
