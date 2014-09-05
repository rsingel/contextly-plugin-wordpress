<?php
/**
 * User: Andrew Nikolaenko
 * Date: 3/21/13
 * Time: 1:59 PM
 */

class Urls {

	protected static $_instance;

	const MODE_LOCAL = 'local';
	const MODE_DEV = 'dev';
	const MODE_LIVE = 'live';

	static public function getMainServerUrl() {
		return ContextlyWpKit::getInstance()->getServerUrl('cp');
	}

	static public function getApiServerUrl() {
		return ContextlyWpKit::getInstance()->getServerUrl('api');
	}

	static public function getPluginCdnUrl( $file, $type = 'js' ) {
		if ( CONTEXTLY_HTTPS ) {
			$prefix = 'https://c714015.ssl.cf2.rackcdn.com/';
		} else {
			$prefix = 'http://contextlysitescripts.contextly.com/';
		}

		return $prefix . 'wp_plugin/' . CONTEXTLY_PLUGIN_VERSION . '/' . $type . '/' . $file;
	}

	static public function getMainJsCdnUrl( $js_file ) {
		if ( CONTEXTLY_HTTPS ) {
			$prefix = 'https://c714015.ssl.cf2.rackcdn.com/';
		} else {
			$prefix = 'http://contextlysitescripts.contextly.com/';
		}

		return $prefix . 'js/' . $js_file;
	}

}
