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

    const WIDGET_SNIPPET_ID = 'ctx-module';
    const WIDGET_SNIPPET_CLASS = 'ctx-module-container ctx-clearfix';
    const WIDGET_SNIPPET_META_BOX_TITLE = 'Contextly Related Links';
    const WIDGET_SOCIALER_META_BOX_TITLE = 'Contextly Socialer';

    const WIDGET_SIDEBAR_CLASS = 'ctx-sidebar-container';
    const WIDGET_SIDEBAR_PREFIX = 'contextly-';
	const WIDGET_AUTO_SIDEBAR_CODE = '[contextly_auto_sidebar id="%HASH%"]';

	const MAIN_MODULE_SHORT_CODE = 'contextly_main_module';
	const MAIN_MODULE_SHORT_CODE_CLASS = 'ctx_widget_hidden';
	const MAIN_MODULE_SHORT_CODE_ID = 'ctx_main_module_short_code';

	/**
	 * @var ContextlyKitApi
	 */
	protected $api;

	protected function api() {
		if (!isset($this->api)) {
			$this->api = ContextlyWpKit::getInstance()->newApi();
		}

		return $this->api;
	}

    public function init() {
        if ( is_admin() ) {
            add_action( 'admin_enqueue_scripts', array( $this, 'initAdmin' ), 1 );
            add_action( 'save_post', array( $this, 'publishBoxControlSavePostHook' ) );
	        add_filter( 'default_content', array( $this, 'addAutosidebarCodeFilter' ), 10, 2 );
			add_action( 'admin_head', array( $this, 'insertMetatags' ) );
			add_action( 'admin_footer', array( $this, 'addQuicktagsEditorIntegration' ) );

			// Register overlay dialog page.
			ContextlyWpKit::getInstance()
					->newWpOverlayPage()
					->addMenuAction();
        } else {
            add_action( 'init', array( $this, 'initDefault' ), 1 );
            add_action( 'the_content', array( $this, 'addSnippetWidgetToContent' ) );
			add_action( 'wp_head', array( $this, 'insertMetatags' ) );
        }

        add_action( 'wp_enqueue_scripts', array( $this, 'loadScripts' ) );
	    add_action( 'wp_enqueue_scripts', array( $this, 'loadStyles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'loadScripts' ) );

        add_action( 'publish_post', array( $this, 'publishPost'), 10, 2 );
        add_action( 'save_post', array( $this, 'publishPost'), 10, 2 );

	    $this->attachAjaxActions();
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

    public function getAPIClientOptions() {
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

    private function getSettingsOptions() {
        $contextly_settings = new ContextlySettings();
        return $contextly_settings->getPluginOptions();
    }

    public function initAdmin() {
        if ( $this->checkWidgetDisplayType() ) {
	        $contextly_settings = new ContextlySettings();
	        $display_types = $contextly_settings->getWidgetDisplayType();

	        foreach ( $display_types as $display_type ) {
		        $this->addAdminMetaboxForPage( $display_type );
				$this->addAdminPublishMetaboxForPage( $display_type );
	        }

            global $post;
            if ( !$contextly_settings->isPageDisplayDisabled( $post->ID ) ) {
                $this->addEditorButtons();
            }
        }
    }

    public function publishBoxControlSavePostHook( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return false;
        if ( !current_user_can( 'edit_page', $post_id ) ) return false;
        if ( empty( $post_id ) ) return false;

	    $display_widget_flag = null;
	    if ( isset( $_POST['contextly_display_widgets'] ) ) {
		    $display_widget_flag = $_POST['contextly_display_widgets'];
	    }

	    $contextly_settings = new ContextlySettings();
	    $contextly_settings->changePageDisplay( $post_id, $display_widget_flag );

        return true;
    }

    private function addAdminPublishMetaboxForPage() {
	    add_action( 'post_submitbox_misc_actions', array( $this, 'echoAdminPublishMetaboxForPage' ) );
    }

    public function echoAdminPublishMetaboxForPage() {
	    echo '<div class="misc-pub-section misc-pub-section-last" style="border-top: 1px solid #eee; margin-bottom: 5px;">';
	    echo 'Contextly: <input type="button" value="Loading..." class="button action button-primary ctx_snippets_editor_btn" disabled="disabled" style="float: right;"/>';
	    echo '</div>';
    }

    public function initDefault() {
        add_shortcode(self::MAIN_MODULE_SHORT_CODE, array( $this, 'prepareMainModule' ) );
        add_shortcode('contextly_sidebar', array( $this, 'prepareSidebar' ) );
        add_shortcode('contextly_auto_sidebar', array( $this, 'prepareAutoSidebar' ) );
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

	private function addKitAssets( $packages, $ignore = array() ) {
		$kit = ContextlyWpKit::getInstance();
		$assets = $kit->newAssetsList();
		$manager = $kit->newAssetsManager();

		$packages = (array) $packages;
		foreach ( $packages as $package ) {
			$manager->extractPackageAssets( $package, $assets, $ignore );
		}

		$kit->newWpAssetsRenderer( $assets )
				->renderAll();
	}

	private function addPostEditor() {
		wp_enqueue_script( 'contextly-post-editor', $this->getPluginJs( 'contextly-post-editor.js' ), 'contextly', null, true );
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

	public function addQuicktagsEditorIntegration()
	{
		if ( $this->checkWidgetDisplayType() ) {

			global $post;
			$contextly_settings = new ContextlySettings();
			if ( !$contextly_settings->isPageDisplayDisabled( $post->ID ) )
			{
				wp_enqueue_script( 'contextly-quicktags', $this->getPluginJs( 'contextly-quicktags.js' ), 'contextly', null, true );
			}
		}
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
							$html .= '<input type="button" class="button action ctx_snippets_editor_btn" value="Loading..." disabled="disabled" />';
						}

            $html .= '<div style="border-top: 1px solid #DFDFDF; margin-top: 8px; padding-top: 8px;"><span id="timestamp">';
            $html .= '<label>Don\'t display Contextly content on this ' . $post->post_type . ': ';
            $html .= "<input type='checkbox' name='contextly_display_widgets' " . ( $flag ? "checked='checked'" : "" ) . " onchange=\"jQuery('#post').submit();\" /></label>";
            $html .= '</span></div>';

					// Wrap with div, so post editor could render button here.
					$html = '<div class="ctx_preview_admin_controls">' . $html . '</div>';
        }

        return $html;
    }

    public function getSnippetWidget() {
        global $post;

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
		    if ( $this->isLoadWidget() )
		    {
			    $api_options = $this->getAPIClientOptions();
				if ( isset( $api_options[ 'appID' ] ) && $api_options[ 'appID' ] && isset( $post ) && $post->ID )
				{
					$additional_html_controls = sprintf( '<a href="%s" style="display: none;">Related</a>',	esc_url( Urls::getApiServerSeoHtmlUrl( $api_options[ 'appID' ], $post->ID ) ) );
				}
		    }
	    }

        return "<div id='" . esc_attr( self::WIDGET_SNIPPET_ID ) . "' class='" . esc_attr( self::WIDGET_SNIPPET_CLASS ) . "'>" . $default_html_code . "</div>" . $additional_html_controls;
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

	public function loadContextlyAjaxJSScripts() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'json2' );

		$include = array(
			'libraries/jquery-cookie',
			'widgets/factory',
		);
		$ignore = array(
			'libraries/jquery' => TRUE,
			'libraries/json2' => TRUE,
		);
		$this->addKitAssets( $include, $ignore );

		wp_enqueue_script( 'contextly', $this->getPluginJs( 'contextly-wordpress.js' ), 'jquery', null, true );
	}

	private function getAjaxUrl() {
		return admin_url( 'admin-ajax.php' );
	}

	private function getOverlayEditorUrl() {
		return admin_url( 'admin.php?page=contextly_overlay_dialog&noheader' );
	}

	public function makeContextlyJSObject( $additional_options = array() ) {
		global $post;

		$api_options = $this->getAPIClientOptions();

		$options = array(
			'ajax_url'      => $this->getAjaxUrl(),
			'api_server'    => Urls::getApiServerUrl(),
			'main_server'   => Urls::getMainServerUrl(),
			'editor_url'    => $this->getOverlayEditorUrl(),
			'app_id'        => $api_options[ 'appID' ],
			'settings'      => $this->getSettingsOptions(),
			'admin'         => (boolean)is_admin(),
			'mode'          => CONTEXTLY_MODE,
			'https'         => CONTEXTLY_HTTPS,
			'version'       => CONTEXTLY_PLUGIN_VERSION
		);

		if ( isset( $post ) && isset( $post->ID ) ) {
			$options[ 'ajax_nonce' ] = wp_create_nonce( "contextly-post-{$post->ID}" );
		}

		if ( is_array( $additional_options ) ) {
			$options = $additional_options + $options;
		}

		wp_localize_script(
			'json2',
			'Contextly',
			array( 'l10n_print_after' => 'Contextly = ' . json_encode( $options ) . ';' )
		);
	}

	private function isLoadWidget()
	{
		global $post;

		$contextly_settings = new ContextlySettings();
		if ( $this->checkWidgetDisplayType() && !$contextly_settings->isPageDisplayDisabled( $post->ID ) )
		{
			return is_page() || is_single() || $this->isAdminEditPage();
		}
		else
		{
			return false;
		}
	}

	public function loadScripts() {
        if ( $this->isLoadWidget() ) {
	        $this->loadContextlyAjaxJSScripts();
		    $this->makeContextlyJSObject();

	        if ( $this->isAdminEditPage() ) {
				$this->addOverlayLibrary();
				$this->addPostEditor();
		        $this->addQuicktagsEditorIntegration();
	        }
        }
    }

	protected function addOverlayLibrary() {
		$this->addKitAssets( 'components/overlay' );
	}

	public function loadStyles() {
		if ( $this->isLoadWidget() )
		{
			wp_register_style( 'video-modal', $this->getPluginCss( 'video-modal/reveal.css' ) );
			wp_enqueue_style( 'video-modal' );
			wp_register_style( 'contextly-branding', $this->getPluginCss( 'branding/branding.css' ) );
			wp_enqueue_style( 'contextly-branding' );
		}
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
				list($src) = wp_get_attachment_image_src($image->ID, 'full');
				$images_array[] = $src;
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
		if (has_post_thumbnail( $post_id ) )
		{
			list($url) = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'single-post-thumbnail' );

			if ( $url )
			{
				return $url;
			}
		}
		else
		{
			$post_images = $this->getPostImages( $post_id );

			if ( count( $post_images ) > 0 )
			{
				$sorted_images = array();
				foreach ( $post_images as $image )
				{
					list($url, $width, $height) = wp_get_attachment_image_src( $image->ID, 'full' );

					$image_rank = $width + $height;

					if ( !isset( $sorted_images[$image_rank] ) )
					{
						$sorted_images[$image_rank] = array($url);
					}
					else
					{
						$sorted_images[$image_rank][] = $url;
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
            return "<div class='" . esc_attr( self::WIDGET_SIDEBAR_CLASS ) . "' id='" . esc_attr( self::WIDGET_SIDEBAR_PREFIX . $attrs[ 'id' ] ) ."'></div>";
        }
        else {
            return '';
        }
    }

	public function ajaxGetAuthTokenCallback() {
		try {
			$this->api()->testCredentials();

			$data = array(
				'success' => 1,
				'contextly_access_token' => (string) $this->api()
						->getAccessToken()
			);
		} catch ( Exception $e ) {
			$data = array(
				'success' => 0,
				'code' => $e->getCode(),
				'message' => $e->getMessage()
			);
		}

		echo json_encode( $data );
		exit;
	}

	/**
	 * @param $content
	 * @param $post
	 * @return mixed
	 */
	public function addAutosidebarCodeFilter( $content, $post ) {
		if ( $this->checkWidgetDisplayType( $post ) ) {
			$hash = $this->getNewAutoSidebarHashForPost( $post->ID );

			if ( null !== $hash ) {
				$content = $this->getAutoSidebarCodeForPost( $hash ) . $content;
			}
		}

		return $content;
	}

	/**
	 * @param $post_id
	 * @return null|string
	 */
	private function getNewAutoSidebarHashForPost( $post_id ) {
		try {
			$response = $publish_post = $this->api()
				->method( 'autosidebars', 'put' )
				->extraParams(
					array(
						'custom_id' => $post_id,
						'editor'    => true
					)
				)->get();

			if ( isset( $response->success ) && isset( $response->id ) ) {
				return $response->id;
			}
		} catch ( Exception $e ) {
		}

		return null;
	}

	/**
	 * @param $hash
	 * @return string
	 */
	private function getAutoSidebarCodeForPost( $hash ) {
		$code = self::WIDGET_AUTO_SIDEBAR_CODE;
		$code = str_replace( '%HASH%', $hash, $code );

		return $code;
	}

	/**
	 * @param $attrs
	 * @return string
	 */
	public function prepareAutoSidebar( $attrs ) {
		if ( isset( $attrs[ 'id' ] ) ) {
			return "<div class='" . self::WIDGET_SIDEBAR_CLASS . "' id='" . self::WIDGET_SIDEBAR_PREFIX . $attrs[ 'id' ] ."' sidebar-type='auto'></div>";
		}
		else {
			return '';
		}
	}

	/**
	 * @return string
	 */
	public function prepareMainModule() {
		return sprintf( "<div class='%s' id='%s'></div>", esc_attr( self::MAIN_MODULE_SHORT_CODE_CLASS ), esc_attr( self::MAIN_MODULE_SHORT_CODE_ID ) );
	}

	/**
	 *
	 */
	public function insertMetatags()
	{
		if ( $this->isLoadWidget() )
		{
			global $post;
			$json_data = null;

			if ( isset( $post ) )
			{
				$json_data = array(
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

			if ( $json_data !== null )
			{
				?>
<meta name='contextly-page' id='contextly-page' content='<?php echo json_encode( $json_data ); ?>' />
<?php
			}
		}
	}

	/**
	 * @param $text
	 * @return string
	 */
	private function escape($text)
	{
		return htmlspecialchars($text, ENT_QUOTES & ~ENT_COMPAT, 'utf-8');
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

}
