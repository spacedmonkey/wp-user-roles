<?php
/**
 * WP CLI Command.
 *
 * @package wp-user-roles
 */

namespace Spacedmonkey\Users;

use WP_CLI_Command;
use WP_CLI;
use WP_CLI\Utils;
use WP_User;

/**
 * Class Role_Command
 *
 * @package Spacedmonkey\Users
 */
class Role_Command extends WP_CLI_Command {

	/**
	 * Create wp_user_role table.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 *
	 * @subcommand create-table
	 */
	public function create_table( array $args, array $assoc_args ) {
		$result = wp_user_roles()->check_table();
		if ( 'created' === $result ) {
			WP_CLI::success( __( 'Table created', 'wp-user-roles' ) );

			return;
		}
		WP_CLI::success( __( 'Table already exist', 'wp-user-roles' ) );
	}

	/**
	 * Migrate existing users.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 *
	 * @subcommand migrate
	 */
	public function migrate( array $args = [], array $assoc_args = [] ) {
		global $wpdb;

		$user_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->users}" );
		$count    = count( $user_ids );
		$success  = 0;
		$fails    = 0;
		/* translators: number of users. in wp-cli. */
		WP_CLI::line( sprintf( __( 'Migrating %d users.', 'wp-user-roles' ), $count ) );
		$notify = Utils\make_progress_bar( __( 'Migrate users', 'wp-user-roles' ), $count );
		foreach ( $user_ids as $user_id ) {
			$site_ids = $this->get_user_site_ids( $user_id );
			foreach ( $site_ids as $blog_id ) {
				$user       = new WP_User( $user_id, '', $blog_id );
				$network_id = wp_user_roles()->get_network_id( $blog_id );
				wp_user_roles()->remove_roles(
					[
						'user_id' => $user_id,
						'site_id' => $blog_id,
					]
				);
				foreach ( $user->roles as $role ) {
					$result = wp_user_roles()->add_role( $user_id, $role, $blog_id, $network_id );
					if ( ! $result ) {
						$fails ++;
					} else {
						$success ++;
					}
				}
			}
			$notify->tick();
		}
		$notify->finish();

		update_network_option( get_current_network_id(), 'user_role.migrated', 1 );
		/* translators: number of migrated users, number of users in wp-cli. */
		WP_CLI::success( sprintf( __( 'Successfully migrated %1$d roles with %2$d errors.', 'wp-user-roles' ), $success, $fails ) );
	}

	/**
	 * Migrate super admins (only for multisite).
	 *
	 * ## OPTIONS
	 *
	 *  [--network_id=<value>]
	 * : Only list the networks with these ids values (comma-separated).
	 *
	 * @param array $args
	 * @param array $assoc_args
	 *
	 * @subcommand migrate-super-admins
	 */
	public function migrate_super_admins( array $args = [], array $assoc_args = [] ) {
		global $wpdb;

		if ( ! is_multisite() ) {
			WP_CLI::error( __( 'Must be multisite to run.', 'wp-user-roles' ) );

			return;
		}
		if ( isset( $assoc_args['network_id'] ) ) {
			$network_ids = explode( ',', $assoc_args['network_id'] );
		} else {
			$network_ids = get_networks( [ 'fields' => 'ids' ] );
		}

		$count  = count( $network_ids );
		$notify = Utils\make_progress_bar( __( 'Migrate super admins', 'wp-user-roles' ), $count );
		foreach ( $network_ids as $network_id ) {
			wp_user_roles()->populate_super_admins( get_network_option( $network_id, 'site_admins', [] ), $network_id );
			$notify->tick();
		}
		$notify->finish();

		update_network_option( get_current_network_id(), 'user_role.super_admins.migrated', 1 );
	}

	/**
	 * Drop wp_user_role table.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 *
	 * @subcommand drop-table
	 */
	public function drop_table( array $args = [], array $assoc_args = [] ) {
		$result = wp_user_roles()->drop_table();
		if ( ! $result ) {
			WP_CLI::error( __( 'Unable to delete table', 'wp-user-roles' ) );

			return;
		}
		WP_CLI::success( __( 'Table dropped.', 'wp-user-roles' ) );
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
