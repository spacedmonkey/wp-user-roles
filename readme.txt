=== WP User Roles ===
Contributors: spacedmonkey
Donate link: https://example.com/
Tags: comments, spam
Requires at least: 5.2
Tested up to: 5.2
Requires PHP: 5.6.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Improve performance of user queries by adding a new table.

== Description ==

This plugin is designed to improve performance of role based user queries. Roles are stored in user meta table, in an array. To query by role, requires a LIKE search on user meta table. For large sites or multisite, with lots of users, this can make queries take a long to return. 

The plugin adds a new table that makes querying by user, a much simplier query, reducing database stain.

This plugin requires WordPress 5.2. Is designed to work with multisite. It is required it should be installed as an mu-plugin.

For this interested in user query performance, you may wish to also install the [WP User Query Cache](https://github.com/spacedmonkey/wp-user-query-cache) plugin.

== Installation ==

### Using The WordPress Dashboard

1. Navigate to the 'Add New' in the plugins dashboard
2. Search for 'wp-user-roles'
3. Click 'Install Now'
4. Activate the plugin on the Plugin dashboard

### Uploading in WordPress Dashboard

1. Navigate to the 'Add New' in the plugins dashboard
2. Navigate to the 'Upload' area
3. Select `wp-user-roles.zip` from your computer
4. Click 'Install Now'
5. Activate the plugin in the Plugin dashboard

### Using FTP
1. Download `wp-user-roles.zip`
2. Extract the `wp-user-roles` directory to your computer
3. Upload the `wp-user-roles` directory to the `/wp-content/plugins/` directory
4. Activate the plugin in the Plugin dashboard


## GitHub Updater

The WP User Roles includes native support for the [GitHub Updater](https://github.com/afragen/github-updater) which allows you to provide updates to your WordPress plugin from GitHub.

== Frequently Asked Questions ==

= This plugin doesn't have a UI, is the correct =
Yes, this it is not needed

== Changelog ==

= 1.0 =
* First release.