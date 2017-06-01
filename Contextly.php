<?php

/**
 * User: Andrew Nikolaenko
 * Date: 2/20/13
 * Time: 10:44 AM
 */

class Contextly
{
    const API_SETTINGS_KEY = 'contextly_options_api';
    const ADVANCED_SETTINGS_KEY = 'contextly_options_advanced';

	const DEFAULT_PLACEMENT_CLASS = 'ctx_default_placement';
	const WIDGET_PLACEMENT_CLASS = 'ctx_widget_placement';
	const SHORTCODE_PLACEMENT_CLASS = 'ctx_shortcode_placement';

    const WIDGET_SNIPPET_META_BOX_TITLE = 'Contextly Related Links';
    const WIDGET_SOCIALER_META_BOX_TITLE = 'Contextly Socialer';

    const WIDGET_SIDEBAR_CLASS = 'ctx-sidebar-container';
    const WIDGET_SIDEBAR_PREFIX = 'ctx-sidebar-container--';

	const WIDGET_AUTO_SIDEBAR_CLASS = 'ctx-autosidebar-container';
	const WIDGET_AUTO_SIDEBAR_PREFIX = 'ctx-autosidebar-container--';
	const WIDGET_AUTO_SIDEBAR_CODE = '[contextly_auto_sidebar]';

	const CLEARFIX_CLASS = 'ctx-clearfix';
	const WIDGET_SNIPPET_CLASS = 'ctx-module-container';
    const WIDGET_STORYLINE_CLASS = 'ctx-subscribe-container';
    const WIDGET_PERSONALIZATION_CLASS = 'ctx-personalization-container';
    const WIDGET_CHANNEL_CLASS = 'ctx-channel-container';
	const WIDGET_SIDERAIL_CLASS = 'ctx-siderail-container';
	const WIDGET_SOCIAL_CLASS = 'ctx-social-container';

	const MAIN_MODULE_SHORT_CODE = 'contextly_main_module';
	const SL_MODULE_SHORT_CODE = 'contextly_sl_button';
	const PERSONALIZATION_MODULE_SHORT_CODE = 'contextly_personalization_button';
	const CHANNEL_MODULE_SHORT_CODE = 'contextly_channel_button';
	const ALL_BUTTONS_SHORT_CODE = 'contextly_all_buttons';
	const SIDERAIL_MODULE_SHORT_CODE = 'contextly_siderail';
	const SOCIAL_MODULE_SHORT_CODE = 'contextly_social';

	/**
	 * @var ContextlyKitApi
	 */
	protected $api;

	protected $inline_js = '';

	protected function api() {
		if (!isset($this->api)) {
			$this->api = ContextlyWpKit::getInstance()->newApi();
		}

		return $this->api;
	}

    public function init()
		{
			if ( is_admin() ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'initAdmin' ), 1 );
				add_action( 'save_post', array( $this, 'publishBoxControlSavePostHook' ) );
				add_filter( 'default_content', array( $this, 'addAutosidebarCodeFilter' ), 10, 2 );
				add_action( 'admin_head', array( $this, 'insertMetatags' ), 0 );
				add_action( 'admin_head', array( $this, 'insertHeadScripts' ), 1 );
				add_action( 'admin_footer', array( $this, 'insertFooterScripts' ), 0 );
				register_activation_hook( CONTEXTLY_PLUGIN_FILE, array( $this, 'addActivationHook' ) );

				// Register overlay dialog page.
				ContextlyWpKit::getInstance()
					->newWpOverlayPage()
					->addMenuAction();
			} else {
				add_action( 'the_content', array( $this, 'addSnippetWidgetToContent' ) );
				add_action( 'wp_head', array( $this, 'insertMetatags' ), 0 );

				$head_action = CONTEXTLY_HEAD_SCRIPT_ACTION;
				if ( ! empty( $head_action ) ) {
					add_action( $head_action, array( $this, 'insertHeadScripts' ), CONTEXTLY_HEAD_SCRIPT_WEIGHT );
				}

				$footer_action = CONTEXTLY_FOOTER_SCRIPT_ACTION;
				if ( ! empty( $footer_action ) ) {
					add_action( $footer_action, array( $this, 'insertFooterScripts' ), CONTEXTLY_FOOTER_SCRIPT_WEIGHT );
				}

				// Add auto-placement anchor with priority a bit higher than default 10 to run after
				// wpautop() that causes the anchor to end up inside a P tag.
				add_action( 'the_content', array( $this, 'addArticleRootAnchorToContent' ), 11 );
			}

			add_action( 'init', array( $this, 'initDefault' ), 1 );
			add_action( 'wp_enqueue_scripts', array( $this, 'loadScripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'loadScripts' ) );
			add_action( 'widgets_init', array( $this, 'registerWidgets' ) );
			add_action( 'publish_post', array( $this, 'publishPost' ), 10, 2 );
			add_action( 'save_post', array( $this, 'publishPost' ), 10, 2 );

			$this->attachAjaxActions();
			$this->exposePublicActions();
		}

	  public function exposePublicActions() {
			add_action( 'contextly_print_metatags', array( $this, 'printPostMetatags' ), 10, 2 );
			add_action( 'contextly_print_init_script', array( $this, 'printInitScript' ) );
			add_action( 'contextly_print_launch_script', array( $this, 'printLaunchScript' ), 10, 2 );
			add_action( 'contextly_print_removal_script', array( $this, 'printRemovalScript' ), 10, 2 );

			add_filter( 'contextly_post_metadata', array( $this, 'fillPostMetadata' ), 10, 2 );
			add_filter( 'contextly_post_js_data', array( $this, 'fillPostJsData' ), 10, 2 );
		}

	private function attachAjaxActions() {
		add_action('wp_ajax_nopriv_contextly_publish_post', array( $this, 'ajaxPublishPostCallback' ) );
		add_action('wp_ajax_contextly_publish_post', array( $this, 'ajaxPublishPostCallback' ) );
		add_action('wp_ajax_contextly_get_auth_token', array( $this, 'ajaxGetAuthTokenCallback' ) );

		ContextlyWpKit::getInstance()
			->newWidgetsEditor()
			->addAjaxActions();
	}

    private function isAdminEditPage() {
        global $pagenow;

        if ( ( $pagenow == "post.php" || $pagenow == "post-new.php" ) && is_admin() ) {
            return true;
        }
        return false;
    }

    public function checkWidgetDisplayType( $post_param = null ) {
	    global $post;

	    $post_object = null;
	    if ( null !== $post_param ) {
			$post_object = $post_param;
	    } elseif ( isset( $post ) ) {
		    $post_object = $post;
	    }

	    if ( $post_object !== null && isset( $post_object->post_type ) ) {
	        $contextly_settings = new ContextlySettings();
	        $display_types = $contextly_settings->getWidgetDisplayType();

		    if ( in_array( $post_object->post_type, array_values( $display_types ) ) ) {
			    return true;
		    }
	    }

        return false;
    }

    public static function getAPIClientOptions() {
        $client_options = array(
            'appID'         => '',
            'appSecret'     => ''
        );

        $api_options = get_option( self::API_SETTINGS_KEY );

        if ( is_array( $api_options ) && isset( $api_options[ 'api_key' ] ) ) {
            $api_key = explode( '-', trim( $api_options[ 'api_key' ] ) );

            if ( count( $api_key ) == 2 ) {
                $client_options[ 'appID' ]      = $api_key[ 0 ];
                $client_options[ 'appSecret' ]  = $api_key[ 1 ];
            }
        }

        return $client_options;
    }

    private function getAuthorFullName( $post ) {
	    if ( get_the_author_meta( "first_name", $post->post_author ) || get_the_author_meta( "last_name", $post->post_author ) )
	    {
	        $name = get_the_author_meta( "first_name", $post->post_author ) . ' ' . get_the_author_meta( "last_name", $post->post_author );
		    return $this->escape( trim( $name ) );
	    }
        return null;
    }

	private function getAuthorDisplayName( $post ) {
		$display_name = get_the_author_meta( "display_name", $post->post_author );
		$nickname = get_the_author_meta( "nickname", $post->post_author );
		$name = $display_name ? $display_name : $nickname;

		return $this->escape( $name );
	}

    public function initAdmin() {
        if ( $this->checkWidgetDisplayType() ) {
	        $contextly_settings = new ContextlySettings();
	        $display_types = $contextly_settings->getWidgetDisplayType();

	        foreach ( $display_types as $display_type ) {
		        $this->addAdminMetaboxForPage( $display_type );

		        if ( $this->isLoadWidget() ) {
			        $this->addAdminPublishMetaboxForPage($display_type);
		        }
	        }

            global $post;
            if ( !$contextly_settings->isPageDisplayDisabled( $post->ID ) ) {
                $this->addEditorButtons();
            }
        }
    }

    public function publishBoxControlSavePostHook( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return false;
        if ( empty( $post_id ) ) return false;

	    if ( isset( $_POST['contextly_display_widgets'] ) ) {
		    $display_widget_flag = $_POST['contextly_display_widgets'];

		    $contextly_settings = new ContextlySettings();
		    $contextly_settings->changePageDisplay( $post_id, $display_widget_flag );
	    }

        return true;
    }

    private function addAdminPublishMetaboxForPage() {
	    add_action( 'post_submitbox_misc_actions', array( $this, 'echoAdminPublishMetaboxForPage' ) );
    }

    public function echoAdminPublishMetaboxForPage() {
	    echo '<div class="misc-pub-section misc-pub-section-last" style="border-top: 1px solid #eee; margin-bottom: 5px;">';
	    echo 'Contextly: <a class="button action button-primary ctx_snippets_editor_btn" disabled="disabled" style="float: right;">Loading...</a>';
	    echo '</div>';
    }

    public function initDefault() {
        add_shortcode(self::MAIN_MODULE_SHORT_CODE, array( $this, 'prepareMainModuleShortCode' ) );
        add_shortcode('contextly_sidebar', array( $this, 'prepareSidebar' ) );
        add_shortcode('contextly_auto_sidebar', array( $this, 'prepareAutoSidebar' ) );
        add_shortcode(self::SL_MODULE_SHORT_CODE, array( $this, 'prepareSLButtonShortCode' ) );
        add_shortcode(self::PERSONALIZATION_MODULE_SHORT_CODE, array( $this, 'preparePersonalizationButtonShortCode' ) );
        add_shortcode(self::CHANNEL_MODULE_SHORT_CODE, array( $this, 'prepareChannelButtonShortCode' ) );
        add_shortcode(self::ALL_BUTTONS_SHORT_CODE, array( $this, 'prepareAllButtonsShortCode' ) );
        add_shortcode(self::SIDERAIL_MODULE_SHORT_CODE, array( $this, 'prepareSiderailShortCode' ) );
        add_shortcode(self::SOCIAL_MODULE_SHORT_CODE, array( $this, 'prepareSocialShortCode' ) );
    }

    private function addEditorButtons() {
        // Don't bother doing this stuff if the current user lacks permissions
        if (! current_user_can('edit_posts') && ! current_user_can('edit_pages') ) return;

        // Add only in Rich Editor mode
        if ( get_user_option('rich_editing') == 'true') {
            add_filter("mce_external_plugins", array( $this, 'addMceButtons' ) );
            add_filter('mce_buttons', array( $this, 'registerMceButtons' ) );
        }
    }

    private function addAdminMetaboxForPage( $page_type ) {
        add_meta_box(
            'contextly_linker_sectionid',
            __( self::WIDGET_SNIPPET_META_BOX_TITLE, 'contextly_linker_textdomain' ),
            array( $this, 'echoAdminMetaboxContent' ),
            $page_type,
            'normal',
            'high'
        );
    }

    public function echoAdminMetaboxContent() {
        echo $this->getSnippetWidget();
    }

    public function addSnippetWidgetToContent( $content ) {
        return $content . $this->getSnippetWidget();
    }

	  public function addArticleRootAnchorToContent( $content ) {
				return $content . $this->getArticleRootAnchor();
		}

	  public function getArticleRootAnchor() {
				return '<span class="ctx-article-root"><!-- --></span>';
		}

    public function registerMceButtons( $buttons ) {
        $options = get_option( self::ADVANCED_SETTINGS_KEY );

        // Now we need to add contextly link btn at position of native link button
        if (is_array($buttons) && count($buttons) > 0) {
            foreach ($buttons as $btn_idx => $button) {
                if ($button == "link") {
                    if ($options['link_type'] == "override") {
                        $buttons[$btn_idx] = "contextlylink";
                    } else {
                        array_splice($buttons, $btn_idx, 0, "contextlylink");
                    }
                }
            }
        } else {
            if (!$options['link_type']) {
                array_push($buttons, "separator", "contextlylink");
            }
        }

        array_push($buttons, "separator", "contextly");
        array_push($buttons, "separator", "contextlysidebar");
        return $buttons;
    }

    public function addMceButtons( $plugin_array ) {
        $plugin_array['contextly'] = plugins_url('js/contextly-tinymce.js?v=' . CONTEXTLY_PLUGIN_VERSION , __FILE__ );

        return $plugin_array;
    }

    public function getAdditionalShowHideControl() {
        global $post;

        $html = '';
        if ( isset( $post ) && $post->ID ) {
            $contextly_settings = new ContextlySettings();
            $flag = $contextly_settings->isPageDisplayDisabled( $post->ID );

			if ( !$flag ) {
				$html .= '<a class="button action ctx_snippets_editor_btn" disabled="disabled">Loading...</a>';
			}

            $html .= '<div style="border-top: 1px solid #DFDFDF; margin-top: 8px; padding-top: 8px;"><span id="timestamp">';
            $html .= '<label>Don\'t display Contextly content on this ' . $post->post_type . ': ';
            $html .= "<input type='hidden' name='contextly_display_widgets' value='' />";
            $html .= "<input type='checkbox' name='contextly_display_widgets' " . ( $flag ? "checked='checked'" : "" ) . " onchange=\"jQuery('#post').submit();\" /></label>";
            $html .= '</span></div>';

					// Wrap with div, so post editor could render button here.
					$html = '<div class="ctx_preview_admin_controls">' . $html . '</div>';
        }

        return $html;
    }

    public function getSnippetWidget() {
        global $post;

		$prefix = '';
        $default_html_code = '';
        $additional_html_controls = '';

        if ( is_admin() ) {
            $contextly_settings = new ContextlySettings();
            $display_post_settings = $contextly_settings->isPageDisplayDisabled( $post->ID );
            $display_global_settings = $this->checkWidgetDisplayType();

            if ( $display_global_settings && !$display_post_settings ) {
                $default_html_code = "Loading data from <a target='_blank' href='http://contextly.com'>contextly.com</a>, please wait...";
                if ( !isset( $post ) || !$post->ID ) {
                    $default_html_code = "Please save a Draft first.";
                }
            } else {
                if ( $display_post_settings && $display_global_settings ) {
                    $default_html_code = 'Contextly content is turned off for this post/page. You can change this via the checkbox below.';
                } else {
                    $default_html_code = 'Contextly content is turned off for this page. You can change this in <a href="admin.php?page=contextly_options&tab=contextly_options_advanced">Contextly settings page</a> in WordPress, under advanced.';
                }
            }

            if ( $display_global_settings ) {
	            $additional_html_controls = $this->getAdditionalShowHideControl();
            }
        }
		else
		{
			$classes = array(
				self::WIDGET_STORYLINE_CLASS,
				self::WIDGET_PERSONALIZATION_CLASS,
				self::DEFAULT_PLACEMENT_CLASS,
				self::CLEARFIX_CLASS,
			);
			$prefix = "<div class='" . $this->escapeClasses($classes) . "'></div>";

			$classes = array(
				self::WIDGET_SOCIAL_CLASS,
				self::DEFAULT_PLACEMENT_CLASS,
				self::CLEARFIX_CLASS,
			);
			$prefix .= "<div class='" . $this->escapeClasses( $classes ) . "'></div>";
		}
				$classes = array(
					self::WIDGET_SNIPPET_CLASS,
					self::DEFAULT_PLACEMENT_CLASS,
					self::CLEARFIX_CLASS,
				);
        return $prefix . "<div class='" . $this->escapeClasses($classes) . "'>" . $default_html_code . "</div>" . $additional_html_controls;
    }

	public function getPluginJs( $script_name ) {
		if ( CONTEXTLY_MODE == Urls::MODE_LIVE ) {
			return Urls::getPluginCdnUrl( $script_name, 'js' );
		} else {
		    return plugins_url( 'js/' . $script_name , __FILE__ );
        }
	}

	public function getPluginCss( $css_name ) {
		if ( CONTEXTLY_MODE == Urls::MODE_LIVE ) {
			return Urls::getPluginCdnUrl( $css_name, 'css' );
		} else {
			return plugins_url( 'css/' . $css_name , __FILE__ );
		}
	}

	public static function getAjaxUrl() {
		return admin_url( 'admin-ajax.php' );
	}

	public static function getOverlayEditorUrl() {
		return admin_url( 'admin.php?page=contextly_overlay_dialog&noheader' );
	}

	public static function getContextlyJSObject( $additional_params = null ) {
		global $post;

		$options = array(
			'ajax_url'      => self::getAjaxUrl(),
		);

		if ( is_admin() ) {
			$options += array(
				'editor_url'    => self::getOverlayEditorUrl(),
				'settings'      => ContextlySettings::getPluginOptions(),
			);

			if ( isset( $post->ID ) ) {
				$options[ 'editor_post_id' ] = $post->ID;
			}
		}

		if ( $additional_params !== null ) {
			$options = $options + $additional_params;
		}

		return $options;
	}

	public static function fillPostJsData( $data, $post )
	{
		if (!empty($post->ID)) {
			$data += array(
				'ajax_nonce' => wp_create_nonce( "contextly-post-{$post->ID}" ),
			);
		}

		return $data;
	}

	public static function getKitSettingsOverrides()
	{
		$api_options = self::getAPIClientOptions();
		$overrides = array(
			'appId' => $api_options[ 'appID' ],
			'https' => CONTEXTLY_HTTPS,
			'client' => array(
				'client' => 'wp',
				'version' => CONTEXTLY_PLUGIN_VERSION,
			),
		);

		if ( CONTEXTLY_MODE !== Urls::MODE_LIVE ) {
			$overrides += array(
				'mode' => CONTEXTLY_MODE,
				'assetUrl' => plugin_dir_url( __FILE__ ) . 'kit/client/src',
			);
		}

		if ( (boolean) is_admin() ) {
			$overrides += array(
				'admin' => true,
				'branding' => false,
			);
		}

		return $overrides;
	}

	public static function getSettingsHandleName() {
		if ( CONTEXTLY_MODE == Urls::MODE_DEV ) {
			return 'contextly-kit-components-create-class';
		}
		return 'contextly-kit-widgets--page-view';
	}

	public function isLoadWidget()
	{
		global $post;
		$contextly_settings = new ContextlySettings();

		if ( $this->checkWidgetDisplayType() && !$contextly_settings->isPageDisplayDisabled( $post->ID ))
		{
			return is_page() || is_single() || $this->isAdminEditPage();
		}
		else
		{
			return false;
		}
	}

	public function loadScripts()
	{
		if ( ! $this->isLoadWidget() ) {
			return;
		}

		wp_enqueue_script( 'jquery' );
	}

	public function insertKitScripts($params = array()) {
		$kit = ContextlyWpKit::getInstance();
		$params += array(
			'preload' => '',
			'libraries' => array(),
			'foreign' => array(),
			'overrides' => true,
			'wpdata' => true,
			'loader' => $kit->getLoaderName(),
		);

		static $known_packages = array(
			'wp/widgets' => array(
				'include' => array(
					'widgets' => true,
				),
				'js' => array(
					'contextly-wordpress.js' => true,
				),
			),
			'wp/editor' => array(
				'include' => array(
					'wp/widgets' => true,
					'overlay-dialogs/overlay' => true,
				),
				'js' => array(
					'contextly-post-editor.js' => true,
					'contextly-quicktags.js' => true,
				),
			),
		);
		$foreign_packages = array_intersect_key($known_packages, $params['foreign']);
		foreach ($foreign_packages as $name => $contents) {
			$contents += array(
				'js' => array(),
				'include' => array(),
			);

			$js = array();
			foreach (array_keys($contents['js']) as $script_name) {
				$js[$this->getPluginJs($script_name)] = true;
			}
			$contents['js'] = $js;

			$foreign_packages[$name] = $kit->newAssetsPackageForeign()
				->addAssets( $contents )
				->addIncluded($contents['include'])
				->toExposed();
		}

		$ready = array();
		if (!empty($foreign_packages)) {
			$ready[] = array('expose', $foreign_packages);
		}
		if (!empty($params['libraries'])) {
			$ready[] = array('libraries', $params['libraries']);
		}
		if (!empty($params['preload'])) {
			$ready[] = array('load', $params['preload']);
		}

		$manager = $kit->newAssetsManager();
		$packages = $manager->getPackageWithDependencies($params['loader']);
		$exposedTree = $manager->buildExposedTree(array_keys($packages));
		$code = '';
		if (!empty($params['overrides'])) {
			$code .= $kit->newOverridesManager( Contextly::getKitSettingsOverrides() )
				->compile(TRUE);
		}
		if (!empty($params['wpdata'])) {
			$code .= $kit->newJsExporter( $this->getContextlyJSObject() )
				->export('wpdata', TRUE);
		}
		print $kit->newAssetsAsyncRenderer($packages, $exposedTree)
			->renderAll( array(
				'ready' => $ready,
				'code' => $code,
			) );
	}

	/**
	 * Inserts async loader into the page head.
	 */
	public function insertHeadScripts()
	{
		$params = array(
			// Give some context, to let filters know who initiated the call.
			'source' => 'contextly-linker',

			'enabled' => $this->isLoadWidget(),
			'preload' => TRUE,
			'editor' => $this->isAdminEditPage(),
		);

		do_action( 'contextly_print_init_script', $params );
	}

	/**
	 * Prints initialization script.
	 *
	 * Important! This function must be called AFTER jQuery and other scripts are
	 * inserted into the page DOM, because otherwise it causes race condition.
	 * According to the tests, the only code always executed before the loader
	 * is the synchronous JS inside the same <script> tag.
	 */
	public function printInitScript( $params = array() )
	{
		$params += array(
			'preload' => TRUE,
			'enabled' => TRUE,
			'editor' => FALSE,
		);
		$params = apply_filters( 'contextly_init_script_options', $params );
		if ( empty( $params['enabled'] ) ) {
			return;
		}

		$package_name = 'wp/widgets';
		$packages = array(
			$package_name => true,
		);
		if ( ! empty( $params['editor'] ) ) {
			$package_name = 'wp/editor';
			$packages[$package_name] = true;
		}

		$this->insertKitScripts( array(
			'foreign' => $packages,
			'preload' => !empty( $params['preload'] ) ? $package_name : NULL,
			'overrides' => true,
			'libraries' => array(
				'jquery' => false,
			),
		));
	}

	public function insertFooterScripts() {
		global $post;
		$params = array(
			// Give some context, to let filters know who initiated the call.
			'source' => 'contextly-linker',

			'enabled' => $this->isLoadWidget(),
			'editor' => $this->isAdminEditPage(),
			'metadata' => FALSE,
			'context' => NULL,
		);

		do_action( 'contextly_print_launch_script', $post, $params );
	}

	public function printLaunchScript( $post, $params = array() ) {
		$params += array(
			'enabled' => TRUE,
			'editor' => FALSE,
			'context' => NULL,
			'metadata' => FALSE,
		);
		$params = apply_filters( 'contextly_launch_script_options', $params, $post );
		if ( empty( $params['enabled'] ) ) {
			return;
		}

		$widgets_options = array();
		if (!empty($params['metadata'])) {
			$widgets_options['metadata'] = apply_filters( 'contextly_post_metadata', array(), $post );
		}
		if (isset($params['context'])) {
			$widgets_options['context'] = $params['context'];
		}

		$widgets_args = array('widgets');
		if ( ! empty( $widgets_options ) ) {
			$widgets_args[] = $widgets_options;
		}

		$post_data_args = array();
		if (!empty($post)) {
			$post_data = apply_filters( 'contextly_post_js_data', array(), $post );
			if (!empty($post_data)) {
				$post_data_args = array($post->ID, $post_data);
			}
		}

		$widgets_code = '';
		if (!empty($post_data_args)) {
			$widgets_code .= 'Contextly.WPSettings.setPostData(' . $this->encodeArgsForScript( $post_data_args ) . ');';
		}
		$widgets_code .= 'Contextly.ready(' . $this->encodeArgsForScript( $widgets_args ) . ');';

		$load = array(
			'wp/widgets' => $widgets_code,
		);
		if ( ! empty( $params['editor'] ) ) {
			$load['wp/editor'] = 'Contextly.PostEditor.loadData();';
		}
		$this->render( 'launch-script', array(
			'load' => $load,
		) );
	}

	public function printRemovalScript( $post, $params = array() )
	{
		$params += array(
			'enabled' => TRUE,
			'editor' => FALSE,
			'context' => NULL,
		);
		$params = apply_filters( 'contextly_removal_script_options', $params, $post );
		if ( empty( $params['enabled'] ) ) {
			return;
		}

		$options = array();
		if (isset($params['context'])) {
			$options['context'] = $params['context'];
		}

		$this->render( 'removal-script', array(
			'package_name' => 'wp/widgets',
			'options' => $options,
		) );
	}

	/**
	 * Encodes passed data as JSON safe for SCRIPT tag.
	 *
	 * PHP 5.3 is required.
	 *
	 * @param $data
	 *
	 * @return mixed|string|void
	 *
	 * @throws Exception
	 */
	public static function encodeJsonForScript( $data )
	{
		static $php_checked = FALSE;
		if (!$php_checked) {
			if ( ! version_compare( PHP_VERSION, "5.3", '>=' ) ) {
				throw new Exception('PHP 5.3 is required to output inline metadata');
			}
			$php_checked = TRUE;
		}

		return json_encode( $data, JSON_HEX_TAG & JSON_HEX_AMP & JSON_HEX_APOS & JSON_HEX_QUOT );
	}

	/**
	 * JSON-encodes passed array so that it may be printed as JS function arguments in SCRIPT tag.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	public static function encodeArgsForScript( $args )
	{
		$args = array_map( array('Contextly', 'encodeJsonForScript'), $args );
		return implode(',', $args);
	}

	public function render( $view_name, $vars )
	{
		extract($vars);
		include dirname( CONTEXTLY_PLUGIN_FILE ) . '/views/' . $view_name . '.php';
	}

	public function ajaxPublishPostCallback() {
		$page_id = $_REQUEST['page_id'];
		check_ajax_referer( "contextly-post-$page_id", 'contextly_nonce');

		$post = get_post( $page_id );
		if ( $post ) {
			$contextly = new Contextly();
			$result = $contextly->publishPost( $page_id, $post );

			echo json_encode( $result );
		}
		exit;
	}

	/**
	 * @param $post_ID
	 * @param $post
	 * @return bool|Exception
	 */
	public function publishPost( $post_ID, $post ) {
		if ( isset( $post ) && $post_ID && $this->checkWidgetDisplayType( $post ) ) {
			try {
				// Check if we have this post in our db
				$contextly_post = $this->api()
					->method( 'posts', 'get' )
					->param( 'page_id', $post->ID )
					->get();

				$post_data = array(
					'post_id'                       => $post->ID,
					'post_title'                    => $post->post_title,
					'post_date'                     => $post->post_date,
					'post_modified'                 => $post->post_modified,
					'post_status'                   => $post->post_status,
					'post_type'                     => $post->post_type,
					'post_content'                  => $post->post_content,
					'url'                           => get_permalink( $post->ID ),
					'author_id'                     => $post->post_author,
					'post_author'                   => $this->getAuthorFullName( $post ),
					'post_author_display_name'      => $this->getAuthorDisplayName( $post ),
					'post_tags'                     => $this->getPostTagsArray( $post->ID ),
					'post_categories'               => $this->getPostCategoriesArray( $post->ID ),
					'post_images'                   => $this->getPostImagesArray( $post->ID )
				);

				// Lets publish this post in our DB
				$publish_post = $this->api()
					->method( 'posts', 'put' )
					->extraParams( $post_data );

				if ( isset( $contextly_post->entry ) && $contextly_post->entry->id ) {
					$publish_post->param( 'id', $contextly_post->entry->id );
				}

				$publish_post->get();
			} catch ( Exception $e ) {
	            return $e;
            }

			return true;
		}

		return false;
	}

	/**
	 * @param $post_id
	 * @param int $tags_num_limit
	 * @return array
	 */
	private function getPostTagsArray( $post_id, $tags_num_limit = 5 ) {
		$tags_array = array();
		$post_tags = get_the_tags( $post_id );
		if (is_array($post_tags) && count($post_tags) > 0) {
			foreach (array_slice($post_tags, 0, $tags_num_limit) as $post_tag) {
				$tags_array[] = $this->escape( $post_tag->name );
			}
		}

		return $tags_array;
	}

	/**
	 * @param $post_id
	 * @param int $categories_num_limit
	 * @return array
	 */
	private function getPostCategoriesArray( $post_id, $categories_num_limit = 5 ) {
		$categories_array = array();
		$post_categories = wp_get_post_categories( $post_id );
		if (is_array($post_categories) && count($post_categories) > 0) {
			foreach ( array_slice($post_categories, 0, $categories_num_limit) as $category_id ) {
				$category = get_category( $category_id );
				if ( $category && strtolower( $category->name ) != "uncategorized" ) {
					$categories_array[] = $this->escape( $category->name );
				}
			}
		}

		return $categories_array;
	}

	/**
	 * @param $post_id
	 * @return array|bool
	 */
	private function getPostImages($post_id)
	{
		$attachment_images = get_children(
			array(
				'post_parent'    => $post_id,
				'post_type'      => 'attachment',
				'numberposts'    => 0,
				'post_mime_type' => 'image'
			)
		);
		return $attachment_images;
	}

	/**
	 * @param $post_id
	 * @return array
	 */
	private function getPostImagesArray( $post_id ) {
		$images_array = array();

		$attachment_images = $this->getPostImages($post_id);

		if ($attachment_images && is_array($attachment_images)) {
			foreach($attachment_images as $image) {
				if ( isset( $image->guid ) ) {
					$images_array[] = $image->guid;
				}

				if ( count( $images_array ) > 5 ) break;
			}
		}

		return $images_array;
	}

	/**
	 * @param $post_id
	 * @return mixed|null
	 */
	private function getPostFeaturedImage( $post_id )
	{
		if (has_post_thumbnail( $post_id ) ) {
			list($url) = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'post-thumbnail' );

			if ( $url )	{
				return $url;
			}
		} else {
			$post_images = $this->getPostImages( $post_id );

			if ( count( $post_images ) > 0 ) {
				$sorted_images = array();
				$check_images_count = 6;

				foreach ( $post_images as $image ) {
					// Check if image url, this is NOT URL to another server. If this is external URL, this can take a lot of time to detect image size
					if ( strpos( $image->guid, site_url() ) !== false ) {
						list($url, $width, $height) = wp_get_attachment_image_src($image->ID, 'full');
						$image_rank = $width + $height;

						if (!isset($sorted_images[$image_rank])) {
							$sorted_images[$image_rank] = array($url);
						} else {
							$sorted_images[$image_rank][] = $url;
						}

						if (count($sorted_images) >= $check_images_count) break;
					}
				}

				if ( count( $sorted_images ) == 0 ) {
					$first_image = reset( $post_images );
					if ( $first_image && isset( $first_image->guid ) ) {
						$sorted_images[0][] = $first_image->guid;
					}
				}

				krsort( $sorted_images );

				return current(reset($sorted_images));
			}
		}

		return null;
	}

	/**
	 * In this method we will display hidden div. After page loading we will load it's content with javascript.
	 * This will help to load page without loosing performance.
	 * @param $attrs
	 * @return string
	 */
	public function prepareSidebar( $attrs ) {
        // We will display sidebar only if we have id for this sidebar
        if ( isset( $attrs[ 'id' ] ) ) {
            return "<div class='" . esc_attr( self::WIDGET_SIDEBAR_CLASS ) . " " . esc_attr( self::WIDGET_SIDEBAR_PREFIX . $attrs[ 'id' ] ) ."'></div>";
        }
        else {
            return '';
        }
    }

	public function ajaxGetAuthTokenCallback() {
		try {
			$this->api()->testCredentials();
			$json_response = $this->api()->getCurrentResponse();

			$data = array(
				'success' => 1,
				'contextly_access_token' => (string) $this->api()->getAccessToken(),
			);
			if ( isset( $json_response->key_different_domain ) ) {
				$data['key_different_domain'] = (bool)$json_response->key_different_domain;
			}
		} catch ( Exception $e ) {
			$data = array(
				'success' => 0,
				'code' => $e->getCode(),
				'message' => $e->getMessage(),
				'api-object' => print_r($e, true)
			);
		}

		echo json_encode( $data );
		exit;
	}

	protected function isAutoSidebarInsertionEnabled() {
		try {
			$response = $this->api()
				->method( 'sitesettings', 'get' )
				->requireSuccess()
			  ->returnProperty('entry')
				->get();

			if ( !empty( $response->enable_auto_sidebars ) ) {
				return true;
			}
		} catch ( Exception $e ) {
		}

		return false;
	}

	/**
	 * @param $content
	 * @param $post
	 * @return mixed
	 */
	public function addAutosidebarCodeFilter( $content, $post ) {
		if ( $this->checkWidgetDisplayType( $post ) && $this->isAutoSidebarInsertionEnabled() )
		{
			$content = self::WIDGET_AUTO_SIDEBAR_CODE . $content;
		}

		return $content;
	}

	/**
	 * @param $attrs
	 * @return string
	 */
	public function prepareAutoSidebar( $attrs ) {
		$classes = array( self::WIDGET_AUTO_SIDEBAR_CLASS );
		if ( isset( $attrs[ 'id' ] ) ) {
			$classes[] = self::WIDGET_AUTO_SIDEBAR_PREFIX . $attrs[ 'id' ];
		}

		return "<div class='" . esc_attr( implode( ' ', $classes ) ) . "'></div>";
	}

	/**
	 * @return string
	 */
	public function prepareMainModuleShortCode() {
		$classes = array(
			self::WIDGET_SNIPPET_CLASS,
			self::SHORTCODE_PLACEMENT_CLASS,
			self::CLEARFIX_CLASS,
		);
		return sprintf( "<div class='%s'></div>", $this->escapeClasses( $classes ) );
	}

	/**
	 * @return string
	 */
	public function prepareSLButtonShortCode() {
		$classes = array(
			self::WIDGET_STORYLINE_CLASS,
			self::SHORTCODE_PLACEMENT_CLASS,
			self::CLEARFIX_CLASS,
		);
		return sprintf( "<div class='%s'></div>", $this->escapeClasses( $classes ) );
	}

	/**
	 * @return string
	 */
	public function preparePersonalizationButtonShortCode() {
		$classes = array(
			self::WIDGET_PERSONALIZATION_CLASS,
			self::SHORTCODE_PLACEMENT_CLASS,
			self::CLEARFIX_CLASS,
		);
		return sprintf( "<div class='%s'></div>", $this->escapeClasses( $classes ) );
	}

	/**
	 * @return string
	 */
	public function prepareChannelButtonShortCode() {
		$classes = array(
			self::WIDGET_CHANNEL_CLASS,
			self::SHORTCODE_PLACEMENT_CLASS,
			self::CLEARFIX_CLASS,
		);
		return sprintf( "<div class='%s'></div>", $this->escapeClasses( $classes ) );
	}

	/**
	 * @return string
	 */
	public function prepareAllButtonsShortCode() {
		$classes = array(
			self::WIDGET_PERSONALIZATION_CLASS,
			self::WIDGET_STORYLINE_CLASS,
			self::WIDGET_CHANNEL_CLASS,
			self::SHORTCODE_PLACEMENT_CLASS,
			self::CLEARFIX_CLASS,
		);
		return sprintf( "<div class='%s'></div>", $this->escapeClasses( $classes ) );
	}

	/**
	 * @return string
	 */
	public function prepareSiderailShortCode() {
		$classes = array(
			self::WIDGET_SIDERAIL_CLASS,
			self::SHORTCODE_PLACEMENT_CLASS,
			self::CLEARFIX_CLASS,
		);
		return sprintf( "<div class='%s'></div>", $this->escapeClasses( $classes ) );
	}

	/**
	 * @return string
	 */
	public function prepareSocialShortCode() {
		$classes = array(
			self::WIDGET_SOCIAL_CLASS,
			self::SHORTCODE_PLACEMENT_CLASS,
			self::CLEARFIX_CLASS,
		);
		return sprintf( "<div class='%s'></div>", $this->escapeClasses( $classes ) );
	}

	public function fillPostMetadata( $metadata, $post )
	{
		if (!empty($post->ID)) {
			$metadata += array(
				'title'                    => $this->escape( $post->post_title ),
				'url'                      => get_permalink( $post->ID ),
				'pub_date'                 => $post->post_date,
				'mod_date'                 => $post->post_modified,
				'type'                     => $post->post_type,
				'post_id'                  => $post->ID,
				'author_id'                => $this->escape( $post->post_author ),
				'author_name'              => $this->getAuthorFullName( $post ),
				'author_display_name'      => $this->getAuthorDisplayName( $post ),
				'tags'                     => $this->getPostTagsArray( $post->ID ),
				'categories'               => $this->getPostCategoriesArray( $post->ID ),
				'image'                    => $this->getPostFeaturedImage( $post->ID )
			);
		}

		return $metadata;
	}

	/**
	 *
	 */
	public function insertMetatags()
	{
		global $post;
		$params = array(
			// Give some context, to let filters know who initiated the call.
			'source' => 'contextly-linker',

			'enabled' => $this->isLoadWidget(),
			'editor' => $this->isAdminEditPage(),
		);

		do_action( 'contextly_print_metatags', $post, $params );
	}

	public function printPostMetatags( $post, $params = array() )
	{
		$params += array(
			'enabled' => TRUE,
		);
		$params = apply_filters( 'contextly_post_metatag_options', $params, $post );

		if (empty($params['enabled'])) {
			return;
		}

		$metadata = apply_filters( 'contextly_post_metadata', array(), $post );
		if (empty($metadata)) {
			return;
		}

		$this->render('metatag', array(
			'metadata' => $metadata,
		));
	}

	/**
	 * @param $text
	 * @return string
	 */
	private function escape($text)
	{
		return htmlspecialchars($text, ENT_QUOTES & ~ENT_COMPAT, 'utf-8');
	}

	public static function escapeClasses($classes)
	{
		$classes = implode((array) $classes, ' ');
		return htmlspecialchars($classes, ENT_QUOTES, 'utf-8');
	}

	public function return404() {
		status_header( 404 );
		$GLOBALS['wp_query']->set_404();
		include( TEMPLATEPATH . '/404.php' );
		exit;
	}

	public function return500( $message = NULL ) {
		status_header( 500 );
		if ( isset( $message ) ) {
			@header( 'Content-type: text/plain; charset=' . get_option( 'blog_charset' ) );
			print $message;
		}
		exit;
	}

	public function addActivationHook()
	{
		self::fireAPIEvent( 'contextlyPluginActivated' );
	}

	public static function fireAPIEvent( $type, $text = '' )
	{
		$api = ContextlyWpKit::getInstance()->newApi();

		try {
			$api->method( 'events', 'put' )
				->extraParams(
					array(
						'event_type' => 'email',
						'event_name' => $type,
						'site_path'  => site_url(),
						'event_message' => $text
					)
				)
				->get();
		} catch ( Exception $e ) {
		}
	}

	public function registerWidgets()
	{
		require_once ( "ContextlySiderailWidget.php" );
		require_once ( "ContextlySocialWidget.php" );

		register_widget( 'ContextlyWpSiderailWidget' );
		register_widget( 'ContextlyWpSocialWidget' );
	}

}
