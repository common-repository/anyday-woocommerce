<?php

namespace Adm;

defined( 'ABSPATH' ) or die( 'No direct script access allowed.' );

if ( class_exists( 'AnydayEvents' ) ) {
	return;
}

class AnydayEvents {
	/**
	 * @var array  of event classes that we can handle.
	 */
	protected $events = array();

	/**
	 * All the available event handler classes
	 * that Anyday WooCommerce supported.
	 *
	 * @var array
	 */
	public static $event_classes = array(
		'AnydayEventRefund',
		'AnydayEventCancel',
		'AnydayEventCapture',
		'AnydayEventAuthorize',
	);

	/**
   * @var string
   */
  const EVENT_CLASS_PREFIX = 'AnydayEvent';

	/**
	 * @param  string $event_key
	 * @param  mixed  $data
	 *
	 * @return void
	 */
	public function handle( $event_key, $data ) {
		$event_hook_suffix= $data['transaction']['type'];
		$eventClass = "Adm\\" . $event_key;
		
		if(!class_exists($eventClass)) {
			return;
		}
		$event = new $eventClass( $data );
		
		/**
		 * Hook before Anyday handle an event from webhook.
		 *
		 * @param mixed $data  a data of an event object
		 */
		do_action( 'adm_before_handle_event_' . $event_hook_suffix, $data );
		if ( $event->validate() ) {
			$this->process_pending_txns($event);
			$result = $event->resolve();

			/**
			 * Hook before Anyday handle an event from webhook.
			 *
			 * @param WC_Order $order  an order object.
			 * @param mixed    $data   a data of an event object
			 */
			do_action( 'adm_handled_event_' . $event_hook_suffix, $event->get_order(), $event->get_data() );
		} 

		/**
		 * Hook after Anyday handle an event from webhook.
		 *
		 * @param WC_Order $order  an order object.
		 * @param mixed    $data   a data of an event object
		 * @param mixed    $result  a result of an event handler
		 */
		do_action( 'adm_after_handle_event_' . $event_hook_suffix, $event->get_order(), $event->get_data(), $result );

		return $result;
	}

	/**
	 * find pending transactions remaining to process
	 * @var mixed
	 * @return void
	 */
	private function process_pending_txns($event) {
		$order_data   = $event->get_order()->get_meta('anyday_payment_transactions');
		$missing_txns = array();
		if(!is_null($this->data['transactions'])) {
			foreach(array_shift($this->data['transactions']) as $txn) {
				if(!in_array($txn, $order_data) && $txn['type'] !== 'authorize') {
					array_push($missing_txns, $txn);
				}
			}
		}
		foreach($missing_txns as $txn) {
			$event_key = self::EVENT_CLASS_PREFIX.ucfirst($txn['type']);
			$eventClass = "Adm\\" . $event_key;
		
			if(!class_exists($eventClass)) {
				return;
			}
			$event = new $eventClass( array('transaction' => $txn) );
			$event->set_is_pending(true);
			if ( $event->validate() ) {
				$event->resolve();
			} 
		}
	}
}
