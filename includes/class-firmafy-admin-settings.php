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

		register_activation_hook( FIRMAFY_PLUGIN, array( $this, 'loads_templates_cpt' ) );
	}

	/**
	 * Load Admin scripts
	 *
	 * @return void
	 */
	public function firmafy_scripts() {
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
	 * Admin setting notices
	 *
	 * @return void
	 */
	public function admin_notices_action() {
		settings_errors( 'firmafy_notification_error' );
	}

	/**
	 * Create admin page.
	 *
	 * @return void
	 */
	public function create_admin_page() {
		global $helpers_firmafy;
		$this->firmafy_settings = get_option( 'firmafy_options' );
		?>
		<div class="header-wrap">
			<div class="wrapper">
				<h2 style="display: none;"></h2>
				<div id="nag-container"></div>
				<div class="header firmafy-header">
					<div class="logo">
						<img src="<?php echo esc_url( FIRMAFY_PLUGIN_URL ) . 'includes/assets/logo.svg'; ?>" height="35" width="154"/>
						<h2><?php esc_html_e( 'Firmafy Settings', 'firmafy' ); ?></h2>
					</div>
					<div class="connection">
						<?php
						$login_result = $helpers_firmafy->login();
						if ( 'error' === $login_result['status'] ) {
							echo '<p><span class="dashicons dashicons-no-alt"></span>';
							esc_html_e( 'ERROR: We could not connect to Firmafy.', 'firmafy' );
							echo esc_html( $login_result['data'] ) . '</p>';
						} else {
							echo '<p><span class="dashicons dashicons-saved"></span>';
							esc_html_e( 'Connected to Firmafy', 'firmafy' ) . '</p>';
							$token                = ! empty( $login_result['data'] ) ? $login_result['data'] : '';
							$credentials['token'] = $token;
							$result_weebhook      = $helpers_firmafy->webhook( $credentials );

							if ( ! $result_weebhook ) {
								echo '<p><span class="dashicons dashicons-no-alt"></span>';
								esc_html_e( 'ERROR: We could not connect to Firmafy.', 'firmafy' );
								echo esc_html( $result_weebhook['data'] ) . '</p>';
							} else {
								echo '<p><span class="dashicons dashicons-saved"></span>';
								esc_html_e( 'Syncronization actived', 'firmafy' ) . '</p>';
							}
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

		add_settings_field(
			'firmafy_signers',
			__( 'Self signers', 'firmafy' ),
			array( $this, 'signers_callback' ),
			'firmafy_options',
			'admin_firmafy_settings'
		);

		if ( class_exists( 'WooCommerce' ) ) {
			add_settings_field(
				'firmafy_woocommerce',
				__( 'Sign WooCommerce orders with Firmafy?', 'firmafy' ),
				array( $this, 'woocommerce_callback' ),
				'firmafy_options',
				'admin_firmafy_settings'
			);
			add_settings_field(
				'firmafy_woocommerce_mode',
				__( 'Mode for signing orders?', 'firmafy' ),
				array( $this, 'woocommerce_mode_callback' ),
				'firmafy_options',
				'admin_firmafy_settings'
			);
		}

		add_settings_field(
			'firmafy_secure_mode',
			__( 'Secure mode', 'firmafy' ),
			array( $this, 'secure_mode_callback' ),
			'firmafy_options',
			'admin_firmafy_settings'
		);
	}

	/**
	 * ## API Settings
	 * --------------------------- */

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

		if ( isset( $input['woocommerce'] ) ) {
			$sanitary_values['woocommerce'] = sanitize_text_field( $input['woocommerce'] );
		}

		if ( isset( $input['woocommerce_mode'] ) ) {
			$sanitary_values['woocommerce_mode'] = sanitize_text_field( $input['woocommerce_mode'] );
		}

		if ( isset( $input['secure_mode'] ) ) {
			$sanitary_values['secure_mode'] = sanitize_text_field( $input['secure_mode'] );
		}

		if ( isset( $_POST['notification'] ) && is_array( $_POST['notification'] ) ) {
			foreach ( $_POST['notification'] as $notification ) {
				$sanitary_values['notification'][] = sanitize_text_field( $notification );
			}
		}

		// Save Signers options.
		if ( isset( $input['signers'] ) ) {
			$index = 0;
			foreach ( $input['signers'] as $signers ) {
				foreach ( $signers as $key => $value ) {
					$sanitary_values['signers'][ $index ][ $key ] = sanitize_text_field( $value );
				}
				$index++;
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

	/**
	 * Username input callback
	 *
	 * @return void
	 */
	public function username_callback() {
		printf(
			'<input class="regular-text" type="text" name="firmafy_options[username]" id="firmafy_username" value="%s">',
			isset( $this->firmafy_settings['username'] ) ? esc_attr( $this->firmafy_settings['username'] ) : ''
		);
	}

	/**
	 * Password input callback
	 *
	 * @return void
	 */
	public function password_callback() {
		printf(
			'<input class="regular-text" type="password" name="firmafy_options[password]" id="password" value="%s">',
			isset( $this->firmafy_settings['password'] ) ? esc_attr( $this->firmafy_settings['password'] ) : ''
		);
	}

	/**
	 * ID Show callback
	 *
	 * @return void
	 */
	public function id_show_callback() {
		printf(
			'<input class="regular-text" type="password" name="firmafy_options[id_show]" id="id_show" value="%s">',
			isset( $this->firmafy_settings['id_show'] ) ? esc_attr( $this->firmafy_settings['id_show'] ) : ''
		);
	}

	/**
	 * Notification callback
	 *
	 * @return void
	 */
	public function notification_callback() {
		$notification = isset( $this->firmafy_settings['notification'] ) ? (array) $this->firmafy_settings['notification'] : array();
		echo '<input type="checkbox" name="notification[]" value="sms" ';
		echo checked( in_array( 'sms', $notification, true ), 1 ) . ' />';
		echo '<label for="notification">SMS</label>';
		echo '<br/><input type="checkbox" name="notification[]" value="email" ';
		echo checked( in_array( 'email', $notification, true ), 1 ) . ' />';
		echo '<label for="notification">Email</label>';
	}

	/**
	 * WooCommerce callback
	 *
	 * @return void
	 */
	public function woocommerce_callback() {
		?>
		<select name="firmafy_options[woocommerce]" id="woocommerce">
			<option value="no" <?php selected( $this->firmafy_settings['woocommerce'], 'no' ); ?>><?php esc_html_e( 'No', 'firmafy' ); ?></option>
			<option value="yes" <?php selected( $this->firmafy_settings['woocommerce'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'firmafy' ); ?></option>
		</select><br/>
		<label for="woocommerce"><?php esc_html_e( 'This adds a NIF field required for Firmafy and signs the order when the order is placed.', 'firmafy' ); ?></label>
		<br/>
		<label for="woocommerce">
			<?php
			echo wp_kses( // phpcs:ignore
				sprintf(
					// translators: %s edit woocommerce settings.
					__( 'You will need to setup the <a href="%s">Terms and conditions page</a>', 'firmafy' ),
					admin_url( 'admin.php?page=wc-settings&tab=advanced' )
				),
				array(
					'a' => array(
						'href' => array(),
					),
				)
			);
			?>
		</label>
		<?php
	}

	/**
	 * Woocommerce mode callback
	 *
	 * @return void
	 */
	public function woocommerce_mode_callback() {
		?>
		<select name="firmafy_options[woocommerce_mode]" id="woocommerce_mode">
			<option value="orders" <?php selected( $this->firmafy_settings['woocommerce_mode'], 'orders' ); ?>><?php esc_html_e( 'Only Orders', 'firmafy' ); ?></option>
			<option value="products" <?php selected( $this->firmafy_settings['woocommerce_mode'], 'products' ); ?>><?php esc_html_e( 'Only Products', 'firmafy' ); ?></option>
			<option value="all" <?php selected( $this->firmafy_settings['woocommerce_mode'], 'all' ); ?>><?php esc_html_e( 'Orders and products', 'firmafy' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Secure mode input callback
	 *
	 * @return void
	 */
	public function secure_mode_callback() {
		?>
		<select name="firmafy_options[secure_mode]" id="secure_mode">
			<option value="no" <?php selected( $this->firmafy_settings['secure_mode'], 'no' ); ?>><?php esc_html_e( 'No', 'firmafy' ); ?></option>
			<option value="yes" <?php selected( $this->firmafy_settings['secure_mode'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'firmafy' ); ?></option>
		</select><br/>
		<label for="secure_mode"><?php esc_html_e( 'Sometimes there is a problem generating the PDF from web style. You can force to create the PDF without styles.', 'firmafy' ); ?></label>
		<?php
	}


	/**
	 * Signers Callback
	 *
	 * @return void
	 */
	public function signers_callback() {
		$signers = isset( $this->firmafy_settings['signers'] ) ? $this->firmafy_settings['signers'] : array();
		$size    = ! empty( $signers ) ? count( $signers ) - 1 : 0;

		for ( $idx = 0, $size; $idx <= $size; ++$idx ) {
			?>
			<div class="firmafy-signers repeating">
				<div class="save-item list">
					<p><strong><?php esc_html_e( 'Full Name', 'firmafy' ); ?></strong></p>
					<?php
					printf(
						'<input class="regular-text" type="text" name="firmafy_options[signers][%s][nombre]" value="%s">',
						(int) $idx,
						isset( $signers[ $idx ]['nombre'] ) ? esc_html( $signers[ $idx ]['nombre'] ) : ''
					);
					?>
				</div>
				<div class="save-item list">
					<p><strong><?php esc_html_e( 'NIF', 'firmafy' ); ?></strong></p>
					<?php
					printf(
						'<input class="regular-text" type="text" name="firmafy_options[signers][%s][nif]" value="%s">',
						(int) $idx,
						isset( $signers[ $idx ]['nif'] ) ? esc_html( $signers[ $idx ]['nif'] ) : ''
					);
					?>
				</div>
				<div class="save-item list">
					<p><strong><?php esc_html_e( 'Position', 'firmafy' ); ?></strong></p>
					<?php
					printf(
						'<input class="regular-text" type="text" name="firmafy_options[signers][%s][cargo]" value="%s">',
						(int) $idx,
						isset( $signers[ $idx ]['cargo'] ) ? esc_html( $signers[ $idx ]['cargo'] ) : ''
					);
					?>
				</div>
				<div class="save-item list">
					<p><strong><?php esc_html_e( 'Email', 'firmafy' ); ?></strong></p>
					<?php
					printf(
						'<input class="regular-text" type="text" name="firmafy_options[signers][%s][email]" value="%s">',
						(int) $idx,
						isset( $signers[ $idx ]['email'] ) ? esc_html( $signers[ $idx ]['email'] ) : ''
					);
					?>
				</div>
				<div class="save-item list">
					<p><strong><?php esc_html_e( 'Phone', 'firmafy' ); ?></strong></p>
					<?php
					printf(
						'<input class="regular-text" type="text" name="firmafy_options[signers][%s][telefono]" value="%s">',
						(int) $idx,
						isset( $signers[ $idx ]['telefono'] ) ? esc_html( $signers[ $idx ]['telefono'] ) : ''
					);
					?>
				</div>
				<div class="save-item list">
					<p><strong><?php esc_html_e( 'Company', 'firmafy' ); ?></strong></p>
					<?php
					printf(
						'<input class="regular-text" type="text" name="firmafy_options[signers][%s][empresa]" value="%s">',
						(int) $idx,
						isset( $signers[ $idx ]['empresa'] ) ? esc_html( $signers[ $idx ]['empresa'] ) : ''
					);
					?>
				</div>
				<div class="save-item list">
					<p><strong><?php esc_html_e( 'CIF', 'firmafy' ); ?></strong></p>
					<?php
					printf(
						'<input class="regular-text" type="text" name="firmafy_options[signers][%s][cif]" value="%s">',
						(int) $idx,
						isset( $signers[ $idx ]['cif'] ) ? esc_html( $signers[ $idx ]['cif'] ) : ''
					);
					?>
				</div>
				<div class="save-item">
					<p><strong><?php esc_html_e( 'Type of notifications', 'firmafy' ); ?></strong></p>
					<select name="firmafy_options[signers][<?php echo esc_html( $idx ); ?>][type_notifications]" class="firmafy-signer-notification">
						<?php
						$type_notifications = array(
							'email' => __( 'Email', 'firmafy' ),
							'sms'   => __( 'SMS', 'firmafy' ),
						);
						// Load Page Options.
						foreach ( $type_notifications as $key => $value ) {
							echo '<option value="' . esc_html( $key ) . '" ';
							selected( $key, isset( $signers[ $idx ]['type_notifications'] ) ? $signers[ $idx ]['type_notifications'] : '' );
							echo '>' . esc_html( $value ) . '</option>';
						}
						?>
					</select>
				</div>
				<div class="save-item">
					<a href="#" class="button alt remove"><span class="dashicons dashicons-remove"></span><?php esc_html_e( 'Remove', 'firmafy' ); ?></a>
					<div class="sync-all-entries-result"></div>

				</div>
			</div>
			<?php
		}
		?>
		<a href="#" class="button repeat"><span class="dashicons dashicons-insert"></span><?php esc_html_e( 'Add Another', 'firmafy' ); ?></a>
		<script type="text/javascript">
		// Prepare new attributes for the repeating section
		var attrs = ['for', 'id', 'name'];
		function resetAttributeNames(section) { 
		var tags = section.find('select, input, label'), idx = section.index();
		tags.each(function() {
			var $this = jQuery(this);
			jQuery.each(attrs, function(i, attr) {
				var attr_val = $this.attr(attr);
				if (attr_val) {
					$this.attr(attr, attr_val.replace(/\[signers\]\[\d+\]\[/, '\[signers\]\['+(idx + 1)+'\]\['))
				}
			})
		})
		}

		// Clone the previous section, and remove all of the values                  
		jQuery('.remove').click(function(e){
			e.preventDefault();
			jQuery(this).parent().parent().remove();
		});

		// Clone the previous section, and remove all of the values                  
		jQuery('.repeat').click(function(e){
			e.preventDefault();
			var lastRepeatingGroup = jQuery('.repeating').last();
			var cloned = lastRepeatingGroup.clone(true)  
			cloned.insertAfter(lastRepeatingGroup);
			cloned.find("input").val("");
			cloned.find("select").val("");
			resetAttributeNames(cloned)
		});
		</script>
		<?php
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
	public function add_new_firmafy_template_columns( $firmafy_template_columns ) {
		$new_columns['cb']        = '<input type="checkbox" />';
		$new_columns['title']     = __( 'Title', 'firmafy' );
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
	public function manage_firmafy_template_columns( $column_name, $id ) {
		global $helpers_firmafy;

		switch ( $column_name ) {
			case 'variables':
				$variables = $helpers_firmafy->get_variables_template( $id );
				if ( is_array( $variables ) ) {
					echo esc_html( implode( ', ', array_column( $variables, 'label' ) ) );
				}
				break;

			default:
				break;
		} // end switch
	}

	/**
	 * Creates predefined templates
	 *
	 * @return void
	 */
	public function loads_templates_cpt() {
		$initial_templates = array(
			array(
				'slug'  => 'sepa',
				'title' => __( 'Sign SEPA', 'firmafy' ),
			),
			array(
				'slug'  => 'rgpd',
				'title' => __( 'RGPD New user', 'firmafy' ),
			),
		);

		foreach ( $initial_templates as $template ) {
			$file_template = FIRMAFY_PLUGIN_PATH . '/includes/templates/' . $template['slug'] . '.html';
			$post_exists   = get_page_by_path( $template['slug'], OBJECT, 'firmafy_template' );

			if ( file_exists( $file_template ) && ! $post_exists ) {
				$template_post = array(
					'post_title'    => isset( $template['title'] ) ? wp_strip_all_tags( $template['title'] ) : '',
					'post_name'     => isset( $template['slug'] ) ? wp_strip_all_tags( $template['slug'] ) : '',
					'post_content'  => file_get_contents( $file_template ),
					'post_status'   => 'publish',
					'post_type'     => 'firmafy_template',
				);
				// Insert the post into the database.
				wp_insert_post( $template_post );
			}
		}

	}
}

new FIRMAFY_ADMIN_SETTINGS();
