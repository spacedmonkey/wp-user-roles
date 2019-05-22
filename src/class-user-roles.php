<?php
/**
 * Main plugin file.
 *
 * @package wp-user-roles
 */

namespace Spacedmonkey\Users;

use WP_User;
use WP_Meta_Query;

/**
 * Class User_Roles
 *
 * @package Spacedmonkey\Users
 */
class User_Roles {

	/**
	 * Constant defining table name.
	 */
	const TABLE_NAME = 'userrole';

	/**
	 * Database version number.
	 */
	const VERSION = '1.0.0';

	/**
	 * Roles constructor.
	 */
	public function __construct() {
		self::startup();
	}

	/**
	 * Bootstrap all the filters and actions.
	 */
	public function bootstrap() {
		add_action( 'add_user_role', [ $this, 'add_user_role' ], 15, 2 );
		add_action( 'remove_user_role', [ $this, 'remove_user_role' ], 15, 2 );
		add_action( 'set_user_role', [ $this, 'set_user_role' ], 15, 2 );

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

	/**
	 * Activation hook.
	 */
	public static function activate() {
		self::check_table();
	}

	/**
	 * Uninstall hook.
	 */
	public static function uninstall() {
		self::drop_table();
	}


	/**
	 * Startup bootstraps in the new table.
	 */
	public static function startup() {
		// Define the table variables.
		if ( empty( $GLOBALS['wpdb']->userrole ) ) {
			$GLOBALS['wpdb']->userrole        = $GLOBALS['wpdb']->base_prefix . self::TABLE_NAME;
			$GLOBALS['wpdb']->global_tables[] = self::TABLE_NAME;
		}
	}


	/**
	 * Check the User role table.
	 *
	 * @return string|boolean One of 'exists' (table already existed), 'created' (table was created), or false if could not be created.
	 */
	public static function check_table() {
		global $wpdb;
		if ( get_network_option( get_current_network_id(), 'user_role.db.version' ) === self::VERSION ) {
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
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$result = dbDelta( $schema );
		// Update db version option.
		update_network_option( get_current_network_id(), 'user_role.db.version', self::VERSION );

		if ( empty( $result ) ) {
			// No changes, database already exists and is up-to-date.
			return 'exists';
		}
		// utf8mb4 conversion.
		maybe_convert_table_to_utf8mb4( $wpdb->userrole );

		return 'created';
	}

	/**
	 * Drop table and reset options.
	 *
	 * @return bool|false|int
	 */
	public static function drop_table() {
		global $wpdb;

		delete_network_option( get_current_network_id(), 'user_role.db.version' );
		delete_network_option( get_current_network_id(), 'user_role.migrated' );

		return $wpdb->query( "DROP TABLE IF EXISTS `$wpdb->userrole`" );
	}

	/**
	 * Filter add_user_role to save to new table.
	 *
	 * @param int    $user_id User ID to update.
	 * @param string $role Value of role to change.
	 */
	public function add_user_role( $user_id, $role ) {
		$blog_id    = get_current_blog_id();
		$network_id = $this->get_network_id( $blog_id );
		$this->add_role( $user_id, $role, $blog_id, $network_id );
	}

	/**
	 *  Filter remove_user_role to save to new table.
	 *
	 * @param int    $user_id User ID to update.
	 * @param string $role Value of role to change.
	 */
	public function remove_user_role( $user_id, $role ) {
		$blog_id = get_current_blog_id();
		$this->remove_roles(
			[
				'user_id' => $user_id,
				'role'    => $role,
				'site_id' => $blog_id,
			]
		);
	}

	/**
	 * On update user in wp_insert_user.
	 *
	 * @param int $user_id User ID to update.
	 */
	public function profile_update( $user_id ) {
		$this->after_user_save( $user_id );
	}

	/**
	 * On register user in wp_insert_user.
	 *
	 * @param int $user_id User ID to update.
	 */
	public function user_register( $user_id ) {
		$this->after_user_save( $user_id );
	}

	/**
	 * On set user role, remove existing roles and save the new one.
	 *
	 * @param int    $user_id User ID to update.
	 * @param string $role Value of role to change.
	 */
	public function set_user_role( $user_id, $role ) {
		$blog_id    = get_current_blog_id();
		$network_id = $this->get_network_id( $blog_id );

		$this->remove_roles(
			[
				'user_id'    => $user_id,
				'site_id'    => $blog_id,
				'network_id' => $network_id,
			]
		);
		if ( ! empty( $role ) ) {
			$this->add_role( $user_id, $role, $blog_id, $network_id );
		}
	}

	/**
	 * Hook into adding a user to a blog and also add the role.
	 *
	 * @param int    $user_id User ID to update.
	 * @param string $role Value of role to change.
	 * @param int    $blog_id Site ID to update. Only for multisite.
	 */
	public function add_user_to_blog( $user_id, $role, $blog_id ) {
		$this->add_role( $user_id, $role, $blog_id );
	}

	/**
	 * Hook into removing a user to a blog and also remove the role.
	 *
	 * @param int $user_id User ID to update.
	 * @param int $blog_id Site ID to update. Only for multisite.
	 */
	public function remove_user_from_blog( $user_id, $blog_id ) {
		$this->remove_roles(
			[
				'user_id' => $user_id,
				'site_id' => $blog_id,
			]
		);
	}

	/**
	 * On delete user, also remove role.
	 *
	 * @param int $user_id User ID to update.
	 */
	public function deleted_user( $user_id ) {
		$blog_id = get_current_blog_id();
		$this->remove_roles(
			[
				'user_id' => $user_id,
				'site_id' => $blog_id,
			]
		);
	}

	/**
	 * On delete user, also remove all roles.
	 *
	 * @param int $user_id User ID to update.
	 */
	public function wpmu_delete_user( $user_id ) {
		$this->remove_roles( [ 'user_id' => $user_id ] );
	}

	/**
	 * On remove super admin, also remove the global role.
	 *
	 * @param int $user_id User ID to update.
	 */
	public function revoke_super_admin( $user_id ) {
		$network_id = get_current_network_id();
		$this->remove_roles(
			[
				'user_id'    => $user_id,
				'role'       => 'super-admin',
				'network_id' => $network_id,
			]
		);
	}

	/**
	 * On add super admin, also add the global role.
	 *
	 * @param int $user_id User ID to update.
	 */
	public function granted_super_admin( $user_id ) {
		$network_id = get_current_network_id();
		$this->add_role( $user_id, 'super-admin', 0, $network_id );
	}

	/**
	 * Hook into update network option, and save global roles.
	 *
	 * @param string $option Unused.
	 * @param mixed  $value Value of super admins.
	 * @param mixed  $old_value Unused.
	 * @param int    $network_id Network id.
	 */
	public function update_site_option_site_admins( $option, $value, $old_value, $network_id ) {
		$this->populate_super_admins( $value, $network_id );
	}

	/**
	 * Helper function to populate super admins.
	 *
	 * @param array $user_logins array of user logins.
	 * @param int   $network_id Network id.
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
		$this->remove_roles(
			[
				'network_id' => $network_id,
				'role'       => 'super-admin',
			]
		);
		foreach ( $user_ids as $user_id ) {
			$this->add_role( $user_id, 'super-admin', 0, $network_id );
		}
	}

	/**
	 * Hook into the create of a network.
	 *
	 * @param array $sitemeta Unused.
	 * @param int   $network_id Network id.
	 */
	public function populate_network_meta( $sitemeta, $network_id ) {
		$this->populate_super_admins( get_network_option( $network_id, 'site_admins', [] ), $network_id );
	}

	/**
	 * Hook into users_pre_query in WP_User_Query and override the query.
	 *
	 * @param array         $users Existing filter values.
	 * @param WP_User_Query $wp_user_query Current WP_User_Query object.
	 *
	 * @return mixed
	 */
	public function users_pre_query( $users, $wp_user_query ) {
		global $wpdb;

		if ( '1' !== get_network_option( get_current_network_id(), 'user_role.migrated', 0 ) ) {
			return $users;
		}

		$qv               = $wp_user_query->query_vars;
		$this->meta_query = new WP_Meta_Query();

		$blog_id = 0;
		if ( isset( $qv['blog_id'] ) ) {
			$blog_id = absint( $qv['blog_id'] );
		}

		$roles = array();
		if ( isset( $qv['role'] ) ) {
			if ( is_array( $qv['role'] ) ) {
				$roles = $qv['role'];
			} elseif ( is_string( $qv['role'] ) && ! empty( $qv['role'] ) ) {
				$roles = array_map( 'trim', explode( ',', $qv['role'] ) );
			}
		}

		$role__in = array();
		if ( isset( $qv['role__in'] ) ) {
			$role__in = (array) $qv['role__in'];
		}

		$role__not_in = array();
		if ( isset( $qv['role__not_in'] ) ) {
			$role__not_in = (array) $qv['role__not_in'];
		}

		$query_where_new = '';

		if ( $blog_id ) {
			$query_where_new = $wpdb->prepare( " AND $wpdb->userrole.site_id = %d", $blog_id );
		}

		if ( ( ! empty( $roles ) || ! empty( $role__in ) || ! empty( $role__not_in ) ) || is_multisite() ) {
			$role_queries = array();

			$roles_clauses = array( 'relation' => 'AND' );
			if ( ! empty( $roles ) ) {
				foreach ( $roles as $role ) {
					$roles_clauses[] = array(
						'key'     => $wpdb->get_blog_prefix( $blog_id ) . 'capabilities',
						'value'   => '"' . $role . '"',
						'compare' => 'LIKE',
					);
				}

				$role_queries[]   = $roles_clauses;
				$query_where_new .= " AND $wpdb->userrole.role IN  ( '" . implode( "', '", $wpdb->_escape( $roles ) ) . "' )";
			}

			$role__in_clauses = array( 'relation' => 'OR' );
			if ( ! empty( $role__in ) ) {
				foreach ( $role__in as $role ) {
					$role__in_clauses[] = array(
						'key'     => $wpdb->get_blog_prefix( $blog_id ) . 'capabilities',
						'value'   => '"' . $role . '"',
						'compare' => 'LIKE',
					);
				}

				$role_queries[] = $role__in_clauses;

				$query_where_new .= " AND $wpdb->userrole.role IN  ( '" . implode( "', '", $wpdb->_escape( $role__in ) ) . "' )";
			}

			$role__not_in_clauses = array( 'relation' => 'AND' );
			if ( ! empty( $role__not_in ) ) {
				foreach ( $role__not_in as $role ) {
					$role__not_in_clauses[] = array(
						'key'     => $wpdb->get_blog_prefix( $blog_id ) . 'capabilities',
						'value'   => '"' . $role . '"',
						'compare' => 'NOT LIKE',
					);
				}

				$role_queries[] = $role__not_in_clauses;

				$query_where_new .= " AND $wpdb->userrole.role NOT IN  ( '" . implode( "', '", $wpdb->_escape( $roles ) ) . "' )";
			}

			// If there are no specific roles named, make sure the user is a member of the site.
			if ( empty( $role_queries ) ) {
				$role_queries[] = array(
					'key'     => $wpdb->get_blog_prefix( $blog_id ) . 'capabilities',
					'compare' => 'EXISTS',
				);
			}

			// Specify that role queries should be joined with AND.
			$role_queries['relation'] = 'AND';

			if ( empty( $this->meta_query->queries ) ) {
				$this->meta_query->queries = $role_queries;
			} else {
				// Append the cap query to the original queries and reparse the query.
				$this->meta_query->queries = array(
					'relation' => 'AND',
					array( $this->meta_query->queries, $role_queries ),
				);
			}

			$this->meta_query->parse_query_vars( $this->meta_query->queries );
		}

		if ( ! empty( $this->meta_query->queries ) ) {
			$clauses        = $this->meta_query->get_sql( 'user', $wpdb->users, 'ID', $wp_user_query );
			$query_from     = $clauses['join'];
			$query_where    = $clauses['where'];
			$compare        = ( $this->meta_query->queries === $wp_user_query->meta_query->queries );
			$query_from_new = " INNER JOIN $wpdb->userrole ON ( $wpdb->users.ID = $wpdb->userrole.user_id )";
			if ( $compare ) {
				$wp_user_query->query_from = str_replace( $query_from, $query_from_new, $wp_user_query->query_from );
			} else {
				$wp_user_query->query_from .= $query_from_new;
			}

			$wp_user_query->query_where = str_replace( $query_where, $query_where_new, $wp_user_query->query_where );
		}

		return $users;
	}

	/**
	 * High jack the user count, and use a simple query to get count values.
	 *
	 * @param int    $count Pre filter count.
	 * @param string $strategy Unused.
	 * @param int    $site_id Site id.
	 *
	 * @return array
	 */
	public function pre_count_users( $count, $strategy, $site_id ) {
		global $wpdb;

		if ( '1' !== get_network_option( get_current_network_id(), 'user_role.migrated', 0 ) ) {
			return $count;
		}

		$total_count        = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->userrole} WHERE site_id = %d", $site_id ) );
		$total_roles        = $wpdb->get_results( $wpdb->prepare( "SELECT role, count(*) as count FROM {$wpdb->userrole} WHERE site_id = %d GROUP BY role", $site_id ), ARRAY_A );
		$total_count_values = [];
		foreach ( $total_roles as $total_role ) {
			$total_count_values[ $total_role['role'] ] = $total_role['count'];
		}

		$counts = [
			'total_users' => $total_count,
			'avail_roles' => $total_count_values,
		];

		return $counts;
	}


	/**
	 * Hook into new network creation to add super admins.
	 *
	 * @param int $network_id New network id.
	 */
	public function add_network( $network_id ) {
		$this->populate_super_admins( get_network_option( $network_id, 'site_admins', [] ) );
	}

	/**
	 * Hook into deleting network.
	 *
	 * @param WP_Network $network Network object that was deleted.
	 */
	public function delete_network( $network ) {
		$this->remove_roles( [ 'network_id' => $network->site_id ] );
	}

	/**
	 * Move site from one network to another network.
	 *
	 * @param int $site_id Site to be moved.
	 * @param int $network_id Old network id.
	 * @param int $new_network_id new network id.
	 */
	public function move_site( $site_id, $network_id, $new_network_id ) {
		if ( $network_id === $new_network_id ) {
			return;
		}

		global $wpdb;

		$wpdb->update(
			$wpdb->userrole,
			[
				'network_id' => $new_network_id,
			],
			[
				'site_id' => $site_id,
			]
		);
	}

	/**
	 * If site is updated, check if it moved network as well.
	 *
	 * @param WP_Site $new_site New site object.
	 * @param WP_Site $old_site Old site object.
	 */
	public function wp_update_site( $new_site, $old_site ) {
		$this->move_site( $new_site->blog_id, $old_site->network_id, $new_site->network_id );
	}

	/**
	 * Hook into delete site, also delete all roles.
	 *
	 * @param WP_Site $old_site Old site object.
	 */
	public function wp_delete_site( $old_site ) {
		$this->remove_roles( [ 'site_id' => $old_site->blog_id ] );
	}

	/**
	 * Helper method to add role by site, network and user.
	 *
	 * @param int    $user_id User ID to update.
	 * @param string $role Value of role to change.
	 * @param int    $blog_id Site ID to update. Only for multisite.
	 * @param int    $network_id Network id to add role too.
	 *
	 * @return bool|false|int
	 */
	public function add_role( $user_id, $role = '', $blog_id = 0, $network_id = 0 ) {
		global $wpdb;
		$test = $this->get_role( $user_id, $role, $blog_id, $network_id );
		$id   = false;
		if ( ! $test ) {
			$id = $wpdb->insert(
				$wpdb->userrole,
				[
					'user_id'    => $user_id,
					'site_id'    => $blog_id,
					'network_id' => $network_id,
					'role'       => $role,
				]
			);
		}

		return $id;
	}

	/**
	 * Remove multiple roles at once.
	 *
	 * @param array $args Params passed to wpdb delete.
	 *
	 * @return false|int
	 */
	public function remove_roles( array $args ) {
		global $wpdb;

		return $wpdb->delete( $wpdb->userrole, $args );
	}

	/**
	 * Helper method to get role by user, role, blog and netowrk.
	 *
	 * @param int    $user_id User ID to update.
	 * @param string $role Value of role to change.
	 * @param int    $blog_id Site ID to update. Only for multisite.
	 * @param int    $network_id Network id to get role from.
	 *
	 * @return array|object|void|null
	 */
	private function get_role( $user_id = 0, $role = '', $blog_id = 0, $network_id = 0 ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->userrole} WHERE user_id = %d AND role = %s AND site_id = %d AND network_id = %d", $user_id, $role, $blog_id, $network_id ) );
	}


	/**
	 * Hook into after user save and add roles.
	 *
	 * @param int $user_id User ID to update.
	 *
	 * @return WP_User
	 */
	public function after_user_save( $user_id ) {
		$blog_id    = get_current_blog_id();
		$network_id = $this->get_network_id( $blog_id );

		$user = new WP_User( $user_id, '', $blog_id );

		if ( ! $user ) {
			$this->remove_roles(
				[
					'user_id' => $user_id,
					'site_id' => $blog_id,
				]
			);
		}
		foreach ( $user->roles as $role ) {
			$this->add_role( $user_id, $role, $blog_id, $network_id );
		}

		return $user;
	}

	/**
	 * Get network id, from blog_id.
	 *
	 * @param int $blog_id source blog id.
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
