<?php
/**
 * Settings page.
 *
 * @package Contextly Related Links
 * @link https://contextly.com
 */

/**
 * Class ContextlySettings
 */
class ContextlySettings {

	const GENERAL_SETTINGS_KEY  = 'contextly_options_general';
	const API_SETTINGS_KEY      = 'contextly_options_api';
	const ADVANCED_SETTINGS_KEY = 'contextly_options_advanced';
	const OPTIONS_KEY           = 'contextly_options';
	const PLUGIN_NAME           = 'contextly-linker';

	const MSG_ERROR_TYPE   = 'error';
	const MSG_SUCCESS_TYPE = 'updated';

	const MSG_SETTINGS_SAVED = 'Settings saved.';

	/**
	 * Array of page tabs.
	 *
	 * @var array
	 */
	public $tabs = array();

	/**
	 * Main init.
	 */
	public function init() {
		$this->init_plugin_settings_link();
		$this->init_wp_settings();
	}

	/**
	 * Init actions.
	 */
	private function init_plugin_settings_link() {
		add_filter( 'plugin_action_links', array( $this, 'display_settings_link' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'check_api_settings' ) );
	}

	/**
	 * Main init method.
	 */
	private function init_wp_settings() {
		add_action( 'admin_menu', array( $this, 'add_settings' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ), 1 );
	}

	/**
	 * Display settings link on plugins page.
	 *
	 * @param array  $links links array.
	 * @param string $file file name.
	 * @return array of links.
	 */
	public function display_settings_link( $links, $file ) {
		if ( strpos( $file, self::PLUGIN_NAME ) !== false ) {
			$links[] = "<a href='admin.php?page=contextly_options'>" . __( 'Settings' ) . '</a>';
		}

		return $links;
	}

	/**
	 * Register settings.
	 */
	public function add_settings() {
		add_options_page( 'Contextly', 'Contextly', 'manage_options', self::OPTIONS_KEY, array( $this, 'display_settings' ) );
	}

	/**
	 * Register settings tabs.
	 */
	public function register_settings() {
		register_setting( self::GENERAL_SETTINGS_KEY, self::GENERAL_SETTINGS_KEY, array( $this, 'validate_general' ) );
		register_setting( self::API_SETTINGS_KEY, self::API_SETTINGS_KEY, array( $this, 'validate_api' ) );
		register_setting( self::ADVANCED_SETTINGS_KEY, self::ADVANCED_SETTINGS_KEY, array( $this, 'validate_advanced' ) );

		add_settings_section( 'api_section', 'API Settings', array( $this, 'api_layout_section' ), self::API_SETTINGS_KEY );
		add_settings_field( 'api_key', 'API Key', array( $this, 'api_key_input' ), self::API_SETTINGS_KEY, 'api_section' );

		add_settings_section( 'main_section', 'Single Link Button', array(), self::ADVANCED_SETTINGS_KEY );
		add_settings_field( 'link_type_override', 'Override', array( $this, 'settings_override' ), self::ADVANCED_SETTINGS_KEY, 'main_section' );
		add_settings_field( 'link_type_default', 'Default', array( $this, 'settings_default' ), self::ADVANCED_SETTINGS_KEY, 'main_section' );

		add_settings_section( 'advanced_section', 'Layout Settings', array( $this, 'settings_layout_section' ), self::ADVANCED_SETTINGS_KEY );

		add_settings_section( 'display_section', 'Main Settings', array(), self::ADVANCED_SETTINGS_KEY );
		add_settings_field( 'display_control', 'Display Contextly Widgets For Post Types:', array( $this, 'settings_display_for' ), self::ADVANCED_SETTINGS_KEY, 'display_section' );
		add_settings_field( 'enable_non_article_pages', 'Allow Contextly to Collect Analytics on Non-Article Pages:', array( $this, 'settings_display_enable_non_article_pages' ), self::ADVANCED_SETTINGS_KEY, 'display_section' );
		add_settings_field( 'publish_confirmation', 'Prompt to Choose Related Posts before publishing:', array( $this, 'settings_display_publish_confirmation' ), self::ADVANCED_SETTINGS_KEY, 'display_section' );

		$this->tabs[ self::GENERAL_SETTINGS_KEY ]  = __( 'General' );
		$this->tabs[ self::API_SETTINGS_KEY ]      = __( 'API Key' );
		$this->tabs[ self::ADVANCED_SETTINGS_KEY ] = __( 'Advanced' );
	}

	/**
	 * Regexp for validate API key.
	 *
	 * @param string $api_key API key.
	 * @return boolean false|int
	 */
	private function validate_api_key_regexp( $api_key ) {
		return preg_match( '/^[a-zA-Z0-9_]+-[a-zA-Z0-9#*;]+$/', $api_key );
	}

	/**
	 * Validate API key.
	 *
	 * @param array $input array of input values.
	 * @return array array of values.
	 */
	public function validate_api( $input ) {
		$input['api_key'] = sanitize_text_field( $input['api_key'] );

		if ( ! $input['api_key'] ) {
			$this->show_message( self::MSG_ERROR_TYPE, 'API Key can not be empty.' );
		} elseif ( ! $this->validate_api_key_regexp( $input['api_key'] ) ) {
			$this->show_message( self::MSG_ERROR_TYPE, 'Invalid characters in API Key.' );
		} else {
			$this->show_message( self::MSG_SUCCESS_TYPE, self::MSG_SETTINGS_SAVED );
		}

		return $input;
	}

	/**
	 * Validate advanced tab.
	 *
	 * @param array $input array of input values.
	 * @return array array of post types.
	 */
	public function validate_advanced( $input ) {
		$input['target_id'] = trim( wp_filter_nohtml_kses( $input['target_id'] ) );

		return $input;
	}

	/**
	 * Validate general tab.
	 *
	 * @param array $input array of available post types.
	 * @return array array of post types.
	 */
	public function validate_general( $input ) {
		if ( ! is_array( $input['display_type'] ) || count( $input['display_type'] ) === 0 ) {
			$this->show_message( self::MSG_ERROR_TYPE, 'At least one of post type need to be selected.' );
		} else {
			$this->show_message( self::MSG_SUCCESS_TYPE, self::MSG_SETTINGS_SAVED );
		}

		return $input;
	}

	/**
	 * Display message.
	 *
	 * @param string $type message type.
	 * @param string $message message text.
	 */
	private function show_message( $type, $message ) {
		add_settings_error(
			'contextlyMessageId',
			esc_attr( 'settings_updated' ),
			$message,
			$type
		);
	}

	/**
	 * Display admin error message.
	 *
	 * @param string $message message text.
	 * @param bool   $error error flag.
	 */
	private function show_admin_message( $message, $error = false ) {
		if ( $error ) {
			$class = 'error';
		} else {
			$class = 'updated';
		}

		echo '<div ' . ( $error ? 'id="contextly_warning" ' : '' ) . 'class="' . esc_attr( $class ) . ' fade"><p>' . $message . '</p></div>';
	}

	/**
	 * Get URL for settings tab.
	 *
	 * @param string $tab tab name.
	 * @return string tab settings URL.
	 */
	private function get_wp_plugin_settings_url( $tab = 'contextly_options_api' ) {
		return admin_url( 'admin.php?page=contextly_options&tab=' . $tab );
	}

	/**
	 * Get base Contextly URL for some settings actions.
	 *
	 * @param string $page_type build Contextly base URL for some action like registration or get API key.
	 * @return string base Contextly URL.
	 */
	private function get_contextly_base_url( $page_type ) {
		$url_params = array(
			'type'              => $page_type,
			'blog_url'          => site_url(),
			'blog_title'        => get_bloginfo( 'name' ),
			'cms_settings_page' => $this->get_wp_plugin_settings_url(),
		);

		$options = get_option( self::API_SETTINGS_KEY );
		if ( isset( $options['api_key'] ) ) {
			$url_params['api_key'] = $options['api_key'];
		}

		// Get MAJOR.MINOR version for the Control Panel.
		$version        = ContextlyWpKit::getInstance()
			->version();
		$version_parsed = ContextlyWpKit::parseVersion( $version );
		if ( $version_parsed ) {
			$url_params['kit_version'] = $version_parsed[0] . '.' . $version_parsed[1];
		}

		try {
			return Urls::get_main_server_url() . 'cms-redirect/?' . http_build_query( $url_params, null, '&' );
		} catch ( ContextlyKitException $e ) {
			return '#';
		}
	}

	/**
	 * Main settings page HTML.
	 */
	public function display_settings() {
		$tab = self::GENERAL_SETTINGS_KEY;
		if ( ! empty( $_GET['tab'] ) ) { // WPCS: CSRF ok.
			$tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );  // WPCS: CSRF ok. Input var okay.
		}
		?>
		<script>
			function open_contextly_page( open_page_url, button_id )
			{
				var auth_token_attr = 'contextly_access_token';
				var token_attr = jQuery( '#' + button_id ).attr( auth_token_attr );

				if ( typeof token_attr !== 'undefined' && token_attr !== false ) {
					open_page_url += "&" + auth_token_attr + "=" + encodeURIComponent( token_attr );
				}

				window.open( open_page_url );

				return false;
			}
			function open_contextly_settings()
			{
				var open_url = <?php echo wp_json_encode( $this->get_contextly_base_url( 'settings' ) ); ?>;
				var button_id = 'contextly-settings-btn';

				return open_contextly_page( open_url, button_id );
			}

			function open_contextly_api_page()
			{
				var open_url = <?php echo wp_json_encode( $this->get_contextly_base_url( '' ) ); ?>;
				var button_id = 'contextly-api-btn';

				return open_contextly_page( open_url, button_id );
			}

			function open_contextly_registration_page()
			{
				var open_url = <?php echo wp_json_encode( $this->get_contextly_base_url( '' ) ); ?>;
				window.open( open_url );

				return false;
			}
		</script>
		<div class="wrap">
			<?php $this->display_settings_tabs(); ?>

				<?php if ( self::GENERAL_SETTINGS_KEY === $tab ) { ?>
					<div id='contextly_warnings'></div>
					<h3>
						Most of the controls for Contextly are hosted outside WordPress. Press The Big Settings Button to securely login.
					</h3>
					<p>
						<input type="button" value="The Big Settings Button" class="button button-hero button-primary" style="font-size: 18px;" id="contextly-settings-btn" onclick="open_contextly_settings();" />
					</p><br />

					<?php
						$this->display_settings_autoload_stuff( 'contextly-settings-btn', true );
}
?>

				<form action="options.php" method="post">
					<?php settings_fields( $tab ); ?>
					<?php do_settings_sections( $tab ); ?>
					<?php if ( self::API_SETTINGS_KEY === $tab ) { ?>
						<style>
							span.btn-step-number {
								font-size: 22px;
								margin-right: 10px;
							}
							span.btn-area {
								line-height: 2em;
							}
						</style>
						<div style="margin-top: 20px;">
							<span class="btn-step-number">1.</span>
							<span class="btn-area">
								<?php
								submit_button(
									'Customize Contextly and Get API Key',
									'primary large button-hero',
									'button',
									null,
									array(
										'style'   => 'font-size: 18px; background-color: #35b137; background-image: linear-gradient(to bottom, #36a739, #249b27); border-color: #36a739;',
										'onclick' => 'return open_contextly_api_page();',
										'id'      => 'contextly-api-btn',
									)
								);
								?>
							<span>
						</div>
						<div style="margin-top: 20px;">
							<span class="btn-step-number">2.</span>
							<span class="btn-area">
								<?php
								submit_button(
									'Save API Key',
									'primary large button-hero',
									'submit',
									null,
									array(
										'style' => 'font-size: 18px;',
									)
								);
								?>
							</span>
						</div>
						<?php
							$this->display_settings_autoload_stuff( 'contextly-api-btn' );
} elseif ( self::ADVANCED_SETTINGS_KEY === $tab ) {
	?>
	<?php submit_button( null, 'primary' ); ?>
					<?php } ?>
				</form>

		</div>
		<?php
	}

	/**
	 * Load Contextly stuff.
	 *
	 * @param string $button_id settings button HTML id.
	 * @param bool   $disabled_flag disabled or enabled button by default.
	 */
	private function display_settings_autoload_stuff( $button_id, $disabled_flag = false ) {
		$options = get_option( self::API_SETTINGS_KEY );

		if ( is_admin() && isset( $options['api_key'] ) && $options['api_key'] ) {
			$contextly_object = new Contextly();
			$contextly_object->insert_kit_scripts(
				array(
					'foreign' => array(
						'wp/widgets' => true,
					),
					'preload' => 'wp/widgets',
				)
			);
			?>
			<script>
				Contextly.ready('load', 'wp/widgets', function() {
					jQuery( document ).ready(
						function () {
							Contextly.WPSettingsAutoLogin.doLogin( <?php echo wp_json_encode( $button_id ); ?>, <?php echo wp_json_encode( $disabled_flag ); ?> );
						}
					);
				});
			</script>
			<?php
		}
	}

	/**
	 * Settings page tabs HTML.
	 */
	public function display_settings_tabs() {
		$current_tab = self::GENERAL_SETTINGS_KEY;
		if ( ! empty( $_GET['tab'] ) ) { // WPCS: CSRF ok.
			$current_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );  // WPCS: CSRF ok. Input var okay.
		}

		echo '<h1 class="nav-tab-wrapper">';
		foreach ( $this->tabs as $tab_key => $tab_caption ) {
			$active = $current_tab === $tab_key ? 'nav-tab-active' : '';
			echo '<a class="nav-tab ' . esc_attr( $active ) . '" href="' . esc_url( '?page=' . self::OPTIONS_KEY . '&tab=' . $tab_key ) . '">' . esc_html( $tab_caption ) . '</a>';
		}
		echo '</h1>';
	}

	/**
	 * API key page HTML.
	 */
	public function api_layout_section() {
		echo "<div id='contextly_warnings'></div>";
		echo '<p>In order to communicate securely, we use a shared secret key. You can find your secret API key with button "Customize Contextly and Get API Key". Copy and paste it below.</p>';
	}

	/**
	 * Display area for API key.
	 */
	public function api_key_input() {
		$options     = get_option( self::API_SETTINGS_KEY );
		$input_style = '';

		if ( ! empty( $_GET['api_key'] ) ) { // WPCS: CSRF ok.
			$get_api_key = sanitize_text_field( wp_unslash( $_GET['api_key'] ) );  // WPCS: CSRF ok. Input var okay.

			if ( $options['api_key'] !== $get_api_key ) {
				Contextly::fire_api_event( 'contextlyApiInserted', $get_api_key );

				$options['api_key'] = $get_api_key;
				$input_style        = 'background-color: #FFEBE8; border-color: #CC0000';

				update_option( self::API_SETTINGS_KEY, $options );
			}
		}

		echo "<label><input name='" . esc_attr( self::API_SETTINGS_KEY ) . "[api_key]' type='text' size='40' value='" . esc_attr( $options['api_key'] ) . "' style='" . esc_attr( $input_style ) . "'/></label>";
	}

	/**
	 * Check API settings and display message if needed.
	 */
	public function check_api_settings() {
		$options = get_option( self::API_SETTINGS_KEY );

		if ( ! $options || ! isset( $options['api_key'] ) || ! $options['api_key'] || ! $this->validate_api_key_regexp( $options['api_key'] ) ) {
			$this->show_admin_message( sprintf( 'You need to get your %ssecret key%s before Contextly shows recommendations.', '<a href="' . esc_url( $this->get_wp_plugin_settings_url() ) . '">', '</a>' ), true );
		}
	}

	/**
	 * Display settings layout HTML settings.
	 */
	public function settings_layout_section() {
		echo "<p>
			By default, Contextly's main recommendation module is set to show up as the very last object in your post template. For most sites, this is perfect. However, if you have other plugins that come after the body of the text, you can adjust where the main module displays.
			</p>
			<p>
			To set the placement of Contextly main module, simply edit your templates by placing this shortcode where you would like the module to display: [contextly_main_module]
			</p>";
	}

	/**
	 * Display settings override editor link HTML settings.
	 */
	public function settings_override() {
		$options = get_option( self::ADVANCED_SETTINGS_KEY );

		echo '<label>';
		echo "<input id='link_type_override' name='" . esc_attr( self::ADVANCED_SETTINGS_KEY ) . "[link_type]' type='radio' value='override' " . checked( $options['link_type'], 'override', false ) . '/>';
		echo ' With this setting, the WordPress link button in the Visual editor is changed to used Contextly to add links to the body of your posts. There is no dedicated button for adding single links through Contextly with this option.';
		echo '</label>';
	}

	/**
	 * Display settings default editor link HTML settings.
	 */
	public function settings_default() {
		$options = get_option( self::ADVANCED_SETTINGS_KEY );
		echo '<label>';
		echo "<input id='link_type_default' name='" . esc_attr( self::ADVANCED_SETTINGS_KEY ) . "[link_type]' type='radio' value='' " . checked( empty( $options['link_type'] ), true, false ) . '/>';
		echo " With this setting, WordPress's single link button in the Visual editor works as it normally does. The Visual editor bar gets an additional single link button so you can add links to the body of your post using Contextly.";
		echo '</label>';
	}

	/**
	 * Display settings display HTML settings.
	 */
	public function settings_display_for() {
		$values     = $this->get_widget_display_type();
		$post_types = get_post_types( '', 'objects' );

		echo "<table cellpadding='0' cellspacing='0'>";
		foreach ( $post_types as $post_type ) {
			if ( $post_type->public ) {
				echo "<tr><td style='padding: 3px;'>";
				echo "<input id='post-type-" . esc_attr( $post_type->name ) . "' name='" . esc_attr( self::ADVANCED_SETTINGS_KEY ) . "[display_type][]' type='checkbox' value='" . esc_attr( $post_type->name ) . "' " . checked( in_array( $post_type->name, array_values( $values ), true ), true, false ) . ' />';
				echo "</td><td style='padding: 3px;'><label for='post-type-" . esc_attr( $post_type->name ) . "'>";
				echo esc_html( $post_type->labels->name );
				echo '</label></td></tr>';
			}
		}
		echo '</table>';
	}

	/**
	 * Get all plugin options from WP db.
	 *
	 * @return array|mixed|void plugin options.
	 */
	public static function get_plugin_options() {
		$options = get_option( self::ADVANCED_SETTINGS_KEY );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return $options;
	}

	/**
	 * Get array of all available post types with enabled Contextly.
	 *
	 * @return array
	 */
	public function get_widget_display_type() {
		$options = get_option( self::ADVANCED_SETTINGS_KEY );

        // check if we have no default value
        if ($options === false) {
			$options = array('display_type' => array('post'));
		}

		// Hack for previous plugin versions and selected values.
		$values = isset( $options['display_type'] ) ? $options['display_type'] : array();
		if ( ! is_array( $values ) ) {
			if ( 'all' === $values ) {
				$values = array( 'post', 'page' );
			} else {
				$values = array( $values );
			}
		}

		return $values;
	}

	/**
	 * Check if Contextly modules disabled or enabled on some page.
	 *
	 * @param int $post_id post ID.
	 * @return boolean enable or disabled.
	 */
	public static function is_page_display_disabled( $post_id ) {
		$post_flag = get_post_meta( $post_id, '_contextly_display_widgets', true );

		if ( isset( $post_flag ) && 'on' === $post_flag ) {
			return true;
		}

		return false;
	}

	/**
	 * Change post settings for enable or disable Contextly modules.
	 *
	 * @param int     $post_id post ID.
	 * @param boolean $param enable or disable module.
	 */
	public function change_page_display( $post_id, $param ) {
		update_post_meta( $post_id, '_contextly_display_widgets', $param, get_post_meta( $post_id, '_contextly_display_widgets', true ) );
	}

	/**
	 * Publish confirmation.
	 */
	public function settings_display_publish_confirmation() {
		$options = get_option( self::ADVANCED_SETTINGS_KEY );
		echo '<label>';
		echo "<input name='" . esc_attr( self::ADVANCED_SETTINGS_KEY ) . "[publish_confirmation]' type='checkbox' value='1' " . checked( 1, $options['publish_confirmation'], false ) . '/>';
		echo '</label>';
	}

	/**
     * Get current page type.
     *
	 * @return string
	 */
	public function get_wp_page_type() {
		global $wp_query;

		$loop = null;

		if ( $wp_query->is_page ) {
			$loop = is_front_page() ? 'front' : 'page';
		} elseif ( $wp_query->is_home ) {
			$loop = 'home';
		} elseif ( $wp_query->is_single ) {
			$loop = ( $wp_query->is_attachment ) ? 'attachment' : 'post';
		} elseif ( $wp_query->is_category ) {
			$loop = 'category';
		} elseif ( $wp_query->is_tag ) {
			$loop = 'tag';
		} elseif ( $wp_query->is_tax ) {
			$loop = 'tax';
		} elseif ( $wp_query->is_archive ) {
			if ( $wp_query->is_day ) {
				$loop = 'day';
			} elseif ( $wp_query->is_month ) {
				$loop = 'month';
			} elseif ( $wp_query->is_year ) {
				$loop = 'year';
			} elseif ( $wp_query->is_author ) {
				$loop = 'author';
			} else {
				$loop = 'archive';
			}
		} elseif ( $wp_query->is_search ) {
			$loop = 'search';
		}

		return $loop;
	}

	/**
	 * Display settings for enable or disable Contextly on non post pages.
	 */
	public function settings_display_enable_non_article_pages() {
		$values     = $this->get_enable_non_article_page_display();
		$non_article_pages = array(
		    'home' => 'Homepage',
		    'tag' => 'Tags',
		    'category' => 'Categories',
        );

		echo "<table cellpadding='0' cellspacing='0'>";
		foreach ( $non_article_pages as $page_type => $label ) {
            echo "<tr><td style='padding: 3px;'>";
            echo "<input type='hidden' name='" . esc_attr( self::ADVANCED_SETTINGS_KEY ) . "[enable_non_article_pages][]' type='checkbox' value='0' />";
            echo "<input id='non-article-" . esc_attr( $page_type ) . "' name='" . esc_attr( self::ADVANCED_SETTINGS_KEY ) . "[enable_non_article_pages][]' type='checkbox' value='" . esc_attr( $page_type ) . "' " . checked( in_array( $page_type, array_values( $values ), true ), true, false ) . ' />';
            echo "</td><td style='padding: 3px;'><label for='non-article-" . esc_attr( $page_type ) . "'>";
            echo esc_html( $label );
            echo '</label></td></tr>';
		}
		echo '</table>';
	}

	/**
	 * Get array of all available non post pages with enable Contextly display.
	 *
	 * @return array
	 */
	public function get_enable_non_article_page_display() {
		$options = get_option( self::ADVANCED_SETTINGS_KEY );

		// Hack for previous plugin versions and selected values.
		$values = isset( $options['enable_non_article_pages'] ) ? $options['enable_non_article_pages'] : false;

		if ( $values === false ) {
			$values = array( 'home', 'tag', 'category' );
		}

		return $values;
	}

}
