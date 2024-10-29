<?php
namespace Adm;

defined( 'ABSPATH' ) || exit;

class AnydayEventRefund extends AnydayEvent {
	/**
	 * @param  mixed $data
	 *
	 * @return void
	 */
	public function resolve() {
		$transaction = $this->data['transaction'];
		$order = wc_get_order( $this->order->get_id() );
		if( $this->handled($order, $transaction['id']) ) {
			return;
		}
		switch ( $this->data['transaction']['status'] ) {
			case 'fail':
				$message         = __( 'Anyday: Payment failed to refund', 'adm' );
				$this->order->add_order_note(
					$message
				);
				break;

			case 'success':
	
				$message = __( 'Anyday: Payment has been refunded. <br/>An amount %1$s %2$s has been refunded', 'adm' );
				update_post_meta($order->get_id(), 'anyday_payment_last_status', ANYDAY_STATUS_REFUND);
				$this->order->add_order_note( 
					sprintf(
						wp_kses( $message, array( 'br' => array() ) ),
						number_format($this->data['transaction']['amount'], 2, ',', '.'),
						$this->order->get_currency()
					)
				);
				update_post_meta( $this->order->get_id(), $transaction['id'] . '_anyday_refunded_payment', wc_clean( $this->data['transaction']['amount'] ) );
			break;
		}
		return;
	}
}
