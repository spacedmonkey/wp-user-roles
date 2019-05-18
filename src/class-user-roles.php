<?php

namespace Spacedmonkey\Users;

use WP_User;

/**
 * Class User_Roles
 * @package Spacedmonkey\Users
 */
class User_Roles {

	const TABLE_NAME = 'userrole';

	const VERSION = '1.0.0';

	/**
	 * Roles constructor.
	 */
	public function __construct() {
		self::startup();
	}

	public function bootstrap() {

		add_action( 'add_user_role', [ $this, 'add_user_role' ], 15, 2 );
		add_action( 'remove_user_role', [ $this, 'remove_user_role' ], 15, 2 );
		add_action( 'set_user_role', [ $this, 'set_user_role' ], 15, 3 );

		add_action( 'add_user_to_blog', [ $this, 'add_user_to_blog' ], 15, 3 );
		add_action( 'remove_user_from_blog', [ $this, 'remove_user_from_blog' ], 15, 2 );

		add_action( 'profile_update', [ $this, 'profile_update' ], 15 );
		add_action( 'user_register', [ $this, 'user_register' ], 15 );

		add_action( 'deleted_user', [ $this, 'deleted_user' ], 15, 2 );
		add_action( 'wpmu_delete_user', [ $this, 'wpmu_delete_user' ], 15, 1 );

		add_action( 'revoke_super_admin', [ $this, 'revoke_super_admin' ], 15, 1 );
		add_action( 'granted_super_admin', [ $this, 'granted_super_admin' ], 15, 1 );
		add_action( 'update_site_option_site_admins', [ $this, 'update_site_option_site_admins' ], 15, 4 );


		add_filter( 'populate_network_meta', [ $this, 'populate_network_meta' ], 15, 2 );
		add_filter( 'users_pre_query', [ $this, 'users_pre_query' ], 15, 2 );

		add_filter( 'pre_count_users', [ $this, 'pre_count_users' ], 15, 3 );

		add_action( 'add_network', [ $this, 'add_network' ], 15, 2 );
		add_action( 'delete_network', [ $this, 'delete_network' ], 15, 1 );
		add_action( 'move_site', [ $this, 'move_site' ], 15, 3 );

		add_action( 'wp_update_site', [ $this, 'wp_update_site' ], 15, 2 );
		add_action( 'wp_delete_site', [ $this, 'wp_delete_site' ], 15, 1 );

		add_action( 'admin_init', [ $this, 'check_table' ], 15, 1 );

	}

	static function activate() {
		self::check_table();
	}


	static function startup() {
		// Define the table variables
		if ( empty( $GLOBALS['wpdb']->userrole ) ) {
			$GLOBALS['wpdb']->userrole        = $GLOBALS['wpdb']->base_prefix . self::TABLE_NAME;
			$GLOBALS['wpdb']->global_tables[] = self::TABLE_NAME;
		}
	}


	/**
	 * Check the Mercator mapping table
	 *
	 * @return string|boolean One of 'exists' (table already existed), 'created' (table was created), or false if could not be created
	 */
	static function check_table() {
		global $wpdb;
		if ( get_site_option( 'user_role.db.version' ) === self::VERSION ) {
			return 'exists';
		}
		self::startup();
		$schema = "CREATE TABLE {$wpdb->userrole} (
			id bigint(20) NOT NULL auto_increment,
			site_id bigint(20) NOT NULL default 0,
			network_id bigint(20) NOT NULL default 0,
			user_id bigint(20) NOT NULL default 0,
			role varchar(191) NOT NULL,
			PRIMARY KEY  (id),
			KEY site_id (site_id),
			KEY network_id (network_id),
			KEY user_id (user_id),
			KEY role (role)
		);";
		if ( ! function_exists( 'dbDelta' ) ) {
			if ( ! is_admin() ) {
				return false;
			}
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$result = dbDelta( $schema );
		// Update db version option.
		update_site_option( 'user_role.db.version', self::VERSION );

		if ( empty( $result ) ) {
			// No changes, database already exists and is up-to-date
			return 'exists';
		}
		// utf8mb4 conversion.
		maybe_convert_table_to_utf8mb4( $wpdb->userrole );

		return 'created';
	}

	/**
	 * @param $user_id
	 * @param $role
	 */
	public function add_user_role( $user_id, $role ) {
		$blog_id    = get_current_blog_id();
		$network_id = $this->get_network_id( $blog_id );
		$this->add_role( $user_id, $role, $blog_id, $network_id );
	}

	/**
	 * @param $user_id
	 * @param $role
	 */
	public function remove_user_role( $user_id, $role ) {
		$blog_id = get_current_blog_id();
		$this->remove_roles( [ 'user_id' => $user_id, 'role' => $role, 'site_id' => $blog_id ] );
	}

	/**
	 * @param $user_id
	 */
	public function profile_update( $user_id ) {
		$this->after_user_save( $user_id );
	}

	/**
	 * @param $user_id
	 */
	public function user_register( $user_id ) {
		$this->after_user_save( $user_id );
	}

	/**
	 * @param $user_id
	 * @param $role
	 * @param $old_roles
	 */
	public function set_user_role( $user_id, $role, $old_roles ) {
		$blog_id    = get_current_blog_id();
		$network_id = $this->get_network_id( $blog_id );

		$this->remove_roles( [ 'user_id' => $user_id, 'site_id' => $blog_id, 'network_id' => $network_id ] );
		if ( ! empty( $role ) ) {
			$this->add_role( $user_id, $role, $blog_id, $network_id );
		}
	}

	/**
	 * @param $user_id
	 * @param $role
	 * @param $blog_id
	 */
	public function add_user_to_blog( $user_id, $role, $blog_id ) {
		$this->add_role( $user_id, $role, $blog_id );
	}

	/**
	 * @param $user_id
	 * @param $blog_id
	 */
	public function remove_user_from_blog( $user_id, $blog_id ) {
		$this->remove_roles( [ 'user_id' => $user_id, 'site_id' => $blog_id ] );
	}

	/**
	 * @param $id
	 */
	public function deleted_user( $user_id ) {
		$blog_id = get_current_blog_id();
		$this->remove_roles( [ 'user_id' => $user_id, 'site_id' => $blog_id ] );
	}

	/**
	 * @param $id
	 */
	public function wpmu_delete_user( $user_id ) {
		$this->remove_roles( [ 'user_id' => $user_id ] );
	}

	/**
	 * @param $user_id
	 */
	public function revoke_super_admin( $user_id ) {
		$network_id = get_current_network_id();
		$this->remove_roles( [ 'user_id' => $user_id, 'role' => 'super-admin', 'network_id' => $network_id ] );
	}

	/**
	 * @param $user_id
	 */
	public function granted_super_admin( $user_id ) {
		$network_id = get_current_network_id();
		$this->add_role( $user_id, 'super-admin', 0, $network_id );
	}

	/**
	 * @param $option
	 * @param $value
	 * @param $old_value
	 * @param $network_id
	 */
	function update_site_option_site_admins( $option, $value, $old_value, $network_id ) {
		$this->populate_super_admins( $value, $network_id );
	}

	/**
	 * @param $user_logins
	 * @param $network_id
	 */
	public function populate_super_admins( $user_logins, $network_id ) {
		$users    = array_map(
			function ( $user_login ) {
				return get_user_by( 'login', $user_login );
			},
			$user_logins
		);
		$user_ids = wp_list_pluck( $users, 'ID' );
		$user_ids = array_filter( $user_ids );
		$this->remove_roles( [ 'network_id' => $network_id, 'role' => 'super-admin' ] );
		foreach ( $user_ids as $user_id ) {
			$this->add_role( $user_id, 'super-admin', 0, $network_id );
		}
	}

	/**
	 * @param $sitemeta
	 * @param $network_id
	 */
	public function populate_network_meta( $sitemeta, $network_id ) {
		$this->populate_super_admins( get_network_option( $network_id, 'site_admins', [] ), $network_id );
	}

	/**
	 * @param $users
	 * @param $wp_user_query
	 */
	public function users_pre_query( $users, $wp_user_query ) {
	}

	/**
	 * @param $count
	 * @param $strategy
	 * @param $site_id
	 */
	public function pre_count_users( $count, $strategy, $site_id ) {
	}


	/**
	 * @param $new_network_id
	 * @param $r
	 */
	public function add_network( $network_id, $r ) {
		$this->populate_super_admins( get_network_option( $network_id, 'site_admins', [] ) );
	}

	/**
	 * @param $network
	 */
	public function delete_network( $network ) {
		$this->remove_roles( [ 'network_id' => $network->site_id ] );
	}

	/**
	 * @param $site_id
	 * @param $network_id
	 * @param $new_network_id
	 */
	public function move_site( $site_id, $network_id, $new_network_id ) {
		if ( $network_id === $new_network_id ) {
			return;
		}

		global $wpdb;

		$wpdb->update( $wpdb->userrole, [
			'network_id' => $new_network_id
		], [
			'site_id' => $site_id
		] );
	}

	/**
	 * @param $new_site
	 * @param $old_site
	 */
	public function wp_update_site( $new_site, $old_site ) {
		$this->move_site( $new_site->blog_id, $old_site->network_id, $new_site->network_id );

	}

	/**
	 * @param $old_site
	 */
	public function wp_delete_site( $old_site ) {
		$this->remove_roles( [ 'site_id' => $old_site->blog_id ] );
	}

	/**
	 * @param int    $user_id
	 * @param string $role
	 * @param bool   $blog_id
	 * @param bool   $network_id
	 *
	 * @return bool|false|int
	 */
	public function add_role( $user_id = 0, $role = '', $blog_id = false, $network_id = false ) {
		global $wpdb;
		$id   = false;
		$test = $this->get_role( $user_id, $role, $blog_id, $network_id );

		if ( ! $test ) {
			$id   = $wpdb->insert( $wpdb->userrole, [
				'user_id'    => $user_id,
				'site_id'    => $blog_id,
				'network_id' => $network_id,
				'role'       => $role
			] );
			$test = $this->get_role( $user_id, $role, $blog_id, $network_id );
		}

		return $test;
	}

	/**
	 * @param array $args
	 *
	 * @return false|int
	 */
	public function remove_roles( array $args ) {
		global $wpdb;

		return $wpdb->delete( $wpdb->userrole, $args );
	}

	/**
	 * @param int    $user_id
	 * @param string $role
	 * @param int    $blog_id
	 * @param int    $network_id
	 *
	 * @return array
	 */
	private function get_role( $user_id = 0, $role = '', $blog_id = 0, $network_id = 0 ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->userrole} WHERE user_id = %d AND role = %s AND site_id = %d AND network_id = %d", $user_id, $role, $blog_id, $network_id ) );
	}


	/**
	 * @param $user_id
	 *
	 * @return \WP_User
	 */
	public function after_user_save( $user_id ) {
		$blog_id    = get_current_blog_id();
		$network_id = $this->get_network_id( $blog_id );

		$user = new WP_User( $user_id, '', $blog_id );
		$this->remove_roles( [ 'user_id' => $user_id, 'site_id' => $blog_id ] );
		foreach ( $user->roles as $role ) {
			$this->add_role( $user_id, $role, $blog_id, $network_id );
		}

		return $user;
	}

	/**
	 * @param $blog_id
	 *
	 * @return int
	 */
	public function get_network_id( $blog_id ) {
		if ( is_multisite() ) {
			$network_id = get_site( $blog_id )->network_id;
		} else {
			$network_id = get_current_network_id();
		}

		return $network_id;
	}
}