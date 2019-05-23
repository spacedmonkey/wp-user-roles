<?php
/**
 * Class SampleTest
 *
 * @package wp_user_roles
 */

/**
 * Sample test case.
 */
class UserRoleTest extends WP_UnitTestCase {
	/**
	 * @var
	 */
	protected static $author_ids;
	/**
	 * @var
	 */
	protected static $sub_ids;
	/**
	 * @var
	 */
	protected static $editor_ids;
	/**
	 * @var
	 */
	protected static $contrib_id;
	/**
	 * @var
	 */
	protected static $admin_ids;

	/**
	 * @var
	 */
	protected $user_id;

	/**
	 * @param $factory
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		wp_user_roles()->check_table();
		self::$author_ids = $factory->user->create_many(
			4,
			array(
				'role' => 'author',
			)
		);

		self::$sub_ids = $factory->user->create_many(
			2,
			array(
				'role' => 'subscriber',
			)
		);

		self::$editor_ids = $factory->user->create_many(
			3,
			array(
				'role' => 'editor',
			)
		);

		self::$contrib_id = $factory->user->create(
			array(
				'role' => 'contributor',
			)
		);

		self::$admin_ids = $factory->user->create_many(
			5,
			array(
				'role' => 'administrator',
			)
		);
	}


	/**
	 *
	 */
	function setUp() {
		wp_user_roles()->check_table();
		update_network_option( get_current_network_id(), 'user_role.migrated', 1 );
	}

	/**
	 *
	 */
	function tearDown() {
		wp_user_roles()->drop_table();
	}


	/**
	 *
	 */
	function test_query_with_roles() {

		// Users with foo = bar or baz restricted to the author role.
		$query = new WP_User_Query(
			array(
				'fields'   => '',
				'role__in' => [ 'author', 'subscriber' ],
			)
		);

		$results = array_merge( self::$author_ids, self::$sub_ids );
		$this->assertEquals( $results, $query->get_results() );
	}

	/**
	 *
	 */
	function test_query_with_role() {

		// Users with foo = bar or baz restricted to the author role.
		$query = new WP_User_Query(
			array(
				'fields' => '',
				'role'   => 'author',
			)
		);

		$this->assertEquals( self::$author_ids, $query->get_results() );
	}

	/**
	 *
	 */
	function test_meta_query_with_role() {
		add_user_meta( self::$author_ids[0], 'foo', 'bar' );
		add_user_meta( self::$author_ids[1], 'foo', 'baz' );

		// Users with foo = bar or baz restricted to the author role.
		$query = new WP_User_Query(
			array(
				'fields'     => '',
				'role'       => 'author',
				'meta_query' => array(
					'relation' => 'OR',
					array(
						'key'   => 'foo',
						'value' => 'bar',
					),
					array(
						'key'   => 'foo',
						'value' => 'baz',
					),
				),
			)
		);

		$this->assertEquals( array( self::$author_ids[0], self::$author_ids[1] ), $query->get_results() );
	}


	/**
	 *
	 */
	public function test_roles_and_caps_should_be_populated_for_explicit_value_of_blog_id_on_nonms() {
		$query = new WP_User_Query(
			array(
				'include' => self::$author_ids[0],
				'blog_id' => get_current_blog_id(),
			)
		);

		$found = $query->get_results();

		$this->assertNotEmpty( $found );
		$user = reset( $found );
		$this->assertSame( array( 'author' ), $user->roles );
		$this->assertSame( array( 'author' => true ), $user->caps );
	}

	/**
	 * @group ms-required
	 */
	public function test_roles_and_caps_should_be_populated_for_explicit_value_of_current_blog_id_on_ms() {
		$query = new WP_User_Query(
			array(
				'include' => self::$author_ids[0],
				'blog_id' => get_current_blog_id(),
			)
		);

		$found = $query->get_results();

		$this->assertNotEmpty( $found );
		$user = reset( $found );
		$this->assertSame( array( 'author' ), $user->roles );
		$this->assertSame( array( 'author' => true ), $user->caps );
	}

	/**
	 * @group ms-required
	 */
	public function test_roles_and_caps_should_be_populated_for_explicit_value_of_different_blog_id_on_ms_when_fields_all_with_meta() {
		$b = self::factory()->blog->create();

		add_user_to_blog( $b, self::$author_ids[0], 'author' );

		$query = new WP_User_Query(
			array(
				'include' => self::$author_ids[0],
				'blog_id' => $b,
				'fields'  => 'all_with_meta',
			)
		);

		$found = $query->get_results();

		$this->assertNotEmpty( $found );
		$user = reset( $found );
		$this->assertSame( array( 'author' ), $user->roles );
		$this->assertSame( array( 'author' => true ), $user->caps );
	}


	/**
	 * @group ms-required
	 */
	public function test_wp_delete_site() {
		global $wpdb;
		$b = self::factory()->blog->create();

		add_user_to_blog( $b, self::$author_ids[0], 'editor' );

		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->userrole} WHERE site_id = %d", $b ) );
		$this->assertSame( 1, count( $results ) );
		wp_delete_site( $b );

		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->userrole} WHERE site_id = %d", $b ) );
		$this->assertEmpty( $results );
	}


	/**
	 * @group ms-required
	 */
	public function test_wp_update_site() {
		global $wpdb;

		$n1 = self::factory()->network->create();
		$n2 = self::factory()->network->create();
		$b  = self::factory()->blog->create();
		wp_update_site( $b, [ 'site_id' => $n1 ] );
		add_user_to_blog( $b, self::$author_ids[0], 'editor' );

		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->userrole} WHERE network_id = %d", $n1 ) );
		$this->assertSame( 1, count( $results ) );
		wp_update_site( $b, [ 'site_id' => $n2 ] );

		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->userrole} WHERE network_id = %d", $n2 ) );
		$this->assertSame( 1, count( $results ) );
	}

	/**
	 * @group ms-required
	 */
	public function test_roles_and_remove_site() {
		$b = self::factory()->blog->create();

		add_user_to_blog( $b, self::$author_ids[0], 'author' );

		$query = new WP_User_Query(
			array(
				'include' => self::$author_ids[0],
				'blog_id' => $b,
				'fields'  => 'all_with_meta',
			)
		);

		$found = $query->get_results();

		$this->assertNotEmpty( $found );

		remove_user_from_blog( self::$author_ids[0], $b );
		$query_1 = new WP_User_Query(
			array(
				'include' => self::$author_ids[0],
				'blog_id' => $b,
				'fields'  => 'all_with_meta',
			)
		);

		$found_1 = $query_1->get_results();

		$this->assertEmpty( $found_1 );
	}

	/**
	 * @group ms-required
	 */
	public function test_add_super_admin() {
		$blog_id    = 0;
		$network_id = get_current_network_id();
		$role_name  = 'super-admin';

		grant_super_admin( self::$admin_ids[0] );

		$role = wp_user_roles()->get_role( self::$admin_ids[0], $role_name, $blog_id, $network_id );
		$this->assertNotEmpty( $role );
		$this->assertSame( $role->network_id, (string) $network_id );
		$this->assertSame( $role->site_id, (string) $blog_id );
		$this->assertSame( $role->role, $role_name );
	}

	/**
	 * @group ms-required
	 */
	public function test_remove_super_admin() {
		$blog_id    = 0;
		$network_id = get_current_network_id();
		$role_name  = 'super-admin';

		grant_super_admin( self::$admin_ids[1] );

		$role = wp_user_roles()->get_role( self::$admin_ids[1], $role_name, $blog_id, $network_id );
		$this->assertNotEmpty( $role );
		$this->assertSame( $role->network_id, (string) $network_id );
		$this->assertSame( $role->site_id, (string) $blog_id );
		$this->assertSame( $role->role, $role_name );

		revoke_super_admin( self::$admin_ids[1] );

		$role_2 = wp_user_roles()->get_role( self::$admin_ids[1], $role_name, $blog_id, $network_id );
		$this->assertEmpty( $role_2 );
	}

	/**
	 * @group ms-required
	 */
	public function test_user_count() {
		$b = self::factory()->blog->create();
		array_map(
			function ( $user_id ) use ( $b ) {
					add_user_to_blog( $b, $user_id, 'editor' );
			},
			self::$sub_ids
		);
		$counts = count_users( 'time', $b );
		$this->assertArrayHasKey( 'avail_roles', $counts );
		$this->assertArrayHasKey( 'total_users', $counts );
		$this->assertArrayHasKey( 'editor', $counts['avail_roles'] );
		$this->assertSame( $counts['avail_roles']['editor'], count( self::$sub_ids ) );

		remove_user_from_blog( self::$sub_ids[0], $b );

		$counts_1 = count_users( 'time', $b );
		$this->assertArrayHasKey( 'avail_roles', $counts_1 );
		$this->assertArrayHasKey( 'total_users', $counts_1 );
		$this->assertArrayHasKey( 'editor', $counts_1['avail_roles'] );
		$this->assertSame( $counts_1['avail_roles']['editor'], count( self::$sub_ids ) - 1 );
	}

}
