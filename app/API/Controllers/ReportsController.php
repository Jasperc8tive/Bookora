<?php
/**
 * Reports / analytics REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Appointments\Clock;
use Bookora\Reports\ReportService;
use Bookora\Security\Capabilities;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Analytics endpoints for the admin reporting dashboard.
 */
final class ReportsController extends AbstractController {

	private ReportService $reports;
	private Clock $clock;

	/**
	 * Constructor.
	 *
	 * @param ReportService $reports Report service.
	 * @param Clock         $clock   Clock.
	 */
	public function __construct( ReportService $reports, Clock $clock ) {
		$this->reports = $reports;
		$this->clock   = $clock;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$can = $this->require_capability( Capabilities::VIEW_REPORTS );

		register_rest_route(
			self::REST_NAMESPACE,
			'/reports/overview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'overview' ),
				'permission_callback' => $can,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/reports/utilization',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'utilization' ),
				'permission_callback' => $can,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/reports/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'export' ),
				'permission_callback' => $can,
			)
		);
	}

	/**
	 * Overview KPIs + breakdowns.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function overview( WP_REST_Request $request ) {
		$range = $this->range( $request );
		if ( $range instanceof \WP_Error ) {
			return $range;
		}

		return $this->success( $this->reports->overview( $range[0], $range[1] ) );
	}

	/**
	 * Per-staff utilisation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function utilization( WP_REST_Request $request ) {
		$from = (string) $request->get_param( 'from' );
		$to   = (string) $request->get_param( 'to' );
		if ( ! $this->valid_date( $from ) || ! $this->valid_date( $to ) ) {
			return $this->error( 'bookora_invalid_range', __( 'from and to must be YYYY-MM-DD.', 'bookora' ), 422 );
		}

		return $this->success( array( 'items' => $this->reports->utilization( $from, $to ) ) );
	}

	/**
	 * CSV export.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function export( WP_REST_Request $request ) {
		$range = $this->range( $request );
		if ( $range instanceof \WP_Error ) {
			return $range;
		}
		$type = (string) $request->get_param( 'type' );
		$csv  = $this->reports->export_csv( in_array( $type, array( 'staff', 'service' ), true ) ? $type : 'revenue', $range[0], $range[1] );

		return $this->success( array( 'csv' => $csv ) );
	}

	/**
	 * Resolve and validate the from/to range into UTC bounds.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{0:string,1:string}|\WP_Error
	 */
	private function range( WP_REST_Request $request ): array|\WP_Error {
		$from = (string) $request->get_param( 'from' );
		$to   = (string) $request->get_param( 'to' );
		if ( ! $this->valid_date( $from ) || ! $this->valid_date( $to ) ) {
			return $this->error( 'bookora_invalid_range', __( 'from and to must be YYYY-MM-DD.', 'bookora' ), 422 );
		}

		return array( $this->clock->day_bounds_utc( $from )[0], $this->clock->day_bounds_utc( $to )[1] );
	}

	/**
	 * Whether a string is a YYYY-MM-DD date.
	 *
	 * @param string $date Candidate.
	 * @return bool
	 */
	private function valid_date( string $date ): bool {
		return 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date );
	}
}
