<?php
/**
 * PHPStan bootstrap: defines plugin constants so static analysis can resolve them
 * without loading WordPress.
 *
 * @package Bookora
 */

define( 'BOOKORA_VERSION', '0.1.0' );
define( 'BOOKORA_DB_VERSION', '1' );
define( 'BOOKORA_FILE', __FILE__ );
define( 'BOOKORA_BASENAME', 'bookora/bookora.php' );
define( 'BOOKORA_PATH', __DIR__ . '/' );
define( 'BOOKORA_URL', 'https://example.test/wp-content/plugins/bookora/' );
define( 'BOOKORA_PREFIX', 'bkra_' );
define( 'BOOKORA_MIN_PHP', '8.2' );
define( 'BOOKORA_MIN_WP', '6.8' );
