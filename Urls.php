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
	const MODE_LIVE = 'production';

    static private function getScheme() {
        return 'http' . ( CONTEXTLY_HTTPS ? 's' : '' );
    }

	static public function getMainServerUrl() {
		if ( CONTEXTLY_MODE == self::MODE_LIVE ) {
			return 'http://contextly.com/';
		} elseif ( CONTEXTLY_MODE == self::MODE_DEV ) {
			return 'http://dev.contextly.com/';
		} else {
			return 'http://linker.site/';
		}
	}

	static public function getPopupServerUrl() {
		if ( CONTEXTLY_MODE == self::MODE_LIVE ) {
			return 'http://app.contextly.com/';
		} elseif ( CONTEXTLY_MODE == self::MODE_DEV ) {
			return 'http://devapi.contextly.com/';
		} else {
			return 'http://linker.local/';
		}
	}

	static public function getApiServerUrl() {
		if ( CONTEXTLY_MODE == self::MODE_LIVE ) {
			return self::getScheme() . '://rest.contextly.com/';
		} elseif ( CONTEXTLY_MODE == self::MODE_DEV ) {
			return 'http://devrest.contextly.com/';
		} else {
			return 'http://contextly-api.local/';
		}
	}

	static public function getPluginJsCdnUrl( $js_file ) {
		if ( CONTEXTLY_HTTPS ) {
			$prefix = 'https://c713421.ssl.cf2.rackcdn.com/';
		} else {
			$prefix = 'http://contextlysiteimages.contextly.com/';
		}

		return $prefix . '_plugin/' . CONTEXTLY_PLUGIN_VERSION . '/js/' . $js_file;
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
