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

    const WIDGET_SNIPPET_ID = 'ctx_linker';
    const WIDGET_SNIPPET_CLASS = 'ctx_widget';
    const WIDGET_SNIPPET_META_BOX_TITLE = 'Contextly Related Links';
    const WIDGET_SOCIALER_META_BOX_TITLE = 'Contextly Socialer';

    const WIDGET_SIDEBAR_CLASS = 'ctx_widget_hidden';
    const WIDGET_SIDEBAR_PREFIX = 'contextly-';
	const WIDGET_AUTO_SIDEBAR_CODE = '[contextly_auto_sidebar id="%HASH%"]';

	const MAIN_MODULE_SHORT_CODE = 'contextly_main_module';
	const MAIN_MODULE_SHORT_CODE_CLASS = 'ctx_widget_hidden';
	const MAIN_MODULE_SHORT_CODE_ID = 'ctx_main_module_short_code';

    function __construct() {
        Contextly_Api::getInstance()->setOptions( $this->getAPIClientOptions() );
    }

    public function init() {
        if ( is_admin() ) {
            add_action( 'admin_enqueue_scripts', array( $this, 'initAdmin' ), 1 );
            add_action( 'save_post', array( $this, 'publishBoxControlSavePostHook' ) );
	        add_filter( 'default_content', array( $this, 'addAutosidebarCodeFilter' ), 10, 2 );
        } else {
            add_action( 'init', array( $this, 'initDefault' ), 1 );
	        add_action( 'the_content', array( $this, 'addSnippetWidgetToContent' ) );
        }

        add_action( 'wp_enqueue_scripts', array( $this, 'loadScripts' ) );
	    add_action( 'wp_enqueue_scripts', array( $this, 'loadStyles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'loadScripts' ) );

        add_action( 'publish_post', array( $this, 'publishPost'), 10, 2 );

	    $this->attachAjaxActions();
    }

	private function attachAjaxActions() {
		add_action('wp_ajax_nopriv_contextly_publish_post', array( $this, 'ajaxPublishPostCallback' ) );
		add_action('wp_ajax_contextly_publish_post', array( $this, 'ajaxPublishPostCallback' ) );
		add_action('wp_ajax_contextly_get_auth_token', array( $this, 'ajaxGetAuthTokenCallback' ) );
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
            'server-url'    => Urls::getApiServerUrl(),
            'auth-api'      => 'auth/auth',
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

    private function getPostData() {
        global $post;

	    if ( isset( $post ) && $post->ID ) {
	        return array(
	            'post_id'       => $post->ID,
	            'post_date'     => $post->post_date,
	            'post_modified' => $post->post_modified,
	            'author'        => $post->post_author,
	            'type'          => $post->post_type,
	        );
	    }

	    return null;
    }

    private function getAuthorFullName( $post ) {
	    if ( get_the_author_meta( "last_name", $post->post_author ) ) {
	        return get_the_author_meta( "last_name", $post->post_author ) . ' ' . get_the_author_meta( "first_name", $post->post_author );
	    }
        return null;
    }

	private function getAuthorDisplayName( $post ) {
		$display_name = get_the_author_meta( "display_name", $post->post_author );
		$nickname = get_the_author_meta( "nickname", $post->post_author );

		return $display_name ? $display_name : $nickname;
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
	    echo 'Contextly: <input type="button" value="Choose Related Posts" class="button action button-primary" onclick="Contextly.PopupHelper.getInstance().snippetPopup();" style="float: right;"/>';
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
        $plugin_array['contextlylink'] = plugins_url('js/contextly_linker_wplink.js?v=' . CONTEXTLY_PLUGIN_VERSION , __FILE__ );
        $plugin_array['contextlysidebar'] = plugins_url('js/contextly_linker_sidebar.js?v=' . CONTEXTLY_PLUGIN_VERSION , __FILE__ );

        return $plugin_array;
    }

    public function getAdditionalShowHideControl() {
        global $post;

        $html = '';
        if ( isset( $post ) && $post->ID ) {
            $contextly_settings = new ContextlySettings();
            $flag = $contextly_settings->isPageDisplayDisabled( $post->ID );

            $html .= '<div style="border-top: 1px solid #DFDFDF; margin-top: 8px; padding-top: 8px;"><span id="timestamp">';
            $html .= '<label>Don\'t display Contextly content on this ' . $post->post_type . ': ';
            $html .= "<input type='checkbox' name='contextly_display_widgets' " . ( $flag == 'on' ? "checked='checked'" : "" ) . " onchange=\"jQuery('#post').submit();\" /></label>";
            $html .= '</span></div>';
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
					$additional_html_controls = sprintf( '<a href="%s" style="display: none;">Related</a>',	Urls::getApiServerSeoHtmlUrl( $api_options[ 'appID' ], $post->ID ) );
				}
		    }
	    }

        return "<div id='" . self::WIDGET_SNIPPET_ID . "' class='" . self::WIDGET_SNIPPET_CLASS . "'>" . $default_html_code . "</div>" . $additional_html_controls;
    }

	public function getPluginJs( $script_name ) {
		if ( CONTEXTLY_MODE == 'production' ) {
			return Urls::getPluginJsCdnUrl( $script_name );
		} else {
		    return plugins_url( 'js/' . $script_name , __FILE__ );
        }
	}

	public function getPluginCss( $css_name ) {
		if ( CONTEXTLY_MODE == 'production' ) {
			return Urls::getPluginCssCdnUrl( $css_name );
		} else {
			return plugins_url( 'css/' . $css_name , __FILE__ );
		}
	}

	public function loadContextlyAjaxJSScripts() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'json2' );
		wp_enqueue_script( 'easy_xdm', Urls::getMainJsCdnUrl( 'easyXDM.min.js' ), 'jquery', null );
		wp_enqueue_script( 'pretty_photo', $this->getPluginJs( 'jquery.prettyPhoto.js' ), 'jquery', null );
		wp_enqueue_script( 'jquery_cookie', $this->getPluginJs( 'jquery.cookie.js' ), 'jquery', null );
		wp_enqueue_script( 'contextly-create-class', $this->getPluginJs( 'contextly-class.min.js' ), 'easy_xdm', null );
		wp_enqueue_script( 'contextly', $this->getPluginJs( 'contextly-wordpress.js' ), 'contextly-create-class', null );
	}

	private function getAjaxUrl() {
		return admin_url( 'admin-ajax.php' );
	}

	public function makeContextlyJSObject( $additional_options = array() ) {
		$api_options = $this->getAPIClientOptions();

		$options = array(
			'ajax_url'      => $this->getAjaxUrl(),
			'api_server'    => Urls::getApiServerUrl(),
			'main_server'   => Urls::getMainServerUrl(),
			'popup_server'  => Urls::getPopupServerUrl(),
			'app_id'        => $api_options[ 'appID' ],
			'settings'      => $this->getSettingsOptions(),
			'post'          => $this->getPostData(),
			'admin'         => (boolean)is_admin(),
			'mode'          => CONTEXTLY_MODE,
			'https'         => CONTEXTLY_HTTPS,
			'version'       => CONTEXTLY_PLUGIN_VERSION
		);

		if ( isset( $api_options[ 'appSecret' ] ) && $api_options[ 'appSecret' ] ) {
			$options[ 'ajax_nonce' ] = wp_create_nonce( $api_options[ 'appSecret' ] );
		}

		if ( is_array( $additional_options ) ) {
			$options = $additional_options + $options;
		}

		wp_localize_script(
			'easy_xdm',
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
	            add_thickbox();
	        }
        }
    }

	public function loadStyles() {
		if ( $this->isLoadWidget() )
		{
			wp_register_style( 'pretty-photo-style', $this->getPluginCss( 'prettyPhoto/style.css' ) );
			wp_enqueue_style( 'pretty-photo-style' );
			wp_register_style( 'contextly-branding', $this->getPluginCss( 'branding/branding.css' ) );
			wp_enqueue_style( 'contextly-branding' );
		}
	}

	public function ajaxPublishPostCallback() {
		$api_options = $this->getAPIClientOptions();
		check_ajax_referer( $api_options[ 'appSecret' ], 'contextly_nonce');

		$page_id = $_REQUEST[ 'page_id' ];
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
				$contextly_post = Contextly_Api::getInstance()
					->api( 'posts', 'get' )
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
				$publish_post = Contextly_Api::getInstance()
					->api( 'posts', 'put' )
					->extraParams( $post_data );

				if ( isset( $contextly_post->entry ) && $contextly_post->entry->id ) {
					$publish_post->param( 'id', $contextly_post->entry->id );
				}

				$publish_post->get();
			} catch ( Exception $e ) {
	            return $e;
            }
		}

		return true;
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
				$tags_array[] = $post_tag->name;
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
					$categories_array[] = $category->name;
				}
			}
		}

		return $categories_array;
	}

	/**
	 * @param $post_id
	 * @return array
	 */
	private function getPostImagesArray( $post_id ) {
		$images_array = array();

		$attachment_images = get_children(
			array(
				'post_parent' => $post_id,
				'post_type' => 'attachment',
				'numberposts' => 0,
				'post_mime_type' => 'image'
			)
		);

		if ($attachment_images && is_array($attachment_images)) {
			foreach($attachment_images as $image) {
				list($src) = wp_get_attachment_image_src($image->ID, 'full');
				$images_array[] = $src;
			}
		}

		return $images_array;
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
            return "<div class='" . self::WIDGET_SIDEBAR_CLASS . "' id='" . self::WIDGET_SIDEBAR_PREFIX . $attrs[ 'id' ] ."'></div>";
        }
        else {
            return '';
        }
    }

	public function ajaxGetAuthTokenCallback() {
		try {
			$data = array(
				'success' => 1,
				'contextly_access_token' => Contextly_Api::getInstance()->getAuthorizeToken()
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
			$response = $publish_post = Contextly_Api::getInstance()
				->api( 'autosidebars', 'put' )
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
		return sprintf( "<div class='%s' id='%s'></div>", self::MAIN_MODULE_SHORT_CODE_CLASS, self::MAIN_MODULE_SHORT_CODE_ID );
	}


}
