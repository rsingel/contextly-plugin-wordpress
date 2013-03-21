<?php

define('DOING_AJAX', true);

if ( !isset( $_POST[ 'action' ] ) && !isset( $_GET[ 'debug' ] ) ) die('-1');

require_once('../../../wp-load.php');

//Typical headers
header('Content-Type: text/html');
send_nosniff_header();

//Disable caching
header('Cache-Control: no-cache');
header('Pragma: no-cache');

$action = esc_attr( $_REQUEST['action'] );

//A bit of security
$allowed_actions = array(
    'contextly_publish_post',
);

if( in_array($action, $allowed_actions) ) {
    if ( is_user_logged_in() ) {
        do_action( 'contextly_linker_ajax_' . $action );
    }
    else {
        do_action( 'contextly_linker_ajax_nopriv_' . $action );
    }
} else {
    die('-1');
}
