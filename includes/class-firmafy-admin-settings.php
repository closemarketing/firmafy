<?php
/**
 * Library for admin settings
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2019 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Library for WooCommerce Settings
 *
 * Settings in order to sync products
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2019 Closemarketing
 * @version    0.1
 */
class FIRMAFY_ADMIN_SETTINGS {
	/**
	 * Settings
	 *
	 * @var array
	 */
	private $firmafy_settings;

	/**
	 * Construct of class
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'firmafy_scripts' ) );
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );

		// Register CPT Templates.
		add_action( 'init', array( $this, 'create_firmafy_templates_type' ) );
	}

	/**
	* function_description
	*
	* @return void
	*/
	function firmafy_scripts() {
		wp_enqueue_style(
			'firmafy-admin',
			FIRMAFY_PLUGIN_URL . '/includes/assets/admin.css',
			array(),
			FIRMAFY_VERSION
		);
	}
	/**
	 * Adds plugin page.
	 *
	 * @return void
	 */
	public function add_plugin_page() {

		add_menu_page(
			__( 'Firmafy', 'firmafy' ),
			__( 'Firmafy', 'firmafy' ),
			'manage_options',
			'settings_firmafy',
			array( $this, 'create_admin_page' ),
			'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTgiIGhlaWdodD0iMTgiIHZpZXdCb3g9IjAgMCAxOCAxOCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTE0LjgxODcgMlY1LjUzNTQxSDcuOTY0NTlWOC4xMzE3M0gxNC4xOTgzVjExLjY2NzFINy45NjQ1OVYxN0g0VjJIMTQuODE4N1oiIGZpbGw9IiMwNjc0OTUiLz4KPC9zdmc+Cg=='
		);

		$submenu_pages = array(
			array(
				'parent_slug' => 'settings_firmafy',
				'page_title'  => __( 'Settings', 'firmafy' ),
				'menu_title'  => __( 'Settings', 'firmafy' ),
				'capability'  => 'manage_options',
				'menu_slug'   => 'settings_firmafy',
				'function'    => array( $this, 'create_admin_page' ),
			),
			array(
				'parent_slug' => 'settings_firmafy',
				'page_title'  => __( 'Templates', 'firmafy' ),
				'menu_title'  => __( 'Templates', 'firmafy' ),
				'capability'  => 'manage_options',
				'menu_slug'   => 'edit.php?post_type=firmafy_template',
				'function'    => null,
			),
		);

		// Add each submenu item to custom admin menu.
		foreach ( $submenu_pages as $submenu ) {
			add_submenu_page(
				$submenu['parent_slug'],
				$submenu['page_title'],
				$submenu['menu_title'],
				$submenu['capability'],
				$submenu['menu_slug'],
				$submenu['function']
			);
		}
	}

	/**
	 * Create admin page.
	 *
	 * @return void
	 */
	public function create_admin_page() {
		global $helpers_firmafy;
		?>
		<div class="header-wrap">
			<div class="wrapper">
				<h2 style="display: none;"></h2>
				<div id="nag-container"></div>
				<div class="header firmafy-header">
					<div class="logo">
						<img src="<?php echo FIRMAFY_PLUGIN_URL . 'includes/assets/logo.svg'; ?>" height="35" width="154"/>
						<h2><?php esc_html_e( 'Firmafy Settings', 'firmafy' ); ?></h2>
					</div>
					<div class="connection">
						<p>
						<?php
						$login_result = $helpers_firmafy->login();
						if ( 'error' === $login_result['status'] ) {
							esc_html_e( 'ERROR: We could not connect to Firmafy.', 'firmafy' );
							echo esc_html( $login_result['data'] );
						} else {
							esc_html_e( 'Connected to Firmafy', 'firmafy' );
						}
						?>
						</p>
					</div>
				</div>
			</div>
		</div>

		<div class="wrap">
			<p></p>
			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php
					settings_fields( 'admin_firmafy_settings' );
					do_settings_sections( 'firmafy_options' );
					submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Init for page
	 *
	 * @return void
	 */
	public function page_init() {

		/**
		 * ## API Settings
		 * --------------------------- */
		register_setting(
			'admin_firmafy_settings',
			'firmafy_options',
			array( $this, 'sanitize_fields_api' )
		);

		add_settings_section(
			'admin_firmafy_settings',
			__( 'Settings for integration to Firmafy', 'firmafy' ),
			array( $this, 'admin_section_api_info' ),
			'firmafy_options'
		);

		add_settings_field(
			'firmafy_username',
			__( 'Username', 'firmafy' ),
			array( $this, 'username_callback' ),
			'firmafy_options',
			'admin_firmafy_settings'
		);

		add_settings_field(
			'firmafy_password',
			__( 'Password', 'firmafy' ),
			array( $this, 'password_callback' ),
			'firmafy_options',
			'admin_firmafy_settings'
		);
	}

	/**
	 * # API Settings
	 * ---------------------------------------------------------------------------------------------------- */

	/**
	 * Sanitize fiels before saves in DB
	 *
	 * @param array $input Input fields.
	 * @return array
	 */
	public function sanitize_fields_api( $input ) {
		$sanitary_values = array();

		if ( isset( $input['username'] ) ) {
			$sanitary_values['username'] = sanitize_text_field( $input['username'] );
		}

		if ( isset( $input['password'] ) ) {
			$sanitary_values['password'] = sanitize_text_field( $input['password'] );
		}

		$this->check_login( $sanitary_values['username'], $sanitary_values['password'] );

		return $sanitary_values;
	}

	/**
	 * Info for neo automate section.
	 *
	 * @return void
	 */
	public function admin_section_api_info() {
		esc_html_e( 'Put the connection API key settings in order to connect external data.', 'firmafy' );
	}

	public function username_callback() {
		printf(
			'<input class="regular-text" type="text" name="firmafy_options[username]" id="firmafy_username" value="%s">',
			isset( $this->firmafy_settings['username'] ) ? esc_attr( $this->firmafy_settings['username'] ) : ''
		);
	}

	public function password_callback() {
		printf(
			'<input class="regular-text" type="password" name="firmafy_options[password]" id="password" value="%s">',
			isset( $this->firmafy_settings['password'] ) ? esc_attr( $this->firmafy_settings['password'] ) : ''
		);
	}

	/**
	 * Register Post Type Templates
	 *
	 * @return void
	 **/
	public function create_firmafy_templates_type() {
		$labels = array(
			'name'               => __( 'Templates', 'firmafy' ),
			'singular_name'      => __( 'Template', 'firmafy' ),
			'add_new'            => __( 'Add New Template', 'firmafy' ),
			'add_new_item'       => __( 'Add New Template', 'firmafy' ),
			'edit_item'          => __( 'Edit Template', 'firmafy' ),
			'new_item'           => __( 'New Template', 'firmafy' ),
			'view_item'          => __( 'View Template', 'firmafy' ),
			'search_items'       => __( 'Search Templates', 'firmafy' ),
			'not_found'          => __( 'Not found Template', 'firmafy' ),
			'not_found_in_trash' => __( 'Not found Template in trash', 'firmafy' ),
		);
		$args   = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'show_in_rest'       => true,
			'query_var'          => true,
			'has_archive'        => false,
			'capability_type'    => 'post',
			'hierarchical'       => false,
			'menu_position'      => 5,
			'supports'           => array( 'title', 'editor', 'revisions' ),
		);
		register_post_type( 'firmafy_template', $args );
	}
}

new FIRMAFY_ADMIN_SETTINGS();
