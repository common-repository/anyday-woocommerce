<?php
namespace Adm;
defined( 'ABSPATH' ) || exit;

class AnydayEventCapture extends AnydayEvent {
	
	/**
	 * This `capture` event is only being used
	 */
	public function resolve() {
		$transaction = $this->data['transaction'];
		$order = wc_get_order( $this->order->get_id() );
		if( $this->handled($order, $transaction['id']) ) {
			return;
		}
		switch ( $transaction['status'] ) {
			case 'fail':
				$message         = __( 'Anyday: Payment failed to capture.<br/>Amount : %1$s %2$s', 'adm' );
				$this->order->add_order_note(
					sprintf(
						wp_kses( $message, array( 'br' => array() ) ),
						number_format($transaction['amount'], 2, ',', '.'),
						$this->order->get_currency()
					)
				);
				break;

			case 'success':
				$message = __( 'Anyday: Payment captured successful.<br/>An amount %1$s %2$s has been captured', 'adm' );
				update_post_meta($order->get_id(), 'anyday_payment_last_status', ANYDAY_STATUS_CAPTURE);
				$this->order->add_order_note(
					sprintf(
						wp_kses( $message, array( 'br' => array() ) ),
						number_format($transaction['amount'], 2, ',', '.'),
						$this->order->get_currency()
					)
				);
				update_post_meta( $this->order->get_id(), $transaction['id']. '_anyday_captured_payment', wc_clean( $transaction['amount'] ) );

				if ( ! $order->has_status( get_option('adm_order_status_after_captured_payment') ) && ! $this->get_is_pending()) {
					$order->update_status( get_option('adm_order_status_after_captured_payment') );
				}
			break;
		}

		return;
	}
}
