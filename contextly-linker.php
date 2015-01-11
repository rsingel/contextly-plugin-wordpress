<?php
/*
Plugin Name: Contextly
Plugin URI: http://contextly.com
Description: Adds the Contextly content recommendation tool to your site. Contextly provides related, trending, evergreen and personalized recommendations to help your readers keep reading. Includes text, video and product recommendations to show off your best content.
Author: Contextly
Version: 1.4
*/

define ( "CONTEXTLY_PLUGIN_VERSION", '1.4' );
define ( "CONTEXTLY_MODE", 'live' );
define ( "CONTEXTLY_HTTPS", is_ssl() );
define ( "CONTEXTLY_PLUGIN_FILE", __FILE__ );

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
