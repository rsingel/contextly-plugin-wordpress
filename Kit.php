<?php

/**
 * Contextly Kit overrides.
 *
 * @method ContextlyWpApiTransport newApiTransport()
 * @method ContextlyWpSharedSession newApiSession()
 * @method ContextlyWpWidgetsEditor newWidgetsEditor()
 * @method ContextlyWpOverlayPage newWpOverlayPage()
 */
class ContextlyWpKit extends ContextlyKit {

	/**
	 * @return ContextlyWpKit
	 */
	public static function getInstance() {
		static $instance;

		if ( !isset( $instance ) ) {
			$config   = self::getDefaultSettings();
			$instance = new self( $config );
		}

		return $instance;
	}

	public static function getDefaultSettings() {
		$config              = new ContextlyKitSettings();
		$config->urlPrefix   = plugins_url('kit', __FILE__);

		if ( CONTEXTLY_MODE !== Urls::MODE_LIVE ) {
			$config->mode = CONTEXTLY_MODE;
		}

		$options = Contextly::getAPIClientOptions();
		$config->appID = $options['appID'];
		$config->appSecret = $options['appSecret'];
		$config->cdn = CONTEXTLY_CDN_VERSION;

		return $config;
	}

	function isHttps() {
		return CONTEXTLY_HTTPS;
	}

	protected function getClassesMap() {
		$map = parent::getClassesMap();

		$map['ApiTransport'] = 'ContextlyWpApiTransport';
		$map['ApiSession'] = 'ContextlyWpSharedSession';
		$map['AssetsPackage'] = 'ContextlyWpAssetsPackage';
		$map['WidgetsEditor'] = 'ContextlyWpWidgetsEditor';
		$map['WpOverlayPage'] = 'ContextlyWpOverlayPage';

		return $map;
	}

	public function getLoaderName() {
		// We only override the loader name in dev mode.
		// See ContextlyWpAssetsPackage::getJs() for live mode.
		if ($this->isDevMode() && CONTEXTLY_MOD !== false) {
			return 'mods/' . CONTEXTLY_MOD;
		}
		else {
			return 'loader';
		}
	}

}

class ContextlyWpAssetsPackage extends ContextlyKitAssetsPackage {

	function getJs() {
		$js = parent::getJs();

		// Since we don't ship aggregated mod configs and can't use the modified loader
		// package, have to replace the asset itself.
		if ($this->kit->isLiveMode() && CONTEXTLY_MOD !== false) {
			foreach ($js as &$name) {
				if ($name === 'loader') {
					$name = 'mods--' . CONTEXTLY_MOD;
				}
			}
			unset($name);
		}

		return $js;
	}

}

class ContextlyWpApiTransport implements ContextlyKitApiTransportInterface {

	/**
	 * Performs the HTTP request.
	 *
	 * @param string $method
	 *   "GET" or "POST".
	 * @param string $url
	 *   Request URL.
	 * @param array  $query
	 *   GET query parameters.
	 * @param array  $data
	 *   POST data.
	 * @param array  $headers
	 *   List of headers.
	 *
	 * @return ContextlyKitApiResponse
	 */
	public function request( $method, $url, $query = array(), $data = array(), $headers = array() ) {
		// Add query to the URL.
		if (!empty($query)) {
			$url = add_query_arg( $query, $url );
		}

		$result = wp_remote_request( $url, array(
			'timeout'   => 40,
			'method'    => $method,
			'body'      => $data,
			'headers'   => $headers,
		) );

		// Build the response for the kit.
		$response = new ContextlyKitApiResponse();
		if ( is_wp_error( $result ) ) {
			$response->code = -1;
			$response->error = $result->get_error_message();
		} else {
			$response->code = $result['response']['code'];
			$response->body = wp_remote_retrieve_body( $result );
		}

		return $response;
	}

}

class ContextlyWpSharedSession extends ContextlyKitBase implements ContextlyKitApiSessionInterface {

	const TOKEN_OPTION_NAME   = 'contextly_access_token';

	protected $token;

	public function __construct( $kit ) {
		parent::__construct( $kit );

		$this->token = $this->loadSharedToken();
	}

	public function loadSharedToken() {
		$cached = get_option(self::TOKEN_OPTION_NAME);
		if ( ! $cached ) {
			return $this->kit->newApiTokenEmpty();
		} else {
			return $cached;
		}
	}

	/**
	 * @param ContextlyKitApiTokenInterface $token
	 */
	public function saveSharedToken( $token ) {
		update_option(self::TOKEN_OPTION_NAME, $token);
	}

	public function removeSharedToken() {
		delete_option(self::TOKEN_OPTION_NAME);
	}

	public function cleanupToken() {
		$this->token = $this->kit->newApiTokenEmpty();
		$this->removeSharedToken();
	}

	public function setToken( $token ) {
		$this->token = $token;
		$this->saveSharedToken( $token );
	}

	public function getToken() {
		return $this->token;
	}

}

/**
 * Handles the page required for the overlay dialogs.
 *
 * @property ContextlyWpKit $kit
 */
class ContextlyWpOverlayPage extends ContextlyKitBase {

	const MENU_KEY = 'contextly_overlay_dialog';

	public function addMenuAction() {
		add_action( 'admin_menu', array( $this, 'registerPage' ) );
	}

	public function registerPage() {
		// Register back-end callback without adding it to the menu.
		add_submenu_page( '', '', '', 'edit_posts', self::MENU_KEY, array( $this, 'display' ) );
	}

	public function display() {
		global $contextly;

		$type = $_GET['editor-type'];
		if (!in_array( $type, array( 'link', 'snippet', 'sidebar' ), TRUE )) {
			$contextly->return404();
		}

		$overrides = $this->kit->newOverridesManager( $contextly->getKitSettingsOverrides() )
			->compile();

		print $this->kit->newOverlayDialog($type)
				->render( array(
					'loader' => $this->kit->getLoaderName(),
				  'code' => $overrides,
				) );
		exit;
	}

}

class ContextlyWpWidgetsEditor extends ContextlyKitWidgetsEditor {

	public function addAjaxActions() {
		add_action( 'wp_ajax_contextly_widgets_editor_request', array( $this, 'handleAjaxAction' ) );
	}

	public function handleAjaxAction() {
		$post_id = $_POST['post_id'];
		check_ajax_referer( "contextly-post-$post_id", 'nonce' );

		if (!isset($_POST['method'])) {
			$GLOBALS['contextly']->return404();
		}
		$method = $_POST['method'];

		if (isset($_POST['params'])) {
			$params = $_POST['params'];
		}
		else {
			$params = array();
		}
		$params['custom_id'] = $post_id;

		try {
			$result = $this->handleRequest( $method, $params );
		} catch (ContextlyKitApiException $e) {
			if ( isset( $e->getApiResult()->error_code ) && $e->getApiResult()->error_code == 413 ) {
				// Access token not exists, try to remove it
				$this->kit->newApiSession()->cleanupToken();
			}

			if (CONTEXTLY_MODE !== Urls::MODE_LIVE) {
				$message = (string) $e;
			} else {
				$message = NULL;
			}
			$GLOBALS['contextly']->return500( $message );
		} catch (Exception $e) {
			Contextly::fireAPIEvent( 'handleAjaxAction', print_r( $e, true ) );

			if (CONTEXTLY_MODE !== Urls::MODE_LIVE) {
				$message = (string) $e;
			} else {
				$message = NULL;
			}
			$GLOBALS['contextly']->return500( $message );
		}

		@header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo json_encode( $result );

		exit;
	}

}
