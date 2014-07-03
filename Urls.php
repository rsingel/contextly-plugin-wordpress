<?php
/**
 * User: Andrew Nikolaenko
 * Date: 3/21/13
 * Time: 1:59 PM
 */

// TODO Use kit for most of URL types.
class Urls {

	protected static $_instance;

	const MODE_LOCAL = 'local';
	const MODE_DEV = 'dev';
	const MODE_LIVE = 'live';

    static private function getScheme() {
        return 'http' . ( CONTEXTLY_HTTPS ? 's' : '' );
    }

	static public function getMainServerUrl() {
		if ( CONTEXTLY_MODE == self::MODE_LIVE ) {
			return 'https://contextly.com/';
		} elseif ( CONTEXTLY_MODE == self::MODE_DEV ) {
			return 'https://dev.contextly.com/';
		} else {
			return 'http://linker.site/';
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

	static public function getApiServerSeoHtmlUrl( $app_id, $page_id )
	{
		return sprintf( '%sstatichtml/get/app_id/%s/page_id/%s/#!related_links', Urls::getApiServerUrl(), $app_id, $page_id );
	}

}
