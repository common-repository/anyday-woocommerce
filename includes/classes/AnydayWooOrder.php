<?php

namespace Adm;

class AnydayWooOrder
{
	public function __construct()
	{
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'adm_editable_order_meta_general' ) );
		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'adm_wc_order_item_add_action_buttons_callback' ), 10, 1 );
		add_action( 'admin_head', array( $this, 'adm_hide_woo_refund') );
		add_action( 'init', array( $this, 'adm_user_anyday_order_rejection' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'adm_user_anyday_order_approval' ) );
		add_action( 'init', array( $this, 'adm_register_refund_order_status' ) );
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'adm_order_custom_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'adm_order_custom_bulk_action_handler'), 10, 3 );
		add_action( 'admin_notices', array( $this, 'adm_order_custom_bulk_action_notices' ) );

		if( get_option('adm_order_status_after_captured_payment') != "default" ) {
			
			add_action( 'woocommerce_order_status_' . str_replace('wc-', '', get_option('adm_order_status_after_captured_payment')), array($this, 'adm_capture_upon_woocommerce_order_status_change') );

		} else {

			add_action( 'woocommerce_order_status_completed', array($this, 'adm_capture_upon_woocommerce_order_status_change') );

		}			
		
		add_action( 'woocommerce_order_status_refunded', array($this, 'adm_refund_upon_woocommerce_order_status_change') );
		add_action( 'woocommerce_order_status_cancelled', array($this, 'adm_cancel_upon_woocommerce_order_status_change') );
	}

	/**
	 * Registers a new order status Anyday Refunded
	 */
	public function adm_register_refund_order_status()
	{
		register_post_status( 'wc-adm-refunded', array(
	        'label'                     => _x( 'Anyday Refunded', 'Order status', 'adm' ),
	        'public'                    => false,
	        'exclude_from_search'       => false,
	        'show_in_admin_all_list'    => true,
	        'show_in_admin_status_list' => true,
	        'label_count'               => _n_noop( 'Anyday Refunded <span class="count">(%s)</span>', 'Anyday Refunded <span class="count">(%s)</span>', 'adm' )
	    ) );

		add_filter( 'wc_order_statuses', function( $statuses ) {

		    $statuses['wc-adm-refunded'] = __( 'Refunded', 'adm' );

		    return $statuses;
		});
	}

	/**
	 * Add filed which holds the anyday transaction id
	 *@method adm_editable_order_meta_general
	 *@param  object                          $order
	 *@return html
	 */
	public function adm_editable_order_meta_general( $order )
	{
		$anyday_payment_transaction = get_post_meta( $order->get_id(), 'anyday_payment_transaction', true );

		if( $order->get_payment_method() == 'anyday_payment_gateway' ) {
			woocommerce_wp_text_input( array(
				'id' => 'anyday_payment_transaction',
				'label' => __('Anyday Transaction ID:'),
				'value' => $anyday_payment_transaction,
				'wrapper_class' => 'form-field-wide',
				'custom_attributes' => array('readonly' => 'readonly')
			) );
		}
	}

	/**
	 * Add Anyday action buttons along with capture and refund history sections
	 *@method adm_wc_order_item_add_action_buttons_callback
	 *@return html
	 */
	public function adm_wc_order_item_add_action_buttons_callback( $order )
	{
		$captured_amount = 0;
		$refunded_amount = 0;

		update_post_meta( $order->get_id(),'full_captured_amount', 'false' );
		update_post_meta( $order->get_id(),'full_refunded_amount', 'false' );

		foreach( get_post_meta( $order->get_id() ) as $key => $meta ) {
			if( strpos($key, 'anyday_captured_payment') !== false ) {
				$captured_amount += floatval($meta[0]);
			}

			if( strpos($key, 'anyday_refunded_payment') !== false ) {
				$refunded_amount += floatval($meta[0]);
			}

			if ( ($order->get_total() - $captured_amount ) == 0 ) {
				update_post_meta( $order->get_id(),'full_captured_amount', 'true' );
			}

			if ( $captured_amount && ($captured_amount - $refunded_amount) == 0 ) {
				update_post_meta( $order->get_id(),'full_refunded_amount', 'true' );
				$order->update_status('wc-adm-refunded');
			}
		}


		if( $order->get_payment_method() == 'anyday_payment_gateway' ) {
			if ( $order->get_status() != 'cancelled' ) {
				if ( !$refunded_amount && get_post_meta( $order->get_id(), 'full_captured_amount' )[0] != 'true' ) {
					echo '<button type="button" class="button anyday-capture anyday-payment-action" data-anyday-action="adm_capture_payment" data-order-id="'.$order->get_id().'">'. __("Anyday Capture", "adm") .'</button>';
				}

				if ( !$refunded_amount && !$this->get_total_captured_amount( $order ) && get_post_meta( $order->get_id(), 'full_refunded_amount' )[0] != 'true' ) {
					echo '<button type="button" class="button anyday-cancel anyday-payment-action" data-anyday-action="adm_cancel_payment" data-order-id="'.$order->get_id().'">'. __("Anyday Cancel", "adm") .'</button>';
				}

				if ( get_post_meta( $order->get_id(), 'full_refunded_amount' )[0] != 'true' && !empty($this->get_total_captured_amount($order))) {
					echo '<button type="button" class="button anyday-refund anyday-payment-action" data-anyday-action="adm_refund_payment" data-order-id="'.$order->get_id().'">'. __("Anyday Refund", "adm") .'</button>';
				}
			}

			$captured_amount = 0;
			?>
			<div class="woocommerce_order_items_wrapper wc-order-items-editable" style="display: block;
width: 100%;margin-top: 20px;">
				<span id="anyday-order-message"></span>
				<table class="woocommerce_order_items" cellspacing="0" cellpadding="0">
					<tbody id="adm_order_refunds">
						<?php foreach( get_post_meta( $order->get_id() ) as $key => $meta ) :?>
							<?php if( strpos($key, 'anyday_captured_payment') !== false ) : $captured_amount = $captured_amount + floatval($meta[0]);?>
								<tr class="refund ">
									<td class="thumb">
										<div></div>
									</td>
									<td class="name">
										<?php echo substr($key, 0, 10) . ', ' . substr($key, 11, 10); ?>
										<p class="description">Captured amount.</p>
									</td>
									<td class="line_cost" width="1%">
										<div class="view">
											<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo $order->get_currency(); ?></span><?php echo number_format(floatval($meta[0]), 2, ',', '.'); ?></span>
										</div>
									</td>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if( $captured_amount > 0 ) : ?>
					<div class="wc-order-data-row wc-order-totals-items wc-order-items-editable">
						<table class="wc-order-totals">
							<tbody>
								<tr>
									<td class="label refunded-total">Total Captured Amount:</td>
									<td width="1%"></td>
									<td class="total refunded-total">
										<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo $order->get_currency(); ?></span><?php echo number_format($captured_amount, 2, ',', '.'); ?></span>
									</td>
								</tr>
								<tr>
									<td class="label">Amount left to be Captured:</td>
									<td width="1%"></td>
									<td class="total">
										<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo $order->get_currency(); ?></span><?php echo ( $refunded_amount ) ? number_format(0, 2, ',', '.') : number_format((float)$order->get_total() - (float)$captured_amount, 2, ',', '.');

										if ( ((float)$order->get_total() - $captured_amount) == 0 ) {
											update_post_meta( $order->get_id(),'full_captured_amount', 'true' );
										}
										?></span>
									</td>
								</tr>
							</tbody>
						</table>
						<div class="clear"></div>
					</div>
				<?php endif; ?>
			</div>
			<?php $refunded_amount = 0;?>
			<div class="woocommerce_order_items_wrapper wc-order-items-editable" style="display: block;
width: 100%;margin-top: 20px;">
				<span id="anyday-order-message"></span>
				<table class="woocommerce_order_items" cellspacing="0" cellpadding="0">
					<tbody id="adm_order_refunds">
						<?php foreach( get_post_meta( $order->get_id() ) as $key => $meta ) :?>
							<?php if( strpos($key, 'anyday_refunded_payment') !== false ) : $refunded_amount = $refunded_amount + floatval($meta[0]);?>
								<tr class="refund ">
									<td class="thumb">
										<div></div>
									</td>
									<td class="name">
										<?php echo substr($key, 0, 10) . ', ' . substr($key, 11, 10); ?>
										<p class="description">Refunded amount.</p>
									</td>
									<td class="line_cost" width="1%">
										<div class="view">
											<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo $order->get_currency(); ?></span><?php echo number_format(floatval($meta[0]), 2, ',', '.'); ?></span>
										</div>
									</td>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if( $refunded_amount > 0 ) : ?>
					<div class="wc-order-data-row wc-order-totals-items wc-order-items-editable">
						<table class="wc-order-totals">
							<tbody>
								<tr>
									<td class="label refunded-total">Total Refunded Amount:</td>
									<td width="1%"></td>
									<td class="total refunded-total">
										<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo $order->get_currency(); ?></span><?php echo number_format($refunded_amount, 2, ',', '.'); ?></span>
									</td>
								</tr>
								<tr>
									<td class="label">Amount left to be Refunded:</td>
									<td width="1%"></td>
									<td class="total">
										<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol"><?php echo $order->get_currency(); ?></span><?php echo  number_format($captured_amount - $refunded_amount, 2, ',', '.');

										if ( ($order->get_total() - $refunded_amount) == 0 ) {
											update_post_meta( $order->get_id(),'full_refunded_amount', 'true' );
										}
										?></span>
									</td>
								</tr>
								<tr>
									<td class="label label-highlight">Net Payment:</td>
									<td width="1%"></td>
									<td class="total">
									<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol"><?php echo $order->get_currency(); ?></span><?php echo number_format((float)$captured_amount - $refunded_amount, 2, ',', '.'); ?></bdi></span></td>
								</tr>
							</tbody>
						</table>
						<div class="clear"></div>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	/**
	 * Hide the default admin order refunds items and refund button
	 *@method adm_hide_wc_refund_button
	 */
	public function adm_hide_woo_refund()
	{

		global $post;

		if ( $post ) {

			$order = wc_get_order( (int)$post->ID );

			if (!current_user_can('administrator') && !current_user_can('editor')) {
				return;
			}
			if (strpos($_SERVER['REQUEST_URI'], 'post.php?post=') === false) {
				return;
			}

			if (empty($post) || $post->post_type != 'shop_order') {
				return;
			}

			if( $order->get_payment_method() != 'anyday_payment_gateway' ) {
				?>
				<script type="text/javascript">
					jQuery(function () {
						var orderStatusField = jQuery('#order_status'),
				        orderPendingOption = orderStatusField.find('option[value="wc-adm-refunded"]');
				    orderPendingOption.remove();
					});
				</script>
				<?php
				return;
			} else { ?>
				<script type="text/javascript">
					jQuery(function () {
						var buttonsToHide = jQuery('.add-line-item,.add-coupon,.refund-items,#order_refunds,.inside > .wc-order-data-row.wc-order-totals-items.wc-order-items-editable');
						buttonsToHide.hide();
					});
				</script>
			<?php return; } ?>
			<script type="text/javascript">
				jQuery(function () {
					var orderStatusField = jQuery('#order_status'),
			        orderPendingOption = orderStatusField.find('option[value="wc-refunded"]');

			    	orderPendingOption.remove();

			    	jQuery('.refund-items').hide();
				});
			</script>
			<?php

		}

	}

	/**
	 * Update the order status after the user rejects the payment on Anyday portal
	 *@method adm_user_anyday_order_rejection
	 */
	public function adm_user_anyday_order_rejection()
	{
		if( isset($_GET['orderId']) && isset($_GET['orderKey']) && isset($_GET['anydayPayment']) ) {
			$order = wc_get_order( $_GET['orderId'] );

			$check_order_key = ($_GET['orderKey'] == $order->get_order_key()) ? true : false;

			if ( $order && $check_order_key && $order->get_payment_method() == 'anyday_payment_gateway' && $order->get_status() != 'cancelled' && $_GET['anydayPayment'] == 'rejected' ) {

				wc_increase_stock_levels( $order->get_id() );

				$order->update_status( 'cancelled', __( 'Anyday payment cancelled!', 'adm' ) );

			}
		}
	}

	/**
	 * Update the order status after the user approves the payment on Anyday portal
	 *@method adm_user_anyday_order_approval
	 */
	public function adm_user_anyday_order_approval( $order_id )
	{
		$order = wc_get_order( $order_id );

		if ( $order && $order->get_payment_method() == 'anyday_payment_gateway' && $order->get_status() == 'pending' && isset($_GET['anydayPayment']) && $_GET['anydayPayment'] == 'approved' ) {

			WC()->cart->empty_cart();

			if( get_option("adm_order_status_after_authorized_payment") != "default" ) {
			
				$order->update_status( get_option("adm_order_status_after_authorized_payment"), __( 'Anyday payment approved!', 'adm' ) );
			
			}else {

				$order->update_status( 'on-hold', __( 'Anyday payment approved!', 'adm' ) );
			
			}
			

		}
	}
	
	/**
	 * Add custom bulk action to capture all Anyday payments
	 */
	public function adm_order_custom_bulk_actions( $bulk_array )
	{
		$bulk_array['anyday_capture_payment'] = 'Capture Anyday Payment';
		$bulk_array['anyday_refund_payment'] = 'Refund Anyday Payment';
		$bulk_array['anyday_cancel_payment'] = 'Cancel Anyday Payment';
		return $bulk_array;
	}

	public function adm_order_custom_bulk_action_handler( $redirect, $doaction, $object_ids )
	{
		$redirect               = remove_query_arg( array( 'anyday_capture_payment_successful', 'anyday_capture_payment_unsuccessful', 'anyday_capture_payment_unsuccessful_order_ids' ), $redirect );
		$anyday_payment         = new AnydayPayment;
		$successfull            = 0;
		$unsuccessful           = 0;
		$unsuccessful_order_ids = '';

		foreach( $object_ids as $object_id ) {
			$order = wc_get_order( $object_id );
			if ( $order->get_payment_method() == 'anyday_payment_gateway' ) {
				switch( $doaction ) {
					case 'anyday_capture_payment':
						$order_amount = $order->get_total() - $this->get_total_captured_amount($order);
						$status = $anyday_payment->adm_capture_payment($order, $order_amount);
						break;
					case 'anyday_refund_payment':
						$total_captured_amount = $this->get_total_captured_amount($order) - $this->get_total_refunded_amount($order);
						$status = $anyday_payment->adm_refund_payment($object_id, $total_captured_amount);
						break;
					case 'anyday_cancel_payment':
						$status = $anyday_payment->adm_cancel_payment($object_id);
						break;
				}

				if( $status ) {
					$successfull++;
				} else  {
					$unsuccessful++;
					$unsuccessful_order_ids .= $object_id . ', ';
				}
			}
		}
		
		$redirect = add_query_arg( array(
			'anyday_order_type' => $doaction,
			'anyday_payment_successful' =>  $successfull,
			'anyday_payment_unsuccessful' => $unsuccessful,
			'anyday_payment_unsuccessful_order_ids' => substr($unsuccessful_order_ids, 0, -2)
		), $redirect );
		return $redirect;
	}

	/**
	 * get notices message to display on order page after submitting bulk order actions.
	 */
	public function adm_order_custom_bulk_action_notices()
	{
		$messages = array();
		$orderType = isset( $_REQUEST['anyday_order_type'] ) ? $_REQUEST['anyday_order_type'] : null;
		switch( $orderType ) {
			case 'anyday_capture_payment':
				if(isset( $_REQUEST['anyday_payment_successful'] ) && $_REQUEST['anyday_payment_successful'] > 0 ) {
					$messages[] = array('notice' => 'payments have been successfully captured', 'count' => sanitize_text_field(intval($_GET['anyday_payment_successful'])));
				}
				if ( isset( $_REQUEST['anyday_payment_unsuccessful'] ) && $_REQUEST['anyday_payment_unsuccessful'] > 0 ) {
					$messages[] = array('notice' => 'payments have failed to be captured', 'count' => sanitize_text_field(intval($_GET['anyday_payment_unsuccessful'])));
				}
				break;
			case 'anyday_refund_payment':
				if(isset( $_REQUEST['anyday_payment_successful'] ) && $_REQUEST['anyday_payment_successful'] > 0 ) {
					$messages[] = array('notice' => 'payments have been successfully refunded', 'count' => sanitize_text_field(intval($_GET['anyday_payment_successful'])));

				}
				if ( isset( $_REQUEST['anyday_payment_unsuccessful'] ) && $_REQUEST['anyday_payment_unsuccessful'] > 0 ) {
					$messages[] = array('notice' => 'payments have failed to be refunded', 'count' => sanitize_text_field(intval($_GET['anyday_payment_unsuccessful'])));
				}
				break;
			case 'anyday_cancel_payment':
				if(isset( $_REQUEST['anyday_payment_successful'] ) && $_REQUEST['anyday_payment_successful'] > 0 ) {
					$messages[] = array('notice' => 'payments have been successfully cancelled', 'count' => sanitize_text_field(intval($_GET['anyday_payment_successful'])));

				}
				if ( isset( $_REQUEST['anyday_payment_unsuccessful'] ) && $_REQUEST['anyday_payment_unsuccessful'] > 0 ) {
					$messages[] = array('notice' => 'payments have failed to be cancelled', 'count' => sanitize_text_field(intval($_GET['anyday_payment_unsuccessful'])));
				}
				break;
		}

		foreach ($messages as $message) {
			printf( '<div id="message" class="notice notice-success is-dismissible"><p><strong>%s</strong> '. __($message['notice'], 'adm') .'.</p></div>', $message['count'] );
		}
	}

	public function adm_capture_upon_woocommerce_order_status_change( $order_id )
	{
		global $pagenow;

		$order = wc_get_order( $order_id );
		$order_amount = $order->get_total();

		if ( $pagenow == "post.php" && $order->get_payment_method() == 'anyday_payment_gateway' ) {
			
			$anyday_payment = new AnydayPayment;

			$request = $anyday_payment->adm_api_capture( $order, $order_amount );

			if ( $request ) {

				update_post_meta( $order->get_id(), $request->transactionId . '_anyday_captured_payment', wc_clean( $order_amount ) );
	
				$comment =  __( 'Anyday payment captured!', 'adm' );
				if( get_option('adm_order_status_after_captured_payment') != "default" ) {
	
					$order->update_status( 'wcompleted', $comment );
	
				} else {
	
					$order->update_status( 'completed', $comment );
	
				}
				
				$order->add_order_note( __( date("Y-m-d, h:i:sa") . ' - Captured amount: ' . number_format($order_amount, 2, ',', '.') . get_option('woocommerce_currency'), 'adm') );
			
				wp_safe_redirect( home_url("/wp-admin/post.php?post=$order_id&action=edit") );
			}
		}
	}

	public function adm_refund_upon_woocommerce_order_status_change( $order_id ) {
		global $pagenow;

		$order = wc_get_order( $order_id );

		if ( $pagenow == "post.php" && $order->get_payment_method() == 'anyday_payment_gateway' ) {
			$anyday_payment = new AnydayPayment;
			$response = $anyday_payment->adm_api_get_order( $order );
			if ($response->data->cancelled === false && (float) $response->data->totalCaptured !== 0.0) {
				$anyday_payment->adm_refund_payment($order_id, $response->data->totalCaptured, true);
			}
		}
		return false;
	}

	public function adm_cancel_upon_woocommerce_order_status_change( $order_id ) {
		global $pagenow;

		$order = wc_get_order( $order_id );

		if ( $pagenow == "post.php" && $order->get_payment_method() == 'anyday_payment_gateway' ) {
			$anyday_payment = new AnydayPayment;
			$response = $anyday_payment->adm_api_get_order( $order );
			if ($response->data->cancelled === false && (float) $response->data->totalCaptured === 0.0) {
				$anyday_payment->adm_cancel_payment($order_id, true);
			}
		}
		return false;
	}

	/**
	 * Get total captured amount for the order.
	 * @param WC_Order $order
	 */
	private function get_total_captured_amount($order) {
		$captured_amount = 0;
		foreach( get_post_meta( $order->get_id() ) as $key => $meta ) {
			if( strpos($key, 'anyday_captured_payment') !== false ) {
				$captured_amount += floatval($meta[0]);
			}
		}
		return $captured_amount;
	}

	/**
	 * Get total refunded amount for the order.
	 * @param WC_Order $order
	 */
	private function get_total_refunded_amount($order) {
		$refunded_amount = 0;
		foreach (get_post_meta($order->get_id()) as $key => $meta) {
			if (strpos($key, 'anyday_refunded_payment') !== false) {
					$refunded_amount += floatval($meta[0]);
			}
		}
		return $refunded_amount;
	}
}
