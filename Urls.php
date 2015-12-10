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

	static public function getPluginCdnUrl( $file, $type = 'js' ) {
		return 'https://rest.contextly.com/wp-plugin/' . CONTEXTLY_PLUGIN_VERSION . '/' . $type . '/' . $file;
	}

}
