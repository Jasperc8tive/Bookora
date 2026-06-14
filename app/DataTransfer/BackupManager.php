<?php
/**
 * On-site backup & restore of Bookora data.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\DataTransfer;

use Bookora\Core\ProtectedDirectory;
use Bookora\Security\ActivityLogger;
use Bookora\Security\Crypto;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Creates point-in-time snapshots of all Bookora data and restores them. Built
 * on {@see DataPortability} so backups and exports share one (well-tested)
 * serialisation format.
 *
 * Snapshots are written to an unguessable, per-site {@see ProtectedDirectory}
 * (Apache/IIS guard files + index, plus an HMAC-named path for nginx) and are
 * **encrypted at rest** via {@see Crypto}, so an exposed file leaks nothing.
 */
final class BackupManager {

	private DataPortability $data;
	private ActivityLogger $audit;
	private Crypto $crypto;
	private string $directory;

	/**
	 * Constructor.
	 *
	 * @param DataPortability $data      Data portability service.
	 * @param ActivityLogger  $audit     Audit logger.
	 * @param Crypto          $crypto    At-rest encryption.
	 * @param string|null     $directory Optional directory override (tests).
	 */
	public function __construct( DataPortability $data, ActivityLogger $audit, Crypto $crypto, ?string $directory = null ) {
		$this->data      = $data;
		$this->audit     = $audit;
		$this->crypto    = $crypto;
		$this->directory = null !== $directory ? rtrim( $directory, '/\\' ) : ProtectedDirectory::path( 'backups' );
	}

	/**
	 * Create a new backup snapshot.
	 *
	 * @return array<string, mixed>|WP_Error Backup manifest entry, or error.
	 */
	public function create(): array|WP_Error {
		if ( ! $this->ensure_directory() ) {
			return new WP_Error( 'bookora_backup_dir', __( 'The backup directory could not be created.', 'bookora' ), array( 'status' => 500 ) );
		}

		$id       = gmdate( 'Ymd-His' ) . '-' . substr( (string) wp_generate_password( 8, false ), 0, 6 );
		$file     = $this->directory . '/' . $id . '.json';
		$document = $this->data->export( true );
		$json     = wp_json_encode( $document );
		if ( false === $json ) {
			return new WP_Error( 'bookora_backup_encode', __( 'The backup could not be encoded.', 'bookora' ), array( 'status' => 500 ) );
		}

		// Encrypt at rest so an exposed backup file leaks nothing.
		$ciphertext = $this->crypto->encrypt( $json );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $file, $ciphertext ) ) {
			return new WP_Error( 'bookora_backup_write', __( 'The backup file could not be written.', 'bookora' ), array( 'status' => 500 ) );
		}

		$this->audit->log( 'backup.created', array( 'id' => $id ) );

		return $this->manifest_entry( $id, $file );
	}

	/**
	 * List existing backups, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list(): array {
		if ( ! is_dir( $this->directory ) ) {
			return array();
		}

		$files = glob( $this->directory . '/*.json' );
		$items = array();
		foreach ( is_array( $files ) ? $files : array() as $file ) {
			$id      = basename( $file, '.json' );
			$items[] = $this->manifest_entry( $id, $file );
		}

		usort( $items, static fn ( array $a, array $b ): int => strcmp( (string) $b['id'], (string) $a['id'] ) );

		return $items;
	}

	/**
	 * Restore a backup by id (replaces current data).
	 *
	 * @param string $id Backup id.
	 * @return array<string, int>|WP_Error
	 */
	public function restore( string $id ): array|WP_Error {
		$file = $this->path_for( $id );
		if ( null === $file ) {
			return new WP_Error( 'bookora_backup_missing', __( 'That backup could not be found.', 'bookora' ), array( 'status' => 404 ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$contents = (string) file_get_contents( $file );

		// Backups are encrypted at rest; fall back to legacy plaintext JSON.
		$json    = $this->crypto->decrypt( $contents );
		$payload = json_decode( null !== $json ? $json : $contents, true );
		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'bookora_backup_corrupt', __( 'The backup file is corrupt or could not be decrypted.', 'bookora' ), array( 'status' => 422 ) );
		}

		$result = $this->data->import( $payload, true );
		if ( ! ( $result instanceof WP_Error ) ) {
			$this->audit->log( 'backup.restored', array( 'id' => $id ) );
		}

		return $result;
	}

	/**
	 * Delete a backup by id.
	 *
	 * @param string $id Backup id.
	 * @return bool
	 */
	public function delete( string $id ): bool {
		$file = $this->path_for( $id );
		if ( null === $file ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.WP.AlternativeFunctions.file_system_operations_unlink
		$ok = unlink( $file );
		if ( $ok ) {
			$this->audit->log( 'backup.deleted', array( 'id' => $id ) );
		}

		return $ok;
	}

	/**
	 * Resolve and validate the path for a backup id (guards against traversal).
	 *
	 * @param string $id Backup id.
	 * @return string|null
	 */
	private function path_for( string $id ): ?string {
		if ( 1 !== preg_match( '/^[A-Za-z0-9\-]+$/', $id ) ) {
			return null;
		}
		$file = $this->directory . '/' . $id . '.json';

		return is_file( $file ) ? $file : null;
	}

	/**
	 * Build a manifest entry for a backup file.
	 *
	 * @param string $id   Backup id.
	 * @param string $file Absolute path.
	 * @return array<string, mixed>
	 */
	private function manifest_entry( string $id, string $file ): array {
		return array(
			'id'         => $id,
			'size'       => is_file( $file ) ? (int) filesize( $file ) : 0,
			'created_at' => is_file( $file ) ? gmdate( 'c', (int) filemtime( $file ) ) : '',
		);
	}

	/**
	 * Create and protect the backup directory if needed.
	 *
	 * @return bool
	 */
	private function ensure_directory(): bool {
		return ProtectedDirectory::ensure( $this->directory );
	}
}
