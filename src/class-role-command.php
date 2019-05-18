<?php

namespace Spacedmonkey\Users;

use WP_CLI_Command;
use WP_CLI;
use WP_CLI\Utils;
use WP_User;

/**
 * Class Role_Command
 * @package Spacedmonkey\Users
 */
class Role_Command extends WP_CLI_Command {
	/**
	 * @param $args
	 * @param $assoc_args
	 */
	public function create_table( $args, $assoc_args ) {
		delete_site_option('user_role.db.version');
		get_wp_user_role()::check_table();
	}

	/**
	 * @param $args
	 * @param $assoc_args
	 */
	public function migrate( $args, $assoc_args ) {
		global $wpdb;

		$user_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->users}" );
		$count    = count( $user_ids );
		$notify   = Utils\make_progress_bar( 'Migrate users', $count );
		foreach ( $user_ids as $user_id ) {
			$site_ids = $this->get_user_site_ids( $user_id );
			foreach ( $site_ids as $blog_id ) {
				$user       = new WP_User( $user_id, '', $blog_id );
				$network_id = get_wp_user_role()->get_network_id( $blog_id );
				get_wp_user_role()->remove_roles( [ 'user_id' => $user_id, 'site_id' => $blog_id ] );
				foreach ( $user->roles as $role ) {
					get_wp_user_role()->add_role( $user_id, $role, $blog_id, $network_id );
				}
			}
			$notify->tick();
		}
		$notify->finish();

	}

	/**
	 * @param $args
	 * @param $assoc_args
	 */
	public function migrate_superadmin( $args, $assoc_args ) {
		global $wpdb;

		if ( ! is_multisite() ) {
			WP_CLI::error( 'Must be multisite to run.' );
		}

		$network_ids = get_networks( [ 'fields' => 'ids' ] );
		$count    = count( $network_ids );
		$notify   = Utils\make_progress_bar( 'Migrate super admins', $count );
		foreach ( $network_ids as $network_id ) {
			get_wp_user_role()->populate_super_admins( get_network_option( $network_id, 'site_admins', [] ), $network_id );
			$notify->tick();
		}
		$notify->finish();
	}

	/**
	 * @param $args
	 * @param $assoc_args
	 */
	public function drop_table( $args, $assoc_args ) {

	}


	/**
	 * Get list of site ids by user id.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array
	 */
	private function get_user_site_ids( $user_id ) {
		global $wpdb;

		$site_ids = array();
		$user_id  = (int) $user_id;
		if ( empty( $user_id ) ) {
			return $site_ids;
		}

		// Logged out users can't have sites.
		$keys = get_user_meta( $user_id );

		if ( empty( $keys ) ) {
			return $site_ids;
		}
		if ( ! is_multisite() ) {
			$site_ids[] = get_current_blog_id();

			return $site_ids;
		}

		if ( isset( $keys[ $wpdb->base_prefix . 'capabilities' ] ) && defined( 'MULTISITE' ) ) {
			$site_ids[] = 1;
			unset( $keys[ $wpdb->base_prefix . 'capabilities' ] );
		}

		$keys = array_keys( $keys );

		foreach ( $keys as $key ) {
			if ( 'capabilities' !== substr( $key, - 12 ) ) {
				continue;
			}
			if ( $wpdb->base_prefix && 0 !== strpos( $key, $wpdb->base_prefix ) ) {
				continue;
			}
			$site_id = str_replace( array( $wpdb->base_prefix, '_capabilities' ), '', $key );
			if ( ! is_numeric( $site_id ) ) {
				continue;
			}

			$site_ids[] = (int) $site_id;
		}

		return $site_ids;
	}
}

WP_CLI::add_command( 'user-roles', __NAMESPACE__ . '\\Role_Command' );