<?php
/**
 * Urls class.
 *
 * @package Contextly Related Links
 * @link https://contextly.com
 */

/**
 * Class Urls
 */
class Urls {

	const MODE_LOCAL = 'local';
	const MODE_DEV   = 'dev';
	const MODE_LIVE  = 'live';

	/**
	 * Get Main server url from config file
	 *
	 * @return null|string
	 * @throws ContextlyKitException In case if server identifier is not exists in config.
	 */
	public static function get_main_server_url() {
		return ContextlyWpKit::getInstance()->getServerUrl( 'cp' );
	}

	/**
	 * Get WP plugin assets url from config
	 *
	 * @param string $file file name.
	 * @param string $type file type.
	 * @return string asset URL in Contextly CDN
	 */
	public static function get_plugin_cdn_url( $file, $type = 'js' ) {
		return 'https://assets.context.ly/wp-plugin/' . CONTEXTLY_PLUGIN_VERSION . '/' . $type . '/' . $file;
	}

}
