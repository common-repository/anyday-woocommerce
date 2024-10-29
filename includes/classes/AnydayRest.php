<?php
namespace Adm;

defined( 'ABSPATH' ) or die( 'No direct script access allowed.' );

if ( class_exists( 'AnydayRest' ) ) {
	return;
}

/**
 * class handling all callback actions.
 */
class AnydayRest {
	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	const ENDPOINT_NAMESPACE = 'anyday';

	/**
	 * @var string
	 */
	const ENDPOINT = 'webhook';

  /**
   * @var string
   */
  const EVENT_CLASS_PREFIX = 'AnydayEvent';

  public function __construct() {
    add_action( 'rest_api_init', function () {
			$this->register_routes();
    } );
	}

	/**
	 * Registering the routes for webhooks.
	 */
	public function register_routes() {
		register_rest_route(
			self::ENDPOINT_NAMESPACE,
			'/' . self::ENDPOINT,
			array(
				'methods' => \WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'callback' ),
				'permission_callback' => '__return_true'
			)
		);
	}

	/**
	 * @param  \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function callback( $request ) {
		$event = new AnydayEvents;
		if ( !$this->validSignature($request) || 'application/json' !== $request->get_header( 'Content-Type' ) ) {
			return new \WP_Error( 'anyday_rest_wrong_header', __( 'Wrong header type.', 'adm' ), array( 'status' => 400 ) );
		}
    $data = $this->lcfirstKeys($request->get_json_params());
		
		if ( is_null($data['transaction']) ) {
			if($data['cancelled'] === true) {
				$eventType = self::EVENT_CLASS_PREFIX.ucfirst('cancel');
				$data['transaction']['type'] = 'cancel';
				$data['transaction']['status'] = 'success';
				$event = $event->handle( $eventType, $data );
				return rest_ensure_response( $event );
			}
			return new \WP_Error( 'anyday_rest_wrong_object', __( 'Wrong object type.', 'adm' ), array( 'status' => 400 ) );
		}

    $eventType = self::EVENT_CLASS_PREFIX.ucfirst($data['transaction']['type']);
		$event = $event->handle( $eventType, $data );

		return rest_ensure_response( $event );
	}

	/**
	 * checks the signature is valid and returns true if valid.
	 * @param \WP_REST_Request $request
	 * @return boolean
	 */
	private function validSignature( $request ) {
		$private    = get_option('adm_private_key');
		$signature  = $request->get_header('x_anyday_signature');
		if(empty($private)) {
			return false;
		}
		$signedBody = hash_hmac('sha256', $request->get_body(), $private);
		if($signature === $signedBody) {
			return true;
		}
		return false;
	}

	private function lcfirstKeys($data) {
		$res = [];
		foreach ($data as $key => $value) {
				$newKey = lcfirst($key);
				$res[$newKey] = is_array($value) ? $this->lcfirstKeys($value) : $value;
		}
		return $res;
	}
}
