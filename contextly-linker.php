<?php
/*
Plugin Name: Contextly
Plugin URI: http://contextly.com
Description: Adds the Contextly content recommendation tool to your site. Contextly provides related, trending, evergreen and personalized recommendations to help your readers keep reading. Includes text, video and product recommendations to show off your best content.
Author: Contextly
Version: 3.2
*/

$is_https = is_ssl();
if (!$is_https && strpos(home_url(), 'https://') !== false) {
    $is_https = true;
}

define ( "CONTEXTLY_MODE", 'live' );
define ( "CONTEXTLY_HTTPS", $is_https );
define ( "CONTEXTLY_PLUGIN_FILE", __FILE__ );
define ( "CONTEXTLY_PLUGIN_VERSION", '3.2' );
define ( "CONTEXTLY_CDN_VERSION", 'branch' );

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
