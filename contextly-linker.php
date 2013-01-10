<?php

/*
Plugin Name: Contextly
Plugin URI: http://contextly.com
Description: Adds the Contextly related links tool to your blog. Contextly lets you create related links that helps your readers find more to read, increases your page views and shows off your best content.
Author: Contextly
Version: 1.0.72
*/

define ( "CONTEXTLY_PLUGIN_VERSION", '1.0.72' );
define ( "CONTEXTLY_MODE", 'production' );

if ( CONTEXTLY_MODE == 'production' )
{
    define ( "CONTEXTLY_MAIN_SERVER_URL",   "http://contextly.com/" );
    define ( "CONTEXTLY_API_SERVER_URL",    "http://rest.contextly.com/" );
    define ( "CONTEXTLY_POPUP_SERVER_URL",  "http://app.contextly.com/" );
}
elseif ( CONTEXTLY_MODE == 'dev' )
{
    define ( "CONTEXTLY_MAIN_SERVER_URL",   "http://dev.contextly.com/" );
    define ( "CONTEXTLY_API_SERVER_URL",    "http://devrest.contextly.com/" );
    define ( "CONTEXTLY_POPUP_SERVER_URL",  "http://devapi.contextly.com/" );
}
else
{
    define ( "CONTEXTLY_MAIN_SERVER_URL",   "http://linker.site/" );
    define ( "CONTEXTLY_API_SERVER_URL",    "http://contextly-api.local/" );
    define ( "CONTEXTLY_POPUP_SERVER_URL",  "http://linker.local/" );
}

require_once ( "Api.php" );
require_once ( "Contextly.php" );

$ctxActivate = new Contextly();

function contextly_get_plugin_url() {
    return "http://contextlysiteimages.contextly.com/_plugin/" . CONTEXTLY_PLUGIN_VERSION . "/js/jquery-contextly-wordpress.js";
    //return plugins_url( 'js/jquery-contextly-wordpress.js' , __FILE__ );
}

function contextly_linker_widget_html( $admin = false ) {
    global $post;

    $default_html_code = '';

    if ($admin) {
        $default_html_code = "Loading data from <a target='_blank' href='http://contextly.com'>contextly.com</a>, please wait...";

        if ( !isset( $post ) || !$post->ID )
        {
            $default_html_code = "Please save a Draft first.";
        }
    }

    return "<div id='linker_widget'>" . $default_html_code . "</div>";
}

function contextly_linker_widget_html_print() {
    echo contextly_linker_widget_html(true);
}

// Display linker widget for a post page
function contextly_linker_widget($content) {
    if (is_single()) {
        return $content . contextly_linker_widget_html();
    } else {
        return $content;
    }
}
add_action('the_content', 'contextly_linker_widget');

function contextly_add_see_also_meta_box() {
    add_meta_box(
        'contextly_linker_sectionid',
        __( 'Create See Also', 'contextly_linker_textdomain' ),
        'contextly_linker_widget_html_print',
        'post',
        'side',
        'low'
    );

    global $ctxActivate;
    if (isset($ctxActivate)) {
        $ctxActivate->initSettings();
    }
}

//////////////////////////////////////////////////////////////////////////////////
//                    Post Editor Functions                                     //
//////////////////////////////////////////////////////////////////////////////////

function contextly_addbuttons() {
    // Don't bother doing this stuff if the current user lacks permissions
    if (! current_user_can('edit_posts') && ! current_user_can('edit_pages') ) return;

    // Add only in Rich Editor mode
    if ( get_user_option('rich_editing') == 'true') {
        add_filter("mce_external_plugins", "add_contextly_tinymce_plugin");
        add_filter('mce_buttons', 'register_contextly_button');
    }
}

function register_contextly_button($buttons) {
    $options = get_option('contextly_options_advanced');

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

// Load the TinyMCE plugin : editor_plugin.js (wp2.5)
function add_contextly_tinymce_plugin($plugin_array) {
    $plugin_array['contextlylink'] = plugins_url('js/contextly_linker_wplink.js' , __FILE__ );
    $plugin_array['contextlysidebar'] = plugins_url('js/contextly_linker_sidebar.js' , __FILE__ );
    $plugin_array['contextly'] = plugins_url('js/contextly_linker_button.js' , __FILE__ );

    return $plugin_array;
}

// Init process for button control
add_action('init', 'contextly_addbuttons');

//////////////////////////////////////////////////////////////////////////////////
//                            Sidebar Functions                                 //
//////////////////////////////////////////////////////////////////////////////////
add_shortcode('contextly_sidebar', array( &$ctxActivate, 'displaySidebar' ) );


add_action( 'admin_menu', array(&$ctxActivate,'addSettngsMenu') );
add_action( 'publish_post', array(&$ctxActivate, 'publishPostAction'), 10, 2 );
add_action( 'admin_init', 'contextly_add_see_also_meta_box', 1 );
add_action( 'wp_enqueue_scripts', array( &$ctxActivate, 'loadScripts' ) );
add_action( 'admin_enqueue_scripts', array( &$ctxActivate, 'loadScripts' ) );

//////////////////////////////////////////////////////////////////////////////////
//                                  AJAX Actions                                //
//////////////////////////////////////////////////////////////////////////////////

add_action('contextly_linker_ajax_contextly_load_page_data', 'contextly_load_page_data_callback');
add_action('contextly_linker_ajax_nopriv_contextly_load_page_data', 'contextly_load_page_data_callback');
add_action('contextly_linker_ajax_contextly_send_page_events', 'contextly_send_page_events_callback');
add_action('contextly_linker_ajax_nopriv_contextly_send_page_events', 'contextly_send_page_events_callback');
add_action('contextly_linker_ajax_contextly_publish_post', 'contextly_publish_post_callback');
add_action('contextly_linker_ajax_nopriv_contextly_publish_post', 'contextly_publish_post_callback');
add_action('contextly_linker_ajax_contextly_load_sidebar', 'contextly_load_sidebar_callback');
add_action('contextly_linker_ajax_nopriv_contextly_load_sidebar', 'contextly_load_sidebar_callback');
add_action('contextly_linker_ajax_contextly_remove_sidebar', 'contextly_remove_sidebar_callback');


function contextly_load_page_data_callback ()
{
    $page_id    = $_REQUEST[ 'page_id' ];
    $admin      = $_REQUEST[ 'admin' ];

    $data = array();
    $site = null;
    $snippet = null;

    $contextly = new Contextly();

    try
    {
        $settings = $contextly->getSnippetSettings();

        if ( $admin )
        {
            $data[ 'popup_server_url' ] = CONTEXTLY_POPUP_SERVER_URL;

            if ( isset( $settings->error_code ) )
            {
                throw new Exception( $settings->error, $settings->error_code );
            }
        }

        $data[ 'settings' ] = $settings;

        if ( $settings->entry )
        {
            $settings_id = $settings->entry->id;
            $data[ 'snippet' ] = $contextly->getSnippet( $page_id, $settings_id, $admin );
        }
    }
    catch ( Exception $e )
    {
	    $error = $e->getCode();
        $data = array();

        if ( isDebug() )
        {
            echo "Page Data Error: " . print_r( $e, true ) . "\r\n";
        }

        switch ( $error )
        {
            case 403:
                $help_url = "http://contextly.com/contact-us/";
                $message =  ( $e->getMessage() ? $e->getMessage() . '. ' : '' ) . "If this is an error, please contact us via <a href='http://contextly.com/contact-us/'>support@contextly.com</a>.";
                break;
            case 407:
                $help_url = CONTEXTLY_MAIN_SERVER_URL . "redirect/?type=home&blog_url=" . site_url() . "&blog_title=" . get_bloginfo("name");
                $message = "Your site isn't currently registered. Use this <a target='_blank' href='{$help_url}'>link</a> to register.";
                break;
            case 408:
                $help_url = "http://contextly.com/contact-us/";
                $message = "Your account has been suspended. If this is an error, please contact us via <a href='http://contextly.com/contact-us/'>support@contextly.com</a>.";
                break;
            default:
                $help_url = "admin.php?page=contextly_options&tab=contextly_options_api";
                $message = "Please check your API setting on Contextly plugin <a href='admin.php?page=contextly_options&tab=contextly_options_api'>Settings</a> page.";
        }

        $data[ 'error' ] = $error;
        $data[ 'message' ] = $message;
        $data[ 'help_url' ] = $help_url;
    }

    if ( isDebug() )
    {
        echo "Page Data Response: " . print_r( $data, true ) . "\r\n";
    }

    echo json_encode( $data );
    exit;
}

function contextly_send_page_events_callback()
{
    $page_id = $_REQUEST[ 'page_id' ];
    $setting_id = $_REQUEST[ 'setting_id' ];
    $events = $_REQUEST[ 'events' ];

    $contextly = new Contextly();
    $client_options = $contextly->getAPIClientOptions();

    Contextly_Api::getInstance()->setOptions( $client_options );

    if ( is_array( $events ) )
    {
        foreach ( $events as $event )
        {
            $event_data = array(
                'post_id'       => $page_id,
                'setting_id'    => $setting_id,
                'event_name'    => $event[ 'name' ],
                'event_key'     => $event[ 'key' ],
                'event_date'    => date( "Y-m-d H:i:s", $event[ 'time' ] )
            );

            Contextly_Api::getInstance()
                ->api( 'siteevents', 'put' )
                ->extraParams( $event_data )
                ->get();
        }
    }

    exit;
}

function contextly_publish_post_callback()
{
    $page_id = $_REQUEST[ 'page_id' ];

    $post = get_post( $page_id );

    if ( $post )
    {
        $contextly = new Contextly();
        $result = $contextly->publishPostAction( $page_id, $post );

        echo json_encode( $result );
    }

    exit;
}

function contextly_load_sidebar_callback()
{
    $sidebar_id = $_REQUEST[ 'sidebar_id' ];

    $data = null;
    $contextly = new Contextly();

    try
    {
        $sidebar_settings = $contextly->getSidebarSettings();
        if ( $sidebar_settings )
        {
            $settings_id = $sidebar_settings->entry->id;
            $sidebar = $contextly->getSidebar( $sidebar_id, $settings_id );

            $data = array(
                'sidebar' => $sidebar,
                'settings' => $sidebar_settings
            );
        }
    }
    catch ( Exception $e )
    {
        $data = array(
            'error'     => $e->getCode(),
            'message'   => $e->getMessage()
        );
    }

    if ( isDebug() )
    {
        echo "Sidebar Response: " . print_r( $data, true ) . "\r\n";
    }

    echo json_encode( $data );
    exit;
}

function contextly_remove_sidebar_callback()
{
    $sidebar_id = $_REQUEST[ 'sidebar_id' ];
    delete_transient( 'sidebar-' . $sidebar_id );
    exit;
}

function isDebug()
{
    return isset( $_REQUEST[ 'debug' ] ) && $_REQUEST[ 'debug' ] == 1;
}
