<?php
/*
Plugin Name: Contextly
Plugin URI: http://contextly.com
Description: Adds the Contextly related links tool to your blog. Contextly lets you create related links that helps your readers find more to read, increases your page views and shows off your best content.
Author: Contextly
Version: 1.0.85
*/

define ( "CONTEXTLY_PLUGIN_VERSION", '1.0.85' );
define ( "CONTEXTLY_MODE", 'local' );
define ( "CONTEXTLY_HTTPS", isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' );

require_once ( "Api.php" );
require_once ( "Urls.php" );
require_once ( "Contextly.php" );
require_once ( "ContextlySettings.php" );

if ( is_admin() ) {
    // Init Contextly WP settings
    $contextly_settings = new ContextlySettings();
    $contextly_settings->init();
}

// Init Contextly
$contextly = new Contextly();
$contextly->init();
