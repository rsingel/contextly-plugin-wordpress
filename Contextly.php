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

    const PAGE_TYPE_POST = 'post';
    const PAGE_TYPE_PAGE = 'page';

    const WIDGET_SNIPPET_ID = 'linker_widget';
    const WIDGET_SNIPPET_CLASS = 'contextly-widget';
    const WIDGET_SNIPPET_META_BOX_TITLE = 'Contextly Related Links';

    const WIDGET_SIDEBAR_CLASS = 'contextly-sidebar-hidden';
    const WIDGET_SIDEBAR_PREFIX = 'contextly-';

    function __construct() {
        Contextly_Api::getInstance()->setOptions( $this->getAPIClientOptions() );
    }

    public function init() {
        if ( is_admin() ) {
            add_action( 'admin_enqueue_scripts', array( $this, 'initAdmin' ), 1 );
            add_action( 'save_post', array( $this, 'publishBoxControlSavePostHook' ) );
        } else {
            add_action( 'init', array( $this, 'initDefault' ), 1 );
            add_action('the_content', array( $this, 'addSnippetWidgetToContent' ) );
        }

        add_action( 'wp_enqueue_scripts', array( $this, 'loadScripts' ) );
	    add_action( 'wp_enqueue_scripts', array( $this, 'loadStyles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'loadScripts' ) );

        add_action( 'publish_post', array( $this, 'publishPost'), 10, 2 );
        add_action('contextly_linker_ajax_contextly_publish_post', array( $this, 'ajaxPublishPostCallback' ) );
        add_action('contextly_linker_ajax_nopriv_contextly_publish_post', array( $this, 'ajaxPublishPostCallback' ) );
    }

    private function isAdminEditPage() {
        global $pagenow;

        if ( ( $pagenow == "post.php" || $pagenow == "post-new.php" ) && is_admin() ) {
            return true;
        }
        return false;
    }

    public function checkWidgetDisplayType() {
        global $post;

        $contextly_settings = new ContextlySettings();
        $display_type = $contextly_settings->getWidgetDisplayType();

        if ( $display_type == $post->post_type ) {
            return true;
        } elseif ( $display_type == 'all' ) {
            return is_single() || is_page() || $this->isAdminEditPage();
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

        return array(
            'post_id'       => $post->ID,
            'post_date'     => $post->post_date,
            'post_modified' => $post->post_modified,
            'author'        => $post->post_author,
            'type'          => $post->post_type,
        );
    }

    private function getAuthorName( $post ) {
        // As last step we can check WP user
        $display_name = get_the_author_meta( "display_name", $post->post_author );
        $user_name = get_the_author_meta( "last_name", $post->post_author ) . ' ' . get_the_author_meta( "first_name", $post->post_author );

        return $display_name ? $display_name : $user_name;
    }

    private function getSettingsOptions() {
        $contextly_settings = new ContextlySettings();
        return $contextly_settings->getOptions();
    }

    public function initAdmin() {
        $this->addAdminMetaboxForPage( self::PAGE_TYPE_PAGE );
        $this->addAdminMetaboxForPage( self::PAGE_TYPE_POST );

        if ( $this->checkWidgetDisplayType() ) {
            $contextly_settings = new ContextlySettings();

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

        $contextly_settings = new ContextlySettings();
        $contextly_settings->changePageDisplay( $post_id, $_POST['contextly_display_widgets'] );

        return true;
    }

    public function initDefault() {
        add_shortcode('contextly_sidebar', array( $this, 'prepareSidebar' ) );
    }

    public function wpautop( $content ) {
        return wpautop( $content, false );
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
            'side',
            'low'
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
        $plugin_array['contextly'] = plugins_url('js/contextly_linker_button.js?v=' . CONTEXTLY_PLUGIN_VERSION , __FILE__ );

        return $plugin_array;
    }

    public function getAdditionalShowHideControl() {
        global $post;

        $html = '';
        if ( isset( $post ) && $post->ID ) {
            $contextly_settings = new ContextlySettings();
            $flag = $contextly_settings->isPageDisplayDisabled( $post->ID );

            $html .= '<div style="border-top: 1px solid #DFDFDF; margin-top: 8px; padding-top: 8px;"><span id="timestamp">';
            $html .= '<label>Do not display Contextly widgets: ';
            $html .= "<input type='checkbox' name='contextly_display_widgets' " . ( $flag == 'on' ? "checked='checked'" : "" ) . " onchange=\"jQuery('#post').submit();\" /></label>";
            $html .= '</span></div>';
        }

        return $html;
    }

    public function getSnippetWidget() {
        global $post;

        $default_html_code = '';
        $additional_admin_controls = '';

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
                    $default_html_code = 'Contextly content is turned off for this page. You can change this in <a href="admin.php?page=contextly_options&tab=contextly_options_advanced">Contextly settings page</a> in Wordpress, under advanced.';
                }
            }

            if ( $display_global_settings ) {
                $additional_admin_controls = $this->getAdditionalShowHideControl();
            }

        }

        return "<div id='" . self::WIDGET_SNIPPET_ID . "' class='" . self::WIDGET_SNIPPET_CLASS . "'>" . $default_html_code . "</div>" . $additional_admin_controls;
    }

	function getPluginJs( $script_name ) {
		if ( CONTEXTLY_MODE == 'production' ) {
			return Urls::getPluginJsCdnUrl( $script_name );
		} else {
		    return plugins_url( 'js/' . $script_name , __FILE__ );
        }
	}

    function loadScripts() {
        global $post;

        $contextly_settings = new ContextlySettings();

        if ( !$this->checkWidgetDisplayType() || $contextly_settings->isPageDisplayDisabled( $post->ID ) ) return;

        if ( is_page() || is_single() || $this->isAdminEditPage() )
        {
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'json2' );
            wp_enqueue_script( 'easy_xdm', Urls::getMainJsCdnUrl( 'easyXDM.min.js' ), 'jquery', CONTEXTLY_PLUGIN_VERSION );
            wp_enqueue_script( 'pretty_photo', $this->getPluginJs( 'jquery.prettyPhoto.js' ), 'jquery', CONTEXTLY_PLUGIN_VERSION );
            wp_enqueue_script( 'contextly-create-class', plugins_url( 'js/contextly-class.js' , __FILE__ ), 'easy_xdm', CONTEXTLY_PLUGIN_VERSION );
            wp_enqueue_script( 'contextly', $this->getPluginJs( 'contextly-wordpress.js' ), 'contextly-create-class', CONTEXTLY_PLUGIN_VERSION, false );

            $ajax_url = plugins_url( 'ajax.php' , __FILE__ );
            $home_url = home_url( '/' );

            $ajax_url_parts = parse_url( $ajax_url );
            $home_url_parts = parse_url( $home_url );

            // Fix in case if we have different url for site address
            if ( !is_admin() ) {
                if ( $ajax_url_parts[ 'host' ] != $home_url_parts[ 'host' ] ) {
                    $ajax_url = rtrim( $home_url, '/' ) . $ajax_url_parts[ 'path' ];
                }
            }

            $api_options = $this->getAPIClientOptions();

            $data = array(
                'ajax_url'      => $ajax_url,
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

            wp_localize_script(
                'easy_xdm',
                'Contextly',
                array( 'l10n_print_after' => 'Contextly = ' . json_encode( $data ) . ';' )
            );
        }
    }

	function loadStyles() {
		wp_register_style( 'pretty-photo-style', plugins_url( 'css/prettyPhoto/style.css', __FILE__ ), '', CONTEXTLY_PLUGIN_VERSION );
		wp_enqueue_style( 'pretty-photo-style' );
	}

    // Publish post action
    function publishPost($post_ID, $post) {
        try {
            if ( $post_ID && $post->post_status == "publish" && in_array( $post->post_type, array( self::PAGE_TYPE_PAGE, self::PAGE_TYPE_POST ) ) ) {

                $client_options = $this->getAPIClientOptions();

                Contextly_Api::getInstance()->setOptions( $client_options );

                // Check if we have this post in our db
                $contextly_post = Contextly_Api::getInstance()
                    ->api( 'posts', 'get' )
                    ->param( 'page_id', $post->ID )
                    ->get();

                $post_data = array(
                    'post_id'       => $post->ID,
                    'post_title'    => $post->post_title,
                    'post_date'     => $post->post_date,
                    'post_modified' => $post->post_modified,
                    'post_status'   => $post->post_status,
                    'post_type'     => self::PAGE_TYPE_POST,
                    'post_content'  => $post->post_content,
                    'url'           => get_permalink( $post->ID ),
                    'author_id'     => $post->post_author,
                    'post_author'   => $this->getAuthorName( $post )
                );

                // Lets publish this post in our DB
                $publish_post = Contextly_Api::getInstance()
                    ->api( 'posts', 'put' )
                    ->extraParams( $post_data );

                if ( isset( $contextly_post->entry ) ) {
                    $publish_post->param( 'id', $contextly_post->entry->id );
                }
                $response = $publish_post->get();

                // Check if all fine and post was really updated
                if ( !isset( $response->error ) && $response->affected ) {
                    // Lets try to update some post related stuff
                    $this->updatePostImages( $post );
                    $this->updatePostTags( $post );
                }

                return $response;
            }
        }
        catch ( Exception $e )
        {
            return $e;
        }

        return null;
    }

    function ajaxPublishPostCallback() {
        $page_id = $_REQUEST[ 'page_id' ];

        $post = get_post( $page_id );

        if ( $post ) {
            $contextly = new Contextly();
            $result = $contextly->publishPost( $page_id, $post );

            echo json_encode( $result );
        }
        exit;
    }

    function updatePostTags( $post )
    {
        $post_id = $post->ID;

        $client_options = $this->getAPIClientOptions();
        Contextly_Api::getInstance()->setOptions( $client_options );

        // First of all we need to get list of all page tags and remove
        $post_tags = Contextly_Api::getInstance()
            ->api( 'poststags', 'list' )
            ->searchParam( 'post_id', Contextly_Api::SEARCH_TYPE_EQUAL, $post_id )
            ->get();

        if ( isset( $post_tags->list ) && is_array( $post_tags->list ) ) {
            foreach ( $post_tags->list as $tag ) {
                Contextly_Api::getInstance()
                    ->api( 'poststags', 'delete' )
                    ->param( 'id', $tag->id )
                    ->get();
            }
        }

        // Save new post tags
        $post_tags = get_the_tags( $post_id );
        if (is_array($post_tags) && count($post_tags) > 0) {
            foreach (array_slice($post_tags, 0, 3) as $post_tag) {
                $tag_data = array(
                    'post_id'   => $post_id,
                    'name'      => $post_tag->name
                );

                Contextly_Api::getInstance()
                    ->api( 'poststags', 'put' )
                    ->extraParams( $tag_data )
                    ->get();
            }
        }
    }

    function updatePostImages( $post )
    {
        $post_id = $post->ID;

        $client_options = $this->getAPIClientOptions();
        Contextly_Api::getInstance()->setOptions( $client_options );

        // Try to get attached images
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

                $image_data = array(
                    'image_url' => $src,
                    'page_id'   => $post_id
                );

                // Lets post save images in contextly
                Contextly_Api::getInstance()
                    ->api( 'images', 'put' )
                    ->extraParams( $image_data )
                    ->get();
            }
        }
    }

    // In this method we will display hidden div. After page loading we will load it's content with javascript.
    // This will help to load page about loosing performance.
    function prepareSidebar( $attrs ) {
        // We will display sidebar only if we have id for this sidebar
        if ( isset( $attrs[ 'id' ] ) ) {
            return "<div class='" . self::WIDGET_SIDEBAR_CLASS . "' id='" . self::WIDGET_SIDEBAR_PREFIX . $attrs[ 'id' ] ."'></div>";
        }
        else {
            return '';
        }
    }

}
