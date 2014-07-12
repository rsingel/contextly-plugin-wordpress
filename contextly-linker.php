<?php
/*
Plugin Name: Contextly
Plugin URI: http://contextly.com
Description: Adds the Contextly related links tool to your blog. Contextly lets you create related links that helps your readers find more to read, increases your page views and shows off your best content.
Author: Contextly
Version: 1.2.2
*/

define ( "CONTEXTLY_PLUGIN_VERSION", '1.2.2' );
define ( "CONTEXTLY_MODE", 'live' );
define ( "CONTEXTLY_HTTPS", is_ssl() );

require_once ( "kit/server/Kit.php" );
ContextlyKit::registerAutoload();

require_once ( "Kit.php" );
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
