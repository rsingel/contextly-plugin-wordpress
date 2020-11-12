<?php
/**
 * Main class.
 *
 * @package Contextly Related Links
 * @link https://contextly.com
 */

/**
 * Class Contextly
 */
class Contextly {

	const API_SETTINGS_KEY      = 'contextly_options_api';
	const ADVANCED_SETTINGS_KEY = 'contextly_options_advanced';

	const DEFAULT_PLACEMENT_CLASS   = 'ctx_default_placement';
	const WIDGET_PLACEMENT_CLASS    = 'ctx_widget_placement';
	const SHORTCODE_PLACEMENT_CLASS = 'ctx_shortcode_placement';

	const WIDGET_SIDEBAR_CLASS  = 'ctx-sidebar-container';
	const WIDGET_SIDEBAR_PREFIX = 'ctx-sidebar-container--';

	const WIDGET_AUTO_SIDEBAR_CLASS  = 'ctx-autosidebar-container';
	const WIDGET_AUTO_SIDEBAR_PREFIX = 'ctx-autosidebar-container--';
	const WIDGET_AUTO_SIDEBAR_CODE   = '[contextly_auto_sidebar]';

	const CLEARFIX_CLASS               = 'ctx-clearfix';
	const WIDGET_SNIPPET_CLASS         = 'ctx-module-container';
	const WIDGET_STORYLINE_CLASS       = 'ctx-subscribe-container';
	const WIDGET_PERSONALIZATION_CLASS = 'ctx-personalization-container';
	const WIDGET_CHANNEL_CLASS         = 'ctx-channel-container';
	const WIDGET_SIDERAIL_CLASS        = 'ctx-siderail-container';
	const WIDGET_AUTHOR_CLASS          = 'ctx-author-container';

	const MAIN_MODULE_SHORT_CODE            = 'contextly_main_module';
	const SL_MODULE_SHORT_CODE              = 'contextly_sl_button';
	const PERSONALIZATION_MODULE_SHORT_CODE = 'contextly_personalization_button';
	const CHANNEL_MODULE_SHORT_CODE         = 'contextly_channel_button';
	const ALL_BUTTONS_SHORT_CODE            = 'contextly_all_buttons';
	const SIDERAIL_MODULE_SHORT_CODE        = 'contextly_siderail';
	const AUTHOR_MODULE_SHORT_CODE          = 'contextly_author';

	/**
	 * APi instance.
	 *
	 * @var ContextlyKitApi
	 */
	protected $api;

	/**
	 * Additional inline JS.
	 *
	 * @var string $inline_js
	 */
	protected $inline_js = '';

	/**
	 * Gte API instance.
	 *
	 * @return ContextlyKitApi API instance.
	 */
	protected function api() {
		if ( ! isset( $this->api ) ) {
			$this->api = ContextlyWpKit::getInstance()->newApi();
		}

		return $this->api;
	}

	/**
	 * Main init method.
	 */
	public function init() {
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'init_admin' ), 1 );
			add_action( 'save_post', array( $this, 'publish_box_control_save_post_hook' ) );
			add_filter( 'default_content', array( $this, 'add_autosidebar_code_filter' ), 10, 2 );
			add_action( 'admin_head', array( $this, 'insert_metatags' ), 0 );
			add_action( 'admin_head', array( $this, 'insert_head_scripts' ), 1 );
			add_action( 'admin_footer', array( $this, 'insert_footer_scripts' ), 0 );
			register_activation_hook( CONTEXTLY_PLUGIN_FILE, array( $this, 'add_activation_hook' ) );

			// Register overlay dialog page.
			ContextlyWpKit::getInstance()
				->newWpOverlayPage()
				->addMenuAction();
		} else {
			add_action( 'the_content', array( $this, 'add_snippet_widget_to_content' ) );
			add_action( 'wp_head', array( $this, 'insert_metatags' ), 0 );

			$head_action = CONTEXTLY_HEAD_SCRIPT_ACTION;
			if ( ! empty( $head_action ) ) {
				add_action( $head_action, array( $this, 'insert_head_scripts' ), CONTEXTLY_HEAD_SCRIPT_WEIGHT );
			}

			$footer_action = CONTEXTLY_FOOTER_SCRIPT_ACTION;
			if ( ! empty( $footer_action ) ) {
				add_action( $footer_action, array( $this, 'insert_footer_scripts' ), CONTEXTLY_FOOTER_SCRIPT_WEIGHT );
			}

			// Add auto-placement anchor with priority a bit higher than default 10 to run after
			// wpautop() that causes the anchor to end up inside a P tag.
			add_action( 'the_content', array( $this, 'add_article_root_anchor_to_content' ), 11 );
		}

		add_action( 'init', array( $this, 'init_default' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
		add_action( 'widgets_init', array( $this, 'register_widgets' ) );
		add_action( 'publish_post', array( $this, 'publish_post' ), 10, 2 );
		add_action( 'save_post', array( $this, 'publish_post' ), 10, 2 );

		add_action('enqueue_block_editor_assets', array( $this, 'register_block_assets' ) );
		add_action('enqueue_block_assets', array( $this, 'register_block_assets' ) );

		$this->attach_ajax_actions();
		$this->expose_public_actions();
	}

	/**
	 * Register blocks.
	 */
	public function register_block_assets() {
		if ( $this->is_load_widget() && function_exists( 'register_block_type' ) ) {
			$this->register_sidebar_block();
			$this->register_auto_sidebar_block();
		}
	}

	/**
	 * Override some actions.
	 */
	public function expose_public_actions() {
		add_action( 'contextly_print_metatags', array( $this, 'print_post_metatags' ), 10, 2 );
		add_action( 'contextly_print_init_script', array( $this, 'print_init_script' ) );
		add_action( 'contextly_print_launch_script', array( $this, 'print_launch_script' ), 10, 2 );
		add_action( 'contextly_print_removal_script', array( $this, 'print_removal_script' ), 10, 2 );

		add_filter( 'contextly_post_metadata', array( $this, 'fill_post_metadata' ), 10, 2 );
		add_filter( 'contextly_post_js_data', array( $this, 'fill_post_js_data' ), 10, 2 );
	}

	/**
	 * Attach AJAX actions.
	 */
	private function attach_ajax_actions() {
		add_action( 'wp_ajax_nopriv_contextly_publish_post', array( $this, 'ajax_publish_post_callback' ) );
		add_action( 'wp_ajax_contextly_publish_post', array( $this, 'ajax_publish_post_callback' ) );
		add_action( 'wp_ajax_contextly_get_auth_token', array( $this, 'ajax_get_auth_token_callback' ) );

		ContextlyWpKit::getInstance()
			->newWidgetsEditor()
			->addAjaxActions();
	}

	/**
	 * Check if this is admin page.
	 *
	 * @return bool true if this is admin edit page.
	 */
	private function is_admin_edit_page() {
		global $pagenow;

		if ( ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) && is_admin() ) {
			return true;
		}
		return false;
	}

	/**
	 * Check widget display type.
	 *
	 * @param null|object $post_param WP post object.
	 * @return bool true if post type is allowed.
	 */
	public function check_widget_display_type( $post_param = null ) {
		global $post;

		$post_object = null;
		if ( null !== $post_param ) {
			$post_object = $post_param;
		} elseif ( isset( $post ) ) {
			$post_object = $post;
		}

		if ( null !== $post_object && isset( $post_object->post_type ) ) {
			if ( $this->is_post_type_display_allowed( $post_object->post_type ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if some post type allowed for display modules.
	 *
	 * @param $post_type
	 * @return bool
	 */
	public function is_post_type_display_allowed( $post_type ) {
		$contextly_settings = new ContextlySettings();
		$display_types      = $contextly_settings->get_widget_display_type();

		return in_array( $post_type, array_values( $display_types ) );
	}

	/**
	 * Get API options.
	 *
	 * @return array array of options.
	 */
	public static function get_api_client_options() {
		$client_options = array(
			'appID'     => '',
			'appSecret' => '',
		);

		$api_options = get_option( self::API_SETTINGS_KEY );

		if ( is_array( $api_options ) && isset( $api_options['api_key'] ) ) {
			$api_key = explode( '-', trim( $api_options['api_key'] ) );

			if ( count( $api_key ) === 2 ) {
				$client_options['appID']     = $api_key[0];
				$client_options['appSecret'] = $api_key[1];
			}
		}

		return $client_options;
	}

	/**
	 * Get author full name.
	 *
	 * @param object $post WP post object.
	 * @return string author full name.
	 */
	private function get_author_full_name( $post ) {
		if ( get_the_author_meta( 'first_name', $post->post_author ) || get_the_author_meta( 'last_name', $post->post_author ) ) {
			$name = get_the_author_meta( 'first_name', $post->post_author ) . ' ' . get_the_author_meta( 'last_name', $post->post_author );
			return trim( $name );
		}
		return null;
	}

	/**
	 * Get post author display name.
	 *
	 * @param object $post WP post object.
	 * @return string author display name.
	 */
	private function get_author_display_name( $post ) {
		$display_name = get_the_author_meta( 'display_name', $post->post_author );
		$nickname     = get_the_author_meta( 'nickname', $post->post_author );
		$name         = $display_name ? $display_name : $nickname;

		return $name;
	}

	/**
	 * Admin init method.
	 */
	public function init_admin() {
		if ( $this->check_widget_display_type() ) {
			$contextly_settings = new ContextlySettings();
			$display_types      = $contextly_settings->get_widget_display_type();

			foreach ( $display_types as $display_type ) {
				$this->add_admin_metabox_for_page( $display_type );

				if ( $this->is_load_widget() ) {
					$this->add_admin_publish_metabox_for_page( $display_type );
				}
			}

			global $post;
			if ( ! $contextly_settings->is_page_display_disabled( $post->ID ) ) {
				$this->add_editor_buttons();
			}
		}
	}

	/**
	 * Hook for post publish action.
	 *
	 * @param int $post_id post ID.
	 * @return bool true if publish box available.
	 */
	public function publish_box_control_save_post_hook( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}
		if ( empty( $post_id ) ) {
			return false;
		}

		if ( isset( $_POST['contextly_display_widgets'] ) ) { // WPCS: CSRF ok.
			$display_widget_flag = sanitize_text_field( wp_unslash( $_POST['contextly_display_widgets'] ) );   // WPCS: CSRF ok. Input var okay.

			$contextly_settings = new ContextlySettings();
			$contextly_settings->change_page_display( $post_id, $display_widget_flag );
		}

		return true;
	}

	/**
	 * Add admin metabox page.
	 */
	private function add_admin_publish_metabox_for_page() {
		add_action( 'post_submitbox_misc_actions', array( $this, 'echo_admin_publish_metabox_for_page' ) );
	}

	/**
	 * Echo admin metabox for page.
	 */
	public function echo_admin_publish_metabox_for_page() {
		echo '<div class="misc-pub-section misc-pub-section-last" style="border-top: 1px solid #eee; margin-bottom: 5px;">';
		echo 'Contextly: <a class="button action button-primary ctx_snippets_editor_btn" disabled="disabled" style="float: right;">Loading...</a>';
		echo '</div>';
	}

	/**
	 * Main init method.
	 */
	public function init_default() {
		add_shortcode( self::MAIN_MODULE_SHORT_CODE, array( $this, 'prepare_main_module_short_code' ) );
		add_shortcode( 'contextly_sidebar', array( $this, 'prepare_sidebar' ) );
		add_shortcode( 'contextly_auto_sidebar', array( $this, 'prepare_auto_sidebar' ) );
		add_shortcode( self::SL_MODULE_SHORT_CODE, array( $this, 'prepare_fu_button_short_code' ) );
		add_shortcode( self::PERSONALIZATION_MODULE_SHORT_CODE, array( $this, 'prepare_personalization_button_short_code' ) );
		add_shortcode( self::CHANNEL_MODULE_SHORT_CODE, array( $this, 'prepare_channel_button_short_code' ) );
		add_shortcode( self::ALL_BUTTONS_SHORT_CODE, array( $this, 'prepare_all_buttons_short_code' ) );
		add_shortcode( self::SIDERAIL_MODULE_SHORT_CODE, array( $this, 'prepare_siderail_short_code' ) );
		add_shortcode( self::AUTHOR_MODULE_SHORT_CODE, array( $this, 'prepare_author_short_code' ) );
	}

	public function register_sidebar_block() {
		if (is_admin()) {
			$block_js = 'js/contextly-sidebar-block.js';
			wp_enqueue_script(
				'contextly-sidebar-block',
				plugins_url($block_js, __FILE__),
				array('wp-blocks', 'wp-element', 'wp-components', 'wp-editor')
			);
		}

		register_block_type( 'contextly-related-links-block/contextly-sidebar', array(
			'editor_script' => 'contextly-sidebar-block',
			'render_callback' => array( $this, 'prepare_sidebar' ),
		) );
	}

	public function register_auto_sidebar_block() {
		if (is_admin()) {
			$block_js = 'js/contextly-auto-sidebar-block.js';
			wp_enqueue_script(
				'contextly-auto-sidebar-block',
				plugins_url($block_js, __FILE__),
				array('wp-blocks', 'wp-element', 'wp-components', 'wp-editor')
			);
		}

		register_block_type( 'contextly-related-links-block/contextly-auto-sidebar', array(
			'editor_script' => 'contextly-auto-sidebar-block',
			'render_callback' => array( $this, 'prepare_auto_sidebar' ),
		) );
	}

	/**
	 * Add MCE editor buttons.
	 */
	private function add_editor_buttons() {
		// Don't bother doing this stuff if the current user lacks permissions.
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
			return;
		}

		// Add only in Rich Editor mode.
		if ( get_user_option( 'rich_editing' ) === 'true' ) {
			add_filter( 'mce_external_plugins', array( $this, 'add_mce_buttons' ) );
			add_filter( 'mce_buttons', array( $this, 'register_mce_buttons' ) );
		}
	}

	/**
	 * Add admin  metabox for Contextly.
	 *
	 * @param string $page_type page type.
	 */
	private function add_admin_metabox_for_page( $page_type ) {
		add_meta_box(
			'contextly_linker_sectionid',
			__( 'Contextly Related Links', 'contextly_linker_textdomain' ),
			array( $this, 'echo_admin_metabox_content' ),
			$page_type,
			'normal',
			'high'
		);
	}

	/**
	 * Echo admin metabox content.
	 */
	public function echo_admin_metabox_content() {
		echo $this->get_snippet_widget(); // WPCS: XSS ok.
	}

	/**
	 * Add snippet shortcode to page content.
	 *
	 * @param string $content page content.
	 * @return string page content.
	 */
	public function add_snippet_widget_to_content( $content ) {
		return $content . $this->get_snippet_widget();
	}

	/**
	 * Add article anchor to post content.
	 *
	 * @param string $content post content.
	 * @return string content.
	 */
	public function add_article_root_anchor_to_content( $content ) {
		return $content . $this->get_article_root_anchor();
	}

	/**
	 * Get article root anchor.
	 *
	 * @return string root anchor HTML.
	 */
	public function get_article_root_anchor() {
		return '<span class="ctx-article-root"><!-- --></span>';
	}

	/**
	 * Registed Contextly tiny MCE buttons.
	 *
	 * @param array $buttons tiny mce buttons.
	 * @return array array of buttons.
	 */
	public function register_mce_buttons( $buttons ) {
		$options = get_option( self::ADVANCED_SETTINGS_KEY );

		// Now we need to add contextly link btn at position of native link button.
		if ( is_array( $buttons ) && count( $buttons ) > 0 ) {
			foreach ( $buttons as $btn_idx => $button ) {
				if ( 'link' === $button ) {
					if ( 'override' === $options['link_type'] ) {
						$buttons[ $btn_idx ] = 'contextlylink';
					} else {
						array_splice( $buttons, $btn_idx, 0, 'contextlylink' );
					}
				}
			}
		} else {
			if ( ! $options['link_type'] ) {
				array_push( $buttons, 'separator', 'contextlylink' );
			}
		}

		array_push( $buttons, 'separator', 'contextly' );
		array_push( $buttons, 'separator', 'contextlysidebar' );
		return $buttons;
	}

	/**
	 * Add MCE editor buttons.
	 *
	 * @param array $plugin_array MCE plugins array.
	 * @return array array of all MCE plugins.
	 */
	public function add_mce_buttons( $plugin_array ) {
		$plugin_array['contextly'] = plugins_url( 'js/contextly-tinymce.js?v=' . CONTEXTLY_PLUGIN_VERSION, __FILE__ );

		return $plugin_array;
	}

	/**
	 * Get Contextly enable/disable control HTML.
	 *
	 * @return string control HTML.
	 */
	public function get_additional_show_hide_control() {
		global $post;

		$html = '';
		if ( isset( $post ) && $post->ID ) {
			$contextly_settings = new ContextlySettings();
			$flag               = $contextly_settings->is_page_display_disabled( $post->ID );

			if ( ! $flag ) {
				$html .= '<a class="button action ctx_snippets_editor_btn" disabled="disabled">Loading...</a>';
			}

			$html .= '<div style="border-top: 1px solid #DFDFDF; margin-top: 8px; padding-top: 8px;"><span id="timestamp">';
			$html .= '<label>Don\'t display Contextly content on this ' . esc_html( $post->post_type ) . ': ';
			$html .= "<input type='hidden' name='contextly_display_widgets' value='' />";
			$html .= "<input type='checkbox' name='contextly_display_widgets' " . ( $flag ? "checked='checked'" : '' ) . " onchange=\"jQuery('#post').submit();\" /></label>";
			$html .= '</span></div>';

			// Wrap with div, so post editor could render button here.
			$html = '<div class="ctx_preview_admin_controls">' . $html . '</div>'; // WPCS: XSS ok.
		}

		return $html;
	}

	/**
	 * Get main module HTML.
	 *
	 * @return string main module HTML.
	 */
	public function get_snippet_widget() {
		global $post;

		$prefix                   = '';
		$default_html_code        = '';
		$additional_html_controls = '';

		if ( is_admin() ) {
			$contextly_settings      = new ContextlySettings();
			$display_post_settings   = $contextly_settings->is_page_display_disabled( $post->ID );
			$display_global_settings = $this->check_widget_display_type();

			if ( $display_global_settings && ! $display_post_settings ) {
				$default_html_code = "Loading data from <a target='_blank' href='http://contextly.com'>contextly.com</a>, please wait...";
				if ( ! isset( $post ) || ! $post->ID ) {
					$default_html_code = 'Please save a Draft first.';
				}
			} else {
				if ( $display_post_settings && $display_global_settings ) {
					$default_html_code = 'Contextly content is turned off for this post/page. You can change this via the checkbox below.';
				} else {
					$default_html_code = 'Contextly content is turned off for this page. You can change this in <a href="admin.php?page=contextly_options&tab=contextly_options_advanced">Contextly settings page</a> in WordPress, under advanced.';
				}
			}

			if ( $display_global_settings ) {
				$additional_html_controls = $this->get_additional_show_hide_control();
			}
		}

		$classes = array(
			self::WIDGET_SNIPPET_CLASS,
			self::DEFAULT_PLACEMENT_CLASS,
			self::CLEARFIX_CLASS,
		);

		return $prefix . "<div class='" . esc_attr( $this->join_classes( $classes ) ) . "'>" . $default_html_code . '</div>' . $additional_html_controls; // WPCS: XSS ok.
	}

	/**
	 * Get plugin JS file URL.
	 *
	 * @param string $script_name script name.
	 * @return string plugin JS url.
	 */
	public function get_plugin_js( $script_name ) {
		if ( CONTEXTLY_MODE === Urls::MODE_LIVE ) {
			return Urls::get_plugin_cdn_url( $script_name, 'js' );
		} else {
			return plugins_url( 'js/' . $script_name, __FILE__ );
		}
	}

	/**
	 * Get WP ajax url.
	 *
	 * @return string url.
	 */
	public static function get_ajax_url() {
		return admin_url( 'admin-ajax.php' );
	}

	/**
	 * Get editor overlay url.
	 *
	 * @return string url.
	 */
	public static function get_overlay_editor_url() {
		return admin_url( 'admin.php?page=contextly_overlay_dialog&noheader' );
	}

	/**
	 * Build Contextly SJ object.
	 *
	 * @param array $additional_params additional parameters if needed.
	 * @return array|null updated Contextly object.
	 */
	public static function get_contextly_js_object( $additional_params = null ) {
		global $post;

		$options = array(
			'ajax_url' => self::get_ajax_url(),
		);

		if ( is_admin() ) {
			$options += array(
				'editor_url' => self::get_overlay_editor_url(),
				'settings'   => ContextlySettings::get_plugin_options(),
			);

			if ( isset( $post->ID ) ) {
				$options['editor_post_id'] = $post->ID;
			}
		}

		if ( null !== $additional_params ) {
			$options = $options + $additional_params;
		}

		return $options;
	}

	/**
	 * Add needed params in post data.
	 *
	 * @param array  $data parameters.
	 * @param object $post WP post object.
	 * @return array array of changed parameters.
	 */
	public static function fill_post_js_data( $data, $post ) {
		if ( ! empty( $post->ID ) ) {
			$data += array(
				'ajax_nonce' => wp_create_nonce( "contextly-post-{$post->ID}" ),
			);
		}

		return $data;
	}

	/**
	 * Override some Kit settings.
	 *
	 * @return array array of Kit settings.
	 */
	public static function get_kit_settings_overrides() {
		$api_options = self::get_api_client_options();
		$overrides   = array(
			'appId'  => $api_options['appID'],
			'https'  => CONTEXTLY_HTTPS,
			'client' => array(
				'client'  => 'wp',
				'version' => CONTEXTLY_PLUGIN_VERSION,
			),
		);

		if ( CONTEXTLY_MODE !== Urls::MODE_LIVE ) {
			$overrides += array(
				'mode'     => CONTEXTLY_MODE,
				'assetUrl' => plugin_dir_url( __FILE__ ) . 'kit/client/src',
			);
		}

		if ( (boolean) is_admin() ) {
			$overrides += array(
				'admin'    => true,
				'branding' => false,
			);
		}

		return $overrides;
	}

	/**
	 * Is widget need to be loaded.
	 *
	 * @return bool if widget nee to be loaded.
	 */
	public function is_load_widget() {
		global $post;

		$contextly_settings = new ContextlySettings();

		// check regular WP pages
		if ( $this->check_widget_display_type() && ! $contextly_settings->is_page_display_disabled( $post->ID ) ) {
			if (is_page() || is_single() || $this->is_admin_edit_page()) {
				return true;
			}
		}

		// check non posts pages
		$page_type = $contextly_settings->get_wp_page_type();
		$enabled_non_article_pages = $contextly_settings->get_enable_non_article_page_display();

		if ( in_array( $page_type, $enabled_non_article_pages) ) {
			return true;
		}

		return false;
	}

	/**
	 * Load needed libraries.
	 */
	public function load_scripts() {
		if ( ! $this->is_load_widget() ) {
			return;
		}

		if ($this->is_admin_edit_page()) {
			wp_enqueue_script( 'jquery' );
		}
	}

	/**
	 * Load needed Contextly scripts.
	 *
	 * @param array $params input parameters.
	 */
	public function insert_kit_scripts( $params = array() ) {
		$kit     = ContextlyWpKit::getInstance();
		$params += array(
			'preload'   => '',
			'libraries' => array(),
			'foreign'   => array(),
			'overrides' => true,
			'wpdata'    => true,
			'loader'    => $kit->getLoaderName(),
		);

		static $known_packages = array(
			'wp/widgets' => array(
				'include' => array(
					'widgets' => true,
				),
				'js'      => array(
					'contextly-wordpress.js' => true,
				),
			),
			'wp/editor'  => array(
				'include' => array(
					'wp/widgets'              => true,
					'overlay-dialogs/overlay' => true,
				),
				'js'      => array(
					'contextly-post-editor.js' => true,
					'contextly-quicktags.js'   => true,
				),
			),
		);
		$foreign_packages      = array_intersect_key( $known_packages, $params['foreign'] );
		foreach ( $foreign_packages as $name => $contents ) {
			$contents += array(
				'js'      => array(),
				'include' => array(),
			);

			$js = array();
			foreach ( array_keys( $contents['js'] ) as $script_name ) {
				$js[ $this->get_plugin_js( $script_name ) ] = true;
			}
			$contents['js'] = $js;

			$foreign_packages[ $name ] = $kit->newAssetsPackageForeign()
				->addAssets( $contents )
				->addIncluded( $contents['include'] )
				->toExposed();
		}

		$ready = array();
		if ( ! empty( $foreign_packages ) ) {
			$ready[] = array( 'expose', $foreign_packages );
		}
		if ( ! empty( $params['libraries'] ) ) {
			$ready[] = array( 'libraries', $params['libraries'] );
		}
		if ( ! empty( $params['preload'] ) ) {
			$ready[] = array( 'load', $params['preload'] );
		}

		$manager      = $kit->newAssetsManager();
		$packages     = $manager->getPackageWithDependencies( $params['loader'] );
		$exposed_tree = $manager->buildExposedTree( array_keys( $packages ) );
		$code         = '';
		if ( ! empty( $params['overrides'] ) ) {
			$code .= $kit->newOverridesManager( Contextly::get_kit_settings_overrides() )
				->compile( true );
		}
		if ( ! empty( $params['wpdata'] ) ) {
			$code .= $kit->newJsExporter( $this->get_contextly_js_object() )
				->export( 'wpdata', true );
		}

		echo $kit->newAssetsAsyncRenderer( $packages, $exposed_tree )
			->renderAll(
				array(
					'ready' => $ready,
					'code'  => $code,
				)
			); // WPCS: XSS ok.
	}

	/**
	 * Inserts async loader into the page head.
	 */
	public function insert_head_scripts() {
		$params = array(
			// Give some context, to let filters know who initiated the call.
			'source'  => 'contextly-linker',

			'enabled' => $this->is_load_widget(),
			'preload' => true,
			'editor'  => $this->is_admin_edit_page(),
		);

		do_action( 'contextly_print_init_script', $params );
	}

	/**
	 * Prints initialization script.
	 *
	 * Important! This function must be called AFTER other scripts are
	 * inserted into the page DOM, because otherwise it causes race condition.
	 * According to the tests, the only code always executed before the loader
	 * is the synchronous JS inside the same <script> tag.
	 *
	 * @param array $params input parameters.
	 */
	public function print_init_script( $params = array() ) {
		$params += array(
			'preload' => true,
			'enabled' => true,
			'editor'  => false,
		);
		$params  = apply_filters( 'contextly_init_script_options', $params );
		if ( empty( $params['enabled'] ) ) {
			return;
		}

		$package_name = 'wp/widgets';
		$packages     = array(
			$package_name => true,
		);
		if ( ! empty( $params['editor'] ) ) {
			$package_name              = 'wp/editor';
			$packages[ $package_name ] = true;
		}

		$this->insert_kit_scripts(
			array(
				'foreign'   => $packages,
				'preload'   => ! empty( $params['preload'] ) ? $package_name : null,
				'overrides' => true,
				'libraries' => array(
					'jquery' => false,
				),
			)
		);
	}

	/**
	 * Add footer scripts.
	 */
	public function insert_footer_scripts() {
		global $post;

		$params = array(
			// Give some context, to let filters know who initiated the call.
			'source'   => 'contextly-linker',

			'enabled'  => $this->is_load_widget(),
			'editor'   => $this->is_admin_edit_page(),
			'metadata' => false,
			'context'  => null,
		);

		do_action( 'contextly_print_launch_script', $post, $params );
	}

	/**
	 * Launch script action.
	 *
	 * @param object $post WP post object.
	 * @param array  $params input params.
	 */
	public function print_launch_script( $post, $params = array() ) {
		$params += array(
			'enabled'  => true,
			'editor'   => false,
			'context'  => null,
			'metadata' => false,
		);
		$params  = apply_filters( 'contextly_launch_script_options', $params, $post );
		if ( empty( $params['enabled'] ) ) {
			return;
		}

		$widgets_options = array();
		if ( ! empty( $params['metadata'] ) ) {
			$widgets_options['metadata'] = apply_filters( 'contextly_post_metadata', array(), $post );
		}
		if ( isset( $params['context'] ) ) {
			$widgets_options['context'] = $params['context'];
		}

		$widgets_args = array( 'widgets' );
		if ( ! empty( $widgets_options ) ) {
			$widgets_args[] = $widgets_options;
		}

		$post_data_args = array();
		if ( ! empty( $post ) ) {
			$post_data = apply_filters( 'contextly_post_js_data', array(), $post );
			if ( ! empty( $post_data ) ) {
				$post_data_args = array( $post->ID, $post_data );
			}
		}

		$widgets_code = '';
		if ( ! empty( $post_data_args ) ) {
			$widgets_code .= 'Contextly.WPSettings.setPostData(' . $this->encode_args_for_script( $post_data_args ) . ');';
		}
		$widgets_code .= 'Contextly.ready(' . $this->encode_args_for_script( $widgets_args ) . ');';

		$load = array(
			'wp/widgets' => $widgets_code,
		);
		if ( ! empty( $params['editor'] ) ) {
			$load['wp/editor'] = 'Contextly.PostEditor.loadData();';
		}
		$this->render(
			'launch-script', array(
				'load' => $load,
			)
		);
	}

	/**
	 * Adjust removal script action.
	 *
	 * @param object $post WP post.
	 * @param array  $params input params.
	 */
	public function print_removal_script( $post, $params = array() ) {
		$params += array(
			'enabled' => true,
			'editor'  => false,
			'context' => null,
		);
		$params  = apply_filters( 'contextly_removal_script_options', $params, $post );
		if ( empty( $params['enabled'] ) ) {
			return;
		}

		$options = array();
		if ( isset( $params['context'] ) ) {
			$options['context'] = $params['context'];
		}

		$this->render(
			'removal-script', array(
				'package_name' => 'wp/widgets',
				'options'      => $options,
			)
		);
	}

	/**
	 * Encodes passed data as JSON safe for SCRIPT tag.
	 *
	 * PHP 5.3 is required.
	 *
	 * @param array $data input params.
	 *
	 * @return mixed|string JSON text.
	 *
	 * @throws Exception In case of very old php version.
	 */
	public static function encode_json_for_script( $data ) {
		static $php_checked = false;
		if ( ! $php_checked ) {
			if ( ! version_compare( PHP_VERSION, '5.3', '>=' ) ) {
				throw new Exception( 'PHP 5.3 is required to output inline metadata' );
			}
			$php_checked = true;
		}

		return wp_json_encode( $data, JSON_HEX_TAG & JSON_HEX_AMP & JSON_HEX_APOS & JSON_HEX_QUOT );
	}

	/**
	 * JSON-encodes passed array so that it may be printed as JS function arguments in SCRIPT tag.
	 *
	 * @param array $args arguments.
	 *
	 * @return string javascript expression.
	 */
	public static function encode_args_for_script( $args ) {
		$args = array_map( array( 'Contextly', 'encode_json_for_script' ), $args );
		return implode( ',', $args );
	}

	/**
	 * Render plugin views.
	 *
	 * @param string $view_name view name.
	 * @param array  $vars context variables.
	 */
	public function render( $view_name, $vars ) {
		include dirname( CONTEXTLY_PLUGIN_FILE ) . '/views/' . $view_name . '.php';
	}

	/**
	 * AJAX callback for post publishing.
	 */
	public function ajax_publish_post_callback() {
		if ( ! empty( $_REQUEST['page_id'] ) ) { // WPCS: CSRF ok.
			$page_id = sanitize_text_field( wp_unslash( $_REQUEST['page_id'] ) );   // WPCS: CSRF ok. Input var okay.
			check_ajax_referer( "contextly-post-$page_id", 'contextly_nonce' );

			$post = get_post( $page_id );
			if ( $post ) {
				$contextly = new Contextly();
				$result    = $contextly->publish_post( $page_id, $post );

				echo wp_json_encode( $result );
			}
		}

		exit;
	}

	/**
	 * Publish post in Contextly DB.
	 *
	 * @param int    $post_ID post ID.
	 * @param object $post WP post.
	 * @return bool result of post publishing.
	 */
	public function publish_post( $post_ID, $post ) {
		if ( isset( $post ) && $post_ID && $this->check_widget_display_type( $post ) ) {
			try {
				// Check if we have this post in our db.
				$contextly_post = $this->api()
					->method( 'posts', 'get' )
					->param( 'page_id', $post->ID )
					->get();

				$post_data = array(
					'post_id'                  => $post->ID,
					'post_title'               => $post->post_title,
					'post_date'                => $post->post_date,
					'post_modified'            => $post->post_modified,
					'post_status'              => $post->post_status,
					'post_type'                => $post->post_type,
					'post_content'             => wp_strip_all_tags($post->post_content),
					'url'                      => get_permalink( $post->ID ),
					'author_id'                => $post->post_author,
					'post_author'              => $this->get_author_full_name( $post ),
					'post_author_display_name' => $this->get_author_display_name( $post ),
					'post_tags'                => $this->get_post_tags_array( $post->ID ),
					'post_categories'          => $this->get_post_categories_array( $post->ID ),
				);

				// Lets publish this post in our DB.
				$publish_post = $this->api()
					->method( 'posts', 'put' )
					->extraParams( $post_data );

				if ( isset( $contextly_post->entry ) && $contextly_post->entry->id ) {
					$publish_post->param( 'id', $contextly_post->entry->id );
				}

				$publish_post->get();

				return true;
			} catch ( Exception $e ) {
				self::log_error( $e );
			}
		}

		return false;
	}

	/**
	 * Get array of post tags.
	 *
	 * @param int $post_id post ID.
	 * @param int $tags_num_limit tags limit.
	 * @return array array of tags.
	 */
	private function get_post_tags_array( $post_id, $tags_num_limit = 5 ) {
		$tags_array = array();
		$post_tags  = get_the_tags( $post_id );
		if ( is_array( $post_tags ) && count( $post_tags ) > 0 ) {
			foreach ( array_slice( $post_tags, 0, $tags_num_limit ) as $post_tag ) {
				$tags_array[] = esc_html( $post_tag->name );
			}
		}

		return $tags_array;
	}

	/**
	 * Get array of post categories.
	 *
	 * @param int $post_id post ID.
	 * @param int $categories_num_limit categories limit.
	 * @return array of categories.
	 */
	private function get_post_categories_array( $post_id, $categories_num_limit = 5 ) {
		$categories_array = array();
		$post_categories  = wp_get_post_categories( $post_id );
		if ( is_array( $post_categories ) && count( $post_categories ) > 0 ) {
			foreach ( array_slice( $post_categories, 0, $categories_num_limit ) as $category_id ) {
				$category = get_category( $category_id );
				if ( $category && strtolower( $category->name ) !== 'uncategorized' ) {
					$categories_array[] = esc_html( $category->name );
				}
			}
		}

		return $categories_array;
	}

	/**
	 * Get array of post images.
	 *
	 * @param int $post_id post ID.
	 * @return array|bool of post images.
	 */
	private function get_post_images( $post_id ) {
		$attachment_images = new WP_Query(
			array(
				'post_parent'    => $post_id,
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 10,
				'post_mime_type' => 'image',
			)
		);
		return $attachment_images->posts;
	}

	/**
	 * Get post featured image description.
	 *
	 * @param int $post_id post ID.
	 * @return string image description.
	 */
	private function get_post_featured_image_alt( $post_id ) {
		$image_alt = get_post_meta( get_post_thumbnail_id( $post_id ), '_wp_attachment_image_alt', true );

		return $image_alt;
	}

	/**
	 * Get best post featured image.
	 *
	 * @param int $post_id post ID.
	 * @return string|null post image URL.
	 */
	private function get_post_featured_image( $post_id ) {
		if ( has_post_thumbnail( $post_id ) ) {
			list($url) = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'post-thumbnail' );

			if ( $url ) {
				return $url;
			}
		} else {
			$post_images = $this->get_post_images( $post_id );

			if ( count( $post_images ) > 0 ) {
				$sorted_images      = array();
				$check_images_count = 6;

				foreach ( $post_images as $image ) {
					// Check if image url, this is NOT URL to another server. If this is external URL, this can take a lot of time to detect image size.
					if ( strpos( $image->guid, site_url() ) !== false ) {
						list($url, $width, $height) = wp_get_attachment_image_src( $image->ID, 'full' );
						$image_rank                 = $width + $height;

						if ( ! isset( $sorted_images[ $image_rank ] ) ) {
							$sorted_images[ $image_rank ] = array( $url );
						} else {
							$sorted_images[ $image_rank ][] = $url;
						}

						if ( count( $sorted_images ) >= $check_images_count ) {
							break;
						}
					}
				}

				if ( count( $sorted_images ) === 0 ) {
					$first_image = reset( $post_images );
					if ( $first_image && isset( $first_image->guid ) ) {
						$sorted_images[0][] = $first_image->guid;
					}
				}

				krsort( $sorted_images );

				return current( reset( $sorted_images ) );
			}
		}

		return null;
	}

	/**
	 * In this method we will display hidden div. After page loading we will load it's content with javascript.
	 * This will help to load page without loosing performance.
	 *
	 * @param array $attrs array of input attributes.
	 * @return string sidebar HTML shortcode.
	 */
	public function prepare_sidebar( $attrs ) {
		// We will display sidebar only if we have id for this sidebar.
		if ( isset( $attrs['id'] ) ) {
			return "<div class='" . esc_attr( self::WIDGET_SIDEBAR_CLASS ) . ' ' . esc_attr( self::WIDGET_SIDEBAR_PREFIX . $attrs['id'] ) . "'></div>";
		} else {
			return '';
		}
	}

	/**
	 * Execute API request and get auth token.
	 */
	public function ajax_get_auth_token_callback() {
		try {
			$this->api()->testCredentials();
			$json_response = $this->api()->getCurrentResponse();

			$data = array(
				'success'                => 1,
				'contextly_access_token' => (string) $this->api()->getAccessToken(),
			);
			if ( isset( $json_response->key_different_domain ) ) {
				$data['key_different_domain'] = (bool) $json_response->key_different_domain;
			}
		} catch ( Exception $e ) {
			$data = array(
				'success'    => 0,
				'code'       => $e->getCode(),
				'message'    => $e->getMessage(),
				'api-object' => wp_json_encode( $e ),
			);
			self::log_error( $data );
		}

		echo wp_json_encode( $data );
		exit;
	}

	/**
	 * Check if autosidebar is enabled for new posts.
	 *
	 * @return bool enabled or disabled.
	 */
	protected function is_auto_sidebar_insertion_enabled() {
		try {
			$response = $this->api()
				->method( 'sitesettings', 'get' )
				->requireSuccess()
				->returnProperty( 'entry' )
				->get();

			if ( ! empty( $response->enable_auto_sidebars ) ) {
				return true;
			}
		} catch ( Exception $e ) {
			self::log_error( $e );
		}

		return false;
	}

	/**
	 * Add autosidebar shortcode automatically if needed.
	 *
	 * @param string $content current page content.
	 * @param object $post post object.
	 * @return string updated page content.
	 */
	public function add_autosidebar_code_filter( $content, $post ) {
		if ( $this->check_widget_display_type( $post ) && $this->is_auto_sidebar_insertion_enabled() ) {
			$content = self::WIDGET_AUTO_SIDEBAR_CODE . $content;
		}

		return $content;
	}

	/**
	 * Creates autosidebar module HTML shortcode.
	 *
	 * @param array $attrs array of autosidebar attributes.
	 * @return string HTML shortcode.
	 */
	public function prepare_auto_sidebar( $attrs ) {
		$classes = array( self::WIDGET_AUTO_SIDEBAR_CLASS );
		if ( isset( $attrs['id'] ) ) {
			$classes[] = self::WIDGET_AUTO_SIDEBAR_PREFIX . $attrs['id'];
		}

		return "<div class='" . esc_attr( $this->join_classes( $classes ) ) . "'></div>";
	}

	/**
	 * Creates main module HTML shortcode.
	 *
	 * @return string HTML shortcode.
	 */
	public function prepare_main_module_short_code() {
		$classes = array(
			self::WIDGET_SNIPPET_CLASS,
			self::SHORTCODE_PLACEMENT_CLASS,
			self::CLEARFIX_CLASS,
		);
		return sprintf( "<div class='%s'></div>", $this->join_classes( $classes ) );
	}

	/**
	 * Creates FU button module HTML shortcode.
	 *
	 * @return string HTML shortcode.
	 */
	public function prepare_fu_button_short_code() {
		$classes = array(
			self::WIDGET_STORYLINE_CLASS,
			self::SHORTCODE_PLACEMENT_CLASS,
			self::CLEARFIX_CLASS,
		);
		return sprintf( "<div class='%s'></div>", $this->join_classes( $classes ) );
	}

	/**
	 * Creates personalized button module HTML shortcode.
	 *
	 * @return string HTML shortcode.
	 */
	public function prepare_personalization_button_short_code() {
		$classes = array(
			self::WIDGET_PERSONALIZATION_CLASS,
			self::SHORTCODE_PLACEMENT_CLASS,
			self::CLEARFIX_CLASS,
		);
		return sprintf( "<div class='%s'></div>", $this->join_classes( $classes ) );
	}

	/**
	 * Creates channel button module HTML shortcode.
	 *
	 * @return string HTML shortcode.
	 */
	public function prepare_channel_button_short_code() {
		$classes = array(
			self::WIDGET_CHANNEL_CLASS,
			self::SHORTCODE_PLACEMENT_CLASS,
			self::CLEARFIX_CLASS,
		);
		return sprintf( "<div class='%s'></div>", $this->join_classes( $classes ) );
	}

	/**
	 * Creates subscription buttons module HTML shortcode.
	 *
	 * @return string HTML shortcode.
	 */
	public function prepare_all_buttons_short_code() {
		$classes = array(
			self::WIDGET_PERSONALIZATION_CLASS,
			self::WIDGET_STORYLINE_CLASS,
			self::WIDGET_CHANNEL_CLASS,
			self::SHORTCODE_PLACEMENT_CLASS,
			self::CLEARFIX_CLASS,
		);
		return sprintf( "<div class='%s'></div>", $this->join_classes( $classes ) );
	}

	/**
	 * Creates siderail module HTML shortcode.
	 *
	 * @return string HTML shortcode.
	 */
	public function prepare_siderail_short_code() {
		$classes = array(
			self::WIDGET_SIDERAIL_CLASS,
			self::SHORTCODE_PLACEMENT_CLASS,
			self::CLEARFIX_CLASS,
		);
		return sprintf( "<div class='%s'></div>", $this->join_classes( $classes ) );
	}

	/**
	 * Creates author module HTML shortcode.
	 *
	 * @return string HTML shortcode.
	 */
	public function prepare_author_short_code() {
		$classes = array(
			self::WIDGET_AUTHOR_CLASS,
			self::SHORTCODE_PLACEMENT_CLASS,
			self::CLEARFIX_CLASS,
		);
		return sprintf( "<div class='%s'></div>", $this->join_classes( $classes ) );
	}

	/**
	 * Prepare post metadata for page header.
	 *
	 * @param array  $metadata metadata array.
	 * @param object $post post object.
	 * @return array updated metadata array.
	 */
	public function fill_post_metadata( $metadata, $post ) {
		$contextly_settings = new ContextlySettings();
		$wp_page_type = $contextly_settings->get_wp_page_type();

		// in some cases we need to allow admin edit pos page
		if ( !$wp_page_type && ! empty( $post->ID ) && is_admin() ) {
			$wp_page_type = $post->post_type;
		}

		$is_wp_regular_page = in_array($wp_page_type, ['post', 'page']);

		// metadata for post or any other page type
		if ( ( ! empty( $post->ID ) && $this->is_post_type_display_allowed( $wp_page_type ) ) || $is_wp_regular_page ) {
			$metadata += array(
				'title'               => esc_html( $post->post_title ),
				'url'                 => get_permalink( $post->ID ),
				'pub_date'            => esc_html( $post->post_date ),
				'mod_date'            => esc_html( $post->post_modified ),
				'type'           	  => esc_html( $post->post_type ),
				'post_id'             => esc_html( $post->ID ),
				'author_id'           => esc_html( $post->post_author ),
				'author_name'         => esc_html( $this->get_author_full_name( $post ) ),
				'author_display_name' => esc_html( $this->get_author_display_name( $post ) ),
				'tags'                => $this->get_post_tags_array( $post->ID ),
				'categories'          => $this->get_post_categories_array( $post->ID ),
				'image'               => esc_html( $this->get_post_featured_image( $post->ID ) ),
			);

			$image_alt = $this->get_post_featured_image_alt( $post->ID );
			if ( $image_alt ) {
				$metadata['image_alt'] = esc_html( $image_alt );
			}
		} else {
			global $wp;

			// if author page, we need to get author name
			if ($wp_page_type == 'author') {
				$author_full_name = $this->get_author_full_name( $post );
				$author_display_name = $this->get_author_display_name( $post );

				$type_term = strlen($author_display_name) > strlen($author_full_name) ? $author_display_name : $author_full_name;
			} else {
				$type_term = single_term_title('', false);
			}

			$metadata += array(
				'url'                 => home_url( $wp->request ),
				'type'           	  => esc_html( $wp_page_type ),
				'type_term'           => esc_html( $type_term ),
			);
		}

		return $metadata;
	}

	/**
	 * Insert page metatags.
	 */
	public function insert_metatags() {
		global $post;

		$params = array(
			// Give some context, to let filters know who initiated the call.
			'source'  => 'contextly-linker',

			'enabled' => $this->is_load_widget(),
			'editor'  => $this->is_admin_edit_page(),
		);

		do_action( 'contextly_print_metatags', $post, $params );
	}

	/**
	 * Print Contextly metatags in page header.
	 *
	 * @param object $post post object.
	 * @param array  $params additional params.
	 */
	public function print_post_metatags( $post, $params = array() ) {
		$params += array(
			'enabled' => true,
		);
		$params  = apply_filters( 'contextly_post_metatag_options', $params, $post );

		if ( empty( $params['enabled'] ) ) {
			return;
		}

		$metadata = apply_filters( 'contextly_post_metadata', array(), $post );
		if ( empty( $metadata ) ) {
			return;
		}

		$this->render(
			'metatag', array(
				'metadata' => $metadata,
			)
		);
	}

	/**
	 * Build string fromm classes array.
	 *
	 * @param array $classes array of classes.
	 * @return string
	 */
	public static function join_classes( $classes ) {
		$classes = implode( ' ', (array) $classes );
		return $classes;
	}

	/**
	 * Retun 404 error.
	 */
	public function return404() {
		status_header( 404 );
		$GLOBALS['wp_query']->set_404();
		include get_template_directory() . '/404.php';
		exit;
	}

	/**
	 * Return 500 error.
	 *
	 * @param string|null $message error message.
	 */
	public function return500( $message = null ) {
		status_header( 500 );
		if ( isset( $message ) ) {
			header( 'Content-type: text/plain; charset=' . get_option( 'blog_charset' ) );
			print esc_html( $message );
		}
		exit;
	}

	/**
	 * Load plugin activation event.
	 */
	public function add_activation_hook() {
		self::fire_api_event( 'contextlyPluginActivated' );
	}

	/**
	 * Log API event from plugin.
	 *
	 * @param string $type event type.
	 * @param string $text event text.
	 */
	public static function fire_api_event( $type, $text = '' ) {
		$api = ContextlyWpKit::getInstance()->newApi();

		try {
			$api->method( 'events', 'put' )
				->extraParams(
					array(
						'event_type'    => 'email',
						'event_name'    => $type,
						'site_path'     => site_url(),
						'event_message' => $text,
					)
				)
				->get();
		} catch ( Exception $e ) {
			self::log_error( $e );
		}
	}

	/**
	 * Register Contextly widgets.
	 */
	public function register_widgets() {
		require_once 'class-contextlywpsiderailwidget.php';
		register_widget( 'ContextlyWpSiderailWidget' );
	}

	/**
	 * Log errors.
	 *
	 * @param string|array $message error message or something else.
	 */
	public static function log_error( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true && function_exists( 'error_log' ) ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			if ( is_array( $message ) || is_object( $message ) ) {
				error_log( print_r( $message, true ) );
			} else {
				error_log( $message );
			}
			// phpcs:enable
		}
	}

	/**
	 * Escape string properly for metadata JSON.
	 *
	 * @param $text string input text
	 * @return string escaped tring
	 */
	private function escape($text) {
		return htmlspecialchars($text, ENT_QUOTES & ~ENT_COMPAT, 'utf-8');
	}

}
