<?php
/**
 * One-file move of this WordPress site: database plus wp-content.
 *
 * @package Spokora
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Site move export streams SQL/ZIP via temp files.

/**
 * Directories under wp-content that are cache or package leftovers.
 *
 * @return string[]
 */
function spokora_backup_skip_dirs() {
	return array( 'cache', 'upgrade', 'node_modules', '.git' );
}

/**
 * Stream a zip of this site to the admin who asked for it.
 */
function spokora_backup_download() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You need to be an administrator to download this site.', 'spokora-seo-toolkit' ) );
	}
	check_admin_referer( 'spokora_backup_download' );
	if ( empty( $_POST['spokora_backup_confirm'] ) ) {
		wp_die( esc_html__( 'Confirm the download on the Backups screen, then try again.', 'spokora-seo-toolkit' ) );
	}
	if ( ! class_exists( 'ZipArchive' ) ) {
		wp_die( esc_html__( 'The zip tool is missing on this server.', 'spokora-seo-toolkit' ) );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 0 );
	}
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}

	$dir = get_temp_dir() . 'spokora-move-' . wp_generate_password( 12, false );
	$zip_path = $dir . '.zip';
	wp_mkdir_p( $dir );
	register_shutdown_function(
		static function () use ( $dir, $zip_path ) {
			spokora_backup_cleanup( $dir, $zip_path );
		}
	);

	$scrub_secrets = ! empty( $_POST['spokora_backup_scrub_secrets'] );
	spokora_backup_write_sql( $dir . '/database.sql', $scrub_secrets );
	spokora_backup_write_manifest( $dir . '/manifest.json' );
	spokora_backup_write_instructions( $dir . '/MOVE.txt' );

	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path, ZipArchive::CREATE ) ) {
		wp_die( esc_html__( 'The download could not be prepared.', 'spokora-seo-toolkit' ) );
	}
	foreach ( array( 'database.sql', 'manifest.json', 'MOVE.txt' ) as $name ) {
		$zip->addFile( $dir . '/' . $name, $name );
	}
	spokora_backup_add_tree( $zip, WP_CONTENT_DIR, 'wp-content' );
	$zip->close();

	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$host = preg_replace( '/[^a-z0-9.-]+/i', '-', $host );
	$file = 'spokora-move-' . trim( (string) $host, '-' ) . '-' . gmdate( 'Y-m-d' ) . '.zip';
	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . $file . '"' );
	header( 'Content-Length: ' . (string) filesize( $zip_path ) );
	readfile( $zip_path );
	spokora_backup_cleanup( $dir, $zip_path );
	exit;
}

/**
 * @param string $dir Working directory.
 * @param string $zip Zip path.
 */
function spokora_backup_cleanup( $dir, $zip ) {
	if ( is_file( $zip ) ) {
		wp_delete_file( $zip );
	}
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iter as $file ) {
		if ( ! $file instanceof SplFileInfo ) {
			continue;
		}
		if ( $file->isDir() ) {
			rmdir( $file->getPathname() );
		} else {
			wp_delete_file( $file->getPathname() );
		}
	}
	rmdir( $dir );
}

/**
 * @param string $path          SQL file path.
 * @param bool   $scrub_secrets Omit API Vault and Spokora license secrets from wp_options export.
 */
function spokora_backup_write_sql( $path, $scrub_secrets = false ) {
	global $wpdb;
	$handle = fopen( $path, 'wb' );
	if ( ! $handle ) {
		wp_die( esc_html__( 'The database export could not be started.', 'spokora-seo-toolkit' ) );
	}
	$scrub_option_names = array(
		'spokora_api_keys',
		'spokora_license_key',
		'spokora_license_state',
		'_transient_spokora_gsc_access_token',
		'_transient_timeout_spokora_gsc_access_token',
	);
	$options_table = $wpdb->prefix . 'options';
	fwrite( $handle, "-- Spokora move. Import into an empty database with the same table prefix.\n" );
	fwrite( $handle, "SET NAMES utf8mb4;\nSET foreign_key_checks = 0;\n" );
	$like   = $wpdb->esc_like( $wpdb->prefix ) . '%';
	$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
	if ( ! is_array( $tables ) ) {
		$tables = array();
	}
	foreach ( $tables as $table ) {
		$table = (string) $table;
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
			continue;
		}
		$create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
		if ( ! is_array( $create ) || empty( $create[1] ) ) {
			continue;
		}
		fwrite( $handle, "DROP TABLE IF EXISTS `{$table}`;\n{$create[1]};\n" );
		$offset = 0;
		do {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` LIMIT %d OFFSET %d", 100, $offset ), ARRAY_A );
			if ( ! is_array( $rows ) || ! $rows ) {
				break;
			}
			foreach ( $rows as $row ) {
				if ( $scrub_secrets && $table === $options_table && isset( $row['option_name'] ) && in_array( (string) $row['option_name'], $scrub_option_names, true ) ) {
					continue;
				}
				$cols = array();
				$vals = array();
				foreach ( $row as $col => $value ) {
					$cols[] = '`' . str_replace( '`', '``', (string) $col ) . '`';
					if ( null === $value ) {
						$vals[] = 'NULL';
					} else {
						$vals[] = "'" . esc_sql( (string) $value ) . "'";
					}
				}
				fwrite( $handle, "INSERT INTO `{$table}` (" . implode( ',', $cols ) . ') VALUES (' . implode( ',', $vals ) . ");\n" );
			}
			$count   = count( $rows );
			$offset += 100;
		} while ( 100 === $count );
	}
	fwrite( $handle, "SET foreign_key_checks = 1;\n" );
	fclose( $handle );
}

/**
 * @param string $path JSON path.
 */
function spokora_backup_write_manifest( $path ) {
	global $wpdb;
	$payload = array(
		'home'          => home_url( '/' ),
		'table_prefix'  => $wpdb->prefix,
		'wp_version'    => get_bloginfo( 'version' ),
		'spokora'       => defined( 'SPOKORA_VERSION' ) ? SPOKORA_VERSION : '',
		'created_gmt'   => gmdate( 'c' ),
		'wp_config'     => 'not included',
	);
	file_put_contents( $path, wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
}

/**
 * @param string $path Text path.
 */
function spokora_backup_write_instructions( $path ) {
	global $wpdb;
	$text  = "Move this WordPress site\n\n";
	$text .= "This zip is the database and wp-content. It does not include wp-config.php. The new host has its own database name, user, and password.\n\n";
	$text .= "1. Install WordPress on the new host.\n";
	$text .= "2. In wp-config.php on the new host, set the table prefix to {$wpdb->prefix} before you import. That is the prefix in this file.\n";
	$text .= "3. In phpMyAdmin, empty the new database, then import database.sql.\n";
	$text .= "4. Replace the new site's wp-content folder with the wp-content folder in this zip.\n";
	$text .= "5. In the options table, set siteurl and home to the new address if they still show " . home_url( '/' ) . "\n";
	$text .= "6. In WordPress, open Settings, then Permalinks, and choose Save.\n";
	file_put_contents( $path, $text );
}

/**
 * @param ZipArchive $zip  Archive.
 * @param string     $root Absolute directory.
 * @param string     $inside Zip folder name.
 */
function spokora_backup_add_tree( ZipArchive $zip, $root, $inside ) {
	if ( ! is_dir( $root ) ) {
		return;
	}
	$root = wp_normalize_path( rtrim( $root, '/\\' ) );
	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
	);
	$skip = spokora_backup_skip_dirs();
	foreach ( $iter as $file ) {
		if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
			continue;
		}
		$path = wp_normalize_path( $file->getPathname() );
		if ( ! str_starts_with( $path, $root . '/' ) ) {
			continue;
		}
		$rel = substr( $path, strlen( $root ) + 1 );
		$parts = explode( '/', $rel );
		$drop  = false;
		foreach ( $parts as $part ) {
			if ( in_array( $part, $skip, true ) ) {
				$drop = true;
				break;
			}
		}
		if ( $drop ) {
			continue;
		}
		$zip->addFile( $file->getPathname(), $inside . '/' . $rel );
	}
}

// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
