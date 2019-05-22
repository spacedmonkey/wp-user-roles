<?php
/**
 * Plugin Name:     WP User Roles
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     Add new table for user roles
 * Author:          Jonny Harris
 * Author URI:      https://www.spacedmonkey.com/
 * Text Domain:     wp-user-roles
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package wp-user-roles
 */

use Spacedmonkey\Users;

/**
 * Define plugin path.
 */
define( 'WP_USER_ROLES_PATH', plugin_dir_path( __FILE__ ) );

require_once WP_USER_ROLES_PATH . 'src/class-user-roles.php';

/**
 * Create a single object.
 *
 * @return Users\User_Roles
 */
function wp_user_roles() {
	static $wp_user_roles;
	if ( ! $wp_user_roles ) {
		$wp_user_roles = new Users\User_Roles();
	}

	return $wp_user_roles;
}

/**
 * Activation hook.
 */
function wp_user_roles_activation() {
	wp_user_roles()->activate();
}

/**
 * Uninstall hook.
 */
function wp_user_roles_uninstall() {
	wp_user_roles()->uninstall();
}



register_activation_hook( __FILE__, 'wp_user_roles_activation' );
register_uninstall_hook( __FILE__, 'wp_user_roles_uninstall' );

wp_user_roles()->bootstrap();

// Only include wp-cli script if WP CLI is active.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once WP_USER_ROLES_PATH . 'src/class-role-command.php';
}
