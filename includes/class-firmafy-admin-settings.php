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
		add_action( 'admin_notices', array( $this, 'admin_notices_action' ) );

		// Register CPT Templates.
		add_action( 'init', array( $this, 'create_firmafy_templates_type' ) );

		add_filter( 'manage_edit-firmafy_template_columns', array( $this, 'add_new_firmafy_template_columns' ) );
		add_action( 'manage_firmafy_template_posts_custom_column', array( $this, 'manage_firmafy_template_columns' ), 10, 2 );
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
	function admin_notices_action() {
		settings_errors( 'firmafy_notification_error' );
	}

	/**
	 * Create admin page.
	 *
	 * @return void
	 */
	public function create_admin_page() {
		global $helpers_firmafy;
		$this->firmafy_settings = get_option('firmafy_options');
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
							echo '<svg width="24" height="24" viewBox="0 0 24 24" class="license-icon"><defs><circle id="license-unchecked-a" cx="8" cy="8" r="8"></circle></defs><g fill="none" fill-rule="evenodd" transform="translate(4 4)"><use fill="#dc3232" xlink:href="#license-unchecked-a"></use><g fill="#FFF" transform="translate(4 4)"><rect width="2" height="8" x="3" rx="1" transform="rotate(-45 4 4)"></rect><rect width="2" height="8" x="3" rx="1" transform="rotate(-135 4 4)"></rect></g></g></svg>';
							esc_html_e( 'ERROR: We could not connect to Firmafy.', 'firmafy' );
							echo esc_html( $login_result['data'] );
						} else {
							echo '<svg width="24" height="24" viewBox="0 0 24 24" class="icon-24 license-icon"><defs><circle id="license-checked-a" cx="8" cy="8" r="8"></circle></defs><g fill="none" fill-rule="evenodd" transform="translate(4 4)"><mask id="license-checked-b" fill="#fff"><use xlink:href="#license-checked-a"></use></mask><use fill="#52AA59" xlink:href="#license-checked-a"></use><path fill="#FFF" fill-rule="nonzero" d="M7.58684811,11.33783 C7.19116948,11.7358748 6.54914653,11.7358748 6.15365886,11.33783 L3.93312261,9.10401503 C3.53744398,8.70616235 3.53744398,8.06030011 3.93312261,7.66244744 C4.32861028,7.26440266 4.97063323,7.26440266 5.36631186,7.66244744 L6.68931454,8.99316954 C6.78918902,9.09344917 6.95131795,9.09344917 7.0513834,8.99316954 L10.6336881,5.38944268 C11.0291758,4.9913979 11.6711988,4.9913979 12.0668774,5.38944268 C12.2568872,5.5805887 12.3636364,5.83993255 12.3636364,6.11022647 C12.3636364,6.3805204 12.2568872,6.63986424 12.0668774,6.83101027 L7.58684811,11.33783 Z" mask="url(#license-checked-b)"></path></g></svg>';
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

		add_settings_field(
			'firmafy_id_show',
			__( 'Public API Key', 'firmafy' ),
			array( $this, 'id_show_callback' ),
			'firmafy_options',
			'admin_firmafy_settings'
		);

		add_settings_field(
			'firmafy_notification',
			__( 'Notification settings', 'firmafy' ),
			array( $this, 'notification_callback' ),
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
		global $helpers_firmafy;
		$sanitary_values = array();

		if ( isset( $input['username'] ) ) {
			$sanitary_values['username'] = sanitize_text_field( $input['username'] );
		}

		if ( isset( $input['password'] ) ) {
			$sanitary_values['password'] = sanitize_text_field( $input['password'] );
		}

		if ( isset( $input['id_show'] ) ) {
			$sanitary_values['id_show'] = sanitize_text_field( $input['id_show'] );
		}

		if ( isset( $_POST['notification'] ) && is_array( $_POST['notification'] ) ) {
			foreach ( $_POST['notification'] as $notification ) {
				$sanitary_values['notification'][] = sanitize_text_field( $notification );
			}
		}

		if ( empty( $sanitary_values['notification'] ) ) {
			add_settings_error(
				'firmafy_notification_error',
				esc_attr( 'settings_updated' ),
				__( 'Notifications option cannot be empty', 'firmafy' ),
				'error'
			);
		}

		$helpers_firmafy->login( $sanitary_values['username'], $sanitary_values['password'] );

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

	public function id_show_callback() {
		printf(
			'<input class="regular-text" type="password" name="firmafy_options[id_show]" id="id_show" value="%s">',
			isset( $this->firmafy_settings['id_show'] ) ? esc_attr( $this->firmafy_settings['id_show'] ) : ''
		);
	}

	public function notification_callback() {
		$notification = isset( $this->firmafy_settings['notification'] ) ? (array) $this->firmafy_settings['notification'] : [];
		echo '<input type="checkbox" name="notification[]" value="sms" ';
		echo checked( in_array( 'sms', $notification ), 1 ) . ' />';
		echo '<label for="notification">SMS</label>';
		echo '<br/><input type="checkbox" name="notification[]" value="email" ';
		echo checked( in_array( 'email', $notification ), 1 ) . ' />';
		echo '<label for="notification">Email</label>';
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

	/**
	 * Adds columns to post type firmafy_template
	 *
	 * @param array $firmafy_template_columns  Header of admin post type list.
	 * @return array $firmafy_template_columns New elements for header.
	 */
	function add_new_firmafy_template_columns( $firmafy_template_columns ) {
		$new_columns['cb']    = '<input type="checkbox" />';
		$new_columns['title'] = __( 'Title', 'firmafy' );
		$new_columns['variables'] = __( 'Variables', 'firmafy' );
	
		return $new_columns;
	}

	/**
	 * Add columns content
	 *
	 * @param array $column_name Column name of actual.
	 * @param array $id Post ID.
	 * @return void
	 */
	function manage_firmafy_template_columns( $column_name, $id ) {
		global $helpers_firmafy;
	
		switch ( $column_name ) {
			case 'variables':
				$variables = $helpers_firmafy->get_variables_template( $id );
				echo implode( ', ', array_column( $variables, 'label' ) );
				break;
	
			default:
				break;
		} // end switch
	}
}

new FIRMAFY_ADMIN_SETTINGS();
