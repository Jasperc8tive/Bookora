<?php
/**
 * Server-side renderer for Bookora's Elementor widgets (and shortcodes).
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Elementor;

use Bookora\Core\ModuleScript;
use Bookora\Services\ServiceRepository;
use Bookora\Staff\StaffRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the HTML for each Bookora widget. Kept free of any Elementor coupling
 * so it is fully unit-tested; the Elementor widget classes are thin wrappers.
 *
 * Grids render server-side (fast, SEO-friendly); the booking flow and portal
 * mount the React front-end bundle.
 */
final class WidgetRenderer {

	/**
	 * Front-end script/style handle (registered by the Frontend module).
	 */
	private const HANDLE = 'bookora-frontend';

	private ServiceRepository $services;
	private StaffRepository $staff;

	/**
	 * Constructor.
	 *
	 * @param ServiceRepository $services Service repository.
	 * @param StaffRepository   $staff    Staff repository.
	 */
	public function __construct( ServiceRepository $services, StaffRepository $staff ) {
		$this->services = $services;
		$this->staff    = $staff;
	}

	/**
	 * Booking wizard mount point.
	 *
	 * @param array<string, mixed> $atts Attributes (service, staff).
	 * @return string
	 */
	public function booking_form( array $atts = array() ): string {
		$this->enqueue_frontend();

		return sprintf(
			'<div id="bookora-booking-root" data-service="%s" data-staff="%s"></div>',
			esc_attr( (string) ( $atts['service'] ?? '' ) ),
			esc_attr( (string) ( $atts['staff'] ?? '' ) )
		);
	}

	/**
	 * Calendar widget — the booking flow contains the date/time calendar.
	 *
	 * @param array<string, mixed> $atts Attributes.
	 * @return string
	 */
	public function calendar( array $atts = array() ): string {
		return $this->booking_form( $atts );
	}

	/**
	 * Customer portal mount (populated by the portal bundle in Stage 14).
	 *
	 * @return string
	 */
	public function customer_dashboard(): string {
		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( \Bookora\Portal\PortalServiceProvider::HANDLE );
			wp_enqueue_style( \Bookora\Portal\PortalServiceProvider::HANDLE );
			ModuleScript::enable( \Bookora\Portal\PortalServiceProvider::HANDLE );
		}

		return '<div id="bookora-portal-root" class="bkra-portal"></div>';
	}

	/**
	 * Server-rendered grid of active services.
	 *
	 * @param array<string, mixed> $atts Attributes (limit, columns).
	 * @return string
	 */
	public function service_grid( array $atts = array() ): string {
		$limit    = max( 1, (int) ( $atts['limit'] ?? 12 ) );
		$services = $this->services->all(
			array(
				'where'   => array( 'status' => 'active' ),
				'orderby' => 'name',
				'order'   => 'ASC',
				'limit'   => $limit,
			)
		);

		if ( array() === $services ) {
			return '<p class="bookora-empty">' . esc_html__( 'No services available.', 'bookora' ) . '</p>';
		}

		$cards = '';
		foreach ( $services as $service ) {
			$cards .= sprintf(
				'<li class="bookora-card bookora-service"><span class="bookora-card-title">%s</span><span class="bookora-card-meta">%s</span><span class="bookora-card-price">%s %s</span></li>',
				esc_html( (string) $service['name'] ),
				esc_html( sprintf( /* translators: %d: minutes */ __( '%d min', 'bookora' ), (int) $service['duration_min'] ) ),
				esc_html( (string) $service['currency'] ),
				esc_html( number_format( (float) $service['price'], 0 ) )
			);
		}

		return '<ul class="bookora-grid bookora-service-grid">' . $cards . '</ul>';
	}

	/**
	 * Server-rendered grid of active staff.
	 *
	 * @param array<string, mixed> $atts Attributes (limit).
	 * @return string
	 */
	public function staff_grid( array $atts = array() ): string {
		$limit = max( 1, (int) ( $atts['limit'] ?? 12 ) );
		$staff = $this->staff->all(
			array(
				'where'   => array( 'status' => 'active' ),
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'limit'   => $limit,
			)
		);

		if ( array() === $staff ) {
			return '<p class="bookora-empty">' . esc_html__( 'No team members to show.', 'bookora' ) . '</p>';
		}

		$cards = '';
		foreach ( $staff as $member ) {
			$avatar = '' !== (string) ( $member['avatar_url'] ?? '' )
				? sprintf( '<img class="bookora-avatar" src="%s" alt="%s" />', esc_url( (string) $member['avatar_url'] ), esc_attr( (string) $member['display_name'] ) )
				: '';
			$cards .= sprintf(
				'<li class="bookora-card bookora-staff">%s<span class="bookora-card-title">%s</span><span class="bookora-card-bio">%s</span></li>',
				$avatar,
				esc_html( (string) $member['display_name'] ),
				esc_html( wp_trim_words( (string) ( $member['bio'] ?? '' ), 24 ) )
			);
		}

		return '<ul class="bookora-grid bookora-staff-grid">' . $cards . '</ul>';
	}

	/**
	 * Enqueue the front-end React bundle (as an ES module).
	 *
	 * @return void
	 */
	private function enqueue_frontend(): void {
		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( self::HANDLE );
			wp_enqueue_style( self::HANDLE );
			ModuleScript::enable( self::HANDLE );
		}
	}
}
