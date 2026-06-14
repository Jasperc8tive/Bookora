<?php
/**
 * File-based PSR-3 logger.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Core;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

defined( 'ABSPATH' ) || exit;

/**
 * Writes level-filtered log lines to a protected directory under uploads.
 *
 * Debug/operational logging only — tamper-evident audit logging lives in the
 * activity_logs table (Stage 2).
 */
class Logger extends AbstractLogger {

	/**
	 * Numeric severity per PSR-3 level (higher = more severe).
	 *
	 * @var array<string, int>
	 */
	private const SEVERITY = array(
		LogLevel::DEBUG     => 100,
		LogLevel::INFO      => 200,
		LogLevel::NOTICE    => 250,
		LogLevel::WARNING   => 300,
		LogLevel::ERROR     => 400,
		LogLevel::CRITICAL  => 500,
		LogLevel::ALERT     => 550,
		LogLevel::EMERGENCY => 600,
	);

	/**
	 * Minimum severity to record.
	 *
	 * @var int
	 */
	private int $threshold;

	/**
	 * Absolute path to the log directory.
	 *
	 * @var string
	 */
	private string $directory;

	/**
	 * Constructor.
	 *
	 * @param Settings|null $settings  Settings (for the configured log level).
	 * @param string|null   $directory Optional override for the log directory.
	 */
	public function __construct( ?Settings $settings = null, ?string $directory = null ) {
		$settings        = $settings ?? new Settings();
		$level           = (string) $settings->get( 'log_level', LogLevel::ERROR );
		$this->threshold = self::SEVERITY[ $level ] ?? self::SEVERITY[ LogLevel::ERROR ];

		$this->directory = null !== $directory ? rtrim( $directory, '/\\' ) : ProtectedDirectory::path( 'logs' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed                $level   PSR-3 level.
	 * @param string|Stringable    $message Message.
	 * @param array<string, mixed> $context Interpolation context.
	 * @return void
	 */
	public function log( $level, string|Stringable $message, array $context = array() ): void {
		$severity = self::SEVERITY[ (string) $level ] ?? 0;
		if ( $severity < $this->threshold ) {
			return;
		}

		if ( ! $this->ensure_directory() ) {
			return;
		}

		$line = sprintf(
			"[%s] %s: %s%s\n",
			gmdate( 'Y-m-d H:i:s' ),
			strtoupper( (string) $level ),
			$this->interpolate( (string) $message, $context ),
			array() === $context ? '' : ' ' . wp_json_encode( $context )
		);

		$file = $this->directory . '/bookora-' . gmdate( 'Y-m-d' ) . '.log';
		error_log( $line, 3, $file ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Replace {placeholders} in the message with context values.
	 *
	 * @param string               $message Message template.
	 * @param array<string, mixed> $context Context.
	 * @return string
	 */
	private function interpolate( string $message, array $context ): string {
		if ( false === strpos( $message, '{' ) ) {
			return $message;
		}

		$replacements = array();
		foreach ( $context as $key => $value ) {
			if ( is_scalar( $value ) || $value instanceof Stringable ) {
				$replacements[ '{' . $key . '}' ] = (string) $value;
			}
		}

		return strtr( $message, $replacements );
	}

	/**
	 * Create and protect the log directory if needed.
	 *
	 * @return bool Whether the directory is usable.
	 */
	private function ensure_directory(): bool {
		return ProtectedDirectory::ensure( $this->directory );
	}
}
