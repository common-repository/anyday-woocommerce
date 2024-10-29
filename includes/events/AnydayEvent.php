<?php
namespace Adm;

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'AnydayEvent' ) ) {
	return;
}

/**
 * @since 4.0
 */
class AnydayEvent {
	/**
	 * @var array  of Anyday event's payload.
	 */
	protected $data;

	/**
	 * @var boolean is pending event
	 */
	protected $is_pending;

	/**
	 * @var \WC_Abstract_Order
	 */
	protected $order;

	public function __construct( $data ) {
		$this->data       = $data;
		$this->is_pending = false;
	}

	public function handled($order, $transaction_id) {
		$order_data = $order->get_meta('anyday_payment_transactions');
		if( !$this->get_is_pending() && in_array($transaction_id, $order_data) )  {
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
	
	/**
	 * validating if order Id does exists, orders transaction id identical with event transaction id and if valid order exists returning true
	 * 
	 * @return boolean
	 */
	public function validate() {
		if ( ! isset( $this->data['orderId'] ) ) {
			return false;
		}

		$this->order = (wc_get_order( $this->data['orderId'])) 
			? wc_get_order( $this->data['orderId']) 
			:	wc_get_order(wc_sequential_order_numbers()->find_order_by_order_number( $this->data['orderId'] ));

		if ( ! $this->order ) {
			return false;
		}

		if ( get_post_meta( $this->order->get_id(), 'anyday_payment_transaction' )[0] !== $this->data['id'] ) {
			return false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function resolve() {
		return true;
	}

	/**
	 * @return array  of Anyday event's payload.
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * @return \WC_Abstract_Order
	 */
	public function get_order() {
		return $this->order;
	}

	/**
	 * @return boolean
	 */
	public function get_is_pending() {
		return $this->is_pending;
	}

	/**
	 * @param boolean $is_pending
	 */
	public function set_is_pending($is_pending) {
		$this->is_pending = $is_pending;
	}
}
