<?php
/**
 * Advanced features REST controller (coupons, gift cards, memberships,
 * resources, waitlist).
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Coupons\CouponManager;
use Bookora\Coupons\CouponRepository;
use Bookora\GiftCards\GiftCardManager;
use Bookora\GiftCards\GiftCardRepository;
use Bookora\Memberships\MembershipManager;
use Bookora\Memberships\MembershipRepository;
use Bookora\Resources\ResourceManager;
use Bookora\Security\Capabilities;
use Bookora\Waitlist\WaitlistManager;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Admin CRUD for the Stage-16 commerce/scheduling features, plus the public
 * coupon-validate, gift-card-balance, and waitlist-join endpoints.
 */
final class AdvancedController extends AbstractController {

	private CouponManager $coupons;
	private CouponRepository $coupon_repo;
	private GiftCardManager $gift_cards;
	private GiftCardRepository $gift_card_repo;
	private MembershipManager $memberships;
	private MembershipRepository $membership_repo;
	private ResourceManager $resources;
	private WaitlistManager $waitlist;

	/**
	 * Constructor.
	 *
	 * @param CouponManager        $coupons         Coupon manager.
	 * @param CouponRepository     $coupon_repo     Coupon repository.
	 * @param GiftCardManager      $gift_cards      Gift card manager.
	 * @param GiftCardRepository   $gift_card_repo  Gift card repository.
	 * @param MembershipManager    $memberships     Membership manager.
	 * @param MembershipRepository $membership_repo Membership repository.
	 * @param ResourceManager      $resources       Resource manager.
	 * @param WaitlistManager      $waitlist        Waitlist manager.
	 */
	public function __construct(
		CouponManager $coupons,
		CouponRepository $coupon_repo,
		GiftCardManager $gift_cards,
		GiftCardRepository $gift_card_repo,
		MembershipManager $memberships,
		MembershipRepository $membership_repo,
		ResourceManager $resources,
		WaitlistManager $waitlist
	) {
		$this->coupons         = $coupons;
		$this->coupon_repo     = $coupon_repo;
		$this->gift_cards      = $gift_cards;
		$this->gift_card_repo  = $gift_card_repo;
		$this->memberships     = $memberships;
		$this->membership_repo = $membership_repo;
		$this->resources       = $resources;
		$this->waitlist        = $waitlist;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$admin = $this->require_capability( Capabilities::MANAGE_SETTINGS );
		$open  = '__return_true';

		// Coupons.
		$this->crud( 'coupons', $admin );
		register_rest_route(
			self::REST_NAMESPACE,
			'/coupons/validate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'validate_coupon' ),
				'permission_callback' => $open,
			)
		);

		// Gift cards.
		register_rest_route(
			self::REST_NAMESPACE,
			'/gift-cards',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_gift_cards' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_gift_card' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/gift-cards/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_gift_card' ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/gift-cards/balance',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'gift_card_balance' ),
				'permission_callback' => $open,
			)
		);

		// Memberships (+ enrolment).
		$this->crud( 'memberships', $admin );
		register_rest_route(
			self::REST_NAMESPACE,
			'/memberships/enroll',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'enroll_member' ),
				'permission_callback' => $admin,
			)
		);

		// Resources.
		$this->crud( 'resources', $admin );

		// Waitlist.
		register_rest_route(
			self::REST_NAMESPACE,
			'/waitlist',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_waitlist' ),
				'permission_callback' => $this->require_capability( Capabilities::MANAGE_BOOKINGS ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/waitlist/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_waitlist' ),
				'permission_callback' => $this->require_capability( Capabilities::MANAGE_BOOKINGS ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/waitlist/join',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'join_waitlist' ),
				'permission_callback' => $open,
			)
		);
	}

	/**
	 * Register list/create + item update/delete routes for a CRUD resource.
	 *
	 * @param string   $base Base path segment.
	 * @param callable $can  Permission callback.
	 * @return void
	 */
	private function crud( string $base, callable $can ): void {
		register_rest_route(
			self::REST_NAMESPACE,
			"/{$base}",
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, "list_{$base}" ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, "create_{$base}" ),
					'permission_callback' => $can,
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			"/{$base}/(?P<id>\\d+)",
			array(
				array(
					'methods'             => array( 'PUT', 'PATCH' ),
					'callback'            => array( $this, "update_{$base}" ),
					'permission_callback' => $can,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, "delete_{$base}" ),
					'permission_callback' => $can,
				),
			)
		);
	}

	// --- Coupons -------------------------------------------------------------

	/**
	 * @return WP_REST_Response
	 */
	public function list_coupons(): WP_REST_Response {
		return $this->success(
			array(
				'items' => $this->coupon_repo->all(
					array(
						'orderby' => 'id',
						'order'   => 'DESC',
					)
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function create_coupons( WP_REST_Request $request ) {
		return $this->result( $this->coupons->create( (array) $request->get_json_params() ), 201 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function update_coupons( WP_REST_Request $request ) {
		return $this->result( $this->coupons->update( (int) $request['id'], (array) $request->get_json_params() ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_coupons( WP_REST_Request $request ): WP_REST_Response {
		return $this->success( array( 'deleted' => $this->coupons->delete( (int) $request['id'] ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function validate_coupon( WP_REST_Request $request ) {
		$body = (array) $request->get_json_params();

		return $this->result( $this->coupons->validate( (string) ( $body['code'] ?? '' ), (float) ( $body['amount'] ?? 0 ) ) );
	}

	// --- Gift cards ----------------------------------------------------------

	/**
	 * @return WP_REST_Response
	 */
	public function list_gift_cards(): WP_REST_Response {
		return $this->success(
			array(
				'items' => $this->gift_card_repo->all(
					array(
						'orderby' => 'id',
						'order'   => 'DESC',
					)
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function create_gift_card( WP_REST_Request $request ) {
		return $this->result( $this->gift_cards->issue( (array) $request->get_json_params() ), 201 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_gift_card( WP_REST_Request $request ): WP_REST_Response {
		return $this->success( array( 'deleted' => $this->gift_cards->delete( (int) $request['id'] ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function gift_card_balance( WP_REST_Request $request ) {
		return $this->result( $this->gift_cards->balance( (string) $request->get_param( 'code' ) ) );
	}

	// --- Memberships ---------------------------------------------------------

	/**
	 * @return WP_REST_Response
	 */
	public function list_memberships(): WP_REST_Response {
		return $this->success(
			array(
				'items' => $this->membership_repo->all(
					array(
						'orderby' => 'id',
						'order'   => 'DESC',
					)
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function create_memberships( WP_REST_Request $request ) {
		return $this->result( $this->memberships->create_plan( (array) $request->get_json_params() ), 201 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function update_memberships( WP_REST_Request $request ) {
		return $this->result( $this->memberships->update_plan( (int) $request['id'], (array) $request->get_json_params() ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_memberships( WP_REST_Request $request ): WP_REST_Response {
		return $this->success( array( 'deleted' => $this->memberships->delete_plan( (int) $request['id'] ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function enroll_member( WP_REST_Request $request ) {
		$body = (array) $request->get_json_params();

		return $this->result( $this->memberships->enroll( (int) ( $body['customer_id'] ?? 0 ), (int) ( $body['membership_id'] ?? 0 ) ), 201 );
	}

	// --- Resources -----------------------------------------------------------

	/**
	 * @return WP_REST_Response
	 */
	public function list_resources(): WP_REST_Response {
		return $this->success( array( 'items' => $this->resources->listing() ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function create_resources( WP_REST_Request $request ) {
		return $this->result( $this->resources->create( (array) $request->get_json_params() ), 201 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function update_resources( WP_REST_Request $request ) {
		return $this->result( $this->resources->update( (int) $request['id'], (array) $request->get_json_params() ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_resources( WP_REST_Request $request ): WP_REST_Response {
		return $this->success( array( 'deleted' => $this->resources->delete( (int) $request['id'] ) ) );
	}

	// --- Waitlist ------------------------------------------------------------

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_waitlist( WP_REST_Request $request ): WP_REST_Response {
		return $this->success( array( 'items' => $this->waitlist->listing( (int) $request->get_param( 'service_id' ) ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_waitlist( WP_REST_Request $request ): WP_REST_Response {
		return $this->success( array( 'deleted' => $this->waitlist->remove( (int) $request['id'] ) ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function join_waitlist( WP_REST_Request $request ) {
		return $this->result( $this->waitlist->join( (array) $request->get_json_params() ), 201 );
	}

	/**
	 * Wrap a manager result (array|WP_Error) into a REST response.
	 *
	 * @param array<string, mixed>|\WP_Error $result Manager result.
	 * @param int                            $status Success status.
	 * @return WP_REST_Response|\WP_Error
	 */
	private function result( array|\WP_Error $result, int $status = 200 ) {
		return is_wp_error( $result ) ? $result : $this->success( $result, $status );
	}
}
