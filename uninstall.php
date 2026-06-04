<?php
/**
 * Uninstall: remove all plugin data.
 *
 * @package Multilang
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'mlr_languages' );
delete_option( 'mlr_settings' );

// Multisite: clean each site.
if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		delete_option( 'mlr_languages' );
		delete_option( 'mlr_settings' );
		restore_current_blog();
	}
}
