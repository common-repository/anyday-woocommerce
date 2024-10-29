<?php
namespace Adm;

defined( 'ABSPATH' ) || exit;

class AnydayEventCancel extends AnydayEvent {
	/**
	 * @param  mixed $data
	 *
	 * @return void
	 */
	public function resolve() {
		$transaction = $this->data['transaction'];
		$order = wc_get_order( $this->order->get_id() );
		if( isset($transaction['id']) && $this->handled($order, $transaction['id']) ) {
			return;
		}
		switch ( $this->data['transaction']['status'] ) {
			case 'fail':
				$message         = __( 'Anyday: Payment failed to cancel', 'adm' );
				$this->order->add_order_note( $message );
				break;

			case 'success':
				update_post_meta($order->get_id(), 'anyday_payment_last_status', ANYDAY_STATUS_CANCEL);
				if ( $this->order->has_status( 'cancelled' ) ) {
					return;
				}
	
				$message = __( 'Anyday: Payment has been canceled.', 'adm' );
	
				$this->order->add_order_note( $message );
				if (! $this->get_is_pending()) {
						$this->order->update_status('cancelled');
				}
			break;
		}
		return;
	}
}
