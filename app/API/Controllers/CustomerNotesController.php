<?php
/**
 * Customer notes REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Customers\CustomerManager;
use Bookora\Security\Capabilities;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * List, add, and delete notes attached to a customer.
 */
final class CustomerNotesController extends AbstractController {

	/**
	 * Customer manager.
	 *
	 * @var CustomerManager
	 */
	private CustomerManager $manager;

	/**
	 * Constructor.
	 *
	 * @param CustomerManager $manager Customer manager.
	 */
	public function __construct( CustomerManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$can = $this->require_capability( Capabilities::MANAGE_CUSTOMERS );

		register_rest_route(
			self::REST_NAMESPACE,
			'/customers/(?P<id>\d+)/notes',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => $can,
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/customers/(?P<id>\d+)/notes/(?P<note>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'destroy' ),
				'permission_callback' => $can,
			)
		);
	}

	/**
	 * List a customer's notes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		return $this->success( array( 'items' => $this->manager->notes( (int) $request['id'] ) ) );
	}

	/**
	 * Add a note.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function create( WP_REST_Request $request ) {
		$body   = (array) $request->get_json_params();
		$result = $this->manager->add_note(
			(int) $request['id'],
			(string) ( $body['body'] ?? '' ),
			(bool) ( $body['is_private'] ?? true )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success( $result, 201 );
	}

	/**
	 * Delete a note.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function destroy( WP_REST_Request $request ): WP_REST_Response {
		return $this->success(
			array( 'deleted' => $this->manager->delete_note( (int) $request['id'], (int) $request['note'] ) )
		);
	}
}
