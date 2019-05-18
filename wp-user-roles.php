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
 * @package         Wp_Roles
 */

use Spacedmonkey\Users;

define( 'WP_ROLES_PATH', plugin_dir_path( __FILE__ ) );

require_once WP_ROLES_PATH . 'src/class-user-roles.php';

function get_wp_user_role() {
	static $wp_user_role;
	if ( ! $wp_user_role ) {
		$wp_user_role = new Users\User_Roles();
	}

	return $wp_user_role;
}

function wp_user_role_activation() {
	get_wp_user_role()::activate();
}

register_activation_hook( __FILE__, 'wp_user_role_activation' );

get_wp_user_role()->bootstrap();

// Only include wp-cli script if WP CLI is active
if ( defined('WP_CLI') && WP_CLI ) {
	require_once( WP_ROLES_PATH . 'src/class-role-command.php' );
}