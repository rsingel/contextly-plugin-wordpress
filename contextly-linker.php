<?php
/*
Plugin Name: Contextly
Plugin URI: http://contextly.com
Description: Adds the Contextly related links tool to your blog. Contextly lets you create related links that helps your readers find more to read, increases your page views and shows off your best content.
Author: Contextly
Version: 1.0.76
*/

define ( "CONTEXTLY_PLUGIN_VERSION", '1.0.76' );
define ( "CONTEXTLY_MODE", 'local' );

require_once ( "Api.php" );
require_once ( "Contextly.php" );
require_once ( "ContextlySettings.php" );

if ( CONTEXTLY_MODE == 'production' ) {
    define ( "CONTEXTLY_MAIN_SERVER_URL",   "http://contextly.com/" );
    define ( "CONTEXTLY_API_SERVER_URL",    "http://rest.contextly.com/" );
    define ( "CONTEXTLY_POPUP_SERVER_URL",  "http://app.contextly.com/" );
} elseif ( CONTEXTLY_MODE == 'dev' ) {
    define ( "CONTEXTLY_MAIN_SERVER_URL",   "http://dev.contextly.com/" );
    define ( "CONTEXTLY_API_SERVER_URL",    "http://devrest.contextly.com/" );
    define ( "CONTEXTLY_POPUP_SERVER_URL",  "http://devapi.contextly.com/" );
} else {
    define ( "CONTEXTLY_MAIN_SERVER_URL",   "http://linker.site/" );
    define ( "CONTEXTLY_API_SERVER_URL",    "http://contextly-api.local/" );
    define ( "CONTEXTLY_POPUP_SERVER_URL",  "http://linker.local/" );
}

function contextly_get_plugin_url() {
    if ( CONTEXTLY_MODE == 'production' ) {
        return "http://contextlysiteimages.contextly.com/_plugin/" . CONTEXTLY_PLUGIN_VERSION . "/js/contextly-wordpress.js";
    }

    return plugins_url( 'js/contextly-wordpress.js' , __FILE__ );
}

if ( is_admin() ) {
    // Init Contextly WP settings
    $contextly_settings = new ContextlySettings();
    $contextly_settings->init();
}

// Init Contextly
$contextly = new Contextly();
$contextly->init();
